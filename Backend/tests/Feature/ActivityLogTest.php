<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Role;
use App\Http\Middleware\AuditTrail;
use App\Models\SecurityAuditLog;
use App\Models\User;
use Tests\TestCase;

/** Every successful change is audited, and the business can read its own activity log. */
final class ActivityLogTest extends TestCase
{
    public function test_every_successful_write_is_logged_without_bodies(): void
    {
        [$merchant, $owner] = $this->merchantWithOwner();
        $this->actingAs($owner);

        $this->postJson('/api/v1/customers', ['name' => 'Ada Obi', 'phone' => '08031112222', 'notes' => 'secret note'])->assertCreated();
        $this->postJson('/api/v1/customers', ['name' => ''])->assertUnprocessable();
        $this->postJson('/api/v1/documents', $this->receiptPayload())->assertCreated();
        $this->postJson('/api/v1/composer/parse', ['text' => '/name = Ada'])->assertOk();

        $events = SecurityAuditLog::query()->where('merchant_id', $merchant->id)->orderBy('id')->pluck('event')->all();
        $this->assertSame(['customer.created', 'document.created'], $events, 'one entry per change, none for failures or previews');
        $log = SecurityAuditLog::query()->where('event', 'customer.created')->firstOrFail();
        $this->assertSame('/api/v1/customers', $log->context['path']);
        $this->assertStringNotContainsString('secret note', (string) json_encode($log->context));

        $this->assertSame('document.credit_note', AuditTrail::eventFor('App\\Http\\Controllers\\Api\\V1\\DocumentController@creditNote', 'creditNote'));
    }

    public function test_activity_page_shows_this_business_only_and_names_support(): void
    {
        [$merchant, $owner] = $this->merchantWithOwner();
        [$other, $otherOwner] = $this->merchantWithOwner();
        $agent = User::factory()->create(['merchant_id' => $merchant->id]);
        $agent->assignRole(Role::SALES_AGENT->value);
        $admin = User::factory()->create(['name' => 'Kemi Support']);
        $admin->assignRole(Role::SUPER_ADMIN->value);

        $this->actingAs($owner)->postJson('/api/v1/documents', $this->receiptPayload())->assertCreated();
        $this->actingAs($otherOwner)->postJson('/api/v1/customers', ['name' => 'Elsewhere'])->assertCreated();
        SecurityAuditLog::query()->create(['merchant_id' => $merchant->id, 'actor_user_id' => $owner->id, 'event' => 'settings.business_updated', 'severity' => 'INFO',
            'context' => ['fields' => ['name'], 'support_admin_id' => $admin->id, 'support_grant_id' => 1], 'user_agent' => 'Mozilla/5.0 (Windows NT 10.0) Chrome/130.0 Safari/537.36']);

        $res = $this->actingAs($owner)->getJson('/api/v1/activity')->assertOk();
        $this->assertSame(2, $res->json('meta.total'));
        $this->assertSame('Updated business details', $res->json('data.0.label'));
        $this->assertSame('Kemi Support', $res->json('data.0.support_admin'));
        $this->assertSame('Chrome on Windows', $res->json('data.0.device'));
        $this->assertArrayNotHasKey('support_admin_id', (array) $res->json('data.0.details'));
        $this->assertSame('Issued a slip', $res->json('data.1.label'));
        $this->assertNotNull($res->json('data.1.document_id'));

        $this->getJson('/api/v1/activity?category=sales')->assertJsonPath('meta.total', 1);
        $this->actingAs($agent)->getJson('/api/v1/activity')->assertForbidden();
    }
}
