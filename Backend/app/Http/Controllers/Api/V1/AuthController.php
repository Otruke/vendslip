<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\AuditSeverity;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Merchant;
use App\Models\Template;
use App\Models\User;
use App\Services\Security\AuditLogger;
use App\Services\Security\SessionPayload;
use App\Services\Security\TotpService;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

final class AuthController extends Controller
{
    private const string PENDING_2FA = 'auth.2fa_user_id';

    private const string PENDING_2FA_REMEMBER = 'auth.2fa_remember';

    public function __construct(
        private readonly AuditLogger $audit,
        private readonly SessionPayload $payload,
        private readonly TotpService $totp,
    ) {}

    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'business_name' => ['required', 'string', 'max:160'],
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email:rfc', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:32'],
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
        ]);

        $user = DB::transaction(function () use ($data): User {
            $default = Template::query()->where('is_default', true)->first();
            $merchant = Merchant::query()->create([
                'name' => $data['business_name'],
                'slug' => $this->uniqueSlug($data['business_name']),
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'default_receipt_template_id' => $default?->id,
                'default_invoice_template_id' => $default?->id,
            ]);
            $user = User::query()->create([
                'merchant_id' => $merchant->id,
                'name' => $data['name'],
                'email' => mb_strtolower($data['email']),
                'phone' => $data['phone'] ?? null,
                'password' => $data['password'],
                'is_active' => true,
            ]);
            $user->assignRole(Role::OWNER->value);

            return $user;
        });

        Auth::guard('web')->login($user);
        $request->session()->regenerate();
        $this->audit->log('auth.registered', $user, $user->merchant_id);

        return response()->json($this->payload->for($user->refresh()), 201);
    }

    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['sometimes', 'boolean'],
        ]);
        $email = mb_strtolower($credentials['email']);

        /** @var User|null $user */
        $user = User::query()->where('email', $email)->first();
        if ($user === null || ! Auth::guard('web')->getProvider()->validateCredentials($user, ['password' => $credentials['password']])) {
            $this->audit->log('auth.login_failed', $user, null, ['email' => $email], AuditSeverity::WARNING);
            throw ValidationException::withMessages(['email' => 'These credentials do not match our records.']);
        }
        if (! $user->is_active) {
            $this->audit->log('auth.login_blocked_inactive', $user, null, [], AuditSeverity::WARNING);
            throw ValidationException::withMessages(['email' => 'This account has been deactivated.']);
        }

        if ($user->hasTwoFactorEnabled()) {
            $request->session()->put(self::PENDING_2FA, $user->id);
            $request->session()->put(self::PENDING_2FA_REMEMBER, (bool) ($credentials['remember'] ?? false));

            return response()->json(['two_factor' => true]);
        }

        return $this->completeLogin($request, $user, (bool) ($credentials['remember'] ?? false));
    }

    public function twoFactor(Request $request): JsonResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:10']]);
        $userId = $request->session()->get(self::PENDING_2FA);
        $user = is_int($userId) ? User::query()->find($userId) : null;

        if ($user === null || ! $user->hasTwoFactorEnabled()) {
            throw ValidationException::withMessages(['code' => 'Your sign-in session expired. Please sign in again.']);
        }
        if (! $this->totp->verify((string) $user->two_factor_secret, $data['code'], 'user:'.$user->id)) {
            $this->audit->log('auth.2fa_failed', $user, null, [], AuditSeverity::WARNING);
            throw ValidationException::withMessages(['code' => 'That code is not valid. Check your authenticator app.']);
        }

        $remember = (bool) $request->session()->pull(self::PENDING_2FA_REMEMBER, false);
        $request->session()->forget(self::PENDING_2FA);

        return $this->completeLogin($request, $user, $remember);
    }

    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json($this->payload->for($user));
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        if ($user instanceof User) {
            $this->audit->log('auth.logout', $user);
        }

        return response()->json(['ok' => true]);
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate(['email' => ['required', 'email']]);
        Password::sendResetLink(['email' => mb_strtolower((string) $request->input('email'))]);

        // Always the same answer: never reveal whether an email is registered.
        return response()->json(['message' => 'If that email is registered, a reset link is on its way.']);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
        ]);

        $status = Password::reset(
            ['email' => mb_strtolower($data['email']), 'password' => $data['password'], 'password_confirmation' => $request->input('password_confirmation'), 'token' => $data['token']],
            function (User $user, string $password): void {
                $user->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();
                event(new PasswordReset($user));
                $this->audit->log('auth.password_reset', $user);
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => __($status)]);
        }

        return response()->json(['message' => 'Password updated. You can sign in now.']);
    }

    private function completeLogin(Request $request, User $user, bool $remember): JsonResponse
    {
        Auth::guard('web')->login($user, $remember);
        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->save();
        $this->audit->log('auth.login', $user);

        return response()->json($this->payload->for($user));
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'merchant';
        $slug = $base;
        while (Merchant::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.Str::lower(Str::random(4));
        }

        return $slug;
    }
}
