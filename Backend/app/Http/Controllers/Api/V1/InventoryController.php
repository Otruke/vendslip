<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\Permission;
use App\Http\Resources\SkuResource;
use App\Models\InventorySku;
use App\Services\Billing\PlanEntitlements;
use App\Services\Security\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

final class InventoryController extends ApiController
{
    public function __construct(
        private readonly PlanEntitlements $entitlements,
        private readonly AuditLogger $audit,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorizePermission($request, Permission::DOCUMENTS_VIEW);
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'low_stock' => ['nullable', 'boolean'],
            'active' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:200'],
        ]);
        $query = InventorySku::query()->forMerchant($this->merchant($request))->orderBy('name');
        if (! empty($filters['q'])) {
            $term = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], (string) $filters['q']).'%';
            $query->where(static fn (Builder $q) => $q->where('name', 'ilike', $term)->orWhere('sku', 'ilike', $term));
        }
        if (! empty($filters['low_stock'])) {
            $query->where('track_stock', true)->whereColumn('stock_quantity', '<=', 'low_stock_threshold');
        }
        if (array_key_exists('active', $filters) && $filters['active'] !== null) {
            $query->where('is_active', (bool) $filters['active']);
        }

        return SkuResource::collection($query->paginate((int) ($filters['per_page'] ?? 50))->withQueryString());
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizePermission($request, Permission::INVENTORY_MANAGE);
        $merchant = $this->merchant($request);
        $this->entitlements->ensure($merchant, PlanEntitlements::INVENTORY);
        $sku = InventorySku::query()->create([...$this->validated($request, null), 'merchant_id' => $merchant->id]);

        return (new SkuResource($sku))->response()->setStatusCode(201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $this->authorizePermission($request, Permission::INVENTORY_MANAGE);
        $this->entitlements->ensure($this->merchant($request), PlanEntitlements::INVENTORY);
        $sku = $this->find($request, $id);
        $sku->update($this->validated($request, $sku));

        return response()->json(['data' => (new SkuResource($sku))->resolve()]);
    }

    public function adjust(Request $request, string $id): JsonResponse
    {
        $this->authorizePermission($request, Permission::INVENTORY_MANAGE);
        $this->entitlements->ensure($this->merchant($request), PlanEntitlements::INVENTORY);
        $data = $request->validate([
            'delta' => ['required', 'integer', 'between:-100000,100000', 'not_in:0'],
            'reason' => ['required', 'string', 'max:200'],
        ]);
        $sku = DB::transaction(function () use ($request, $id, $data): InventorySku {
            $sku = InventorySku::query()->forMerchant($this->merchant($request))->lockForUpdate()->findOrFail($id);
            $sku->stock_quantity = max(0, $sku->stock_quantity + (int) $data['delta']);
            $sku->save();

            return $sku;
        });
        $this->audit->log('inventory.adjusted', $this->user($request), $sku->merchant_id, ['sku' => $sku->sku, 'delta' => (int) $data['delta'], 'reason' => $data['reason']]);

        return response()->json(['data' => (new SkuResource($sku))->resolve()]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $this->authorizePermission($request, Permission::INVENTORY_MANAGE);
        $this->find($request, $id)->delete();

        return response()->json(['ok' => true]);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?InventorySku $sku): array
    {
        $merchantId = $this->merchant($request)->id;

        return $request->validate([
            'sku' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9._\-]+$/',
                Rule::unique('inventory_skus', 'sku')->where('merchant_id', $merchantId)->ignore($sku?->id)],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
            'unit_price_minor' => ['required', 'integer', 'min:0', 'max:1000000000000000'],
            'stock_quantity' => ['required', 'integer', 'min:0', 'max:10000000'],
            'low_stock_threshold' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'track_stock' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
    }

    private function find(Request $request, string $id): InventorySku
    {
        abort_unless(preg_match('/^[0-9a-f-]{36}$/i', $id) === 1, 404);

        return InventorySku::query()->forMerchant($this->merchant($request))->findOrFail($id);
    }
}
