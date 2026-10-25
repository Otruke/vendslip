<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Enums\Permission;
use App\Http\Resources\CustomerResource;
use App\Http\Resources\DocumentResource;
use App\Models\Customer;
use App\Models\Document;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class CustomerController extends ApiController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorizePermission($request, Permission::DOCUMENTS_VIEW);
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'debtors' => ['nullable', 'boolean'],
            'sort' => ['nullable', 'in:name,outstanding,recent'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
        ]);
        $outstandingStatuses = "'".implode("','", [DocumentStatus::UNPAID->value, DocumentStatus::PARTIAL->value, DocumentStatus::OVERDUE->value])."'";

        $query = Customer::query()->forMerchant($this->merchant($request))
            ->select('customers.*')
            ->selectSub(Document::query()->selectRaw('count(*)')->whereColumn('documents.customer_id', 'customers.id')->whereNull('parent_document_id'), 'documents_count')
            ->selectSub(Document::query()->selectRaw('coalesce(sum(total_minor),0)')->whereColumn('documents.customer_id', 'customers.id')
                ->whereNull('parent_document_id')->where('doc_type', '!=', DocumentType::CREDIT_NOTE->value)->where('status', '!=', DocumentStatus::VOID->value), 'total_billed_minor')
            ->selectSub(Document::query()->selectRaw('coalesce(sum(balance_due_minor),0)')->whereColumn('documents.customer_id', 'customers.id')
                ->where('doc_type', DocumentType::INVOICE->value)->whereRaw("status in ({$outstandingStatuses})"), 'outstanding_minor')
            ->selectSub(Document::query()->selectRaw('max(issued_at)')->whereColumn('documents.customer_id', 'customers.id'), 'last_purchase_at');

        if (! empty($filters['q'])) {
            $term = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], (string) $filters['q']).'%';
            $query->where(static fn (Builder $q) => $q->where('name', 'ilike', $term)->orWhere('phone', 'ilike', $term)->orWhere('email', 'ilike', $term));
        }
        if (! empty($filters['debtors'])) {
            $query->whereExists(static fn ($q) => $q->selectRaw('1')->from('documents')->whereColumn('documents.customer_id', 'customers.id')
                ->where('doc_type', DocumentType::INVOICE->value)->where('balance_due_minor', '>', 0)->whereRaw("status in ({$outstandingStatuses})"));
        }
        match ($filters['sort'] ?? 'recent') {
            'name' => $query->orderBy('name'),
            'outstanding' => $query->orderByDesc('outstanding_minor'),
            default => $query->orderByRaw('last_purchase_at desc nulls last'),
        };

        return CustomerResource::collection($query->paginate((int) ($filters['per_page'] ?? 20))->withQueryString());
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizePermission($request, Permission::CUSTOMERS_MANAGE);
        $customer = Customer::query()->create([...$this->validated($request), 'merchant_id' => $this->merchant($request)->id]);

        return (new CustomerResource($customer))->response()->setStatusCode(201);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $this->authorizePermission($request, Permission::DOCUMENTS_VIEW);
        $customer = $this->find($request, $id);
        $documents = Document::query()->forMerchant($this->merchant($request))->where('customer_id', $customer->id)
            ->orderByDesc('issued_at')->limit(50)->get();

        return response()->json([
            'data' => (new CustomerResource($customer))->resolve(),
            'documents' => DocumentResource::collection($documents)->resolve(),
            'summary' => [
                'total_billed_minor' => (int) $documents->whereNull('parent_document_id')->where('doc_type', '!=', DocumentType::CREDIT_NOTE)->where('status', '!=', DocumentStatus::VOID)->sum('total_minor'),
                'outstanding_minor' => (int) $documents->where('doc_type', DocumentType::INVOICE)->filter(static fn (Document $d): bool => $d->status->isOutstanding())->sum('balance_due_minor'),
            ],
        ]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $this->authorizePermission($request, Permission::CUSTOMERS_MANAGE);
        $customer = $this->find($request, $id);
        $customer->update($this->validated($request));

        return response()->json(['data' => (new CustomerResource($customer))->resolve()]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $this->authorizePermission($request, Permission::CUSTOMERS_MANAGE);
        // Documents keep their customer snapshot; the FK is nulled on delete.
        $this->find($request, $id)->delete();

        return response()->json(['ok' => true]);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'phone' => ['nullable', 'string', 'max:32'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
    }

    private function find(Request $request, string $id): Customer
    {
        abort_unless(preg_match('/^[0-9a-f-]{36}$/i', $id) === 1, 404);

        return Customer::query()->forMerchant($this->merchant($request))->findOrFail($id);
    }
}
