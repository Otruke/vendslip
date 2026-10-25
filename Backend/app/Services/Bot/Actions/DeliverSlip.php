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
        public string $pdfUrl,
        public string $verifyUrl,
        public ?string $payUrl,
        public string $caption,
        public string $filename,
    ) {}
}
