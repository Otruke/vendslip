<?php

declare(strict_types=1);

namespace App\Services\Documents;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;

final readonly class Totals
{
    /**
     * @param  list<array{description: string, quantity: int, unit_price_minor: int, line_total_minor: int, sku_id: string|null}>  $lines
     */
    public function __construct(
        public array $lines,
        public int $subtotalMinor,
        public int $discountMinor,
        public int $vatRateBps,
        public bool $vatInclusive,
        public int $taxMinor,
        public int $shippingMinor,
        public int $totalMinor,
        public int $amountPaidMinor,
        public int $balanceDueMinor,
    ) {}

    public function statusFor(DocumentType $type, ?\DateTimeInterface $dueDate = null, ?\DateTimeInterface $today = null): DocumentStatus
    {
        if ($type === DocumentType::CREDIT_NOTE) {
            return DocumentStatus::PAID;
        }
        if ($this->balanceDueMinor === 0) {
            return DocumentStatus::PAID;
        }
        if ($type === DocumentType::INVOICE && $dueDate !== null && $today !== null && $dueDate->format('Y-m-d') < $today->format('Y-m-d')) {
            return DocumentStatus::OVERDUE;
        }

        return $this->amountPaidMinor > 0 ? DocumentStatus::PARTIAL : DocumentStatus::UNPAID;
    }
}
