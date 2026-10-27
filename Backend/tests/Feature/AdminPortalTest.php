<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\MutabilityMode;
use App\Enums\Role;
use App\Models\Merchant;
use App\Models\Template;
use App\Models\User;
use App\Services\Settings\PlatformSettings;
use Tests\TestCase;

final class AdminPortalTest extends TestCase
{
    private function admin(Role $role = Role::SUPER_ADMIN): User
    {
        $user = User::factory()->create();
        $user->assignRole($role->value);

        return $user;
    }

    public function test_every_admin_page_renders_for_super_admin(): void
    {
        [$merchant, $owner] = $this->merchantWithOwner(mode: MutabilityMode::MUTABLE_WITH_AUDIT);
        $this->actingAs($owner);
        $id = $this->postJson('/api/v1/documents', $this->receiptPayload())->json('data.id');
        $this->putJson("/api/v1/documents/{$id}", ['reason' => 'Fix typo please', 'draft' => $this->receiptPayload(['customer_name' => 'Ada Obi-Okafor'])])->assertOk();

        $this->actingAs($this->admin());
        $template = Template::query()->firstOrFail();
        foreach ([
            route('admin.dashboard'), route('admin.merchants.index'), route('admin.merchants.show', $merchant),
            route('admin.payments.index'), route('admin.security.index'), route('admin.templates.index'),
            route('admin.templates.create'), route('admin.templates.edit', $template), route('admin.monetization.edit'),
            route('admin.gateways.edit'), route('admin.admins.index'), route('admin.profile.show'),
        ] as $url) {
            $csp = (string) $this->get($url)->assertOk()->headers->get('Content-Security-Policy');
            $this->assertStringContainsString("script-src 'self';", $csp, "Admin CSP must not allow inline scripts ({$url})");
        }
        $this->get(route('admin.merchants.show', $merchant))->assertSee('Revision history')->assertSee('Ada Obi-Okafor');
        $this->get(route('admin.templates.preview', $template))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_access_control(): void
    {
        $this->get(route('admin.dashboard'))->assertRedirect(route('admin.login'));

        [, $owner] = $this->merchantWithOwner();
        $this->actingAs($owner)->get(route('admin.dashboard'))->assertForbidden();

        $this->actingAs($this->admin(Role::ADMIN));
        $this->get(route('admin.merchants.index'))->assertOk();
        $this->get(route('admin.gateways.edit'))->assertForbidden();
        $this->get(route('admin.monetization.edit'))->assertForbidden();
    }

    public function test_login_requires_platform_role(): void
    {
        [, $owner] = $this->merchantWithOwner();
        $this->post(route('admin.login.attempt'), ['email' => $owner->email, 'password' => 'password'])->assertSessionHasErrors('email');
        $admin = $this->admin();
        $this->post(route('admin.login.attempt'), ['email' => $admin->email, 'password' => 'password'])->assertRedirect(route('admin.dashboard'));
    }

    public function test_mutability_controller_and_credit_adjustment_are_audited(): void
    {
        [$merchant] = $this->merchantWithOwner();
        $this->actingAs($this->admin(Role::ADMIN));

        $this->put(route('admin.merchants.mutability', $merchant), [
            'record_mutability_mode' => 'MUTABLE_WITH_AUDIT', 'allow_staff_edits' => '1', 'mutable_window_hours' => '48',
        ])->assertRedirect()->assertSessionHas('status');
        $merchant->refresh();
        $this->assertSame(MutabilityMode::MUTABLE_WITH_AUDIT, $merchant->record_mutability_mode);
        $this->assertTrue($merchant->allow_staff_edits);
        $this->assertSame(48, $merchant->mutable_window_hours);
        $this->assertDatabaseHas('security_audit_logs', ['event' => 'admin.mutability_changed', 'merchant_id' => $merchant->id]);

        $this->post(route('admin.merchants.credits', $merchant), ['delta' => 25, 'reason' => 'Goodwill credit'])->assertRedirect();
        $this->assertSame(25, $merchant->refresh()->paid_credits_balance);

        $this->put(route('admin.merchants.status', $merchant), ['status' => 'SUSPENDED', 'reason' => 'Chargeback review'])->assertRedirect();
        $this->assertTrue(Merchant::query()->findOrFail($merchant->id)->isSuspended());
    }

    public function test_gateway_secrets_are_encrypted_and_never_echoed(): void
    {
        $this->actingAs($this->admin());
        $this->put(route('admin.gateways.update'), ['settings' => ['telegram__bot_token' => '999:SUPERSECRETTOKEN', 'telegram__bot_username' => 'NetSlipBot']])->assertRedirect();

        $raw = \DB::table('platform_settings')->where('key', 'telegram.bot_token')->value('value');
        $this->assertNotSame('999:SUPERSECRETTOKEN', $raw);
        $this->assertSame('999:SUPERSECRETTOKEN', app(PlatformSettings::class)->string('telegram.bot_token'));
        $this->get(route('admin.gateways.edit'))->assertDontSee('SUPERSECRETTOKEN')->assertSee('NetSlipBot');
    }

    public function test_custom_html_template_is_sanitised_and_renders(): void
    {
        $this->actingAs($this->admin());
        $this->post(route('admin.templates.store'), [
            'name' => 'Bold Lagos', 'description' => 'Test', 'min_plan_tier' => 'FREE', 'sort_order' => 5,
            'supports_receipt' => '1', 'supports_invoice' => '1', 'is_active' => '1',
            'html' => '<h1 onclick="steal()">{{business_name}}</h1><script>alert(1)</script><img src="https://evil.test/x.png">{{items_table}}{{totals_block}}',
        ])->assertRedirect();
        $template = Template::query()->where('slug', 'bold-lagos')->firstOrFail();
        $this->assertStringNotContainsString('<script', (string) $template->html);
        $this->assertStringNotContainsString('onclick', (string) $template->html);
        $this->assertStringNotContainsString('evil.test', (string) $template->html);
        $this->get(route('admin.templates.preview', $template))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    }
}
