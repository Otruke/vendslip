<?php

declare(strict_types=1);

namespace App\Services\Bot\Meta;

use App\Enums\AuditSeverity;
use App\Enums\Channel;
use App\Services\Bot\Actions\AskConfirmation;
use App\Services\Bot\Actions\BotAction;
use App\Services\Bot\Actions\DeliverSlip;
use App\Services\Bot\Actions\SendText;
use App\Services\Bot\BotOrchestrator;
use App\Services\Bot\ChannelProviders;
use App\Services\Bot\InboundMessage;
use App\Services\Security\AuditLogger;
use App\Services\Settings\PlatformSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;
use VendSlip\Bots\Facebook\MessengerGraphSender;
use VendSlip\Bots\Instagram\InstagramGraphSender;
use VendSlip\Bots\WhatsApp\WhatsAppCloudSender;

/**
 * GET|POST /api/v1/webhooks/meta: one endpoint for the whole Meta app.
 *
 *  object=whatsapp_business_account  entry[].changes[].value.messages[]   → WhatsApp
 *  object=page                       entry[].messaging[]                  → Messenger
 *  object=instagram                  entry[].messaging[]                  → Instagram
 *
 * Each message goes through the same BotOrchestrator as every other channel. Meta retries
 * deliveries, so duplicates are dropped by the orchestrator's idempotency claim on the message id;
 * after a valid signature the response is always 200 so one bad message can't cause a retry storm.
 */
final class MetaWebhookHandler
{
    public function __construct(
        private readonly BotOrchestrator $orchestrator,
        private readonly MetaSignature $signature,
        private readonly ChannelProviders $providers,
        private readonly PlatformSettings $settings,
        private readonly AuditLogger $audit,
    ) {}

    public function verify(Request $request): Response
    {
        $challenge = $this->signature->challenge($request);
        if ($challenge === null) {
            $this->audit->log('webhook.meta_bad_verify_token', null, null, ['ip' => $request->ip()], AuditSeverity::WARNING);

            return response('Forbidden', 403);
        }

        return response($challenge, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    public function handle(Request $request): Response
    {
        if (! $this->signature->isAuthentic($request)) {
            $this->audit->log('webhook.meta_bad_signature', null, null, ['ip' => $request->ip()], AuditSeverity::CRITICAL);

            return response('Forbidden', 403);
        }

        $payload = json_decode($request->getContent(), true);
        if (! is_array($payload) || ! $this->providers->usesMeta()) {
            // Twilio is the selected route: acknowledge so Meta doesn't retry, but do nothing.
            return response('EVENT_RECEIVED', 200);
        }

        $messages = match ($payload['object'] ?? null) {
            'whatsapp_business_account' => $this->whatsappMessages($payload),
            'page' => $this->messagingEvents($payload, Channel::FACEBOOK),
            'instagram' => $this->messagingEvents($payload, Channel::INSTAGRAM),
            default => [],
        };

        foreach ($messages as $message) {
            try {
                $this->render($this->orchestrator->handle($message), $message);
            } catch (Throwable $e) {
                report($e);
            }
        }

        return response('EVENT_RECEIVED', 200);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<InboundMessage>
     */
    private function whatsappMessages(array $payload): array
    {
        $ourNumber = $this->settings->string('meta.whatsapp_phone_number_id');
        $out = [];
        foreach ($this->list($payload['entry'] ?? null) as $entry) {
            foreach ($this->list($entry['changes'] ?? null) as $change) {
                $value = is_array($change['value'] ?? null) ? $change['value'] : [];
                if (($change['field'] ?? null) !== 'messages' || ! isset($value['messages'])) {
                    continue; // delivery/read statuses, template updates …
                }
                $phoneNumberId = (string) ($value['metadata']['phone_number_id'] ?? '');
                if ($ourNumber === null || $phoneNumberId !== $ourNumber) {
                    Log::info('meta.whatsapp_other_number', ['phone_number_id' => $phoneNumberId]);

                    continue;
                }
                $names = [];
                foreach ($this->list($value['contacts'] ?? null) as $contact) {
                    $names[(string) ($contact['wa_id'] ?? '')] = $contact['profile']['name'] ?? null;
                }

                foreach ($this->list($value['messages']) as $msg) {
                    $from = (string) preg_replace('/\D/', '', (string) ($msg['from'] ?? ''));
                    $id = (string) ($msg['id'] ?? '');
                    if ($from === '' || $id === '') {
                        continue;
                    }
                    [$text, $action] = match ($msg['type'] ?? null) {
                        'text' => [(string) ($msg['text']['body'] ?? ''), null],
                        'interactive' => [
                            (string) ($msg['interactive']['button_reply']['title'] ?? $msg['interactive']['list_reply']['title'] ?? ''),
                            ButtonPayloads::action($msg['interactive']['button_reply']['id'] ?? $msg['interactive']['list_reply']['id'] ?? null),
                        ],
                        'button' => [(string) ($msg['button']['text'] ?? ''), ButtonPayloads::action($msg['button']['payload'] ?? null)],
                        default => [null, null], // images, stickers, reactions …: nothing to parse
                    };
                    if ($text === null || ($text === '' && $action === null)) {
                        continue;
                    }
                    $name = $names[$from] ?? null;

                    $out[] = new InboundMessage(
                        channel: Channel::WHATSAPP,
                        messageId: $id,
                        externalChatId: 'whatsapp:+'.$from,
                        externalUserId: 'whatsapp:+'.$from,
                        displayName: is_string($name) && $name !== '' ? $name : null,
                        text: $text,
                        action: $action,
                    );
                }
            }
        }

        return $out;
    }

    /**
     * Messenger and Instagram share the Messenger Platform event shape.
     *
     * @param  array<string, mixed>  $payload
     * @return list<InboundMessage>
     */
    private function messagingEvents(array $payload, Channel $channel): array
    {
        $prefix = $channel === Channel::FACEBOOK ? 'messenger:' : 'instagram:';
        $ours = $this->settings->string($channel === Channel::FACEBOOK ? 'meta.page_id' : 'meta.instagram_account_id');
        $out = [];

        foreach ($this->list($payload['entry'] ?? null) as $entry) {
            foreach ($this->list($entry['messaging'] ?? null) as $event) {
                $message = is_array($event['message'] ?? null) ? $event['message'] : null;
                $postback = is_array($event['postback'] ?? null) ? $event['postback'] : null;
                if (($message === null && $postback === null) || ($message['is_echo'] ?? false)) {
                    continue; // read receipts, reactions, our own echoed replies
                }

                // Only act on messages sent to this platform's own Page / Instagram account.
                $recipient = (string) ($event['recipient']['id'] ?? '');
                if ($ours === null || ! in_array($ours, [(string) ($entry['id'] ?? ''), $recipient], true)) {
                    Log::info('meta.messaging_other_account', ['channel' => $channel->value, 'entry_id' => $entry['id'] ?? null, 'recipient_id' => $recipient]);

                    continue;
                }

                $sender = (string) ($event['sender']['id'] ?? '');
                $mid = (string) ($message['mid'] ?? $postback['mid'] ?? '');
                if ($sender === '' || $mid === '') {
                    continue;
                }
                $action = ButtonPayloads::action($message['quick_reply']['payload'] ?? $postback['payload'] ?? null);
                $text = (string) ($message['text'] ?? $postback['title'] ?? '');
                if ($text === '' && $action === null) {
                    continue; // attachments only
                }

                $out[] = new InboundMessage(
                    channel: $channel,
                    messageId: $mid,
                    externalChatId: $prefix.$sender,
                    externalUserId: $prefix.$sender,
                    displayName: null,
                    text: $text,
                    action: $action,
                );
            }
        }

        return $out;
    }

    private function render(BotAction $action, InboundMessage $message): void
    {
        $sender = $this->sender($message->channel);
        $to = (string) preg_replace('/^(whatsapp:\+?|messenger:|instagram:)/', '', $message->externalChatId);

        match (true) {
            $action instanceof SendText => $sender->text($to, $action->text),
            // Later field changes are buffered silently (no fee); /preview re-sends the summary.
            $action instanceof AskConfirmation => $action->isUpdate || $sender->confirmation($to, $action),
            $action instanceof DeliverSlip => $sender->slip($to, $action),
            default => null, // StaySilent
        };
    }

    private function sender(Channel $channel): MetaChannelSender
    {
        return match ($channel) {
            Channel::WHATSAPP => app(WhatsAppCloudSender::class),
            Channel::FACEBOOK => app(MessengerGraphSender::class),
            default => app(InstagramGraphSender::class),
        };
    }

    /** @return list<array<string, mixed>> */
    private function list(mixed $value): array
    {
        return is_array($value) ? array_values(array_filter($value, 'is_array')) : [];
    }
}
