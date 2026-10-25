<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\Channel;
use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Enums\Permission;
use App\Http\Resources\DocumentResource;
use App\Http\Resources\SkuResource;
use App\Models\BotDraftSession;
use App\Models\Document;
use App\Models\InventorySku;
use App\Services\Billing\PlanEntitlements;
use App\Services\Billing\QuotaService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class DashboardController extends ApiController
{
    public function __construct(
        private readonly QuotaService $quota,
        private readonly PlanEntitlements $entitlements,
    ) {}

    public function overview(Request $request): JsonResponse
    {
        $this->authorizePermission($request, Permission::DOCUMENTS_VIEW);
        $range = (string) ($request->validate(['range' => ['nullable', 'in:7d,30d,90d,12m']])['range'] ?? '30d');
        $merchant = $this->merchant($request);
        $tz = (string) config('netslip.timezone');
        $now = CarbonImmutable::now($tz);
        [$start, $bucket] = match ($range) {
            '7d' => [$now->subDays(6)->startOfDay(), 'day'],
            '90d' => [$now->subDays(89)->startOfDay(), 'week'],
            '12m' => [$now->subMonths(11)->startOfMonth(), 'month'],
            default => [$now->subDays(29)->startOfDay(), 'day'],
        };
        $previousStart = $start->sub($now->diffAsCarbonInterval($start));

        $settled = fn (CarbonImmutable $from, CarbonImmutable $to): int => $this->settledRevenue($merchant->id, $from, $to);
        $revenue = $settled($start, $now);
        $previousRevenue = $settled($previousStart, $start);

        $receivables = Document::query()->forMerchant($merchant)
            ->where('doc_type', DocumentType::INVOICE->value)
            ->whereIn('status', [DocumentStatus::UNPAID->value, DocumentStatus::PARTIAL->value, DocumentStatus::OVERDUE->value]);
        $receivablesTotal = (int) (clone $receivables)->sum('balance_due_minor');
        $overdueCount = (clone $receivables)->where('status', DocumentStatus::OVERDUE->value)->count();

        $savedCalls = (int) BotDraftSession::query()->forMerchant($merchant)->where('created_at', '>=', $start)->sum('saved_outbound_calls_count');
        $issuedCount = Document::query()->forMerchant($merchant)->whereNull('parent_document_id')
            ->where('doc_type', '!=', DocumentType::CREDIT_NOTE->value)
            ->where('status', '!=', DocumentStatus::VOID->value)
            ->where('issued_at', '>=', $start)->count();

        return response()->json([
            'range' => $range,
            'kpis' => [
                'settled_revenue_minor' => $revenue,
                'settled_revenue_change_pct' => $previousRevenue > 0 ? round(($revenue - $previousRevenue) / $previousRevenue * 100, 1) : null,
                'receivables_minor' => $receivablesTotal,
                'overdue_count' => $overdueCount,
                'slips_issued' => $issuedCount,
                'api_calls_saved' => $savedCalls,
                'free_remaining' => $this->quota->remainingFree($merchant),
                'monthly_quota' => $this->entitlements->monthlyQuota($merchant),
                'paid_credits' => $merchant->paid_credits_balance,
            ],
            'series' => $this->series($merchant->id, $start, $now, $bucket, $tz),
            'channels' => $this->channels($merchant->id, $start),
            'recent' => DocumentResource::collection(
                Document::query()->forMerchant($merchant)->orderByDesc('issued_at')->limit(8)->get(),
            )->resolve(),
            'debtors' => Document::query()->forMerchant($merchant)
                ->where('doc_type', DocumentType::INVOICE->value)
                ->where('balance_due_minor', '>', 0)
                ->where('status', '!=', DocumentStatus::VOID->value)
                ->selectRaw('customer_name, customer_phone, customer_id, sum(balance_due_minor) as outstanding_minor, count(*) as invoices')
                ->groupBy('customer_name', 'customer_phone', 'customer_id')
                ->orderByDesc('outstanding_minor')->limit(5)->get()
                ->map(static fn ($r): array => [
                    'customer_id' => $r->getAttribute('customer_id'),
                    'customer_name' => $r->getAttribute('customer_name'),
                    'customer_phone' => $r->getAttribute('customer_phone'),
                    'outstanding_minor' => (int) $r->getAttribute('outstanding_minor'),
                    'invoices' => (int) $r->getAttribute('invoices'),
                ]),
            'low_stock' => $this->entitlements->has($merchant, PlanEntitlements::INVENTORY)
                ? SkuResource::collection(InventorySku::query()->forMerchant($merchant)->where('track_stock', true)
                    ->whereColumn('stock_quantity', '<=', 'low_stock_threshold')->orderBy('stock_quantity')->limit(5)->get())->resolve()
                : [],
        ]);
    }

    /**
     * Cash actually received: standalone receipts + invoice payments − credit notes. Payment receipts
     * (children of invoices) are excluded because their cash is already counted via document_payments.
     */
    private function settledRevenue(string $merchantId, CarbonImmutable $from, CarbonImmutable $to): int
    {
        $receipts = (int) DB::table('documents')->where('merchant_id', $merchantId)
            ->where('doc_type', DocumentType::RECEIPT->value)->whereNull('parent_document_id')
            ->where('status', '!=', DocumentStatus::VOID->value)
            ->whereBetween('issued_at', [$from, $to])->sum('amount_paid_minor');

        $invoicePayments = (int) DB::table('document_payments')
            ->join('documents', 'documents.id', '=', 'document_payments.document_id')
            ->where('documents.merchant_id', $merchantId)
            ->where('documents.status', '!=', DocumentStatus::VOID->value)
            ->whereBetween('document_payments.paid_at', [$from, $to])->sum('document_payments.amount_minor');

        $credits = (int) DB::table('documents')->where('merchant_id', $merchantId)
            ->where('doc_type', DocumentType::CREDIT_NOTE->value)
            ->where('status', '!=', DocumentStatus::VOID->value)
            ->whereBetween('issued_at', [$from, $to])->sum('total_minor');

        return $receipts + $invoicePayments - $credits;
    }

    /** @return list<array{bucket: string, revenue_minor: int, invoiced_minor: int}> */
    private function series(string $merchantId, CarbonImmutable $start, CarbonImmutable $now, string $bucket, string $tz): array
    {
        // Bucket and timezone are bound as parameters (no SQL string interpolation).
        $receipts = DB::table('documents')->where('merchant_id', $merchantId)
            ->where('doc_type', DocumentType::RECEIPT->value)->whereNull('parent_document_id')
            ->where('status', '!=', DocumentStatus::VOID->value)->where('issued_at', '>=', $start)
            ->selectRaw("date_trunc(?, issued_at AT TIME ZONE 'UTC' AT TIME ZONE ?) as b, sum(amount_paid_minor) as v", [$bucket, $tz])
            ->groupBy('b')->pluck('v', 'b');
        $payments = DB::table('document_payments')->join('documents', 'documents.id', '=', 'document_payments.document_id')
            ->where('documents.merchant_id', $merchantId)->where('documents.status', '!=', DocumentStatus::VOID->value)
            ->where('document_payments.paid_at', '>=', $start)
            ->selectRaw("date_trunc(?, document_payments.paid_at AT TIME ZONE 'UTC' AT TIME ZONE ?) as b, sum(document_payments.amount_minor) as v", [$bucket, $tz])
            ->groupBy('b')->pluck('v', 'b');
        $invoiced = DB::table('documents')->where('merchant_id', $merchantId)
            ->where('doc_type', DocumentType::INVOICE->value)->where('status', '!=', DocumentStatus::VOID->value)
            ->where('issued_at', '>=', $start)
            ->selectRaw("date_trunc(?, issued_at AT TIME ZONE 'UTC' AT TIME ZONE ?) as b, sum(total_minor) as v", [$bucket, $tz])
            ->groupBy('b')->pluck('v', 'b');

        $normalise = static function ($collection): array {
            $out = [];
            foreach ($collection as $key => $value) {
                $out[substr((string) $key, 0, 10)] = (int) $value;
            }

            return $out;
        };
        $receipts = $normalise($receipts);
        $payments = $normalise($payments);
        $invoiced = $normalise($invoiced);

        $points = [];
        $cursor = match ($bucket) {
            'week' => $start->startOfWeek(),
            'month' => $start->startOfMonth(),
            default => $start->startOfDay(),
        };
        while ($cursor <= $now) {
            $key = $cursor->toDateString();
            $points[] = [
                'bucket' => $key,
                'revenue_minor' => ($receipts[$key] ?? 0) + ($payments[$key] ?? 0),
                'invoiced_minor' => $invoiced[$key] ?? 0,
            ];
            $cursor = match ($bucket) {
                'week' => $cursor->addWeek(),
                'month' => $cursor->addMonth(),
                default => $cursor->addDay(),
            };
        }

        return $points;
    }

    /** @return list<array{channel: string, label: string, count: int, total_minor: int}> */
    private function channels(string $merchantId, CarbonImmutable $start): array
    {
        $rows = DB::table('documents')->where('merchant_id', $merchantId)->whereNull('parent_document_id')
            ->where('doc_type', '!=', DocumentType::CREDIT_NOTE->value)
            ->where('status', '!=', DocumentStatus::VOID->value)->where('issued_at', '>=', $start)
            ->selectRaw('channel, count(*) as c, sum(total_minor) as t')->groupBy('channel')->get()->keyBy('channel');

        return array_map(static function (Channel $channel) use ($rows): array {
            $row = $rows->get($channel->value);

            return [
                'channel' => $channel->value,
                'label' => $channel->label(),
                'count' => (int) ($row->c ?? 0),
                'total_minor' => (int) ($row->t ?? 0),
            ];
        }, Channel::cases());
    }
}
