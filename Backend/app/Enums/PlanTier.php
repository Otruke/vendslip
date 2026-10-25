<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum PlanTier: string
{
    use EnumHelpers;

    case FREE = 'FREE';
    case PRO = 'PRO';
    case BUSINESS_PLUS = 'BUSINESS_PLUS';

    public function label(): string
    {
        return match ($this) {
            self::FREE => 'Free',
            self::PRO => 'Pro Merchant',
            self::BUSINESS_PLUS => 'Business Plus',
        };
    }

    public function rank(): int
    {
        return match ($this) {
            self::FREE => 0,
            self::PRO => 1,
            self::BUSINESS_PLUS => 2,
        };
    }

    public function atLeast(self $tier): bool
    {
        return $this->rank() >= $tier->rank();
    }
}
