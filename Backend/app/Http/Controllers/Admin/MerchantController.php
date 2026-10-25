<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\AuditSeverity;
use App\Enums\BillingCycle;
use App\Enums\MerchantStatus;
use App\Enums\MutabilityMode;
use App\Enums\PlanTier;
use App\Http\Controllers\Controller;
use App\Models\BotDraftSession;
use App\Models\Document;
use App\Models\Merchant;
use App\Models\User;
use App\Services\Billing\PlanEntitlements;
use App\Services\Billing\QuotaService;
use App\Services\Security\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class MerchantController extends Controller
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly QuotaService $quota,
        private readonly PlanEntitlements $entitlements,
    ) {}

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'tier' => ['nullable', Rule::enum(PlanTier::class)],
            'status' => ['nullable', Rule::enum(MerchantStatus::class)],
            'mode' => ['nullable', Rule::enum(MutabilityMode::class)],
        ]);
        $query = Merchant::query()->withCount('documents')->latest();
        if (! empty($filters['q'])) {
            $term = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], (string) $filters['q']).'%';
            $query->where(static fn (Builder $q) => $q->where('name', 'ilike', $term)->orWhere('email', 'ilike', $term)->orWhere('slug', 'ilike', $term));
        }
        foreach (['tier' => 'plan_tier', 'status' => 'status', 'mode' => 'record_mutability_mode'] as $key => $column) {
            if (! empty($filters[$key])) {
                $query->where($column, $filters[$key]);
            }
        }

        return view('admin.merchants.index', ['merchants' => $query->paginate(25)->withQueryString(), 'filters' => $filters]);
    }

    public function show(Merchant $merchant): View
    {
        $documents = Document::query()->forMerchant($merchant)->withCount('revisions')
            ->with(['revisions' => static fn ($q) => $q->with('actor')->limit(20)])
            ->latest('issued_at')->limit(20)->get();

        return view('admin.merchants.show', [
            'merchant' => $merchant,
            'owner' => User::query()->where('merchant_id', $merchant->id)->role('Owner')->first(),
            'members' => User::query()->where('merchant_id', $merchant->id)->count(),
            'documents' => $documents,
            'stats' => [
                'documents' => Document::query()->forMerchant($merchant)->count(),
                'billed' => (int) Document::query()->forMerchant($merchant)->whereNull('parent_document_id')->where('doc_type', '!=', 'CREDIT_NOTE')->where('status', '!=', 'VOID')->sum('total_minor'),
                'saved_calls' => (int) BotDraftSession::query()->forMerchant($merchant)->sum('saved_outbound_calls_count'),
                'free_used' => $this->quota->freeUsedThisPeriod($merchant),
                'quota' => $this->entitlements->monthlyQuota($merchant),
            ],
        ]);
    }

    public function updateMutability(Request $request, Merchant $merchant): RedirectResponse
    {
        $data = $request->validate([
            'record_mutability_mode' => ['required', Rule::enum(MutabilityMode::class)],
            'allow_staff_edits' => ['required', 'boolean'],
            'mutable_window_hours' => ['required', 'integer', 'between:0,8760'],
        ]);
        $before = $merchant->only(['record_mutability_mode', 'allow_staff_edits', 'mutable_window_hours']);
        $merchant->update($data);
        $this->audit->log('admin.mutability_changed', $request->user() instanceof User ? $request->user() : null, $merchant, [
            'before' => ['mode' => $before['record_mutability_mode']->value, 'staff' => $before['allow_staff_edits'], 'window' => $before['mutable_window_hours']],
            'after' => ['mode' => $data['record_mutability_mode'], 'staff' => (bool) $data['allow_staff_edits'], 'window' => (int) $data['mutable_window_hours']],
        ], AuditSeverity::WARNING);

        return back()->with('status', 'Ledger mutability policy updated.');
    }

    public function updatePlan(Request $request, Merchant $merchant): RedirectResponse
    {
        $data = $request->validate([
            'plan_tier' => ['required', Rule::enum(PlanTier::class)],
            'billing_cycle' => ['nullable', Rule::enum(BillingCycle::class)],
            'plan_expires_at' => ['nullable', 'date', 'after:today', 'required_unless:plan_tier,FREE'],
            'reason' => ['required', 'string', 'min:5', 'max:300'],
        ]);
        $merchant->update([
            'plan_tier' => $data['plan_tier'],
            'billing_cycle' => $data['plan_tier'] === PlanTier::FREE->value ? null : ($data['billing_cycle'] ?? BillingCycle::MONTHLY->value),
            'plan_expires_at' => $data['plan_tier'] === PlanTier::FREE->value ? null : $data['plan_expires_at'],
        ]);
        $this->audit->log('admin.plan_override', $request->user() instanceof User ? $request->user() : null, $merchant, $data, AuditSeverity::WARNING);

        return back()->with('status', 'Plan updated.');
    }

    public function adjustCredits(Request $request, Merchant $merchant): RedirectResponse
    {
        $data = $request->validate([
            'delta' => ['required', 'integer', 'between:-100000,100000', 'not_in:0'],
            'reason' => ['required', 'string', 'min:5', 'max:300'],
        ]);
        DB::transaction(static function () use ($merchant, $data): void {
            $locked = Merchant::query()->whereKey($merchant->id)->lockForUpdate()->firstOrFail();
            $locked->paid_credits_balance = max(0, $locked->paid_credits_balance + (int) $data['delta']);
            $locked->save();
        });
        $this->audit->log('admin.credits_adjusted', $request->user() instanceof User ? $request->user() : null, $merchant, $data, AuditSeverity::WARNING);

        return back()->with('status', 'Credits adjusted.');
    }

    public function updateStatus(Request $request, Merchant $merchant): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::enum(MerchantStatus::class)],
            'reason' => ['required_if:status,SUSPENDED', 'nullable', 'string', 'max:500'],
        ]);
        $merchant->update(['status' => $data['status'], 'suspension_reason' => $data['status'] === MerchantStatus::SUSPENDED->value ? $data['reason'] : null]);
        $this->audit->log('admin.merchant_status', $request->user() instanceof User ? $request->user() : null, $merchant, $data, AuditSeverity::CRITICAL);

        return back()->with('status', $data['status'] === 'SUSPENDED' ? 'Merchant suspended.' : 'Merchant reactivated.');
    }
}
