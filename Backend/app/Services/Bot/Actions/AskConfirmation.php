<?php

declare(strict_types=1);

namespace App\Services\Bot\Actions;

use App\Models\BotDraftSession;

/**
 * The single consolidated pre-confirmation summary.
 *
 * @param  list<array{description: string, quantity: int, unit_minor: int, total_minor: int}>  $lines
 */
final readonly class AskConfirmation implements BotAction
{
    /**
     * @param  list<array{description: string, quantity: int, unit_minor: int, total_minor: int}>  $lines
     */
    public function __construct(
        public BotDraftSession $session,
        public string $docLabel,
        public string $customerName,
        public ?string $customerPhone,
        public ?string $customerAddress,
        public array $lines,
        public int $subtotalMinor,
        public int $discountMinor,
        public int $taxMinor,
        public string $taxLabel,
        public int $shippingMinor,
        public int $totalMinor,
        public int $paidMinor,
        public int $balanceMinor,
        public string $currency,
        public bool $willWatermark,
        public bool $canUseCleanCredit,
        public bool $isUpdate = false,
    ) {}
}
