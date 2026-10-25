<?php

declare(strict_types=1);

namespace NetSlip\Bots\Instagram;

use App\Enums\Channel;
use App\Services\Bot\Actions\AskConfirmation;
use App\Services\Bot\Actions\DeliverSlip;
use App\Services\Bot\BotOrchestrator;
use App\Services\Bot\InboundMessage;
use App\Services\Bot\Twilio\AbstractTwilioWebhookHandler;
use App\Services\Bot\Twilio\TwilioGateway;
use App\Services\Security\AuditLogger;
use Symfony\Component\HttpFoundation\Response;

/** POST /api/v1/webhooks/instagram */
final class InstagramWebhookHandler extends AbstractTwilioWebhookHandler
{
    /** @var array<string, mixed> */
    private array $config;

    public function __construct(BotOrchestrator $orchestrator, TwilioGateway $twilio, AuditLogger $audit)
    {
        parent::__construct($orchestrator, $twilio, $audit);
        $this->config = require dirname(__DIR__).'/config.php';
    }

    protected function channel(): Channel
    {
        return Channel::INSTAGRAM;
    }

    protected function renderConfirmation(AskConfirmation $action, InboundMessage $message): Response
    {
        return (new InstagramPayloadBuilder($this->config))->confirmation($action);
    }

    protected function renderSlip(DeliverSlip $action, InboundMessage $message): Response
    {
        return (new InstagramPayloadBuilder($this->config))->slip($action);
    }
}
