<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PlanTier;
use App\Models\CreditPack;
use App\Models\Document;
use App\Models\PaymentTransaction;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class PaymentWebhookTest extends TestCase
{
    public function test_credit_pack_via_flutterwave_is_verified_and_idempotent(): void
    {
        Http::fake([
            'api.flutterwave.com/v3/payments' => Http::response(['status' => 'success', 'data' => ['link' => 'https://checkout.flutterwave.com/pay/x']]),
        ]);
        [$merchant, $user] = $this->merchantWithOwner();
        $this->actingAs($user);
        $pack = CreditPack::query()->firstOrFail();

        $ref = $this->postJson('/api/v1/billing/checkout', ['purpose' => 'CREDIT_PACK', 'gateway' => 'FLUTTERWAVE', 'credit_pack_id' => $pack->id])
            ->assertCreated()->assertJsonPath('checkout_url', 'https://checkout.flutterwave.com/pay/x')->json('reference');

        Http::fake([
            'api.flutterwave.com/v3/transactions/verify_by_reference*' => Http::response(['status' => 'success', 'data' => [
                'id' => 9911, 'tx_ref' => $ref, 'status' => 'successful', 'amount' => 1000, 'currency' => 'NGN',
            ]]),
        ]);

        $this->postJson('/api/v1/webhooks/flutterwave', ['event' => 'charge.completed', 'data' => ['tx_ref' => $ref]], ['verif-hash' => 'wrong'])->assertUnauthorized();
        $this->postJson('/api/v1/webhooks/flutterwave', ['event' => 'charge.completed', 'data' => ['tx_ref' => $ref]], ['verif-hash' => 'flw-hash'])->assertOk();
        $this->postJson('/api/v1/webhooks/flutterwave', ['event' => 'charge.completed', 'data' => ['tx_ref' => $ref]], ['verif-hash' => 'flw-hash'])->assertOk();

        $this->assertSame(50, $merchant->refresh()->paid_credits_balance);
        $this->assertSame('SUCCESSFUL', PaymentTransaction::query()->where('reference', $ref)->firstOrFail()->status->value);
    }

    public function test_underpayment_is_rejected(): void
    {
        Http::fake(['api.flutterwave.com/v3/payments' => Http::response(['status' => 'success', 'data' => ['link' => 'https://x']])]);
        [$merchant, $user] = $this->merchantWithOwner();
        $this->actingAs($user);
        $ref = $this->postJson('/api/v1/billing/checkout', ['purpose' => 'SUBSCRIPTION', 'gateway' => 'FLUTTERWAVE', 'plan_tier' => 'PRO', 'billing_cycle' => 'MONTHLY'])->json('reference');
        Http::fake(['api.flutterwave.com/v3/transactions/verify_by_reference*' => Http::response(['status' => 'success', 'data' => [
            'id' => 1, 'tx_ref' => $ref, 'status' => 'successful', 'amount' => 25, 'currency' => 'NGN',
        ]])]);
        $this->postJson('/api/v1/webhooks/flutterwave', ['data' => ['tx_ref' => $ref]], ['verif-hash' => 'flw-hash'])->assertOk();

        $this->assertSame(PlanTier::FREE, $merchant->refresh()->plan_tier);
        $this->assertSame('FAILED', PaymentTransaction::query()->where('reference', $ref)->firstOrFail()->status->value);
        $this->assertDatabaseHas('security_audit_logs', ['event' => 'payments.amount_mismatch']);
    }

    public function test_subscription_via_nowpayments_ipn(): void
    {
        Http::fake(['api.nowpayments.io/v1/invoice' => Http::response(['id' => '4522', 'invoice_url' => 'https://nowpayments.io/payment/?iid=4522'])]);
        [$merchant, $user] = $this->merchantWithOwner();
        $this->actingAs($user);
        $ref = $this->postJson('/api/v1/billing/checkout', ['purpose' => 'SUBSCRIPTION', 'gateway' => 'NOWPAYMENTS', 'plan_tier' => 'BUSINESS_PLUS', 'billing_cycle' => 'YEARLY'])
            ->assertCreated()->json('reference');

        $payload = ['payment_id' => 5077125051, 'payment_status' => 'finished', 'order_id' => $ref, 'price_amount' => 60000, 'price_currency' => 'ngn', 'pay_currency' => 'usdttrc20', 'actually_paid' => 38.2];
        $sorted = $payload;
        ksort($sorted);
        $sig = hash_hmac('sha512', (string) json_encode($sorted, JSON_UNESCAPED_SLASHES), 'np-ipn-secret');

        $this->postJson('/api/v1/webhooks/nowpayments', $payload, ['x-nowpayments-sig' => 'bad'])->assertUnauthorized();
        $this->postJson('/api/v1/webhooks/nowpayments', $payload, ['x-nowpayments-sig' => $sig])->assertOk();

        $merchant->refresh();
        $this->assertSame(PlanTier::BUSINESS_PLUS, $merchant->plan_tier);
        $this->assertTrue($merchant->plan_expires_at->greaterThan(now()->addMonths(11)));
    }

    public function test_customer_pays_invoice_with_merchant_keys_and_gets_receipt(): void
    {
        [$merchant, $user] = $this->merchantWithOwner(PlanTier::BUSINESS_PLUS);
        $merchant->update(['flutterwave_secret_key' => 'FLWSECK_TEST-merchant', 'flutterwave_secret_hash' => 'merchant-hash']);
        $this->actingAs($user);
        $invoice = $this->postJson('/api/v1/documents', $this->receiptPayload(['type' => 'INVOICE', 'vat_rate_bps' => 0]))->json('data');
        auth()->guard('web')->logout();
        $uid = Document::query()->findOrFail($invoice['id'])->public_uid;

        $this->getJson("/api/v1/public/invoices/{$uid}")->assertOk()->assertJsonPath('gateways.flutterwave', true)->assertJsonPath('balance_due_minor', 3000000);

        Http::fake(['api.flutterwave.com/v3/payments' => Http::response(['status' => 'success', 'data' => ['link' => 'https://checkout.flutterwave.com/m']])]);
        $ref = $this->postJson("/api/v1/public/invoices/{$uid}/pay", ['gateway' => 'FLUTTERWAVE', 'email' => 'ada@example.com'])->assertOk()->json('reference');
        Http::assertSent(fn ($r) => $r->hasHeader('Authorization', 'Bearer FLWSECK_TEST-merchant'));

        Http::fake(['api.flutterwave.com/v3/transactions/verify_by_reference*' => Http::response(['status' => 'success', 'data' => [
            'id' => 42, 'tx_ref' => $ref, 'status' => 'successful', 'amount' => 30000, 'currency' => 'NGN',
        ]])]);
        // Platform webhook must not settle merchant invoice payments
        $this->postJson('/api/v1/webhooks/flutterwave', ['data' => ['tx_ref' => $ref]], ['verif-hash' => 'flw-hash'])->assertOk();
        $this->assertSame('UNPAID', Document::query()->findOrFail($invoice['id'])->status->value);

        $this->postJson("/api/v1/webhooks/flutterwave/merchants/{$merchant->id}", ['data' => ['tx_ref' => $ref]], ['verif-hash' => 'merchant-hash'])->assertOk();
        $paid = Document::query()->findOrFail($invoice['id']);
        $this->assertSame('PAID', $paid->status->value);
        $this->assertSame(1, Document::query()->where('parent_document_id', $paid->id)->where('doc_type', 'RECEIPT')->count());
    }
}
