<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\AuditSeverity;
use App\Http\Controllers\Api\V1\AuthController as ApiAuthController;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Security\AuditLogger;
use App\Services\Security\TotpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class AuthController extends Controller
{
    private const string PENDING = 'admin.2fa_user_id';

    public function __construct(
        private readonly AuditLogger $audit,
        private readonly TotpService $totp,
    ) {}

    public function show(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        if ($user instanceof User && $user->isPlatformStaff()) {
            return redirect()->route('admin.dashboard');
        }

        return view('admin.auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);
        // Limited per address AND per account, so spreading attempts over many IPs doesn't help.
        $key = 'admin-login:'.$request->ip();
        $accountKey = 'admin-login-account:'.sha1(mb_strtolower($data['email']));
        foreach ([$key => 5, $accountKey => 5] as $limitKey => $max) {
            if (RateLimiter::tooManyAttempts($limitKey, $max)) {
                throw ValidationException::withMessages(['email' => 'Too many attempts. Try again in '.RateLimiter::availableIn($limitKey).' seconds.']);
            }
        }

        $user = User::query()->where('email', mb_strtolower($data['email']))->first();
        $passwordOk = Hash::check($data['password'], $user !== null ? $user->password : ApiAuthController::dummyHash());
        if ($user === null || ! $passwordOk || ! $user->is_active || ! $user->isPlatformStaff()) {
            RateLimiter::hit($key, 300);
            RateLimiter::hit($accountKey, 900);
            $this->audit->log('admin.login_failed', $user, null, ['email' => mb_strtolower($data['email'])], AuditSeverity::WARNING);
            throw ValidationException::withMessages(['email' => 'These credentials do not match an administrator account.']);
        }
        RateLimiter::clear($key);
        RateLimiter::clear($accountKey);

        if ($user->hasTwoFactorEnabled()) {
            $request->session()->put(self::PENDING, $user->id);

            return redirect()->route('admin.two-factor');
        }

        return $this->complete($request, $user);
    }

    public function twoFactorForm(Request $request): View|RedirectResponse
    {
        return $request->session()->has(self::PENDING) ? view('admin.auth.two-factor') : redirect()->route('admin.login');
    }

    public function twoFactor(Request $request): RedirectResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:10']]);
        $id = $request->session()->get(self::PENDING);
        $user = is_int($id) ? User::query()->find($id) : null;
        if ($user === null || ! $this->totp->verify((string) $user->two_factor_secret, $data['code'], 'user:'.$user->id)) {
            $this->audit->log('admin.2fa_failed', $user, null, [], AuditSeverity::WARNING);
            throw ValidationException::withMessages(['code' => 'That code is not valid.']);
        }
        $request->session()->forget(self::PENDING);

        return $this->complete($request, $user);
    }

    public function logout(Request $request): RedirectResponse
    {
        $user = $request->user();
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        if ($user instanceof User) {
            $this->audit->log('admin.logout', $user);
        }

        return redirect()->route('admin.login');
    }

    private function complete(Request $request, User $user): RedirectResponse
    {
        Auth::guard('web')->login($user);
        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->save();
        $this->audit->log('admin.login', $user);

        return redirect()->intended(route('admin.dashboard'));
    }
}
