<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\BillingCycle;
use App\Enums\PaymentGateway;
use App\Enums\PaymentPurpose;
use App\Enums\PaymentStatus;
use App\Enums\PlanTier;
use App\Models\PaymentTransaction;
use App\Services\Settings\PlatformSettings;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** The two testing helpers: the key check and the signed NOWPayments test notification. */
final class GatewayToolsTest extends TestCase
{
    public function test_check_gateways_reports_modes_and_webhook_urls(): void
    {
        config(['vendslip.nowpayments.ipn_secret' => null]);
        $settings = app(PlatformSettings::class);
        $settings->set('flutterwave.secret_key', 'FLWSECK_TEST-abc');
        $settings->set('flutterwave.secret_hash', 'hash-123');
        $settings->set('nowpayments.api_key', 'np-key');
        Http::fake([
            'api.flutterwave.com/v3/banks/NG' => Http::response(['status' => 'success', 'data' => []]),
            'api.nowpayments.io/v1/status' => Http::response(['message' => 'OK']),
            'api.nowpayments.io/v1/merchant/coins' => Http::response(['selectedCurrencies' => ['USDTTRC20', 'BTC']]),
        ]);

        $this->artisan('vendslip:check-gateways')
            ->expectsOutputToContain('TEST keys (no real money)')
            ->expectsOutputToContain('/api/v1/webhooks/flutterwave')
            ->expectsOutputToContain('USDTTRC20, BTC')
            ->expectsOutputToContain('missing: payment notifications will be refused')
            ->assertExitCode(1); // no IPN secret yet

        $settings->set('nowpayments.ipn_secret', 'ipn-secret');
        $this->artisan('vendslip:check-gateways')->assertSuccessful();
    }

    public function test_signed_test_ipn_completes_a_crypto_checkout(): void
    {
        app(PlatformSettings::class)->set('nowpayments.ipn_secret', 'ipn-secret');
        [$merchant] = $this->merchantWithOwner();
        $tx = PaymentTransaction::query()->create([
            'reference' => 'VS-IPN-TEST1', 'merchant_id' => $merchant->id, 'purpose' => PaymentPurpose::SUBSCRIPTION,
            'gateway' => PaymentGateway::NOWPAYMENTS, 'status' => PaymentStatus::PENDING, 'amount_minor' => 900, 'currency' => 'USD',
            'plan_tier' => PlanTier::PRO, 'billing_cycle' => BillingCycle::MONTHLY, 'gateway_reference' => '5551234',
        ]);

        Artisan::call('vendslip:test-ipn', ['reference' => $tx->reference, '--dry' => true]);
        $lines = explode("\n", trim(Artisan::output()));
        $this->assertSame('POST '.url('/api/v1/webhooks/nowpayments'), $lines[0]);
        $signature = substr($lines[1], strlen('x-nowpayments-sig: '));

        // Tampered body: refused.
        $this->call('POST', '/api/v1/webhooks/nowpayments', [], [], [], ['HTTP_X_NOWPAYMENTS_SIG' => $signature, 'CONTENT_TYPE' => 'application/json'], str_replace('"finished"', '"failed"', $lines[2]))->assertUnauthorized();
        // As signed: the plan is activated.
        $this->call('POST', '/api/v1/webhooks/nowpayments', [], [], [], ['HTTP_X_NOWPAYMENTS_SIG' => $signature, 'CONTENT_TYPE' => 'application/json'], $lines[2])->assertOk();
        $this->assertSame(PaymentStatus::SUCCESSFUL, $tx->refresh()->status);
        $this->assertSame(PlanTier::PRO, $merchant->refresh()->plan_tier);

        config(['app.env' => 'production']);
        $this->app['env'] = 'production';
        $this->artisan('vendslip:test-ipn', ['reference' => $tx->reference])->assertFailed();
    }
}
