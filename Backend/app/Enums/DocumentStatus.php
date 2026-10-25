<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum DocumentStatus: string
{
    use EnumHelpers;

    case DRAFT = 'DRAFT';
    case UNPAID = 'UNPAID';
    case PARTIAL = 'PARTIAL';
    case PAID = 'PAID';
    case OVERDUE = 'OVERDUE';
    case VOID = 'VOID';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Draft',
            self::UNPAID => 'Unpaid',
            self::PARTIAL => 'Partially Paid',
            self::PAID => 'Paid',
            self::OVERDUE => 'Overdue',
            self::VOID => 'Void',
        };
    }

    /** Statuses that still carry an outstanding balance. */
    public function isOutstanding(): bool
    {
        return in_array($this, [self::UNPAID, self::PARTIAL, self::OVERDUE], true);
    }
}
