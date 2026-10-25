<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToMerchant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $merchant_id
 * @property string $sku
 * @property string $name
 * @property string|null $description
 * @property int $unit_price_minor
 * @property int $stock_quantity
 * @property int $low_stock_threshold
 * @property bool $track_stock
 * @property bool $is_active
 */
class InventorySku extends Model
{
    use BelongsToMerchant, HasUuids;

    protected $table = 'inventory_skus';

    /** @var list<string> */
    protected $fillable = [
        'merchant_id', 'sku', 'name', 'description', 'unit_price_minor', 'stock_quantity',
        'low_stock_threshold', 'track_stock', 'is_active',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'unit_price_minor' => 'integer',
            'stock_quantity' => 'integer',
            'low_stock_threshold' => 'integer',
            'track_stock' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function isLowStock(): bool
    {
        return $this->track_stock && $this->stock_quantity <= $this->low_stock_threshold;
    }
}
