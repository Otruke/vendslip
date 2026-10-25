<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $document_id
 * @property string|null $sku_id
 * @property string $description
 * @property int $quantity
 * @property int $unit_price_minor
 * @property int $line_total_minor
 * @property int $position
 */
class DocumentItem extends Model
{
    /** @var list<string> */
    protected $fillable = ['document_id', 'sku_id', 'description', 'quantity', 'unit_price_minor', 'line_total_minor', 'position'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price_minor' => 'integer',
            'line_total_minor' => 'integer',
            'position' => 'integer',
        ];
    }

    /** @return BelongsTo<Document, $this> */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /** @return BelongsTo<InventorySku, $this> */
    public function sku(): BelongsTo
    {
        return $this->belongsTo(InventorySku::class, 'sku_id');
    }
}
