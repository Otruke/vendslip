<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Bot\Meta\MetaWebhookHandler;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use VendSlip\Bots\Facebook\FacebookMessengerWebhookHandler;
use VendSlip\Bots\Instagram\InstagramWebhookHandler;
use VendSlip\Bots\Telegram\Telegram103WebhookHandler;
use VendSlip\Bots\WhatsApp\WhatsAppWebhookHandler;

/**
 * POST /api/v1/webhooks/{channel} → the dedicated bot module for that channel.
 * GET|POST /api/v1/webhooks/meta → WhatsApp, Messenger and Instagram via Meta directly (primary).
 * The Twilio endpoints stay wired as the secondary route; only the selected one acts.
 */
final class BotWebhookController extends Controller
{
    public function metaVerify(Request $request, MetaWebhookHandler $handler): Response
    {
        return $handler->verify($request);
    }

    public function meta(Request $request, MetaWebhookHandler $handler): Response
    {
        return $handler->handle($request);
    }

    public function whatsapp(Request $request, WhatsAppWebhookHandler $handler): Response
    {
        return $handler->handle($request);
    }

    public function telegram(Request $request, Telegram103WebhookHandler $handler): Response
    {
        return $handler->handle($request);
    }

    public function instagram(Request $request, InstagramWebhookHandler $handler): Response
    {
        return $handler->handle($request);
    }

    public function facebook(Request $request, FacebookMessengerWebhookHandler $handler): Response
    {
        return $handler->handle($request);
    }
}
