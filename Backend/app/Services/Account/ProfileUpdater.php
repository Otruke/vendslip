<?php

declare(strict_types=1);

namespace App\Services\Account;

use App\Enums\AuditSeverity;
use App\Models\User;
use App\Services\Security\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * "Edit my details" for everyone (merchant staff in the app, platform admins in the portal).
 * Name, email and phone change only after the person re-enters their current password, so a
 * borrowed laptop or a hijacked session can't quietly take over the sign-in email.
 */
final class ProfileUpdater
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  string  $errorBag  named bag for pages that show several forms (admin portal)
     * @return list<string> the fields that actually changed
     */
    public function update(Request $request, User $user, string $errorBag = 'default'): array
    {
        $data = $request->validateWithBag($errorBag, [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email:rfc', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:32'],
            'current_password' => ['required', 'string', 'current_password:web'],
        ], [
            'current_password.required' => 'Enter your current password to save these changes.',
            'current_password.current_password' => 'Your current password is incorrect.',
            'email.unique' => 'Another account already uses that email.',
        ]);

        $before = ['name' => $user->name, 'email' => $user->email, 'phone' => $user->phone];
        $user->fill([
            'name' => trim((string) $data['name']),
            'email' => mb_strtolower(trim((string) $data['email'])),
            'phone' => isset($data['phone']) && trim((string) $data['phone']) !== '' ? trim((string) $data['phone']) : null,
        ]);
        $changed = array_keys($user->getDirty());
        if ($changed === []) {
            return [];
        }
        $user->save();

        $emailChanged = in_array('email', $changed, true);
        $this->audit->log(
            'account.profile_updated',
            $user,
            null,
            ['fields' => $changed] + ($emailChanged ? ['previous_email' => $before['email']] : []),
            $emailChanged ? AuditSeverity::WARNING : AuditSeverity::INFO,
        );

        return $changed;
    }
}
