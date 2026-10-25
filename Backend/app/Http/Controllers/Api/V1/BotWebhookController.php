<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use NetSlip\Bots\Facebook\FacebookMessengerWebhookHandler;
use NetSlip\Bots\Instagram\InstagramWebhookHandler;
use NetSlip\Bots\Telegram\Telegram103WebhookHandler;
use NetSlip\Bots\WhatsApp\WhatsAppWebhookHandler;
use Symfony\Component\HttpFoundation\Response;

/** POST /api/v1/webhooks/{channel} → the dedicated bot module for that channel. */
final class BotWebhookController extends Controller
{
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
