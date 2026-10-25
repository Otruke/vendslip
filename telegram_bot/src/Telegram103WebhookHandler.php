<?php

declare(strict_types=1);

namespace NetSlip\Bots\Telegram;

use App\Enums\AuditSeverity;
use App\Enums\Channel;
use App\Models\BotDraftSession;
use App\Services\Bot\Actions\AskConfirmation;
use App\Services\Bot\Actions\BotAction;
use App\Services\Bot\Actions\DeliverSlip;
use App\Services\Bot\Actions\SendText;
use App\Services\Bot\BotOrchestrator;
use App\Services\Bot\InboundMessage;
use App\Services\Bot\SummaryFormatter;
use App\Services\Security\AuditLogger;
use App\Services\Settings\PlatformSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * POST /api/v1/webhooks/telegram — Telegram Bot API 10.3.
 *
 *  message         → /slash text → BotOrchestrator
 *  callback_query  → ns:confirm | ns:clean | ns:edit | ns:cancel buttons
 *
 * Confirm flow: answerCallbackQuery → buttons switch to DisabledButton (no double credit spend)
 * → sendRichMessageDraft "rendering…" (private chats) → sendRichMessage with the PDF document block.
 */
final class Telegram103WebhookHandler
{
    /** @var array<string, mixed> */
    private array $config;

    private TelegramRichMessageBuilder $builder;

    private TelegramEphemeralDraftService $ephemeral;

    public function __construct(
        private readonly BotOrchestrator $orchestrator,
        private readonly TelegramApiClient $api,
        private readonly PlatformSettings $settings,
        private readonly AuditLogger $audit,
    ) {
        $this->config = require dirname(__DIR__).'/config.php';
        $this->builder = new TelegramRichMessageBuilder((string) $this->config['callback_prefix']);
        $this->ephemeral = new TelegramEphemeralDraftService($this->api, $this->builder, (bool) $this->config['ephemeral_in_groups']);
    }

    public function handle(Request $request): JsonResponse
    {
        $secret = $this->settings->string('telegram.webhook_secret');
        $header = $request->header('X-Telegram-Bot-Api-Secret-Token');
        if ($secret === null || $header === null || ! hash_equals($secret, $header)) {
            $this->audit->log('webhook.telegram_bad_secret', null, null, ['ip' => $request->ip()], AuditSeverity::CRITICAL);

            return response()->json(['ok' => false], 401);
        }

        /** @var array<string, mixed> $update */
        $update = $request->json()->all();
        $message = $this->toInbound($update);
        if ($message === null) {
            return response()->json(['ok' => true]);
        }

        $callbackId = $message->meta['callback_query_id'] ?? null;
        $isConfirm = in_array($message->action, ['confirm', 'confirm_clean'], true);

        if (is_string($callbackId)) {
            $this->api->call('answerCallbackQuery', array_filter([
                'callback_query_id' => $callbackId,
                'text' => $isConfirm ? 'Generating your slip…' : null,
            ]));
            if ($message->action === null) {
                return response()->json(['ok' => true]); // noop / unknown button
            }
        }
        if ($isConfirm) {
            $this->disableButtons($message);
            if ((bool) $this->config['stream_render_draft']) {
                $this->ephemeral->streamRendering($message);
            }
        }

        $this->render($this->orchestrator->handle($message), $message);

        return response()->json(['ok' => true]);
    }

    /** @param array<string, mixed> $update */
    private function toInbound(array $update): ?InboundMessage
    {
        $updateId = (string) ($update['update_id'] ?? '');
        if ($updateId === '') {
            return null;
        }

        if (isset($update['callback_query']) && is_array($update['callback_query'])) {
            $cb = $update['callback_query'];
            $from = (array) ($cb['from'] ?? []);
            $msg = (array) ($cb['message'] ?? []);
            $chat = (array) ($msg['chat'] ?? []);
            $data = (string) ($cb['data'] ?? '');
            $prefix = (string) $this->config['callback_prefix'];
            $action = str_starts_with($data, $prefix) ? match (substr($data, strlen($prefix))) {
                'confirm' => 'confirm',
                'clean' => 'confirm_clean',
                'edit' => 'edit',
                'cancel' => 'cancel',
                default => null,
            } : null;
            if (! isset($from['id']) || ! isset($chat['id'])) {
                return null;
            }

            return new InboundMessage(
                channel: Channel::TELEGRAM,
                messageId: 'u'.$updateId,
                externalChatId: (string) $chat['id'],
                externalUserId: (string) $from['id'],
                displayName: self::displayName($from),
                text: '',
                action: $action,
                isGroup: in_array($chat['type'] ?? 'private', ['group', 'supergroup'], true),
                meta: [
                    'callback_query_id' => (string) ($cb['id'] ?? ''),
                    'callback_message_id' => $msg['message_id'] ?? null,
                    'from_ephemeral' => isset($msg['ephemeral_message_id']),
                    'ephemeral_message_id' => $msg['ephemeral_message_id'] ?? null,
                ],
            );
        }

        $msg = $update['message'] ?? null;
        if (! is_array($msg) || ! isset($msg['text'], $msg['from']['id'], $msg['chat']['id'])) {
            return null;
        }
        $text = (string) $msg['text'];
        // Deep link: t.me/<bot>?start=<CODE> arrives as "/start CODE".
        if (preg_match('/^\/start(?:@\w+)?\s+([A-Za-z0-9]{6,16})$/', trim($text), $m) === 1) {
            $text = '/link '.$m[1];
        }

        return new InboundMessage(
            channel: Channel::TELEGRAM,
            messageId: 'u'.$updateId,
            externalChatId: (string) $msg['chat']['id'],
            externalUserId: (string) $msg['from']['id'],
            displayName: self::displayName((array) $msg['from']),
            text: $text,
            isGroup: in_array($msg['chat']['type'] ?? 'private', ['group', 'supergroup'], true),
            meta: ['message_id' => $msg['message_id'] ?? null],
        );
    }

    private function render(BotAction $action, InboundMessage $message): void
    {
        if ($action instanceof SendText) {
            $this->api->call('sendMessage', [
                'chat_id' => $message->externalChatId,
                'text' => $action->text,
                'link_preview_options' => ['is_disabled' => true],
                ...$this->ephemeral->ephemeralParams($message),
            ]);

            return;
        }
        if ($action instanceof AskConfirmation) {
            $this->sendSummary($action, $message);

            return;
        }
        if ($action instanceof DeliverSlip) {
            $this->deliver($action, $message);
        }
    }

    private function sendSummary(AskConfirmation $action, InboundMessage $message): void
    {
        $session = $action->session;
        $keyboard = $this->builder->confirmKeyboard($action);
        $rich = (bool) $this->config['rich_messages'] && (bool) config('netslip.telegram.rich_messages', true);

        // Free on Telegram: refresh the existing summary in place when fields change.
        if ($action->isUpdate && $session->confirmation_message_id !== null && ! $message->isGroup) {
            $edit = $rich
                ? $this->api->call('editMessageText', ['chat_id' => $message->externalChatId, 'message_id' => (int) $session->confirmation_message_id, 'rich_message' => $this->builder->summary($action), 'reply_markup' => $keyboard])
                : ['ok' => false];
            if (! $edit['ok']) {
                $this->api->call('editMessageText', ['chat_id' => $message->externalChatId, 'message_id' => (int) $session->confirmation_message_id, 'text' => SummaryFormatter::text($action, false), 'reply_markup' => $keyboard]);
            }

            return;
        }

        $extra = $this->ephemeral->ephemeralParams($message, replaceCallbackMessage: true);
        $result = $rich
            ? $this->api->call('sendRichMessage', ['chat_id' => $message->externalChatId, 'rich_message' => $this->builder->summary($action), 'reply_markup' => $keyboard, ...$extra])
            : ['ok' => false];
        if (! $result['ok']) {
            $result = $this->api->call('sendMessage', ['chat_id' => $message->externalChatId, 'text' => SummaryFormatter::text($action, false), 'reply_markup' => $keyboard, ...$extra]);
        }
        $this->rememberMessage($session, $result);
    }

    private function deliver(DeliverSlip $action, InboundMessage $message): void
    {
        $extra = $this->ephemeral->ephemeralParams($message);
        $keyboard = $this->builder->verifyKeyboard($action);
        $rich = (bool) $this->config['rich_messages'] && (bool) config('netslip.telegram.rich_messages', true);

        $result = $rich
            ? $this->api->call('sendRichMessage', ['chat_id' => $message->externalChatId, 'rich_message' => $this->builder->delivery($action), 'reply_markup' => $keyboard, ...$extra])
            : ['ok' => false];
        if (! $result['ok']) {
            $result = $this->api->call('sendDocument', [
                'chat_id' => $message->externalChatId,
                'document' => $action->pdfUrl,
                'caption' => mb_substr($action->caption, 0, 1024),
                'reply_markup' => $keyboard,
                ...$extra,
            ]);
        }
        if (! $result['ok']) {
            $this->api->call('sendMessage', ['chat_id' => $message->externalChatId, 'text' => $action->caption."\n📄 ".$action->pdfUrl, ...$extra]);
        }
    }

    private function disableButtons(InboundMessage $message): void
    {
        $messageId = $message->meta['callback_message_id'] ?? null;
        $ephemeralId = $message->meta['ephemeral_message_id'] ?? null;
        $markup = $this->builder->disabledKeyboard();

        if ($ephemeralId !== null) {
            $this->api->call('editEphemeralMessageReplyMarkup', ['chat_id' => $message->externalChatId, 'ephemeral_message_id' => $ephemeralId, 'reply_markup' => $markup]);
        } elseif ($messageId !== null) {
            $this->api->call('editMessageReplyMarkup', ['chat_id' => $message->externalChatId, 'message_id' => (int) $messageId, 'reply_markup' => $markup]);
        }
    }

    /** @param array<string, mixed> $result */
    private function rememberMessage(BotDraftSession $session, array $result): void
    {
        $sent = is_array($result['result'] ?? null) ? $result['result'] : [];
        $updates = [];
        if (isset($sent['message_id'])) {
            $updates['confirmation_message_id'] = (string) $sent['message_id'];
        }
        if (isset($sent['ephemeral_message_id'])) {
            $updates['ephemeral_message_id'] = (string) $sent['ephemeral_message_id'];
        }
        if ($updates !== []) {
            $session->update($updates);
        }
    }

    /** @param array<string, mixed> $from */
    private static function displayName(array $from): ?string
    {
        $name = trim(((string) ($from['first_name'] ?? '')).' '.((string) ($from['last_name'] ?? '')));

        return $name !== '' ? $name : (isset($from['username']) ? '@'.$from['username'] : null);
    }
}
