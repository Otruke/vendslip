<?php

declare(strict_types=1);

return [
    /*
    | Public URLs. The React SPA serves /verify/{hash} and /pay/{uid}; links embedded
    | in PDFs and bot replies point there.
    */
    'frontend_url' => rtrim((string) env('FRONTEND_URL', 'http://localhost:5173'), '/'),

    /*
    | HMAC-SHA256 key for slip verification hashes. Set VENDSLIP_VERIFICATION_KEY in
    | production and never rotate it casually: rotating invalidates every QR code.
    | Falls back to a key derived from APP_KEY.
    */
    'verification_key' => env('VENDSLIP_VERIFICATION_KEY'),

    'timezone' => env('VENDSLIP_TIMEZONE', 'Africa/Lagos'),

    'pdf' => [
        'disk' => env('VENDSLIP_PDF_DISK', 'local'),
        'directory' => 'slips',
        'signed_url_minutes' => (int) env('VENDSLIP_PDF_LINK_MINUTES', 60 * 24 * 7),
    ],

    // Super-Admin support access to a business dashboard (code emailed to the business owner).
    'support_access' => [
        'code_minutes' => (int) env('VENDSLIP_SUPPORT_CODE_MINUTES', 10),
        'session_minutes' => (int) env('VENDSLIP_SUPPORT_SESSION_MINUTES', 60),
    ],

    'bots' => [
        // Twilio/Meta free-form replies are only allowed inside the 24h customer-service window.
        'draft_ttl_minutes' => (int) env('VENDSLIP_DRAFT_TTL_MINUTES', 60 * 24),
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

    /*
    | Which route carries WhatsApp, Messenger and Instagram: 'meta' (primary, Meta Cloud API /
    | Messenger Platform direct) or 'twilio' (secondary). Switchable at /admin/gateways.
    */
    'channels' => [
        'meta_provider' => env('VENDSLIP_META_CHANNELS_PROVIDER', 'meta'),
    ],

    'meta' => [
        'graph_base' => env('META_GRAPH_BASE', 'https://graph.facebook.com'),
        'instagram_base' => env('META_INSTAGRAM_BASE', 'https://graph.instagram.com'),
        'graph_version' => env('META_GRAPH_VERSION', 'v24.0'),
        'app_secret' => env('META_APP_SECRET'),                  // signs X-Hub-Signature-256
        'verify_token' => env('META_VERIFY_TOKEN'),              // webhook GET handshake
        'whatsapp_phone_number_id' => env('META_WHATSAPP_PHONE_NUMBER_ID'),
        'whatsapp_token' => env('META_WHATSAPP_TOKEN'),          // system-user token
        'whatsapp_number' => env('META_WHATSAPP_NUMBER'),        // e.g. +2348012345678 (shown to vendors)
        'page_id' => env('META_PAGE_ID'),
        'page_token' => env('META_PAGE_TOKEN'),
        'instagram_account_id' => env('META_INSTAGRAM_ACCOUNT_ID'),
        'instagram_username' => env('META_INSTAGRAM_USERNAME'),
        'instagram_token' => env('META_INSTAGRAM_TOKEN'),        // optional: Instagram Login token (else page token)
        'instagram_app_secret' => env('META_INSTAGRAM_APP_SECRET'), // optional: Instagram Login app secret
        'validate_signature' => (bool) env('META_VALIDATE_SIGNATURE', true),
        'timeout' => (int) env('META_HTTP_TIMEOUT', 15),
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

    // Terms of Service and Privacy Policy. Bump the version whenever either changes materially:
    // people must accept the new version again before their next payment.
    'legal' => [
        'version' => env('VENDSLIP_LEGAL_VERSION', '2026-11-08'),
        'entity' => env('VENDSLIP_LEGAL_ENTITY', 'Techberryz Futurecore Limited'),
        'address' => env('VENDSLIP_LEGAL_ADDRESS', 'Lagos, Nigeria'),
        'privacy_email' => env('VENDSLIP_PRIVACY_EMAIL', 'privacy@vendslip.com'),
        'support_email' => env('VENDSLIP_SUPPORT_EMAIL', 'support@vendslip.com'),
        // Once a newer version takes effect, people must accept it before changing anything.
        'require_current' => (bool) env('VENDSLIP_LEGAL_REQUIRE_CURRENT', true),
    ],

    // Exchange rates for pricing plans in other currencies (free, no key; refreshed every 6 hours).
    'fx' => [
        'url' => env('VENDSLIP_FX_URL', 'https://open.er-api.com/v6/latest/USD'),
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
        'email' => env('VENDSLIP_SUPER_ADMIN_EMAIL', 'superadmin@vendslip.test'),
        'password' => env('VENDSLIP_SUPER_ADMIN_PASSWORD'),
    ],
];
