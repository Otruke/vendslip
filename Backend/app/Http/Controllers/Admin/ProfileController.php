<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\AuditSeverity;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Pdf\QrCodeService;
use App\Services\Security\AuditLogger;
use App\Services\Security\PasswordChanger;
use App\Services\Security\TotpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** "My profile" for Admins and Super-Admins: details, password and two-factor authentication. */
final class ProfileController extends Controller
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly TotpService $totp,
        private readonly QrCodeService $qr,
    ) {}

    public function show(Request $request): View
    {
        $user = $this->user($request);
        $pending = ! $user->hasTwoFactorEnabled() && $user->two_factor_secret !== null && $request->session()->get('admin_2fa_setup') === true;

        return view('admin.profile.show', [
            'user' => $user,
            'role' => $user->isSuperAdmin() ? 'Super Admin' : 'Admin',
            'setupSecret' => $pending ? $user->two_factor_secret : null,
            'setupQr' => $pending ? $this->qr->svg($this->totp->otpauthUri((string) $user->two_factor_secret, $user->email, 'NetSlip Control')) : null,
            'recentEvents' => $user->auditLogs()->latest('id')->limit(6)->get(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $this->user($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:32'],
        ]);
        $user->update($data);

        return back()->with('status', 'Profile saved.');
    }

    public function password(Request $request, PasswordChanger $passwords): RedirectResponse
    {
        $ended = $passwords->change($request, $this->user($request));
        $note = $ended > 0 ? " {$ended} other session".($ended === 1 ? ' was' : 's were').' signed out.' : '';

        return back()->with('status', 'Password changed.'.$note);
    }

    public function startTwoFactor(Request $request): RedirectResponse
    {
        $user = $this->user($request);
        if ($user->hasTwoFactorEnabled()) {
            return back()->with('info', 'Two-factor authentication is already on.');
        }
        $user->forceFill(['two_factor_secret' => $this->totp->generateSecret(), 'two_factor_confirmed_at' => null])->save();
        $request->session()->put('admin_2fa_setup', true);

        return redirect()->route('admin.profile.show', ['_fragment' => 'two-factor']);
    }

    public function confirmTwoFactor(Request $request): RedirectResponse
    {
        $user = $this->user($request);
        $data = $request->validate(['code' => ['required', 'string', 'max:10']]);
        if ($user->two_factor_secret === null || ! $this->totp->verify($user->two_factor_secret, $data['code'], 'user:'.$user->id)) {
            throw ValidationException::withMessages(['code' => 'That code is not valid. Try the newest one in your app.']);
        }
        $user->forceFill(['two_factor_confirmed_at' => now()])->save();
        $request->session()->forget('admin_2fa_setup');
        $this->audit->log('account.2fa_enabled', $user);

        return redirect()->route('admin.profile.show')->with('status', 'Two-factor authentication is on.');
    }

    public function disableTwoFactor(Request $request): RedirectResponse
    {
        $user = $this->user($request);
        $request->validate(['confirm_password' => ['required', 'string']]);
        if (! Hash::check((string) $request->input('confirm_password'), $user->password)) {
            throw ValidationException::withMessages(['confirm_password' => 'Password is incorrect.']);
        }
        $user->forceFill(['two_factor_secret' => null, 'two_factor_confirmed_at' => null])->save();
        $request->session()->forget('admin_2fa_setup');
        $this->audit->log('account.2fa_disabled', $user, null, [], AuditSeverity::WARNING);

        return redirect()->route('admin.profile.show')->with('warning', 'Two-factor authentication is off.');
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
