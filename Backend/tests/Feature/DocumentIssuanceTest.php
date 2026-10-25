<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PlanTier;
use App\Enums\Role;
use App\Models\Document;
use App\Models\InventorySku;
use App\Services\Settings\PlatformSettings;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class DocumentIssuanceTest extends TestCase
{
    public function test_issues_receipt_with_gapless_numbers_hash_and_pdf(): void
    {
        [$merchant, $user] = $this->merchantWithOwner();
        $this->actingAs($user);

        $first = $this->postJson('/api/v1/documents', $this->receiptPayload())->assertCreated();
        $first->assertJsonPath('data.doc_number', 'RCP-'.now('Africa/Lagos')->year.'-0001')
            ->assertJsonPath('data.status', 'PAID')
            ->assertJsonPath('data.subtotal_minor', 3000000)
            ->assertJsonPath('data.tax_minor', 225000)
            ->assertJsonPath('data.total_minor', 3225000)
            ->assertJsonPath('data.has_watermark', true)
            ->assertJsonPath('data.consumed_paid_credit', false);
        $this->assertMatchesRegularExpression('/^NSV-[0-9A-F]{4}-[0-9A-F]{4}-[0-9A-F]{4}$/', (string) $first->json('data.verification_code'));

        $this->postJson('/api/v1/documents', $this->receiptPayload())->assertJsonPath('data.doc_number', 'RCP-'.now('Africa/Lagos')->year.'-0002');
        $this->postJson('/api/v1/documents', $this->receiptPayload(['type' => 'INVOICE']))->assertJsonPath('data.doc_number', 'INV-'.now('Africa/Lagos')->year.'-0001')
            ->assertJsonPath('data.status', 'UNPAID');

        $doc = Document::query()->where('doc_number', 'like', 'RCP-%-0001')->firstOrFail();
        $this->assertNotNull($doc->pdf_storage_path);
        Storage::disk('local')->assertExists((string) $doc->pdf_storage_path);
        $this->get('/api/v1/documents/'.$doc->id.'/pdf')->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertSame(1, $doc->customer()->count());
    }

    public function test_free_quota_then_credits_then_payment_required(): void
    {
        [$merchant, $user] = $this->merchantWithOwner(credits: 1);
        $merchant->update(['free_quota_used' => 15, 'free_quota_period' => now('Africa/Lagos')->format('Y-m')]);
        $this->actingAs($user);

        $this->postJson('/api/v1/documents', $this->receiptPayload())->assertCreated()
            ->assertJsonPath('data.consumed_paid_credit', true)
            ->assertJsonPath('data.has_watermark', false);
        $this->assertSame(0, $merchant->refresh()->paid_credits_balance);

        $this->postJson('/api/v1/documents', $this->receiptPayload())->assertStatus(402)->assertJsonPath('code', 'quota_exceeded');
        $this->assertSame(1, Document::query()->count(), 'failed issuance must not leave a document or burn a number');
    }

    public function test_watermark_rule_is_admin_configurable_and_clean_credit_option(): void
    {
        app(PlatformSettings::class)->set('billing.remove_watermark_on_paid_credit', false);
        [, $user] = $this->merchantWithOwner(credits: 2);
        $this->actingAs($user);
        $this->postJson('/api/v1/documents', $this->receiptPayload(['use_paid_credit' => true]))
            ->assertJsonPath('data.consumed_paid_credit', true)->assertJsonPath('data.has_watermark', true);
    }

    public function test_pro_plan_is_unlimited_and_clean(): void
    {
        [$merchant, $user] = $this->merchantWithOwner(PlanTier::PRO);
        $merchant->update(['free_quota_used' => 999, 'free_quota_period' => now('Africa/Lagos')->format('Y-m')]);
        $this->actingAs($user);
        $this->postJson('/api/v1/documents', $this->receiptPayload())->assertCreated()->assertJsonPath('data.has_watermark', false);
    }

    public function test_expired_plan_falls_back_to_free(): void
    {
        [$merchant] = $this->merchantWithOwner(PlanTier::PRO);
        $merchant->update(['plan_expires_at' => now()->subDay()]);
        $this->assertSame(PlanTier::FREE, $merchant->refresh()->effectiveTier());
    }

    public function test_receipt_requires_payment_and_validation_errors(): void
    {
        [, $user] = $this->merchantWithOwner();
        $this->actingAs($user);
        $this->postJson('/api/v1/documents', $this->receiptPayload(['amount_paid_minor' => 0]))->assertUnprocessable()->assertJsonPath('code', 'receipt_unpaid');
        $this->postJson('/api/v1/documents', $this->receiptPayload(['items' => []]))->assertUnprocessable()->assertJsonValidationErrors('items');
        $this->postJson('/api/v1/documents', $this->receiptPayload(['type' => 'CREDIT_NOTE']))->assertUnprocessable();
    }

    public function test_invoice_payment_issues_linked_receipt_and_credit_note(): void
    {
        [, $user] = $this->merchantWithOwner();
        $this->actingAs($user);
        $invoiceId = $this->postJson('/api/v1/documents', $this->receiptPayload(['type' => 'INVOICE', 'vat_rate_bps' => 0]))->json('data.id');

        $res = $this->postJson("/api/v1/documents/{$invoiceId}/payments", ['amount_minor' => 1000000, 'method' => 'BANK_TRANSFER', 'reference' => 'TRF-1'])
            ->assertCreated()
            ->assertJsonPath('invoice.status', 'PARTIAL')
            ->assertJsonPath('invoice.balance_due_minor', 2000000)
            ->assertJsonPath('receipt.total_minor', 1000000)
            ->assertJsonPath('receipt.parent_document_id', $invoiceId);
        $this->assertStringStartsWith('RCP-', (string) $res->json('receipt.doc_number'));

        $this->postJson("/api/v1/documents/{$invoiceId}/payments", ['amount_minor' => 9999999, 'method' => 'CASH'])->assertUnprocessable();

        $this->postJson("/api/v1/documents/{$invoiceId}/credit-notes", ['amount_minor' => 2000000, 'reason' => 'Returned one pair'])
            ->assertCreated()->assertJsonPath('data.doc_type', 'CREDIT_NOTE');
        $this->getJson("/api/v1/documents/{$invoiceId}")->assertJsonPath('data.balance_due_minor', 0)->assertJsonPath('data.status', 'PAID')
            ->assertJsonPath('meta.credited_minor', 2000000);
    }

    public function test_inventory_is_decremented_and_restored_on_void(): void
    {
        [$merchant, $user] = $this->merchantWithOwner(PlanTier::BUSINESS_PLUS);
        $sku = InventorySku::query()->create(['merchant_id' => $merchant->id, 'sku' => 'S-1', 'name' => 'Sneakers', 'unit_price_minor' => 1000, 'stock_quantity' => 3]);
        $this->actingAs($user);

        $id = $this->postJson('/api/v1/documents', $this->receiptPayload(['items' => [['description' => 'Sneakers', 'quantity' => 2, 'unit_price_minor' => 1000, 'sku_id' => $sku->id]]]))
            ->assertCreated()->json('data.id');
        $this->assertSame(1, $sku->refresh()->stock_quantity);

        $this->postJson('/api/v1/documents', $this->receiptPayload(['items' => [['description' => 'Sneakers', 'quantity' => 2, 'unit_price_minor' => 1000, 'sku_id' => $sku->id]]]))
            ->assertUnprocessable()->assertJsonPath('code', 'insufficient_stock');

        $this->postJson("/api/v1/documents/{$id}/void", ['reason' => 'Customer cancelled'])->assertOk()->assertJsonPath('data.status', 'VOID');
        $this->assertSame(3, $sku->refresh()->stock_quantity);
    }

    public function test_tenant_isolation(): void
    {
        [, $owner] = $this->merchantWithOwner();
        [, $other] = $this->merchantWithOwner();
        $this->actingAs($owner);
        $id = $this->postJson('/api/v1/documents', $this->receiptPayload())->json('data.id');

        $this->actingAs($other);
        $this->getJson("/api/v1/documents/{$id}")->assertNotFound();
        $this->getJson("/api/v1/documents/{$id}/pdf")->assertNotFound();
        $this->postJson("/api/v1/documents/{$id}/void", ['reason' => 'malicious'])->assertNotFound();
        $this->getJson('/api/v1/documents')->assertJsonCount(0, 'data');
    }

    public function test_role_permissions_are_enforced(): void
    {
        [, $accountant] = $this->merchantWithOwner(role: Role::ACCOUNTANT);
        $this->actingAs($accountant);
        $this->postJson('/api/v1/documents', $this->receiptPayload())->assertForbidden();
        $this->getJson('/api/v1/documents')->assertOk();
    }

    public function test_dashboard_overview_counts_cash_once(): void
    {
        [, $user] = $this->merchantWithOwner();
        $this->actingAs($user);
        $this->postJson('/api/v1/documents', $this->receiptPayload(['vat_rate_bps' => 0]));          // 30,000 receipt
        $inv = $this->postJson('/api/v1/documents', $this->receiptPayload(['type' => 'INVOICE', 'vat_rate_bps' => 0]))->json('data.id');
        $this->postJson("/api/v1/documents/{$inv}/payments", ['amount_minor' => 500000, 'method' => 'CASH']); // + 5,000 (and a payment receipt)

        $this->getJson('/api/v1/dashboard/overview?range=7d')->assertOk()
            ->assertJsonPath('kpis.settled_revenue_minor', 3500000)
            ->assertJsonPath('kpis.receivables_minor', 2500000)
            ->assertJsonPath('kpis.slips_issued', 2);
    }
}
