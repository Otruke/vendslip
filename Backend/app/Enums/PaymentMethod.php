<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum PaymentMethod: string
{
    use EnumHelpers;

    case CASH = 'CASH';
    case BANK_TRANSFER = 'BANK_TRANSFER';
    case CARD = 'CARD';
    case POS = 'POS';
    case FLUTTERWAVE = 'FLUTTERWAVE';
    case CRYPTO = 'CRYPTO';
    case OTHER = 'OTHER';

    public function label(): string
    {
        return match ($this) {
            self::CASH => 'Cash',
            self::BANK_TRANSFER => 'Bank transfer',
            self::CARD => 'Card',
            self::POS => 'POS',
            self::FLUTTERWAVE => 'Flutterwave',
            self::CRYPTO => 'Crypto',
            self::OTHER => 'Other',
        };
    }
}
