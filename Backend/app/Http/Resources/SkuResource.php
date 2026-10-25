<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\InventorySku;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin InventorySku */
class SkuResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var InventorySku $s */
        $s = $this->resource;

        return [
            'id' => $s->id,
            'sku' => $s->sku,
            'name' => $s->name,
            'description' => $s->description,
            'unit_price_minor' => $s->unit_price_minor,
            'stock_quantity' => $s->stock_quantity,
            'low_stock_threshold' => $s->low_stock_threshold,
            'track_stock' => $s->track_stock,
            'is_active' => $s->is_active,
            'is_low_stock' => $s->isLowStock(),
        ];
    }
}
