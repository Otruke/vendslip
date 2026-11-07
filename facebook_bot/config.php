<?php

declare(strict_types=1);

/*
| VendSlip Facebook Messenger bot module.
|   Primary:   Meta Messenger Platform (MessengerGraphSender): META_PAGE_ID, META_PAGE_TOKEN
|   Secondary: Twilio (FacebookMessengerWebhookHandler):     TWILIO_FACEBOOK_FROM=messenger:<your-page-id>
*/
return [
    'channel' => 'FACEBOOK',
    'webhook_path' => '/api/v1/webhooks/facebook',
    'address_prefix' => 'messenger:',
    'max_summary_chars' => 1900,
    // Messenger accepts file attachments; set false to send only the link.
    'attach_pdf' => true,
    // Quick-reply chips on the Meta route (20 characters each).
    'quick_replies' => [
        'confirm' => '✅ Confirm',
        'clean' => '✨ Use 1 credit',
        'edit' => '✏️ Edit',
        'cancel' => '❌ Cancel',
    ],
];
