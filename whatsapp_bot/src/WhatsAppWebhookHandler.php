<?php

declare(strict_types=1);

namespace NetSlip\Bots\WhatsApp;

use App\Enums\Channel;
use App\Services\Bot\Actions\AskConfirmation;
use App\Services\Bot\Actions\DeliverSlip;
use App\Services\Bot\BotOrchestrator;
use App\Services\Bot\InboundMessage;
use App\Services\Bot\Twilio\AbstractTwilioWebhookHandler;
use App\Services\Bot\Twilio\TwilioGateway;
use App\Services\Bot\Twilio\TwiML;
use App\Services\Security\AuditLogger;
use App\Services\Settings\PlatformSettings;
use Symfony\Component\HttpFoundation\Response;

/**
 * POST /api/v1/webhooks/whatsapp  (Twilio "A message comes in" webhook)
 *
 * - Validates X-Twilio-Signature.
 * - Partial /slash messages → empty <Response/> (₦0 outbound).
 * - First complete draft → ONE quick-reply summary inside the free 24h service window.
 * - Confirm → PDF slip as media + verification link.
 */
final class WhatsAppWebhookHandler extends AbstractTwilioWebhookHandler
{
    /** @var array<string, mixed> */
    private array $config;

    public function __construct(
        BotOrchestrator $orchestrator,
        TwilioGateway $twilio,
        AuditLogger $audit,
        private readonly PlatformSettings $settings,
    ) {
        parent::__construct($orchestrator, $twilio, $audit);
        $this->config = require dirname(__DIR__).'/config.php';
    }

    protected function channel(): Channel
    {
        return Channel::WHATSAPP;
    }

    protected function renderConfirmation(AskConfirmation $action, InboundMessage $message): Response
    {
        $summary = new WhatsAppInteractiveSummary($this->config);
        $contentSid = $this->settings->string('twilio.whatsapp_confirm_content_sid');
        $from = $this->twilio->from('whatsapp');

        if ($contentSid !== null && $from !== null) {
            $sent = $this->twilio->send(
                $message->externalChatId,
                (new WhatsAppPayloadBuilder($this->config))->quickReplyOptions($from, $contentSid, $summary->contentVariables($action)),
            );
            if ($sent) {
                return TwiML::empty();
            }
        }

        return TwiML::message($summary->body($action, true));
    }

    protected function renderSlip(DeliverSlip $action, InboundMessage $message): Response
    {
        return (new WhatsAppPayloadBuilder($this->config))->slip($action);
    }
}
