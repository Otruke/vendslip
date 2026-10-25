<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\Merchant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tenant scoping helper. Every merchant-owned model exposes forMerchant() so that
 * controllers never query across tenants by accident.
 *
 * @property string $merchant_id
 */
trait BelongsToMerchant
{
    /** @return BelongsTo<Merchant, $this> */
    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeForMerchant(Builder $query, Merchant|string $merchant): Builder
    {
        return $query->where($this->qualifyColumn('merchant_id'), $merchant instanceof Merchant ? $merchant->id : $merchant);
    }
}
