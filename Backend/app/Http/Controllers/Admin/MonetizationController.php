<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\AuditSeverity;
use App\Http\Controllers\Controller;
use App\Models\CreditPack;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\Billing\PlanEntitlements;
use App\Services\Security\AuditLogger;
use App\Services\Settings\PlatformSettings;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class MonetizationController extends Controller
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly PlatformSettings $settings,
    ) {}

    public function edit(): View
    {
        return view('admin.monetization.edit', [
            'plans' => SubscriptionPlan::query()->orderBy('sort_order')->get(),
            'packs' => CreditPack::query()->orderBy('sort_order')->get(),
            'features' => PlanEntitlements::LABELS,
            'removeWatermarkOnCredit' => (bool) $this->settings->get('billing.remove_watermark_on_paid_credit', true),
            'yearlyLabel' => (string) $this->settings->get('billing.yearly_discount_label'),
        ]);
    }

    public function updatePlan(Request $request, SubscriptionPlan $plan): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'tagline' => ['nullable', 'string', 'max:120'],
            'monthly_price' => ['required', 'string', 'max:20'],
            'yearly_price' => ['required', 'string', 'max:20'],
            'monthly_quota' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'max_team_members' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'features' => ['array'],
            'features.*' => ['in:'.implode(',', array_keys(PlanEntitlements::LABELS))],
            'highlights' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['required', 'boolean'],
        ]);
        $monthly = Money::parseToMinor($data['monthly_price']);
        $yearly = Money::parseToMinor($data['yearly_price']);
        if ($monthly === null || $yearly === null) {
            return back()->withInput()->withErrors(['monthly_price' => 'Enter prices like 2500 or 24,000.']);
        }

        $features = [];
        foreach (array_keys(PlanEntitlements::LABELS) as $feature) {
            $features[$feature] = in_array($feature, (array) ($data['features'] ?? []), true);
        }
        $features['max_team_members'] = $data['max_team_members'] !== null ? (int) $data['max_team_members'] : null;

        $before = $plan->only(['monthly_price_minor', 'yearly_price_minor', 'monthly_quota']);
        $plan->update([
            'name' => $data['name'],
            'tagline' => $data['tagline'],
            'monthly_price_minor' => $monthly,
            'yearly_price_minor' => $yearly,
            'monthly_quota' => $data['monthly_quota'],
            'features' => $features,
            'highlights' => array_values(array_filter(array_map('trim', preg_split('/\R/', (string) ($data['highlights'] ?? '')) ?: []))),
            'is_active' => (bool) $data['is_active'],
        ]);
        $this->audit->log('admin.plan_pricing_changed', $this->actor($request), null, ['tier' => $plan->tier->value, 'before' => $before, 'after' => $plan->only(['monthly_price_minor', 'yearly_price_minor', 'monthly_quota'])], AuditSeverity::WARNING);

        return back()->with('status', "{$plan->name} updated.");
    }

    public function savePack(Request $request, ?CreditPack $pack = null): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'credits' => ['required', 'integer', 'min:1', 'max:100000'],
            'price' => ['required', 'string', 'max:20'],
            'is_active' => ['required', 'boolean'],
        ]);
        $price = Money::parseToMinor($data['price']);
        if ($price === null || $price <= 0) {
            return back()->withInput()->withErrors(['price' => 'Enter a price like 1000.']);
        }
        $attributes = ['name' => $data['name'], 'credits' => (int) $data['credits'], 'price_minor' => $price, 'is_active' => (bool) $data['is_active'], 'currency' => 'NGN'];
        $pack === null ? CreditPack::query()->create([...$attributes, 'sort_order' => (int) CreditPack::query()->max('sort_order') + 1]) : $pack->update($attributes);
        $this->audit->log('admin.credit_pack_saved', $this->actor($request), null, $attributes, AuditSeverity::WARNING);

        return back()->with('status', 'Credit pack saved.');
    }

    public function updateRules(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'remove_watermark_on_paid_credit' => ['required', 'boolean'],
            'yearly_discount_label' => ['nullable', 'string', 'max:40'],
        ]);
        $this->settings->set('billing.remove_watermark_on_paid_credit', (bool) $data['remove_watermark_on_paid_credit']);
        $this->settings->set('billing.yearly_discount_label', $data['yearly_discount_label']);
        $this->audit->log('admin.billing_rules_changed', $this->actor($request), null, $data, AuditSeverity::WARNING);

        return back()->with('status', 'Billing rules saved.');
    }

    private function actor(Request $request): ?User
    {
        $user = $request->user();

        return $user instanceof User ? $user : null;
    }
}
