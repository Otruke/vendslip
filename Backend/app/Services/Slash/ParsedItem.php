<?php

declare(strict_types=1);

namespace App\Services\Slash;

final readonly class ParsedItem
{
    public function __construct(
        public string $description,
        public int $quantity,
        public ?int $unitPriceMinor,
        public string $raw,
    ) {}

    /** @return array{description: string, quantity: int, unit_price_minor: int|null} */
    public function toArray(): array
    {
        return [
            'description' => $this->description,
            'quantity' => $this->quantity,
            'unit_price_minor' => $this->unitPriceMinor,
        ];
    }
}
