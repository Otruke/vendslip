<?php

declare(strict_types=1);

namespace App\Services\Bot\Meta;

use App\Services\Bot\Actions\AskConfirmation;
use App\Services\Bot\Actions\DeliverSlip;

/**
 * One per Meta channel (WhatsApp Cloud API, Messenger, Instagram). $to is the platform's own
 * recipient id (wa_id digits, PSID or IGSID) with VendSlip's "whatsapp:+" style prefix removed.
 * Every method returns false when Meta refused the call, so callers can fall back.
 */
interface MetaChannelSender
{
    public function text(string $to, string $text): bool;

    /** The single consolidated summary with tappable Confirm / Edit / Cancel. */
    public function confirmation(string $to, AskConfirmation $action): bool;

    /** Caption + PDF, falling back to the signed PDF link when the attachment is refused. */
    public function slip(string $to, DeliverSlip $action): bool;
}
