<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\AuditSeverity;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Security\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

final class AdminUserController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(): View
    {
        return view('admin.admins.index', [
            'admins' => User::query()->role([Role::SUPER_ADMIN->value, Role::ADMIN->value, Role::SUPPORT_AGENT->value, Role::QA_REVIEWER->value])->with('roles')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', Rule::in([Role::ADMIN->value, Role::SUPER_ADMIN->value, Role::SUPPORT_AGENT->value, Role::QA_REVIEWER->value])],
            'password' => ['required', Password::min(12)->letters()->mixedCase()->numbers()->symbols()],
        ]);
        $admin = User::query()->create(['name' => $data['name'], 'email' => mb_strtolower($data['email']), 'password' => $data['password'], 'is_active' => true]);
        $admin->forceFill(['email_verified_at' => now()])->save();
        $admin->assignRole($data['role']);
        $actor = $request->user();
        $this->audit->log('admin.admin_created', $actor instanceof User ? $actor : null, null, ['email' => $admin->email, 'role' => $data['role']], AuditSeverity::CRITICAL);

        return back()->with('status', "{$admin->name} can now sign in. Ask them to enable 2FA.");
    }

    public function edit(Request $request, User $admin): View|RedirectResponse
    {
        abort_unless($admin->isPlatformStaff(), 404);
        if ($request->user()?->getKey() === $admin->id) {
            return redirect()->route('admin.profile.show')->with('info', 'Edit your own details on My profile.');
        }

        $role = Role::ADMIN->value;
        foreach ([Role::SUPER_ADMIN, Role::ADMIN, Role::SUPPORT_AGENT, Role::QA_REVIEWER] as $candidate) {
            if ($admin->hasRole($candidate->value)) {
                $role = $candidate->value;
                break;
            }
        }

        return view('admin.admins.edit', ['admin' => $admin, 'role' => $role]);
    }

    /**
     * A Super-Admin edits another platform admin: details, role and (optionally) a new password.
     * Confirmed with the Super-Admin's own password; the platform always keeps one active Super-Admin.
     */
    public function update(Request $request, User $admin): RedirectResponse
    {
        abort_unless($admin->isPlatformStaff(), 404);
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);
        if ($actor->id === $admin->id) {
            return redirect()->route('admin.profile.show')->with('info', 'Edit your own details on My profile.');
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email:rfc', 'max:255', Rule::unique('users', 'email')->ignore($admin->id)],
            'phone' => ['nullable', 'string', 'max:32'],
            'role' => ['required', Rule::in([Role::ADMIN->value, Role::SUPER_ADMIN->value, Role::SUPPORT_AGENT->value, Role::QA_REVIEWER->value])],
            'password' => ['nullable', 'string', 'confirmed', Password::min(12)->letters()->mixedCase()->numbers()->symbols()],
            'your_password' => ['required', 'string', 'current_password:web'],
        ], [
            'your_password.required' => 'Confirm with your own password.',
            'your_password.current_password' => 'Your password is incorrect.',
            'email.unique' => 'Another account already uses that email.',
        ]);

        $wasSuper = $admin->isSuperAdmin();
        if ($wasSuper && $data['role'] !== Role::SUPER_ADMIN->value && ! $this->anotherActiveSuperAdmin($admin)) {
            return back()->withInput()->with('error', 'The platform must keep at least one active Super-Admin.');
        }

        $admin->fill([
            'name' => trim((string) $data['name']),
            'email' => mb_strtolower(trim((string) $data['email'])),
            'phone' => isset($data['phone']) && trim((string) $data['phone']) !== '' ? trim((string) $data['phone']) : null,
        ]);
        $changed = array_keys($admin->getDirty());
        $passwordReset = isset($data['password']) && $data['password'] !== '';

        DB::transaction(function () use ($admin, $data, $passwordReset, &$changed): void {
            if ($passwordReset) {
                $admin->forceFill(['password' => (string) $data['password'], 'remember_token' => Str::random(60)]);
                $changed[] = 'password';
            }
            $admin->save();
            if (! $admin->hasRole($data['role']) || $admin->roles->count() !== 1) {
                $admin->syncRoles([$data['role']]);
                $changed[] = 'role';
            }
            // A new password or a changed role ends their open sessions so the change applies at once.
            if ($passwordReset || in_array('role', $changed, true) || in_array('email', $changed, true)) {
                DB::table('sessions')->where('user_id', $admin->id)->delete();
                $admin->tokens()->delete();
            }
        });

        if ($changed === []) {
            return back()->with('info', 'Nothing changed.');
        }
        $this->audit->log('admin.admin_updated', $actor, null, ['admin_id' => $admin->id, 'email' => $admin->email, 'fields' => array_values(array_unique($changed)), 'role' => $data['role']], AuditSeverity::CRITICAL);

        return redirect()->route('admin.admins.index')->with('status', "{$admin->name} updated.".($passwordReset ? ' Share the new password with them privately.' : ''));
    }

    public function toggle(Request $request, User $admin): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($admin->isPlatformStaff(), 404);
        if ($actor instanceof User && $actor->id === $admin->id) {
            return back()->with('error', 'You cannot deactivate yourself.');
        }
        if ($admin->is_active && $admin->isSuperAdmin() && ! $this->anotherActiveSuperAdmin($admin)) {
            return back()->with('error', 'The platform must keep at least one active Super-Admin.');
        }
        $admin->update(['is_active' => ! $admin->is_active]);
        if (! $admin->is_active) {
            DB::table('sessions')->where('user_id', $admin->id)->delete();
        }
        $this->audit->log('admin.admin_toggled', $actor instanceof User ? $actor : null, null, ['email' => $admin->email, 'active' => $admin->is_active], AuditSeverity::CRITICAL);

        return back()->with('status', $admin->is_active ? 'Admin reactivated.' : 'Admin deactivated.');
    }

    private function anotherActiveSuperAdmin(User $except): bool
    {
        return User::query()->role(Role::SUPER_ADMIN->value)->where('is_active', true)->whereKeyNot($except->id)->exists();
    }
}
