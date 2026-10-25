<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\MutabilityMode;
use App\Enums\Role;
use App\Models\Document;
use App\Models\DocumentRevision;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class MutabilityAndVerificationTest extends TestCase
{
    private function edit(string $id, array $draftOverrides, ?string $reason = 'Customer changed size')
    {
        return $this->putJson("/api/v1/documents/{$id}", [
            'reason' => $reason,
            'draft' => $this->receiptPayload($draftOverrides),
        ]);
    }

    public function test_immutable_merchants_cannot_edit(): void
    {
        [, $user] = $this->merchantWithOwner(mode: MutabilityMode::IMMUTABLE);
        $this->actingAs($user);
        $id = $this->postJson('/api/v1/documents', $this->receiptPayload())->json('data.id');

        $this->getJson("/api/v1/documents/{$id}")->assertJsonPath('meta.can_edit', false)->assertJsonPath('meta.mutability_mode', 'IMMUTABLE');
        $this->edit($id, ['customer_name' => 'Hacked'])->assertForbidden()->assertJsonPath('code', 'document_immutable');
        $this->assertSame('Ada Obi', Document::query()->findOrFail($id)->customer_name);
    }

    public function test_mutable_with_audit_requires_reason_and_writes_forensic_revision(): void
    {
        [, $user] = $this->merchantWithOwner(mode: MutabilityMode::MUTABLE_WITH_AUDIT);
        $this->actingAs($user);
        $created = $this->postJson('/api/v1/documents', $this->receiptPayload())->json('data');
        $id = $created['id'];

        $this->edit($id, ['customer_name' => 'Ada B. Obi'], null)->assertUnprocessable()->assertJsonPath('code', 'reason_required');

        $this->edit($id, ['customer_name' => 'Ada B. Obi', 'items' => [['description' => 'Leather Sneakers', 'quantity' => 1, 'unit_price_minor' => 1500000]]])
            ->assertOk()
            ->assertJsonPath('data.revision_number', 2)
            ->assertJsonPath('data.total_minor', 1612500);

        $revision = DocumentRevision::query()->where('document_id', $id)->firstOrFail();
        $this->assertSame('EDIT', $revision->action);
        $this->assertSame('Customer changed size', $revision->edit_reason);
        $this->assertSame($user->id, $revision->actor_user_id);
        $this->assertSame('Ada Obi', $revision->before_snapshot_json['customer_name']);
        $this->assertSame('Ada B. Obi', $revision->after_snapshot_json['customer_name']);
        $this->assertSame($created['verification_hash'], $revision->previous_verification_hash);
        $this->assertArrayHasKey('total_minor', $revision->diff());

        // Old QR codes resolve as "superseded", the new one as current.
        $this->getJson('/api/v1/public/verify/'.$created['verification_hash'])->assertOk()->assertJsonPath('superseded', true)->assertJsonPath('current_revision', 2);
        $new = Document::query()->findOrFail($id);
        $this->getJson('/api/v1/public/verify/'.$new->verification_code)->assertOk()->assertJsonPath('authentic', true)->assertJsonPath('superseded', false);

        $this->getJson("/api/v1/documents/{$id}/revisions")->assertOk()->assertJsonPath('data.0.action', 'EDIT');
    }

    public function test_staff_edits_follow_allow_staff_edits_and_window(): void
    {
        [$merchant, $agent] = $this->merchantWithOwner(mode: MutabilityMode::MUTABLE_WITH_AUDIT, role: Role::SALES_AGENT);
        $this->actingAs($agent);
        $id = $this->postJson('/api/v1/documents', $this->receiptPayload())->json('data.id');

        $this->edit($id, ['customer_name' => 'X'])->assertForbidden();
        $merchant->update(['allow_staff_edits' => true]);
        $this->edit($id, ['customer_name' => 'Ada Obi Jr'])->assertOk();

        $merchant->update(['mutable_window_hours' => 1]);
        Document::query()->whereKey($id)->update(['issued_at' => now()->subHours(2)]);
        $this->edit($id, ['customer_name' => 'Too late'])->assertForbidden();
    }

    public function test_unrestricted_mode_still_snapshots(): void
    {
        [, $user] = $this->merchantWithOwner(mode: MutabilityMode::MUTABLE_UNRESTRICTED);
        $this->actingAs($user);
        $id = $this->postJson('/api/v1/documents', $this->receiptPayload())->json('data.id');
        $this->edit($id, ['customer_name' => 'Ada'], null)->assertOk();
        $this->assertSame(1, DocumentRevision::query()->where('document_id', $id)->count());
    }

    public function test_edit_without_changes_is_rejected_and_logs_nothing(): void
    {
        [, $user] = $this->merchantWithOwner(mode: MutabilityMode::MUTABLE_WITH_AUDIT);
        $this->actingAs($user);
        $id = $this->postJson('/api/v1/documents', $this->receiptPayload())->json('data.id');
        $this->edit($id, [])->assertUnprocessable()->assertJsonPath('code', 'no_changes');
        $this->assertSame(0, DocumentRevision::query()->where('document_id', $id)->where('action', DocumentRevision::ACTION_EDIT)->count());
        $this->assertSame(1, Document::query()->findOrFail($id)->revision_number);
    }

    public function test_public_verification_detects_tampering_and_minimises_data(): void
    {
        [, $user] = $this->merchantWithOwner();
        $this->actingAs($user);
        $doc = $this->postJson('/api/v1/documents', $this->receiptPayload(['customer_name' => 'Chief Emeka Nwosu']))->json('data');
        auth()->guard('web')->logout();

        $this->getJson('/api/v1/public/verify/'.$doc['verification_hash'])->assertOk()
            ->assertJsonPath('authentic', true)
            ->assertJsonPath('document.customer_display', 'Chief E. N.')
            ->assertJsonMissingPath('document.customer_phone');
        $this->getJson('/api/v1/public/verify/nsv-'.strtolower(substr($doc['verification_code'], 4)))->assertOk();
        $this->getJson('/api/v1/public/verify/NSV-0000-0000-0000')->assertNotFound();

        // Someone edits the ledger directly in the database: the HMAC no longer matches.
        DB::table('documents')->where('id', $doc['id'])->update(['total_minor' => 1]);
        $this->getJson('/api/v1/public/verify/'.$doc['verification_hash'])->assertOk()->assertJsonPath('authentic', false);
    }

    public function test_signed_pdf_link_requires_valid_signature(): void
    {
        [, $user] = $this->merchantWithOwner();
        $this->actingAs($user);
        $id = $this->postJson('/api/v1/documents', $this->receiptPayload())->json('data.id');
        $share = $this->getJson("/api/v1/documents/{$id}/share")->assertOk()->json();
        $this->assertStringContainsString('wa.me/2348031112222', $share['whatsapp_url']);

        $uid = Document::query()->findOrFail($id)->public_uid;
        $this->get('/api/v1/public/documents/'.$uid.'/pdf')->assertForbidden();
        $this->get($share['pdf_url'])->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertGreaterThan(0, User::query()->count());
    }
}
