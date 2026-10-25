<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum PaymentPurpose: string
{
    use EnumHelpers;

    case SUBSCRIPTION = 'SUBSCRIPTION';
    case CREDIT_PACK = 'CREDIT_PACK';
    case INVOICE_PAYMENT = 'INVOICE_PAYMENT';

    public function label(): string
    {
        return match ($this) {
            self::SUBSCRIPTION => 'Subscription',
            self::CREDIT_PACK => 'Credit pack',
            self::INVOICE_PAYMENT => 'Invoice payment',
        };
    }
}
