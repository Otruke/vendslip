<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum BillingCycle: string
{
    use EnumHelpers;

    case MONTHLY = 'MONTHLY';
    case YEARLY = 'YEARLY';

    public function label(): string
    {
        return match ($this) {
            self::MONTHLY => 'Monthly',
            self::YEARLY => 'Yearly',
        };
    }
}
