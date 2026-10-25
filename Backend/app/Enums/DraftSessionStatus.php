<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum DraftSessionStatus: string
{
    use EnumHelpers;

    case COLLECTING = 'COLLECTING';
    case AWAITING_CONFIRMATION = 'AWAITING_CONFIRMATION';
    case CONFIRMED = 'CONFIRMED';
    case CANCELLED = 'CANCELLED';
    case EXPIRED = 'EXPIRED';

    public function label(): string
    {
        return match ($this) {
            self::COLLECTING => 'Collecting',
            self::AWAITING_CONFIRMATION => 'Awaiting confirmation',
            self::CONFIRMED => 'Confirmed',
            self::CANCELLED => 'Cancelled',
            self::EXPIRED => 'Expired',
        };
    }
}
