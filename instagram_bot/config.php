<?php

declare(strict_types=1);

/*
| VendSlip Instagram DM bot module.
|   Primary:   Meta directly (InstagramGraphSender): META_INSTAGRAM_ACCOUNT_ID plus either
|              META_INSTAGRAM_TOKEN (Instagram Login) or META_PAGE_TOKEN (Page linked to the account)
|   Secondary: Twilio (InstagramWebhookHandler): TWILIO_INSTAGRAM_FROM=instagram:<account-id>
|              (Twilio does not currently offer Instagram, so this route is dormant.)
| On the Meta route the PDF is attached; if Instagram refuses it the signed link is sent instead.
*/
return [
    'channel' => 'INSTAGRAM',
    'webhook_path' => '/api/v1/webhooks/instagram',
    'address_prefix' => 'instagram:',
    'max_summary_chars' => 1000,
    // Meta route: Instagram caps a text message at 1000 bytes of UTF-8.
    'max_text_bytes' => 1000,
    'attach_pdf' => true,
    'quick_replies' => [
        'confirm' => '✅ Confirm',
        'clean' => '✨ Use 1 credit',
        'edit' => '✏️ Edit',
        'cancel' => '❌ Cancel',
    ],
];
