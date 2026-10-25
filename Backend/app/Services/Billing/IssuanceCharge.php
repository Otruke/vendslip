<?php

declare(strict_types=1);

namespace App\Services\Billing;

final readonly class IssuanceCharge
{
    public function __construct(
        public bool $consumedPaidCredit,
        public bool $usedFreeQuota,
        public bool $hasWatermark,
    ) {}
}
