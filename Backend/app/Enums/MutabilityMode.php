<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum MutabilityMode: string
{
    use EnumHelpers;

    case IMMUTABLE = 'IMMUTABLE';
    case MUTABLE_WITH_AUDIT = 'MUTABLE_WITH_AUDIT';
    case MUTABLE_UNRESTRICTED = 'MUTABLE_UNRESTRICTED';

    public function label(): string
    {
        return match ($this) {
            self::IMMUTABLE => 'Immutable',
            self::MUTABLE_WITH_AUDIT => 'Mutable with audit',
            self::MUTABLE_UNRESTRICTED => 'Mutable (unrestricted)',
        };
    }
}
