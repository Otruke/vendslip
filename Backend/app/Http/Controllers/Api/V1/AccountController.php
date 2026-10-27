<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\AuditSeverity;
use App\Services\Pdf\QrCodeService;
use App\Services\Security\AuditLogger;
use App\Services\Security\PasswordChanger;
use App\Services\Security\TotpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

final class AccountController extends ApiController
{
    public function __construct(
        private readonly TotpService $totp,
        private readonly QrCodeService $qr,
        private readonly AuditLogger $audit,
    ) {}

    public function updateProfile(Request $request): JsonResponse
    {
        $user = $this->user($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:32'],
        ]);
        $user->update($data);

        return response()->json(['ok' => true]);
    }

    public function updatePassword(Request $request, PasswordChanger $passwords): JsonResponse
    {
        $ended = $passwords->change($request, $this->user($request));

        return response()->json(['ok' => true, 'other_sessions_ended' => $ended]);
    }

    /** Step 1: generate a secret and return the otpauth URI + QR (not active until confirmed). */
    public function enableTwoFactor(Request $request): JsonResponse
    {
        $user = $this->user($request);
        abort_if($user->hasTwoFactorEnabled(), 409, 'Two-factor authentication is already on.');

        $secret = $this->totp->generateSecret();
        $user->forceFill(['two_factor_secret' => $secret, 'two_factor_confirmed_at' => null])->save();
        $uri = $this->totp->otpauthUri($secret, $user->email);

        return response()->json([
            'secret' => $secret,
            'otpauth_uri' => $uri,
            'qr_svg' => $this->qr->svg($uri),
        ]);
    }

    /** Step 2: prove possession of the authenticator. */
    public function confirmTwoFactor(Request $request): JsonResponse
    {
        $user = $this->user($request);
        $data = $request->validate(['code' => ['required', 'string', 'max:10']]);
        if ($user->two_factor_secret === null || ! $this->totp->verify($user->two_factor_secret, $data['code'], 'user:'.$user->id)) {
            throw ValidationException::withMessages(['code' => 'That code is not valid. Try the latest one in your app.']);
        }
        $user->forceFill(['two_factor_confirmed_at' => now()])->save();
        $this->audit->log('account.2fa_enabled', $user);

        return response()->json(['ok' => true]);
    }

    public function disableTwoFactor(Request $request): JsonResponse
    {
        $user = $this->user($request);
        $request->validate(['password' => ['required', 'string']]);
        if (! Hash::check((string) $request->input('password'), $user->password)) {
            throw ValidationException::withMessages(['password' => 'Password is incorrect.']);
        }
        $user->forceFill(['two_factor_secret' => null, 'two_factor_confirmed_at' => null])->save();
        $this->audit->log('account.2fa_disabled', $user, null, [], AuditSeverity::WARNING);

        return response()->json(['ok' => true]);
    }
}
