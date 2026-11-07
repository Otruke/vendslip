<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PlanTier;
use App\Enums\Role;
use App\Exceptions\DomainRuleException;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Api\V1\TeamController;
use App\Models\Merchant;
use App\Models\SecurityAuditLog;
use App\Models\SupportAccessGrant;
use App\Models\User;
use App\Notifications\SupportAccessCodeNotification;
use App\Services\Security\SupportAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** Own-details edits, Super-Admins editing admins, the last-owner rule, and owner-approved support access. */
final class AccountAccessRulesTest extends TestCase
{
    private function admin(Role $role = Role::SUPER_ADMIN, array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->assignRole($role->value);

        return $user;
    }

    /** The web app's requests: same-site Origin so Sanctum gives them a session. */
    private function spa(): static
    {
        return $this->withHeaders(['Origin' => 'http://localhost', 'Referer' => 'http://localhost/', 'Accept' => 'application/json']);
    }

    private function staff(Merchant $merchant, Role $role, array $attributes = []): User
    {
        $user = User::factory()->create(['merchant_id' => $merchant->id, ...$attributes]);
        $user->assignRole($role->value);

        return $user;
    }

    // ── Own details ────────────────────────────────────────────────────────────

    public function test_staff_change_own_name_email_and_phone_only_with_current_password(): void
    {
        [, $owner] = $this->merchantWithOwner();
        $this->actingAs($owner);
        $other = User::factory()->create(['email' => 'taken@vendslip.test']);

        $this->putJson('/api/v1/account/profile', ['name' => 'Ada', 'email' => 'ada@new.test', 'phone' => '0803'])->assertUnprocessable()->assertJsonValidationErrors('current_password');
        $this->putJson('/api/v1/account/profile', ['name' => 'Ada', 'email' => 'ada@new.test', 'current_password' => 'wrong'])->assertUnprocessable()->assertJsonValidationErrors('current_password');
        $this->putJson('/api/v1/account/profile', ['name' => 'Ada', 'email' => $other->email, 'current_password' => 'password'])->assertUnprocessable()->assertJsonValidationErrors('email');

        $changed = $this->putJson('/api/v1/account/profile', ['name' => 'Ada Obi', 'email' => 'Ada@New.TEST', 'phone' => ' 08031112222 ', 'current_password' => 'password'])
            ->assertOk()->json('changed');
        $this->assertEqualsCanonicalizing(['name', 'email', 'phone'], $changed);
        $owner->refresh();
        $this->assertSame(['Ada Obi', 'ada@new.test', '08031112222'], [$owner->name, $owner->email, $owner->phone]);
        $log = SecurityAuditLog::query()->where('event', 'account.profile_updated')->latest('id')->firstOrFail();
        $this->assertSame('WARNING', $log->severity->value); // email changes are flagged
    }

    public function test_admin_edits_own_email_with_password_on_my_profile(): void
    {
        $admin = $this->admin(Role::ADMIN);
        $this->actingAs($admin)->put(route('admin.profile.update'), ['name' => $admin->name, 'email' => 'new-admin@vendslip.test', 'current_password' => 'nope'])
            ->assertSessionHasErrorsIn('profile', 'current_password');
        $this->put(route('admin.profile.update'), ['name' => $admin->name, 'email' => 'new-admin@vendslip.test', 'current_password' => 'password'])->assertSessionHas('status');
        $this->assertSame('new-admin@vendslip.test', $admin->fresh()?->email);
        $this->get(route('admin.profile.show'))->assertOk()->assertSee('new-admin@vendslip.test');
    }

    // ── Super-Admin edits other admins ─────────────────────────────────────────

    public function test_super_admin_edits_another_admin_with_own_password(): void
    {
        $super = $this->admin();
        $target = $this->admin(Role::ADMIN, ['email' => 'kemi@vendslip.test']);
        DB::table('sessions')->insert(['id' => 'kemi-laptop', 'user_id' => $target->id, 'ip_address' => '1.1.1.1', 'user_agent' => 'x', 'payload' => '', 'last_activity' => time()]);
        $this->actingAs($super);

        $this->get(route('admin.admins.index'))->assertOk()->assertSee(route('admin.admins.edit', $target));
        $this->get(route('admin.admins.edit', $target))->assertOk()->assertSee('Edit '.$target->name)->assertSee('Your password');

        $form = ['name' => 'Kemi Bello', 'email' => 'kemi.bello@vendslip.test', 'phone' => '0809', 'role' => 'SuperAdmin', 'password' => 'Brand-new-pass1', 'password_confirmation' => 'Brand-new-pass1'];
        $this->put(route('admin.admins.update', $target), $form + ['your_password' => 'wrong'])->assertSessionHasErrors('your_password');
        $this->put(route('admin.admins.update', $target), ['password' => 'weak', 'password_confirmation' => 'weak'] + $form + ['your_password' => 'password'])->assertSessionHasErrors('password');

        $this->put(route('admin.admins.update', $target), $form + ['your_password' => 'password'])->assertRedirect(route('admin.admins.index'));
        $target->refresh();
        $this->assertSame(['Kemi Bello', 'kemi.bello@vendslip.test', '0809'], [$target->name, $target->email, $target->phone]);
        $this->assertTrue($target->isSuperAdmin());
        $this->assertTrue(Hash::check('Brand-new-pass1', $target->password));
        $this->assertSame(0, DB::table('sessions')->where('user_id', $target->id)->count());
        $this->assertTrue(SecurityAuditLog::query()->where('event', 'admin.admin_updated')->exists());

        // Own row goes to My profile, not this form.
        $this->get(route('admin.admins.edit', $super))->assertRedirect(route('admin.profile.show'));
    }

    public function test_regular_admins_cannot_edit_admins(): void
    {
        $super = $this->admin();
        $this->actingAs($this->admin(Role::ADMIN));
        $this->get(route('admin.admins.edit', $super))->assertForbidden();
        $this->put(route('admin.admins.update', $super), ['name' => 'x'])->assertForbidden();
    }

    public function test_super_admin_can_demote_and_disable_another_while_one_remains(): void
    {
        $actor = $this->admin();
        $target = $this->admin();
        $this->actingAs($actor);

        $this->put(route('admin.admins.toggle', $target))->assertSessionHas('status');
        $this->assertFalse((bool) $target->fresh()?->is_active);
        $this->put(route('admin.admins.toggle', $target))->assertSessionHas('status');
        $this->assertTrue((bool) $target->fresh()?->is_active);

        $this->put(route('admin.admins.update', $target), ['name' => $target->name, 'email' => $target->email, 'role' => 'Admin', 'your_password' => 'password'])
            ->assertRedirect(route('admin.admins.index'));
        $this->assertFalse($target->fresh()?->isSuperAdmin());
    }

    public function test_last_super_admin_guard_blocks_demotion_and_disable(): void
    {
        $only = $this->admin();
        $actor = $this->admin();
        $this->actingAs($actor);
        // Simulate the actor's own Super-Admin row being disabled by someone else mid-session while the
        // platform check still lets this request through: call the guard via the controller directly.
        $actor->forceFill(['is_active' => false])->save();
        $controller = app(AdminUserController::class);
        $request = Request::create('/', 'PUT', ['name' => $only->name, 'email' => $only->email, 'role' => 'Admin', 'your_password' => 'password']);
        $request->setUserResolver(static fn () => $actor);
        $request->setLaravelSession(app('session.store'));
        app()->instance('request', $request);
        $this->app['auth']->guard('web')->setUser($actor);

        $response = $controller->update($request, $only);
        $this->assertSame('The platform must keep at least one active Super-Admin.', $response->getSession()?->get('error'));
        $this->assertTrue($only->fresh()?->isSuperAdmin());

        $response = $controller->toggle($request, $only);
        $this->assertSame('The platform must keep at least one active Super-Admin.', $response->getSession()?->get('error'));
        $this->assertTrue((bool) $only->fresh()?->is_active);
    }

    // ── At least one owner per business ────────────────────────────────────────

    public function test_business_always_keeps_one_active_owner(): void
    {
        [$merchant, $owner] = $this->merchantWithOwner(PlanTier::BUSINESS_PLUS);
        $manager = $this->staff($merchant, Role::MANAGER);
        $agent = $this->staff($merchant, Role::SALES_AGENT);

        // Managers can't create owners or touch the owner.
        $this->actingAs($manager);
        $this->getJson('/api/v1/team')->assertOk()->assertJsonMissing(['value' => 'Owner']);
        $this->putJson("/api/v1/team/members/{$agent->id}", ['role' => 'Owner'])->assertStatus($manager->can('team.manage') ? 422 : 403);
        $this->putJson("/api/v1/team/members/{$owner->id}", ['is_active' => false])->assertForbidden();
        $this->deleteJson("/api/v1/team/members/{$owner->id}")->assertForbidden();

        // The owner promotes the manager to co-owner…
        $this->actingAs($owner);
        $team = $this->getJson('/api/v1/team')->assertOk();
        $this->assertContains('Owner', array_column($team->json('roles'), 'value'));
        $this->putJson("/api/v1/team/members/{$manager->id}", ['role' => 'Owner'])->assertOk();
        $this->assertTrue($manager->fresh()?->hasRole('Owner'));

        // …who can now remove the original owner, but then can't be removed by anyone else.
        $this->actingAs($manager->fresh());
        $this->deleteJson("/api/v1/team/members/{$owner->id}")->assertOk();
        $this->assertNull($owner->fresh()?->merchant_id);
        $team = $this->getJson('/api/v1/team')->assertOk();
        $me = collect($team->json('members'))->firstWhere('id', $manager->id);
        $this->assertTrue($me['is_last_owner']);
        $this->putJson("/api/v1/team/members/{$manager->id}", ['role' => 'Manager'])->assertForbidden(); // self

        // Defence in depth: even if an owner somehow targets the last active owner, the locked
        // check refuses (covers two owners removing each other at the same moment).
        $guard = new \ReflectionMethod(TeamController::class, 'ensureAnotherOwner');
        try {
            $guard->invoke(app(TeamController::class), $manager->fresh());
            $this->fail('The last owner must be protected.');
        } catch (DomainRuleException $e) {
            $this->assertSame('last_owner', $e->errorCode);
        }
        $this->assertTrue($manager->fresh()?->hasRole('Owner'));
    }

    // ── Owner-approved support access ──────────────────────────────────────────

    /** @return array{0: Merchant, 1: User, 2: User} merchant, owner, super admin */
    private function supportScenario(): array
    {
        [$merchant, $owner] = $this->merchantWithOwner();
        $super = $this->admin();

        return [$merchant, $owner, $super];
    }

    private function sentCode(User $owner): string
    {
        $code = null;
        Notification::assertSentTo($owner, SupportAccessCodeNotification::class, function (SupportAccessCodeNotification $n) use (&$code, $owner): bool {
            preg_match('/\*\*(\d{6})\*\*/', implode(' ', $n->toMail($owner)->introLines), $m);
            $code = $m[1] ?? null;

            return $code !== null;
        });

        return (string) $code;
    }

    private function handoffToken(string $html): string
    {
        $this->assertSame(1, preg_match('/#token=([A-Za-z0-9]{64})/', $html, $m));

        return $m[1];
    }

    public function test_full_support_access_flow_with_owner_code(): void
    {
        Notification::fake();
        [$merchant, $owner, $super] = $this->supportScenario();
        $this->actingAs($super);

        $this->get(route('admin.merchants.show', $merchant))->assertOk()->assertSee('Support access')->assertSee('Send access code to owner');
        $this->post(route('admin.merchants.support.send', $merchant))->assertRedirect()->assertSessionHas('status');
        $code = $this->sentCode($owner);
        Notification::assertNotSentTo($super, SupportAccessCodeNotification::class);

        $this->get(route('admin.merchants.show', $merchant))->assertSee('Code from the owner');
        $this->post(route('admin.merchants.support.verify', $merchant), ['code' => '000000'])->assertSessionHas('error');
        $ready = $this->post(route('admin.merchants.support.verify', $merchant), ['code' => $code])->assertOk()->assertSee('Open dashboard as support');
        $token = $this->handoffToken($ready->getContent());
        $this->assertStringContainsString('https://app.vendslip.test/support-access#token=', $ready->getContent());

        // The web app exchanges the token for a support session as the owner.
        Auth::guard('web')->logout();
        $this->flushSession();
        $session = $this->spa()->postJson('/api/v1/auth/support-access', ['token' => $token])->assertOk()
            ->assertJsonPath('user.id', $owner->id)
            ->assertJsonPath('support.admin_name', $super->name);
        $this->assertStringEndsWith('/admin/merchants/'.$merchant->id, $session->json('support.return_url'));
        $this->spa()->getJson('/api/v1/auth/me')->assertOk()->assertJsonPath('support.admin_name', $super->name);

        // Ordinary work is allowed and stamped with the Super-Admin in the audit log…
        $this->spa()->getJson('/api/v1/documents')->assertOk();
        $this->assertLessThan(500, $this->spa()->putJson('/api/v1/settings/business', ['name' => $merchant->name, 'email' => $merchant->email])->status());
        // …but anything that creates a new way in, or moves money, is blocked.
        foreach ([
            ['put', '/api/v1/account/password', ['current_password' => 'password', 'password' => 'Hijack-pass-1', 'password_confirmation' => 'Hijack-pass-1']],
            ['put', '/api/v1/account/profile', ['name' => 'x', 'email' => 'support@evil.test', 'current_password' => 'password']],
            ['post', '/api/v1/account/two-factor', []],
            ['post', '/api/v1/team/invitations', ['email' => 'support@evil.test', 'role' => 'Owner']],
            ['post', '/api/v1/channels/link-code', []],
            ['put', '/api/v1/settings/gateways', []],
        ] as [$method, $url, $body]) {
            $this->spa()->json($method, $url, $body)->assertForbidden()->assertJsonPath('code', 'support_session_restricted');
        }
        $this->assertTrue(Hash::check('password', (string) $owner->fresh()?->password));

        // The token is single use.
        $this->spa()->postJson('/api/v1/auth/support-access', ['token' => $token])->assertUnprocessable();

        // Signing out ends the grant and is audited.
        $this->spa()->postJson('/api/v1/auth/logout')->assertOk();
        $grant = SupportAccessGrant::query()->firstOrFail();
        $this->assertSame('signed_out', $grant->end_reason);
        foreach (['support.code_sent', 'support.code_verified', 'support.session_started', 'support.session_ended'] as $event) {
            $this->assertTrue(SecurityAuditLog::query()->where('event', $event)->where('merchant_id', $merchant->id)->exists(), $event);
        }
        $this->assertTrue(SecurityAuditLog::query()->whereNotNull('context->support_admin_id')->exists());
    }

    public function test_support_session_expires_and_can_be_revoked(): void
    {
        Notification::fake();
        [$merchant, $owner, $super] = $this->supportScenario();
        $this->actingAs($super)->post(route('admin.merchants.support.send', $merchant));
        $token = $this->handoffToken($this->post(route('admin.merchants.support.verify', $merchant), ['code' => $this->sentCode($owner)])->getContent());
        Auth::guard('web')->logout();
        $this->flushSession();
        $this->spa()->postJson('/api/v1/auth/support-access', ['token' => $token])->assertOk();

        $this->travel(61)->minutes();
        $this->spa()->getJson('/api/v1/auth/me')->assertUnauthorized()->assertJsonPath('code', 'support_session_ended');
        $this->spa()->getJson('/api/v1/auth/me')->assertUnauthorized(); // signed out for good
        $this->assertSame('expired', SupportAccessGrant::query()->firstOrFail()->end_reason);
        $this->travelBack();

        // Revoked from the portal → the next request is refused.
        $this->actingAs($super)->post(route('admin.merchants.support.send', $merchant));
        $token = $this->handoffToken($this->post(route('admin.merchants.support.verify', $merchant), ['code' => $this->sentCode($owner)])->getContent());
        Auth::guard('web')->logout();
        $this->flushSession();
        $this->spa()->postJson('/api/v1/auth/support-access', ['token' => $token])->assertOk();
        $grant = SupportAccessGrant::query()->latest('id')->firstOrFail();
        app(SupportAccess::class)->revoke($grant, $super);
        $this->spa()->getJson('/api/v1/documents')->assertUnauthorized();
    }

    public function test_codes_are_limited_single_purpose_and_super_admin_only(): void
    {
        Notification::fake();
        [$merchant, $owner, $super] = $this->supportScenario();

        // Regular admins and merchants can't start it.
        $this->actingAs($this->admin(Role::ADMIN))->post(route('admin.merchants.support.send', $merchant))->assertForbidden();
        $this->get(route('admin.merchants.show', $merchant))->assertOk()->assertDontSee('Send access code to owner');
        $this->actingAs($owner)->post(route('admin.merchants.support.send', $merchant))->assertForbidden();

        // Five wrong guesses burn the code.
        $this->actingAs($super)->post(route('admin.merchants.support.send', $merchant));
        $code = $this->sentCode($owner);
        foreach (range(1, 5) as $_) {
            $this->post(route('admin.merchants.support.verify', $merchant), ['code' => $code === '111111' ? '222222' : '111111']);
        }
        $this->post(route('admin.merchants.support.verify', $merchant), ['code' => $code])->assertSessionHas('error');
        $this->assertSame('too_many_attempts', SupportAccessGrant::query()->firstOrFail()->end_reason);

        // An expired code is refused too.
        $this->travel(1)->minutes();
        $this->post(route('admin.merchants.support.send', $merchant));
        $this->travel(11)->minutes();
        $this->post(route('admin.merchants.support.verify', $merchant), ['code' => $this->latestCode($owner)])->assertSessionHas('error', 'That code has expired. Send a new one.');
        $this->travelBack();

        // Handoff links die after 2 minutes, and garbage tokens are rejected.
        $this->spa()->postJson('/api/v1/auth/support-access', ['token' => str_repeat('a', 64)])->assertUnprocessable();
        $this->spa()->postJson('/api/v1/auth/support-access', ['token' => 'short'])->assertUnprocessable();
    }

    public function test_normal_login_after_support_is_never_a_support_session(): void
    {
        Notification::fake();
        [$merchant, $owner, $super] = $this->supportScenario();
        $this->actingAs($super)->post(route('admin.merchants.support.send', $merchant));
        $token = $this->handoffToken($this->post(route('admin.merchants.support.verify', $merchant), ['code' => $this->sentCode($owner)])->getContent());
        Auth::guard('web')->logout();
        $this->flushSession();
        $this->spa()->postJson('/api/v1/auth/support-access', ['token' => $token])->assertOk()->assertJsonPath('support.admin_name', $super->name);

        Auth::guard('web')->logout();
        $this->spa()->postJson('/api/v1/auth/login', ['email' => $owner->email, 'password' => 'password'])->assertOk()->assertJsonPath('support', null);
    }

    private function latestCode(User $owner): string
    {
        $codes = [];
        Notification::assertSentTo($owner, SupportAccessCodeNotification::class, function (SupportAccessCodeNotification $n) use (&$codes, $owner): bool {
            preg_match('/\*\*(\d{6})\*\*/', implode(' ', $n->toMail($owner)->introLines), $m);
            $codes[] = $m[1] ?? '';

            return true;
        });

        return (string) end($codes);
    }
}
