<?php

declare(strict_types=1);

namespace App\Services\Security;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * One password-change path for merchant users (API) and platform admins (Blade portal).
 * Requires the current password, refuses re-using it, then signs the user out everywhere else:
 * other browser sessions are deleted and API tokens revoked. The current session is kept and
 * its id rotated.
 */
final class PasswordChanger
{
    public function __construct(private readonly AuditLogger $audit) {}

    /** @return array<string, list<mixed>> */
    public static function rules(): array
    {
        return [
            'current_password' => ['required', 'string', 'current_password:web'],
            'password' => ['required', 'string', 'confirmed', 'different:current_password', Password::defaults()],
        ];
    }

    /** @return array<string, string> */
    public static function messages(): array
    {
        return [
            'current_password.current_password' => 'Your current password is incorrect.',
            'password.different' => 'Choose a new password that is different from your current one.',
        ];
    }

    /** Validate the request and change the password. Returns how many other sessions were ended. */
    public function change(Request $request, User $user): int
    {
        $data = $request->validate(self::rules(), self::messages());
        // Rotating the remember token invalidates "remember me" cookies on every other device.
        $user->forceFill(['password' => (string) $data['password'], 'remember_token' => Str::random(60)])->save();

        // Sign out every other browser session. A token-authenticated request has no session of its
        // own, so in that case all of the user's browser sessions end.
        $currentId = null;
        if ($request->hasSession()) {
            $request->session()->migrate(true); // rotate this session's id, destroy the old one
            $currentId = $request->session()->getId();
        }
        $ended = 0;
        if (config('session.driver') === 'database') {
            $ended = DB::connection(config('session.connection'))
                ->table((string) config('session.table', 'sessions'))
                ->where('user_id', $user->getKey())
                ->when($currentId !== null, static fn ($q) => $q->where('id', '!=', $currentId))
                ->delete();
        }
        // Revoke API tokens on other devices, keeping the one making this request (if any).
        $current = $user->currentAccessToken();
        $user->tokens()->when($current instanceof PersonalAccessToken, static fn ($q) => $q->whereKeyNot($current->getKey()))->delete();
        $this->audit->log('account.password_changed', $user, null, ['other_sessions_ended' => $ended]);

        return $ended;
    }
}
