<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum PaymentGateway: string
{
    use EnumHelpers;

    case FLUTTERWAVE = 'FLUTTERWAVE';
    case NOWPAYMENTS = 'NOWPAYMENTS';
    case MANUAL = 'MANUAL';

    public function label(): string
    {
        return match ($this) {
            self::FLUTTERWAVE => 'Flutterwave',
            self::NOWPAYMENTS => 'NOWPayments (Crypto)',
            self::MANUAL => 'Manual',
        };
    }
}
