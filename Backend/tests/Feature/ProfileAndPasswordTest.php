<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use App\Services\Security\TotpService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class ProfileAndPasswordTest extends TestCase
{
    private function admin(Role $role = Role::ADMIN): User
    {
        $user = User::factory()->create(['password' => 'Old-password-1']);
        $user->assignRole($role->value);

        return $user;
    }

    /** Pretend the user is also signed in on two other devices. */
    private function otherSessions(User $user): void
    {
        foreach (['laptop-session', 'phone-session'] as $id) {
            DB::table('sessions')->insert(['id' => $id, 'user_id' => $user->id, 'ip_address' => '10.0.0.9', 'user_agent' => 'x', 'payload' => '', 'last_activity' => time()]);
        }
        DB::table('sessions')->insert(['id' => 'someone-else', 'user_id' => null, 'ip_address' => '10.0.0.8', 'user_agent' => 'x', 'payload' => '', 'last_activity' => time()]);
    }

    public function test_every_admin_role_can_open_and_edit_their_profile(): void
    {
        foreach ([Role::ADMIN, Role::SUPER_ADMIN] as $role) {
            $user = $this->admin($role);
            $this->actingAs($user)->get(route('admin.profile.show'))->assertOk()->assertSee('My profile')->assertSee($user->email);
            $this->put(route('admin.profile.update'), ['name' => 'Ngozi Admin', 'phone' => '08030000000'])->assertSessionHas('status');
            $this->assertSame('Ngozi Admin', $user->fresh()?->name);
        }
    }

    public function test_admin_password_change_rules(): void
    {
        $user = $this->admin();
        $this->actingAs($user);

        $this->from(route('admin.profile.show'))->put(route('admin.profile.password'), ['current_password' => 'wrong', 'password' => 'New-password-2', 'password_confirmation' => 'New-password-2'])
            ->assertSessionHasErrors('current_password');
        $this->put(route('admin.profile.password'), ['current_password' => 'Old-password-1', 'password' => 'Old-password-1', 'password_confirmation' => 'Old-password-1'])
            ->assertSessionHasErrors('password');
        $this->put(route('admin.profile.password'), ['current_password' => 'Old-password-1', 'password' => 'New-password-2', 'password_confirmation' => 'Mismatch-3'])
            ->assertSessionHasErrors('password');
        $this->assertTrue(Hash::check('Old-password-1', (string) $user->fresh()?->password));
    }

    public function test_admin_password_change_signs_out_other_devices(): void
    {
        config(['session.driver' => 'database']);
        $user = $this->admin();
        $this->otherSessions($user);
        $rememberBefore = $user->getRememberToken();

        $this->actingAs($user)->from(route('admin.profile.show'))
            ->put(route('admin.profile.password'), ['current_password' => 'Old-password-1', 'password' => 'New-password-2', 'password_confirmation' => 'New-password-2'])
            ->assertRedirect(route('admin.profile.show'))
            ->assertSessionHas('status', fn (string $m): bool => str_contains($m, '2 other sessions were signed out'));

        $fresh = $user->fresh();
        $this->assertTrue(Hash::check('New-password-2', (string) $fresh?->password));
        $this->assertNotSame($rememberBefore, $fresh?->getRememberToken());
        $this->assertSame(0, DB::table('sessions')->whereIn('id', ['laptop-session', 'phone-session'])->count());
        $this->assertSame(1, DB::table('sessions')->where('id', 'someone-else')->count());
        // Still signed in here.
        $this->get(route('admin.profile.show'))->assertOk();
    }

    public function test_admin_two_factor_setup_confirm_and_disable(): void
    {
        $user = $this->admin();
        $this->actingAs($user)->post(route('admin.profile.two-factor.start'))->assertRedirect();
        $secret = (string) $user->fresh()?->two_factor_secret;
        $this->get(route('admin.profile.show'))->assertOk()->assertSee($secret);

        $this->post(route('admin.profile.two-factor.confirm'), ['code' => '000000'])->assertSessionHasErrors('code');
        $code = app(TotpService::class)->codeAt($secret, intdiv(time(), 30));
        $this->post(route('admin.profile.two-factor.confirm'), ['code' => $code])->assertSessionHas('status');
        $this->assertTrue((bool) $user->fresh()?->hasTwoFactorEnabled());

        $this->delete(route('admin.profile.two-factor.disable'), ['confirm_password' => 'nope'])->assertSessionHasErrors('confirm_password');
        $this->delete(route('admin.profile.two-factor.disable'), ['confirm_password' => 'Old-password-1'])->assertSessionHas('warning');
        $this->assertFalse((bool) $user->fresh()?->hasTwoFactorEnabled());
    }

    public function test_merchant_staff_change_password_via_api_and_other_sessions_end(): void
    {
        config(['session.driver' => 'database']);
        [$merchant] = $this->merchantWithOwner();
        $agent = User::factory()->create(['merchant_id' => $merchant->id, 'password' => 'Old-password-1']);
        $agent->assignRole(Role::SALES_AGENT->value);
        $this->otherSessions($agent);
        $agent->createToken('old-device');

        $this->actingAs($agent)->putJson('/api/v1/account/password', ['current_password' => 'Old-password-1', 'password' => 'Old-password-1', 'password_confirmation' => 'Old-password-1'])
            ->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->putJson('/api/v1/account/password', ['current_password' => 'Old-password-1', 'password' => 'New-password-2', 'password_confirmation' => 'New-password-2'])
            ->assertOk()->assertJsonPath('other_sessions_ended', 2);

        $this->assertTrue(Hash::check('New-password-2', (string) $agent->fresh()?->password));
        $this->assertSame(0, $agent->tokens()->count());
        $this->putJson('/api/v1/account/profile', ['name' => 'Tobi Sales', 'phone' => null])->assertOk();
    }
}
