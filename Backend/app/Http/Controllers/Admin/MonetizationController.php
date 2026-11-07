<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\AuditSeverity;
use App\Http\Controllers\Controller;
use App\Models\CreditPack;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\Billing\PlanEntitlements;
use App\Services\Marketing\ActivityFeed;
use App\Services\Pricing\FxRates;
use App\Services\Security\AuditLogger;
use App\Services\Settings\PlatformSettings;
use App\Support\Currencies;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class MonetizationController extends Controller
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly PlatformSettings $settings,
        private readonly FxRates $fx,
    ) {}

    /** Currencies offered for fixed prices in the admin (others are converted). */
    public const array OVERRIDE_CURRENCIES = ['NGN', 'GHS', 'KES', 'ZAR', 'UGX', 'TZS', 'XAF', 'XOF', 'EGP', 'GBP', 'EUR', 'CAD'];

    public function edit(): View
    {
        return view('admin.monetization.edit', [
            'plans' => SubscriptionPlan::query()->orderBy('sort_order')->get(),
            'packs' => CreditPack::query()->orderBy('sort_order')->get(),
            'features' => PlanEntitlements::LABELS,
            'removeWatermarkOnCredit' => (bool) $this->settings->get('billing.remove_watermark_on_paid_credit', true),
            'yearlyLabel' => (string) $this->settings->get('billing.yearly_discount_label'),
            'currencies' => Currencies::all(),
            'overrideCurrencies' => self::OVERRIDE_CURRENCIES,
            'fxRates' => array_intersect_key($this->fx->all(), array_flip(self::OVERRIDE_CURRENCIES)),
            'fxFetchedAt' => $this->fx->fetchedAt(),
            'popups' => [
                'enabled' => (bool) $this->settings->get('marketing.popups.enabled'),
                'show_stats' => (bool) $this->settings->get('marketing.popups.show_stats'),
                'min_seconds' => (int) $this->settings->get('marketing.popups.min_seconds'),
                'max_seconds' => (int) $this->settings->get('marketing.popups.max_seconds'),
                'max_per_visit' => (int) $this->settings->get('marketing.popups.max_per_visit'),
            ],
            'popupPreview' => app(ActivityFeed::class)->payload(),
        ]);
    }

    public function updatePlan(Request $request, SubscriptionPlan $plan): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'tagline' => ['nullable', 'string', 'max:120'],
            'currency' => ['required', 'string', 'size:3', Rule::in(Currencies::codes())],
            'overrides' => ['array'],
            'overrides.*.monthly' => ['nullable', 'string', 'max:20'],
            'overrides.*.yearly' => ['nullable', 'string', 'max:20'],
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

        $overrides = [];
        foreach ((array) ($data['overrides'] ?? []) as $code => $row) {
            $code = strtoupper((string) $code);
            if (! in_array($code, self::OVERRIDE_CURRENCIES, true) || $code === strtoupper($data['currency']) || ! is_array($row)) {
                continue;
            }
            $prices = [];
            foreach (['monthly', 'yearly'] as $cycle) {
                $text = trim((string) ($row[$cycle] ?? ''));
                if ($text === '') {
                    continue;
                }
                $minor = Money::parseToMinor($text);
                if ($minor === null || $minor <= 0) {
                    return back()->withInput()->withErrors(['overrides' => "Check the {$code} {$cycle} price."]);
                }
                $prices[$cycle] = $minor;
            }
            if ($prices !== []) {
                $overrides[$code] = $prices;
            }
        }

        $before = $plan->only(['currency', 'monthly_price_minor', 'yearly_price_minor', 'price_overrides', 'monthly_quota']);
        $plan->update([
            'name' => $data['name'],
            'tagline' => $data['tagline'],
            'currency' => strtoupper($data['currency']),
            'price_overrides' => $overrides === [] ? null : $overrides,
            'monthly_price_minor' => $monthly,
            'yearly_price_minor' => $yearly,
            'monthly_quota' => $data['monthly_quota'],
            'features' => $features,
            'highlights' => array_values(array_filter(array_map('trim', preg_split('/\R/', (string) ($data['highlights'] ?? '')) ?: []))),
            'is_active' => (bool) $data['is_active'],
        ]);
        $this->audit->log('admin.plan_pricing_changed', $this->actor($request), null, ['tier' => $plan->tier->value, 'before' => $before, 'after' => $plan->only(['currency', 'monthly_price_minor', 'yearly_price_minor', 'price_overrides', 'monthly_quota'])], AuditSeverity::WARNING);

        return back()->with('status', "{$plan->name} updated.");
    }

    public function savePack(Request $request, ?CreditPack $pack = null): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'credits' => ['required', 'integer', 'min:1', 'max:100000'],
            'price' => ['required', 'string', 'max:20'],
            'currency' => ['nullable', 'string', 'size:3', Rule::in(Currencies::codes())],
            // "NGN 1000, KES 150": fixed prices; other currencies are converted.
            'overrides' => ['nullable', 'string', 'max:300'],
            'is_active' => ['required', 'boolean'],
        ]);
        $price = Money::parseToMinor($data['price']);
        if ($price === null || $price <= 0) {
            return back()->withInput()->withErrors(['price' => 'Enter a price like 1000.']);
        }
        $currency = strtoupper((string) ($data['currency'] ?? ($pack->currency ?? 'USD')));
        $overrides = [];
        foreach (preg_split('/[,;\n]+/', (string) ($data['overrides'] ?? '')) ?: [] as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }
            if (preg_match('/^([A-Za-z]{3})\s*[:=]?\s*(.+)$/', $part, $m) !== 1 || ! in_array(strtoupper($m[1]), self::OVERRIDE_CURRENCIES, true)
                || ($minor = Money::parseToMinor($m[2])) === null || $minor <= 0) {
                return back()->withInput()->withErrors(['overrides' => "Couldn't read \"{$part}\". Use e.g. NGN 1000, KES 150."]);
            }
            if (strtoupper($m[1]) !== $currency) {
                $overrides[strtoupper($m[1])] = $minor;
            }
        }
        $attributes = ['name' => $data['name'], 'credits' => (int) $data['credits'], 'price_minor' => $price, 'is_active' => (bool) $data['is_active'], 'currency' => $currency, 'price_overrides' => $overrides === [] ? null : $overrides];
        $pack === null ? CreditPack::query()->create([...$attributes, 'sort_order' => (int) CreditPack::query()->max('sort_order') + 1]) : $pack->update($attributes);
        $this->audit->log('admin.credit_pack_saved', $this->actor($request), null, $attributes, AuditSeverity::WARNING);

        return back()->with('status', 'Credit pack saved.');
    }

    public function refreshRates(Request $request): RedirectResponse
    {
        $count = $this->fx->refresh();
        $this->audit->log('admin.fx_refreshed', $this->actor($request), null, ['count' => $count]);

        return $count === null
            ? back()->with('warning', 'Couldn’t reach the exchange-rate service. The last saved rates are still used.')
            : back()->with('status', "Exchange rates updated ({$count} currencies).");
    }

    /** Website activity notes: on/off, timing and whether true platform stats are shown. */
    public function updateMarketing(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
            'show_stats' => ['required', 'boolean'],
            'min_seconds' => ['required', 'integer', 'between:5,600'],
            'max_seconds' => ['required', 'integer', 'between:5,900', 'gte:min_seconds'],
            'max_per_visit' => ['required', 'integer', 'between:1,20'],
        ]);
        foreach ($data as $key => $value) {
            $this->settings->set("marketing.popups.{$key}", is_bool($value) ? $value : (string) $value);
        }
        ActivityFeed::forget();
        $this->audit->log('admin.marketing_popups_changed', $this->actor($request), null, $data, AuditSeverity::WARNING);

        return back()->with('status', 'Website activity notes saved.');
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
