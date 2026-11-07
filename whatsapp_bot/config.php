<?php

declare(strict_types=1);

/*
| VendSlip WhatsApp bot module.
|   Primary:   Meta Cloud API  (WhatsAppCloudSender, webhook /api/v1/webhooks/meta)
|              META_APP_SECRET, META_VERIFY_TOKEN, META_WHATSAPP_PHONE_NUMBER_ID, META_WHATSAPP_TOKEN
|   Secondary: Twilio          (WhatsAppWebhookHandler, webhook /api/v1/webhooks/whatsapp)
|              TWILIO_ACCOUNT_SID, TWILIO_AUTH_TOKEN, TWILIO_WHATSAPP_FROM=whatsapp:+14155238886
|              TWILIO_WHATSAPP_CONFIRM_CONTENT_SID=HX...   (optional quick-reply template, see README)
| Credentials are editable at /admin/gateways, where the primary/secondary switch lives too.
*/
return [
    'channel' => 'WHATSAPP',
    'webhook_path' => '/api/v1/webhooks/whatsapp',
    'address_prefix' => 'whatsapp:',
    // Reply-button titles on the Meta route (WhatsApp: max 3 buttons, 20 characters each).
    'button_titles' => [
        'confirm' => '✅ Confirm',
        'edit' => '✏️ Edit',
        'cancel' => '❌ Cancel',
    ],
    // Quick-reply button ids defined in the Twilio Content template. Must match exactly.
    'buttons' => [
        'confirm' => 'NS_CONFIRM',
        'edit' => 'NS_EDIT',
        'cancel' => 'NS_CANCEL',
    ],
    // WhatsApp caps a quick-reply body at 1024 characters.
    'max_summary_chars' => 1000,
    // PDFs are attached as media; Twilio fetches them from this signed VendSlip URL.
    'attach_pdf' => true,
];
