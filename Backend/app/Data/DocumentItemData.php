<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Uuid;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class DocumentItemData extends Data
{
    public function __construct(
        #[Max(500)]
        public string $description,
        #[Min(1), Max(1_000_000)]
        public int $quantity,
        #[Min(0), Max(1_000_000_000_000_000)]
        public int $unit_price_minor,
        #[Uuid]
        public ?string $sku_id = null,
    ) {}
}
