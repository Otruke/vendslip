<?php

declare(strict_types=1);

/*
| NetSlip Instagram DM bot module (Twilio Programmable Messaging).
|   TWILIO_INSTAGRAM_FROM=instagram:<your-instagram-business-account-id>
| Instagram DMs cannot carry PDF attachments, so the slip is delivered as a signed link.
*/
return [
    'channel' => 'INSTAGRAM',
    'webhook_path' => '/api/v1/webhooks/instagram',
    'address_prefix' => 'instagram:',
    'max_summary_chars' => 1000,
];
