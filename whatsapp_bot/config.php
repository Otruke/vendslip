<?php

declare(strict_types=1);

/*
| NetSlip WhatsApp bot module (Twilio Programmable Messaging).
| Runtime credentials live in Backend platform settings (/admin/gateways) or Backend/.env:
|   TWILIO_ACCOUNT_SID, TWILIO_AUTH_TOKEN, TWILIO_WHATSAPP_FROM=whatsapp:+14155238886
|   TWILIO_WHATSAPP_CONFIRM_CONTENT_SID=HX...   (optional quick-reply template, see README)
*/
return [
    'channel' => 'WHATSAPP',
    'webhook_path' => '/api/v1/webhooks/whatsapp',
    'address_prefix' => 'whatsapp:',
    // Quick-reply button ids defined in the Twilio Content template. Must match exactly.
    'buttons' => [
        'confirm' => 'NS_CONFIRM',
        'edit' => 'NS_EDIT',
        'cancel' => 'NS_CANCEL',
    ],
    // WhatsApp caps a quick-reply body at 1024 characters.
    'max_summary_chars' => 1000,
    // PDFs are attached as media; Twilio fetches them from this signed NetSlip URL.
    'attach_pdf' => true,
];
