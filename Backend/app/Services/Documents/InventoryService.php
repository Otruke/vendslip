<?php

declare(strict_types=1);

namespace App\Services\Documents;

use App\Exceptions\DomainRuleException;
use App\Models\InventorySku;
use App\Models\Merchant;
use App\Services\Notifications\AlertCatalog;
use App\Services\Notifications\MerchantAlerts;
use Illuminate\Support\Facades\DB;

final class InventoryService
{
    public function __construct(private readonly MerchantAlerts $alerts) {}

    /**
     * Lock SKUs referenced by the lines, verify tenancy and stock, then apply the delta.
     * $direction = -1 to deduct (issue), +1 to restock (void / edit rollback).
     *
     * @param  list<array{quantity: int, sku_id?: string|null}>  $lines
     */
    public function apply(Merchant $merchant, array $lines, int $direction): void
    {
        $quantities = [];
        foreach ($lines as $line) {
            $skuId = $line['sku_id'] ?? null;
            if ($skuId !== null) {
                $quantities[$skuId] = ($quantities[$skuId] ?? 0) + $line['quantity'];
            }
        }
        if ($quantities === []) {
            return;
        }

        $low = [];
        $skus = InventorySku::query()
            ->forMerchant($merchant)
            ->whereIn('id', array_keys($quantities))
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        foreach ($quantities as $skuId => $quantity) {
            $sku = $skus->get($skuId);
            if (! $sku instanceof InventorySku) {
                throw new DomainRuleException('One of the products is not in your inventory.', 'sku_not_found');
            }
            if (! $sku->track_stock) {
                continue;
            }
            // Tracking stock and blocking oversells are separate switches: a product can count down
            // into negative stock (sell now, restock later) when blocking is off.
            if ($direction < 0 && $sku->block_overselling && $sku->stock_quantity < $quantity) {
                throw new DomainRuleException("Only {$sku->stock_quantity} × {$sku->name} left in stock.", 'insufficient_stock');
            }
            $before = $sku->stock_quantity;
            $sku->stock_quantity += $direction * $quantity;
            $sku->save();
            // Alert once, as stock crosses the threshold (not on every sale while it stays low).
            if ($direction < 0 && $before > $sku->low_stock_threshold && $sku->stock_quantity <= $sku->low_stock_threshold) {
                $low[] = $sku;
            }
        }

        if ($low !== []) {
            DB::afterCommit(function () use ($merchant, $low): void {
                foreach ($low as $sku) {
                    $this->alerts->send(
                        $merchant,
                        AlertCatalog::LOW_STOCK,
                        "Low stock: {$sku->name}",
                        "{$sku->name} ({$sku->sku}) is down to {$sku->stock_quantity}. Your low-stock level is {$sku->low_stock_threshold}.",
                        '/dashboard/inventory?low=1',
                    );
                }
            });
        }
    }

    /**
     * Resolve unpriced draft lines ("/item = 2x SKU-SNK-02") against inventory by SKU code
     * or exact name, filling unit price and sku_id.
     *
     * @param  list<array{description: string, quantity: int, unit_price_minor: int|null, sku_id?: string|null}>  $items
     * @return list<array{description: string, quantity: int, unit_price_minor: int|null, sku_id?: string|null}>
     */
    public function resolveDraftItems(Merchant $merchant, array $items): array
    {
        foreach ($items as $i => $item) {
            if (($item['sku_id'] ?? null) !== null) {
                continue;
            }
            $needle = trim($item['description']);
            $sku = InventorySku::query()->forMerchant($merchant)->where('is_active', true)
                ->where(static fn ($q) => $q->whereRaw('lower(sku) = ?', [mb_strtolower($needle)])
                    ->orWhereRaw('lower(name) = ?', [mb_strtolower($needle)]))
                ->first();
            if ($sku === null) {
                continue;
            }
            $items[$i]['sku_id'] = $sku->id;
            $items[$i]['description'] = $sku->name;
            if ($item['unit_price_minor'] === null) {
                $items[$i]['unit_price_minor'] = $sku->unit_price_minor;
            }
        }

        return $items;
    }
}
