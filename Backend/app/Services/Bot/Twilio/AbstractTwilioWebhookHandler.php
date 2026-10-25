<?php

declare(strict_types=1);

namespace App\Services\Bot\Twilio;

use App\Enums\AuditSeverity;
use App\Enums\Channel;
use App\Services\Bot\Actions\AskConfirmation;
use App\Services\Bot\Actions\BotAction;
use App\Services\Bot\Actions\DeliverSlip;
use App\Services\Bot\Actions\SendText;
use App\Services\Bot\BotOrchestrator;
use App\Services\Bot\InboundMessage;
use App\Services\Security\AuditLogger;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Template method for Twilio-delivered channels. Each bot module supplies the channel,
 * how to read the sender, and how to render the summary / slip for that platform.
 */
abstract class AbstractTwilioWebhookHandler
{
    public function __construct(
        protected readonly BotOrchestrator $orchestrator,
        protected readonly TwilioGateway $twilio,
        protected readonly AuditLogger $audit,
    ) {}

    abstract protected function channel(): Channel;

    abstract protected function renderConfirmation(AskConfirmation $action, InboundMessage $message): Response;

    abstract protected function renderSlip(DeliverSlip $action, InboundMessage $message): Response;

    public function handle(Request $request): Response
    {
        if (! $this->twilio->isAuthentic($request)) {
            $this->audit->log('webhook.twilio_bad_signature', null, null, ['channel' => $this->channel()->value, 'ip' => $request->ip()], AuditSeverity::CRITICAL);

            return response('Forbidden', 403);
        }

        $message = $this->toInbound($request);
        if ($message === null) {
            return TwiML::empty();
        }

        return $this->render($this->orchestrator->handle($message), $message);
    }

    protected function toInbound(Request $request): ?InboundMessage
    {
        $sid = (string) $request->input('MessageSid', $request->input('SmsMessageSid', ''));
        $from = (string) $request->input('From', '');
        if ($sid === '' || $from === '') {
            return null;
        }

        return new InboundMessage(
            channel: $this->channel(),
            messageId: $sid,
            externalChatId: $from,
            externalUserId: $from,
            displayName: $request->input('ProfileName') !== null ? (string) $request->input('ProfileName') : null,
            text: (string) $request->input('Body', ''),
            action: $this->buttonAction($request),
        );
    }

    /** Quick-reply payloads arrive as ButtonPayload (WhatsApp) or Payload/postback text. */
    protected function buttonAction(Request $request): ?string
    {
        $payload = (string) ($request->input('ButtonPayload') ?? $request->input('Payload') ?? '');

        return match ($payload) {
            'NS_CONFIRM' => 'confirm',
            'NS_CONFIRM_CLEAN' => 'confirm_clean',
            'NS_EDIT' => 'edit',
            'NS_CANCEL' => 'cancel',
            default => null,
        };
    }

    protected function render(BotAction $action, InboundMessage $message): Response
    {
        return match (true) {
            $action instanceof SendText => TwiML::message($action->text),
            $action instanceof AskConfirmation => $action->isUpdate ? TwiML::empty() : $this->renderConfirmation($action, $message),
            $action instanceof DeliverSlip => $this->renderSlip($action, $message),
            default => TwiML::empty(),
        };
    }
}
