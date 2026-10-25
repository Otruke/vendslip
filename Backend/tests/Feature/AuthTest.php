<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Services\Security\TotpService;
use Tests\TestCase;

final class AuthTest extends TestCase
{
    private function spa(): self
    {
        return $this->withHeaders(['Origin' => 'http://localhost', 'Referer' => 'http://localhost/', 'Accept' => 'application/json']);
    }

    public function test_register_creates_merchant_owner_and_session(): void
    {
        $this->spa()->postJson('/api/v1/auth/register', [
            'business_name' => 'Kemi Fabrics', 'name' => 'Kemi', 'email' => 'Kemi@Example.com',
            'password' => 'secret123A', 'password_confirmation' => 'secret123A',
        ])->assertCreated()
            ->assertJsonPath('user.email', 'kemi@example.com')
            ->assertJsonPath('user.role', 'Owner')
            ->assertJsonPath('merchant.name', 'Kemi Fabrics')
            ->assertJsonPath('merchant.plan_tier', 'FREE')
            ->assertJsonPath('usage.monthly_quota', 15);

        $this->spa()->getJson('/api/v1/auth/me')->assertOk()->assertJsonPath('user.name', 'Kemi');
    }

    public function test_login_failure_is_audited_and_generic(): void
    {
        [, $user] = $this->merchantWithOwner();
        $this->spa()->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'wrong'])
            ->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->assertDatabaseHas('security_audit_logs', ['event' => 'auth.login_failed', 'actor_user_id' => $user->id]);
    }

    public function test_two_factor_login_flow(): void
    {
        [, $user] = $this->merchantWithOwner();
        $totp = new TotpService;
        $secret = $totp->generateSecret();
        $user->forceFill(['two_factor_secret' => $secret, 'two_factor_confirmed_at' => now()])->save();

        $this->spa()->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'password'])
            ->assertOk()->assertExactJson(['two_factor' => true]);
        $this->spa()->getJson('/api/v1/auth/me')->assertUnauthorized();

        $this->spa()->postJson('/api/v1/auth/two-factor', ['code' => '000000'])->assertUnprocessable();
        $this->spa()->postJson('/api/v1/auth/two-factor', ['code' => $totp->codeAt($secret, intdiv(time(), 30))])
            ->assertOk()->assertJsonPath('user.two_factor_enabled', true);
        $this->spa()->getJson('/api/v1/auth/me')->assertOk();
    }

    public function test_two_factor_enrolment(): void
    {
        [, $user] = $this->merchantWithOwner();
        $this->actingAs($user);
        $secret = $this->postJson('/api/v1/account/two-factor')->assertOk()->json('secret');
        $this->assertFalse($user->refresh()->hasTwoFactorEnabled());
        $this->postJson('/api/v1/account/two-factor/confirm', ['code' => (new TotpService)->codeAt($secret, intdiv(time(), 30))])->assertOk();
        $this->assertTrue($user->refresh()->hasTwoFactorEnabled());
        $this->deleteJson('/api/v1/account/two-factor', ['password' => 'password'])->assertOk();
        $this->assertFalse($user->refresh()->hasTwoFactorEnabled());
    }

    public function test_inactive_users_cannot_log_in(): void
    {
        [, $user] = $this->merchantWithOwner();
        $user->update(['is_active' => false]);
        $this->spa()->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'password'])->assertUnprocessable();
        $this->assertSame(1, User::query()->count());
    }
}
