<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum DocumentType: string
{
    use EnumHelpers;

    case RECEIPT = 'RECEIPT';
    case INVOICE = 'INVOICE';
    case CREDIT_NOTE = 'CREDIT_NOTE';

    public function label(): string
    {
        return match ($this) {
            self::RECEIPT => 'Receipt',
            self::INVOICE => 'Invoice',
            self::CREDIT_NOTE => 'Credit Note',
        };
    }

    public function prefix(): string
    {
        return match ($this) {
            self::RECEIPT => 'RCP',
            self::INVOICE => 'INV',
            self::CREDIT_NOTE => 'CRN',
        };
    }
}
