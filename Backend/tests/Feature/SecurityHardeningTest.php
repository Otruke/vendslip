<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PlanTier;
use App\Enums\Role;
use App\Models\User;
use App\Services\Settings\PlatformSettings;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Twilio\Security\RequestValidator;

/** No way to learn which emails have accounts, limits on everything that can be guessed, clean uploads. */
final class SecurityHardeningTest extends TestCase
{
    private function spa(): self
    {
        return $this->withHeaders(['Origin' => 'http://localhost', 'Referer' => 'http://localhost/', 'Accept' => 'application/json']);
    }

    public function test_sign_in_and_password_reset_never_reveal_whether_an_email_exists(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'real@shop.test']);

        $unknown = $this->spa()->postJson('/api/v1/auth/login', ['email' => 'nobody@shop.test', 'password' => 'Wrong-pass-1'])->assertUnprocessable();
        $wrong = $this->spa()->postJson('/api/v1/auth/login', ['email' => 'real@shop.test', 'password' => 'Wrong-pass-1'])->assertUnprocessable();
        $this->assertSame($unknown->json('errors'), $wrong->json('errors'));

        $a = $this->spa()->postJson('/api/v1/auth/forgot-password', ['email' => 'nobody@shop.test'])->assertOk()->json();
        $b = $this->spa()->postJson('/api/v1/auth/forgot-password', ['email' => 'real@shop.test'])->assertOk()->json();
        $this->assertSame($a, $b);
        Notification::assertSentTo($user, ResetPassword::class); // still sent, just after the reply

        $reset = fn (string $email) => $this->spa()->postJson('/api/v1/auth/reset-password', ['token' => 'bad-token', 'email' => $email, 'password' => 'Brand-new-pass-9', 'password_confirmation' => 'Brand-new-pass-9'])
            ->assertUnprocessable()->json('errors.email.0');
        $this->assertSame('This reset link is invalid or has expired. Request a new one.', $reset('nobody@shop.test'));
        $this->assertSame($reset('nobody2@shop.test'), $reset('real@shop.test'));
    }

    public function test_repeated_sign_in_attempts_are_blocked(): void
    {
        User::factory()->create(['email' => 'target@shop.test']);
        for ($i = 0; $i < 5; $i++) {
            $this->spa()->postJson('/api/v1/auth/login', ['email' => 'target@shop.test', 'password' => 'guess-'.$i])->assertUnprocessable();
        }
        $this->spa()->postJson('/api/v1/auth/login', ['email' => 'target@shop.test', 'password' => 'guess-6'])->assertStatus(429);
    }

    public function test_admin_sign_in_is_limited_per_account_across_addresses(): void
    {
        $admin = User::factory()->create(['email' => 'boss@vendslip.test']);
        $admin->assignRole(Role::SUPER_ADMIN->value);
        for ($i = 1; $i <= 5; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => "10.0.0.{$i}"])
                ->post(route('admin.login.attempt'), ['email' => 'boss@vendslip.test', 'password' => 'nope'])->assertSessionHasErrors('email');
        }
        // A new address doesn't help, and even the right password waits.
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.99'])
            ->post(route('admin.login.attempt'), ['email' => 'boss@vendslip.test', 'password' => 'password'])
            ->assertSessionHasErrors(['email' => 'Too many attempts. Try again in 900 seconds.']);
        $this->assertGuest();
    }

    public function test_password_change_is_rate_limited(): void
    {
        [, $owner] = $this->merchantWithOwner();
        $this->actingAs($owner);
        for ($i = 0; $i < 6; $i++) {
            $this->putJson('/api/v1/account/password', ['current_password' => 'guess-'.$i, 'password' => 'New-password-2x', 'password_confirmation' => 'New-password-2x'])->assertUnprocessable();
        }
        $this->putJson('/api/v1/account/password', ['current_password' => 'password', 'password' => 'New-password-2x', 'password_confirmation' => 'New-password-2x'])->assertStatus(429);
    }

    public function test_a_chat_cannot_keep_guessing_link_codes(): void
    {
        app(PlatformSettings::class)->set('channels.meta_provider', 'twilio');
        [, $owner] = $this->merchantWithOwner();
        $code = $this->actingAs($owner)->postJson('/api/v1/channels/link-code')->assertCreated()->json('code');
        auth()->guard('web')->logout();

        $sid = 0;
        $send = function (string $body) use (&$sid) {
            $url = url('/api/v1/webhooks/whatsapp');
            $params = ['MessageSid' => 'SM'.(++$sid).uniqid(), 'From' => 'whatsapp:+2348039999999', 'To' => 'whatsapp:+14155238886', 'Body' => $body, 'ProfileName' => 'Guess'];

            return $this->withHeader('X-Twilio-Signature', (new RequestValidator('twilio-test-token'))->computeSignature($url, $params))->post($url, $params);
        };
        for ($i = 0; $i < 5; $i++) {
            $send('/link WRONG'.$i.'XY')->assertSee('invalid or expired', false);
        }
        $send('/link '.$code)->assertSee('Too many wrong codes', false);
    }

    public function test_logos_are_redrawn_so_nothing_hidden_survives(): void
    {
        [$merchant, $owner] = $this->merchantWithOwner(PlanTier::PRO);
        $fake = UploadedFile::fake()->image('logo.jpg', 2400, 1200);
        // A real JPEG with a script tucked on the end.
        file_put_contents($fake->getRealPath(), file_get_contents($fake->getRealPath()).'<?php echo "pwned"; ?><script>alert(1)</script>');
        $fake = UploadedFile::fake()->createWithContent('logo.jpg', (string) file_get_contents($fake->getRealPath()));

        $this->actingAs($owner)->post('/api/v1/settings/logo', ['logo' => $fake], ['Accept' => 'application/json'])->assertUnprocessable(); // over 2000 px: refused

        $ok = UploadedFile::fake()->image('logo.jpg', 1600, 400);
        file_put_contents($ok->getRealPath(), file_get_contents($ok->getRealPath()).'<script>alert(1)</script>');
        $ok = UploadedFile::fake()->createWithContent('logo.jpg', (string) file_get_contents($ok->getRealPath()));
        $this->post('/api/v1/settings/logo', ['logo' => $ok], ['Accept' => 'application/json'])->assertOk();

        $path = (string) $merchant->refresh()->logo_path;
        $this->assertStringEndsWith('.png', $path);
        $stored = (string) Storage::disk('public')->get($path);
        $this->assertStringStartsWith("\x89PNG", $stored);
        $this->assertStringNotContainsString('<script>', $stored);
        $this->assertSame([1000, 250], array_slice((array) getimagesizefromstring($stored), 0, 2));

        $text = UploadedFile::fake()->createWithContent('logo.png', '<svg onload="alert(1)"></svg>');
        $this->post('/api/v1/settings/logo', ['logo' => $text], ['Accept' => 'application/json'])->assertUnprocessable();
    }
}
