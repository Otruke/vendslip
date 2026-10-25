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
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

final class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $plans = SubscriptionPlan::query()->get()->keyBy(static fn (SubscriptionPlan $p): string => $p->tier->value);
        $active = Merchant::query()->where('plan_tier', '!=', PlanTier::FREE->value)->where('plan_expires_at', '>', now())->get(['plan_tier', 'billing_cycle']);

        // MRR: yearly plans are normalised to a monthly amount.
        $mrr = 0;
        foreach ($active as $merchant) {
            $plan = $plans->get($merchant->plan_tier->value);
            if ($plan !== null) {
                $mrr += $merchant->billing_cycle === BillingCycle::YEARLY ? intdiv($plan->yearly_price_minor, 12) : $plan->monthly_price_minor;
            }
        }

        $monthStart = now()->startOfMonth();
        $packSales = PaymentTransaction::query()->where('purpose', PaymentPurpose::CREDIT_PACK->value)
            ->where('status', PaymentStatus::SUCCESSFUL->value)->where('paid_at', '>=', $monthStart)
            ->selectRaw('gateway, sum(amount_minor) as total, count(*) as n')->groupBy('gateway')->get()->keyBy('gateway');

        $tiers = Merchant::query()->selectRaw('plan_tier, count(*) as n')->groupBy('plan_tier')->pluck('n', 'plan_tier');
        $channels = Document::query()->where('issued_at', '>=', now()->subDays(30))->whereNull('parent_document_id')
            ->selectRaw('channel, count(*) as n')->groupBy('channel')->orderByDesc('n')->pluck('n', 'channel');

        return view('admin.dashboard', [
            'mrr' => $mrr,
            'arr' => $mrr * 12,
            'merchantsTotal' => Merchant::query()->count(),
            'merchantsNew30' => Merchant::query()->where('created_at', '>=', now()->subDays(30))->count(),
            'packFiat' => (int) ($packSales->get(PaymentGateway::FLUTTERWAVE->value)?->getAttribute('total') ?? 0),
            'packCrypto' => (int) ($packSales->get(PaymentGateway::NOWPAYMENTS->value)?->getAttribute('total') ?? 0),
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
