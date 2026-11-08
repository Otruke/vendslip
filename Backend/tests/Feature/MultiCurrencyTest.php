<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\BillingCycle;
use App\Enums\PaymentGateway;
use App\Enums\PlanTier;
use App\Models\Document;
use App\Models\PaymentTransaction;
use App\Models\SubscriptionPlan;
use App\Services\Pricing\FxRates;
use App\Services\Pricing\PlanPricing;
use App\Services\Slash\DraftPayload;
use App\Services\Slash\SlashCommandParser;
use App\Support\Money;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Per-slip currencies, plans priced in USD with fixed overrides, and cached exchange rates. */
final class MultiCurrencyTest extends TestCase
{
    private function fakeRates(): void
    {
        Http::fake(['open.er-api.com/*' => Http::response(['result' => 'success', 'rates' => ['USD' => 1, 'NGN' => 1580.25, 'KES' => 129.4, 'UGX' => 3712.5, 'XXX' => 5, 'BAD' => -1]])]);
        $this->assertSame(3, app(FxRates::class)->refresh(), 'only known ISO currencies with positive rates are stored');
    }

    public function test_slip_currency_from_slash_command_and_api(): void
    {
        [$merchant, $owner] = $this->merchantWithOwner();
        [$payload, $errors] = DraftPayload::empty()->apply(app(SlashCommandParser::class)->parse("/name = Ada\n/item = Shoe @ 50\n/currency = usd"));
        $this->assertSame([], $errors);
        $this->assertSame('USD', $payload->currency());
        [, $bad] = DraftPayload::empty()->apply(app(SlashCommandParser::class)->parse('/currency = ABC'));
        $this->assertNotEmpty($bad);
        [$reset] = $payload->apply(app(SlashCommandParser::class)->parse('/currency = default'));
        $this->assertNull($reset->currency());

        $this->actingAs($owner);
        $this->postJson('/api/v1/documents', $this->receiptPayload(['currency' => 'GHS']))->assertCreated()->assertJsonPath('data.currency', 'GHS');
        $this->postJson('/api/v1/documents', $this->receiptPayload())->assertCreated()->assertJsonPath('data.currency', $merchant->currency);
        $this->postJson('/api/v1/documents', $this->receiptPayload(['currency' => 'ZZZ']))->assertUnprocessable();

        // Dashboard money is per currency, with a list to switch between.
        $overview = $this->getJson('/api/v1/dashboard/overview?currency=GHS')->assertOk();
        $this->assertSame('GHS', $overview->json('currency'));
        $this->assertContains('GHS', $overview->json('currencies'));
    }

    public function test_plan_prices_use_overrides_then_conversion_then_base(): void
    {
        $this->fakeRates();
        $plan = SubscriptionPlan::forTier(PlanTier::PRO);
        $plan->update(['currency' => 'USD', 'monthly_price_minor' => 900, 'yearly_price_minor' => 8600, 'price_overrides' => ['NGN' => ['monthly' => 1_500_000]]]);
        $pricing = app(PlanPricing::class);

        $this->assertSame(['amount_minor' => 1_500_000, 'currency' => 'NGN', 'source' => 'override'], $pricing->plan($plan, BillingCycle::MONTHLY, 'NGN'));
        // No yearly NGN override: 86 USD × 1580.25 = 135,901.5 → rounded up to 136,000.
        $this->assertSame(['amount_minor' => 13_600_000, 'currency' => 'NGN', 'source' => 'converted'], $pricing->plan($plan, BillingCycle::YEARLY, 'NGN'));
        // 9 × 129.4 = 1,164.6 → 1,170; UGX 9 × 3,712.5 = 33,412.5 → 34,000
        $this->assertSame(117_000, $pricing->plan($plan, BillingCycle::MONTHLY, 'KES')['amount_minor']);
        // No rate for GHS: falls back to the USD base.
        $this->assertSame(['amount_minor' => 900, 'currency' => 'USD', 'source' => 'base'], $pricing->plan($plan, BillingCycle::MONTHLY, 'GHS'));
        $this->assertSame('USh 34,000', Money::format($pricing->plan($plan, BillingCycle::MONTHLY, 'UGX')['amount_minor'], 'UGX'));

        $this->assertSame('NGN', PlanPricing::chargeCurrency(PaymentGateway::FLUTTERWAVE, 'ngn'));
        $this->assertSame('USD', PlanPricing::chargeCurrency(PaymentGateway::NOWPAYMENTS, 'KES'));

        $this->getJson('/api/v1/public/plans?currency=KES')->assertOk()->assertJsonPath('currency', 'KES')
            ->assertJsonFragment(['tier' => 'PRO', 'currency' => 'KES', 'monthly_price_minor' => 117_000, 'converted' => true]);
    }

    public function test_checkout_charges_the_business_currency_when_the_gateway_takes_it(): void
    {
        $this->fakeRates();
        Http::fake(['api.flutterwave.com/v3/payments' => Http::response(['status' => 'success', 'data' => ['link' => 'https://checkout.flutterwave.com/pay/x']])]);
        SubscriptionPlan::forTier(PlanTier::PRO)->update(['currency' => 'USD', 'monthly_price_minor' => 900, 'price_overrides' => null]);
        [$merchant, $owner] = $this->merchantWithOwner();
        $merchant->update(['currency' => 'KES']);
        $this->actingAs($owner);

        $ref = $this->postJson('/api/v1/billing/checkout', ['purpose' => 'SUBSCRIPTION', 'gateway' => 'FLUTTERWAVE', 'plan_tier' => 'PRO', 'billing_cycle' => 'MONTHLY', 'accept_terms' => true])->assertCreated()->json('reference');
        $tx = PaymentTransaction::query()->where('reference', $ref)->firstOrFail();
        $this->assertSame('KES', $tx->currency);
        $this->assertSame(117_000, $tx->amount_minor);
        Http::assertSent(fn ($request): bool => str_contains($request->url(), '/payments') && $request['currency'] === 'KES' && (float) $request['amount'] === 1170.0);
    }

    public function test_failed_rate_refresh_keeps_the_last_good_rates(): void
    {
        Http::fake(['open.er-api.com/*' => Http::sequence()
            ->push(['result' => 'success', 'rates' => ['USD' => 1, 'NGN' => 1580.25]])
            ->push('down', 503)->push('down', 503)->push('down', 503)]);
        $this->assertSame(1, app(FxRates::class)->refresh());
        $this->assertNull(app(FxRates::class)->refresh());
        $this->assertSame(1580.25, app(FxRates::class)->rate('NGN'));
    }

    public function test_revenue_change_compares_with_the_previous_period(): void
    {
        [, $owner] = $this->merchantWithOwner();
        $this->actingAs($owner);
        $old = $this->postJson('/api/v1/documents', $this->receiptPayload())->assertCreated()->json('data.id');
        Document::query()->whereKey($old)->update(['issued_at' => now()->subDays(40)]);
        $this->postJson('/api/v1/documents', $this->receiptPayload())->assertCreated();
        $this->postJson('/api/v1/documents', $this->receiptPayload())->assertCreated();

        // 2 receipts now vs 1 in the 30 days before: +100%.
        $this->getJson('/api/v1/dashboard/overview?range=30d')->assertOk()->assertJsonPath('kpis.settled_revenue_change_pct', 100);
    }
}
