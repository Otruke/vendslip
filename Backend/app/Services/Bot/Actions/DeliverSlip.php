<?php

declare(strict_types=1);

namespace App\Services\Bot\Actions;

use App\Models\BotDraftSession;
use App\Models\Document;

final readonly class DeliverSlip implements BotAction
{
    public function __construct(
        public BotDraftSession $session,
        public Document $document,
        /** Signed file address for chat apps to fetch the attachment. Never shown in a message. */
        public string $pdfUrl,
        public string $verifyUrl,
        public ?string $payUrl,
        public string $caption,
        public string $filename,
        /** The short public link (already in the caption). */
        public string $shareUrl = '',
    ) {}
}
