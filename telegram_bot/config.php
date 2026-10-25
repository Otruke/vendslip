<?php

declare(strict_types=1);

/*
| NetSlip Telegram bot module — Telegram Bot API 10.3 (24 Aug 2026).
| Credentials: /admin/gateways or Backend/.env
|   TELEGRAM_BOT_TOKEN, TELEGRAM_WEBHOOK_SECRET, TELEGRAM_BOT_USERNAME
| Register the webhook once:  php artisan netslip:telegram-webhook
*/
return [
    'channel' => 'TELEGRAM',
    'webhook_path' => '/api/v1/webhooks/telegram',
    'callback_prefix' => 'ns:',
    // Use sendRichMessage / InputRichBlock* (Bot API 10.1+). Falls back to sendMessage automatically on error.
    'rich_messages' => true,
    // Stream a "rendering…" draft in private chats while the PDF is generated (sendRichMessageDraft, 10.3 can_stop/keep_on_stop).
    'stream_render_draft' => true,
    // In groups, summaries are ephemeral: only the sales agent who typed the order sees them.
    'ephemeral_in_groups' => true,
    'allowed_updates' => ['message', 'callback_query'],
];
