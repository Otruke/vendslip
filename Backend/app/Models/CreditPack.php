<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $name
 * @property int $credits
 * @property int $price_minor
 * @property array<string, int>|null $price_overrides
 * @property string $currency
 * @property bool $is_active
 * @property int $sort_order
 */
class CreditPack extends Model
{
    /** @var list<string> */
    protected $fillable = ['name', 'credits', 'price_minor', 'price_overrides', 'currency', 'is_active', 'sort_order'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'credits' => 'integer',
            'price_minor' => 'integer',
            'price_overrides' => 'array',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
