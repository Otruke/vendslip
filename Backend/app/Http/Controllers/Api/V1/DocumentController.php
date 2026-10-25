<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Data\DocumentDraftData;
use App\Enums\Channel;
use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Enums\PaymentMethod;
use App\Enums\Permission;
use App\Http\Resources\DocumentResource;
use App\Http\Resources\RevisionResource;
use App\Models\Document;
use App\Services\Billing\PlanEntitlements;
use App\Services\Documents\DocumentIssuanceService;
use App\Services\Documents\DocumentLifecycleService;
use App\Services\Documents\MutabilityGuard;
use App\Services\Pdf\SlipRenderer;
use App\Support\Money;
use App\Support\Phone;
use App\Support\Urls;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class DocumentController extends ApiController
{
    public function __construct(
        private readonly DocumentIssuanceService $issuance,
        private readonly DocumentLifecycleService $lifecycle,
        private readonly MutabilityGuard $guard,
        private readonly SlipRenderer $renderer,
        private readonly PlanEntitlements $entitlements,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorizePermission($request, Permission::DOCUMENTS_VIEW);
        $filters = $request->validate([
            'type' => ['nullable', Rule::enum(DocumentType::class)],
            'status' => ['nullable', Rule::enum(DocumentStatus::class)],
            'channel' => ['nullable', Rule::enum(Channel::class)],
            'customer_id' => ['nullable', 'uuid'],
            'q' => ['nullable', 'string', 'max:120'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
            'outstanding' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
        ]);

        $documents = $this->filtered($request, $filters)
            ->withCount('revisions')
            ->orderByDesc('issued_at')
            ->orderByDesc('doc_number')
            ->paginate((int) ($filters['per_page'] ?? 20))
            ->withQueryString();

        return DocumentResource::collection($documents);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizePermission($request, Permission::DOCUMENTS_ISSUE);
        $draft = DocumentDraftData::validateAndCreate($request->all());
        $document = $this->issuance->issue($this->merchant($request), $this->user($request), $draft, Channel::WEB);

        return (new DocumentResource($document->load('items')))->response()->setStatusCode(201);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $this->authorizePermission($request, Permission::DOCUMENTS_VIEW);
        $document = $this->find($request, $id)->load(['items', 'payments', 'children', 'parent', 'createdBy'])->loadCount('revisions');
        $verdict = $this->guard->evaluate($document, $this->user($request));

        return response()->json([
            'data' => (new DocumentResource($document))->resolve(),
            'meta' => [
                'can_edit' => $verdict['allowed'] && $this->user($request)->can(Permission::DOCUMENTS_EDIT->value),
                'edit_reason_required' => $verdict['reason_required'],
                'edit_blocked_reason' => $verdict['message'],
                'mutability_mode' => $document->merchant->record_mutability_mode->value,
                'credited_minor' => $document->creditedAmountMinor(),
            ],
        ]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $this->authorizePermission($request, Permission::DOCUMENTS_EDIT);
        $request->validate(['reason' => ['nullable', 'string', 'max:1000'], 'draft' => ['required', 'array']]);
        $draft = DocumentDraftData::validateAndCreate((array) $request->input('draft'));
        $document = $this->lifecycle->edit(
            $this->find($request, $id), $this->user($request), $draft, $request->input('reason'), $request->ip(), $request->userAgent(),
        );

        return response()->json(['data' => (new DocumentResource($document))->resolve()]);
    }

    public function void(Request $request, string $id): JsonResponse
    {
        $this->authorizePermission($request, Permission::DOCUMENTS_VOID);
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:500']]);
        $document = $this->lifecycle->void($this->find($request, $id), $this->user($request), $data['reason'], $request->ip(), $request->userAgent());

        return response()->json(['data' => (new DocumentResource($document))->resolve()]);
    }

    public function creditNote(Request $request, string $id): JsonResponse
    {
        $this->authorizePermission($request, Permission::DOCUMENTS_VOID);
        $data = $request->validate([
            'amount_minor' => ['required', 'integer', 'min:1', 'max:'.Money::MAX_MINOR],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);
        $credit = $this->lifecycle->issueCreditNote($this->find($request, $id), $this->user($request), (int) $data['amount_minor'], $data['reason'], $request->ip());

        return (new DocumentResource($credit))->response()->setStatusCode(201);
    }

    public function recordPayment(Request $request, string $id): JsonResponse
    {
        $this->authorizePermission($request, Permission::PAYMENTS_RECORD);
        $data = $request->validate([
            'amount_minor' => ['required', 'integer', 'min:1', 'max:'.Money::MAX_MINOR],
            'method' => ['required', Rule::enum(PaymentMethod::class)],
            'reference' => ['nullable', 'string', 'max:120'],
            'paid_at' => ['nullable', 'date', 'before_or_equal:now'],
            'issue_receipt' => ['sometimes', 'boolean'],
        ]);

        $result = $this->lifecycle->recordPayment(
            $this->find($request, $id),
            (int) $data['amount_minor'],
            PaymentMethod::from($data['method']),
            $data['reference'] ?? null,
            $this->user($request),
            isset($data['paid_at']) ? CarbonImmutable::parse($data['paid_at']) : null,
            null,
            (bool) ($data['issue_receipt'] ?? true),
        );

        return response()->json([
            'invoice' => (new DocumentResource($result['invoice']))->resolve(),
            'receipt' => $result['receipt'] !== null ? (new DocumentResource($result['receipt']->load('items')))->resolve() : null,
        ], 201);
    }

    public function revisions(Request $request, string $id): AnonymousResourceCollection
    {
        $this->authorizePermission($request, Permission::DOCUMENTS_VIEW);

        return RevisionResource::collection($this->find($request, $id)->revisions()->with('actor')->get());
    }

    public function pdf(Request $request, string $id): Response
    {
        $this->authorizePermission($request, Permission::DOCUMENTS_VIEW);
        $document = $this->find($request, $id);
        $disposition = $request->boolean('download') ? 'attachment' : 'inline';

        return response($this->renderer->content($document), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => $disposition.'; filename="'.$document->doc_number.'.pdf"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function share(Request $request, string $id): JsonResponse
    {
        $this->authorizePermission($request, Permission::DOCUMENTS_VIEW);
        $document = $this->find($request, $id);
        $pdfUrl = URL::temporarySignedRoute('documents.public-pdf', now()->addMinutes((int) config('netslip.pdf.signed_url_minutes')), ['uid' => $document->public_uid]);
        $total = Money::format($document->total_minor, $document->currency);
        $label = $document->doc_type === DocumentType::INVOICE ? 'invoice' : ($document->doc_type === DocumentType::RECEIPT ? 'receipt' : 'credit note');
        $text = "Hi {$document->customer_name}, here is your {$label} {$document->doc_number} from {$document->merchant->name} ({$total}).\n{$pdfUrl}\nVerify: ".Urls::verify($document->verification_hash);
        if ($document->doc_type === DocumentType::INVOICE && $document->balance_due_minor > 0) {
            $text .= "\nPay online: ".Urls::pay($document->public_uid);
        }

        return response()->json([
            'pdf_url' => $pdfUrl,
            'verify_url' => Urls::verify($document->verification_hash),
            'pay_url' => $document->doc_type === DocumentType::INVOICE ? Urls::pay($document->public_uid) : null,
            'message' => $text,
            'whatsapp_url' => 'https://wa.me/'.(Phone::internationalDigits($document->customer_phone) ?? '').'?text='.rawurlencode($text),
            'telegram_url' => 'https://t.me/share/url?url='.rawurlencode($pdfUrl).'&text='.rawurlencode("{$document->doc_number} · {$total}"),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $this->authorizePermission($request, Permission::REPORTS_VIEW);
        $this->entitlements->ensure($this->merchant($request), PlanEntitlements::EXPORT);
        $filters = $request->validate([
            'type' => ['nullable', Rule::enum(DocumentType::class)],
            'status' => ['nullable', Rule::enum(DocumentStatus::class)],
            'channel' => ['nullable', Rule::enum(Channel::class)],
            'q' => ['nullable', 'string', 'max:120'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
        ]);
        $query = $this->filtered($request, $filters)->orderBy('issued_at');

        return response()->streamDownload(static function () use ($query): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }
            fputcsv($out, ['Number', 'Type', 'Status', 'Channel', 'Issued', 'Due', 'Customer', 'Phone', 'Currency', 'Subtotal', 'Discount', 'VAT', 'Shipping', 'Total', 'Paid', 'Balance', 'Verification code'], escape: '\\');
            $query->chunk(500, static function ($rows) use ($out): void {
                foreach ($rows as $d) {
                    /** @var Document $d */
                    fputcsv($out, [
                        $d->doc_number, $d->doc_type->value, $d->status->value, $d->channel->value,
                        $d->issued_at->toDateTimeString(), $d->due_date?->toDateString(),
                        self::csvSafe($d->customer_name), self::csvSafe((string) $d->customer_phone), $d->currency,
                        $d->subtotal_minor / 100, $d->discount_minor / 100, $d->tax_minor / 100, $d->shipping_minor / 100,
                        $d->total_minor / 100, $d->amount_paid_minor / 100, $d->balance_due_minor / 100, $d->verification_code,
                    ], escape: '\\');
                }
            });
            fclose($out);
        }, 'netslip-ledger-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Neutralise spreadsheet formula injection (OWASP CSV injection). */
    private static function csvSafe(string $value): string
    {
        return preg_match('/^[=+\-@\t\r]/', $value) === 1 ? "'".$value : $value;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<Document>
     */
    private function filtered(Request $request, array $filters): Builder
    {
        $query = Document::query()->forMerchant($this->merchant($request));

        if (! empty($filters['type'])) {
            $query->where('doc_type', $filters['type']);
        }
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['channel'])) {
            $query->where('channel', $filters['channel']);
        }
        if (! empty($filters['customer_id'])) {
            $query->where('customer_id', $filters['customer_id']);
        }
        if (! empty($filters['outstanding'])) {
            $query->whereIn('status', [DocumentStatus::UNPAID->value, DocumentStatus::PARTIAL->value, DocumentStatus::OVERDUE->value]);
        }
        $tz = (string) config('netslip.timezone');
        if (! empty($filters['from'])) {
            $query->where('issued_at', '>=', CarbonImmutable::createFromFormat('Y-m-d', $filters['from'], $tz)?->startOfDay());
        }
        if (! empty($filters['to'])) {
            $query->where('issued_at', '<=', CarbonImmutable::createFromFormat('Y-m-d', $filters['to'], $tz)?->endOfDay());
        }
        if (! empty($filters['q'])) {
            $term = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], (string) $filters['q']).'%';
            $query->where(static fn (Builder $q) => $q->where('doc_number', 'ilike', $term)
                ->orWhere('customer_name', 'ilike', $term)
                ->orWhere('customer_phone', 'ilike', $term)
                ->orWhere('verification_code', 'ilike', $term));
        }

        return $query;
    }

    private function find(Request $request, string $id): Document
    {
        abort_unless(preg_match('/^[0-9a-f-]{36}$/i', $id) === 1, 404);

        return Document::query()->forMerchant($this->merchant($request))->findOrFail($id);
    }
}
