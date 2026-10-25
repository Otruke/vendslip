<?php

declare(strict_types=1);

/*
| NetSlip Facebook Messenger bot module (Twilio Programmable Messaging).
|   TWILIO_FACEBOOK_FROM=messenger:<your-page-id>
*/
return [
    'channel' => 'FACEBOOK',
    'webhook_path' => '/api/v1/webhooks/facebook',
    'address_prefix' => 'messenger:',
    'max_summary_chars' => 1900,
    // Messenger accepts file attachments; set false to send only the link.
    'attach_pdf' => true,
];
