<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\BillingCycle;
use App\Enums\PaymentGateway;
use App\Enums\PaymentPurpose;
use App\Enums\PaymentStatus;
use App\Enums\PlanTier;
use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\Merchant;
use App\Models\PaymentTransaction;
use App\Models\SecurityAuditLog;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\Pricing\FxRates;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

final class DashboardController extends Controller
{
    public function __invoke(Request $request): View|RedirectResponse
    {
        // Support agents and QA reviewers work from the support console.
        $user = $request->user();
        if ($user instanceof User && ! $user->isPlatformAdmin()) {
            return redirect()->route('admin.support.index');
        }

        $plans = SubscriptionPlan::query()->get()->keyBy(static fn (SubscriptionPlan $p): string => $p->tier->value);
        $active = Merchant::query()->where('plan_tier', '!=', PlanTier::FREE->value)->where('plan_expires_at', '>', now())->get(['plan_tier', 'billing_cycle']);

        // One reporting currency (the plans' base, USD recommended): amounts in other currencies are
        // converted at the cached rate so money is never added across currencies.
        $fx = app(FxRates::class);
        $reporting = (string) ($plans->first()->currency ?? 'USD');
        $toReporting = static fn (int $minor, string $currency): int => $fx->convert($minor, $currency, $reporting) ?? ($currency === $reporting ? $minor : 0);

        // MRR: yearly plans are normalised to a monthly amount.
        $mrr = 0;
        foreach ($active as $merchant) {
            $plan = $plans->get($merchant->plan_tier->value);
            if ($plan !== null) {
                $mrr += $toReporting($merchant->billing_cycle === BillingCycle::YEARLY ? intdiv($plan->yearly_price_minor, 12) : $plan->monthly_price_minor, $plan->currency);
            }
        }

        $monthStart = now()->startOfMonth();
        $packSales = PaymentTransaction::query()->where('purpose', PaymentPurpose::CREDIT_PACK->value)
            ->where('status', PaymentStatus::SUCCESSFUL->value)->where('paid_at', '>=', $monthStart)
            ->selectRaw('gateway, currency, sum(amount_minor) as total')->groupBy('gateway', 'currency')->get();
        $packTotal = static fn (string $gateway): int => (int) $packSales->where('gateway', $gateway)
            ->sum(static fn ($row): int => $toReporting((int) $row->getAttribute('total'), (string) $row->getAttribute('currency')));

        $tiers = Merchant::query()->selectRaw('plan_tier, count(*) as n')->groupBy('plan_tier')->pluck('n', 'plan_tier');
        $channels = Document::query()->where('issued_at', '>=', now()->subDays(30))->whereNull('parent_document_id')
            ->selectRaw('channel, count(*) as n')->groupBy('channel')->orderByDesc('n')->pluck('n', 'channel');

        return view('admin.dashboard', [
            'mrr' => $mrr,
            'arr' => $mrr * 12,
            'merchantsTotal' => Merchant::query()->count(),
            'merchantsNew30' => Merchant::query()->where('created_at', '>=', now()->subDays(30))->count(),
            'packFiat' => $packTotal(PaymentGateway::FLUTTERWAVE->value),
            'packCrypto' => $packTotal(PaymentGateway::NOWPAYMENTS->value),
            'reportingCurrency' => $reporting,
            'tiers' => $tiers,
            'channels' => $channels,
            'slips30' => (int) $channels->sum(),
            'queue' => [
                'pending' => DB::table('jobs')->count(),
                'failed' => DB::table('failed_jobs')->count(),
            ],
            'recentPayments' => PaymentTransaction::query()->with('merchant')->latest()->limit(8)->get(),
            'recentSecurity' => SecurityAuditLog::query()->with('actor')->whereIn('severity', ['WARNING', 'CRITICAL'])->latest('created_at')->limit(8)->get(),
        ]);
    }
}
