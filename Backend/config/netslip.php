<?php

declare(strict_types=1);

return [
    /*
    | Public URLs. The React SPA serves /verify/{hash} and /pay/{uid}; links embedded
    | in PDFs and bot replies point there.
    */
    'frontend_url' => rtrim((string) env('FRONTEND_URL', 'http://localhost:5173'), '/'),

    /*
    | HMAC-SHA256 key for slip verification hashes. Set NETSLIP_VERIFICATION_KEY in
    | production and never rotate it casually: rotating invalidates every QR code.
    | Falls back to a key derived from APP_KEY.
    */
    'verification_key' => env('NETSLIP_VERIFICATION_KEY'),

    'timezone' => env('NETSLIP_TIMEZONE', 'Africa/Lagos'),

    'pdf' => [
        'disk' => env('NETSLIP_PDF_DISK', 'local'),
        'directory' => 'slips',
        'signed_url_minutes' => (int) env('NETSLIP_PDF_LINK_MINUTES', 60 * 24 * 7),
    ],

    'bots' => [
        // Twilio/Meta free-form replies are only allowed inside the 24h customer-service window.
        'draft_ttl_minutes' => (int) env('NETSLIP_DRAFT_TTL_MINUTES', 60 * 24),
        'link_code_ttl_minutes' => 30,
    ],

    /*
    | Bootstrap credentials. Super-Admins can override every value below at runtime
    | from /admin/gateways (stored encrypted in platform_settings).
    */
    'telegram' => [
        'bot_token' => env('TELEGRAM_BOT_TOKEN'),
        'webhook_secret' => env('TELEGRAM_WEBHOOK_SECRET'),
        'bot_username' => env('TELEGRAM_BOT_USERNAME'),
        'api_base' => env('TELEGRAM_API_BASE', 'https://api.telegram.org'),
        'rich_messages' => (bool) env('TELEGRAM_RICH_MESSAGES', true),
    ],

    'twilio' => [
        'account_sid' => env('TWILIO_ACCOUNT_SID'),
        'auth_token' => env('TWILIO_AUTH_TOKEN'),
        'whatsapp_from' => env('TWILIO_WHATSAPP_FROM'),      // e.g. whatsapp:+14155238886
        'instagram_from' => env('TWILIO_INSTAGRAM_FROM'),    // e.g. instagram:17841400000000000
        'facebook_from' => env('TWILIO_FACEBOOK_FROM'),      // e.g. messenger:102345678901234
        'whatsapp_confirm_content_sid' => env('TWILIO_WHATSAPP_CONFIRM_CONTENT_SID'), // HX... quick-reply template
        'validate_signature' => (bool) env('TWILIO_VALIDATE_SIGNATURE', true),
    ],

    'flutterwave' => [
        'base_url' => env('FLUTTERWAVE_BASE_URL', 'https://api.flutterwave.com/v3'),
        'public_key' => env('FLUTTERWAVE_PUBLIC_KEY'),
        'secret_key' => env('FLUTTERWAVE_SECRET_KEY'),
        'secret_hash' => env('FLUTTERWAVE_SECRET_HASH'),
    ],

    'nowpayments' => [
        'base_url' => env('NOWPAYMENTS_BASE_URL', 'https://api.nowpayments.io/v1'),
        'api_key' => env('NOWPAYMENTS_API_KEY'),
        'ipn_secret' => env('NOWPAYMENTS_IPN_SECRET'),
        'price_currency' => env('NOWPAYMENTS_PRICE_CURRENCY', 'ngn'),
    ],

    'super_admin' => [
        'email' => env('NETSLIP_SUPER_ADMIN_EMAIL', 'superadmin@netslip.test'),
        'password' => env('NETSLIP_SUPER_ADMIN_PASSWORD'),
    ],
];
