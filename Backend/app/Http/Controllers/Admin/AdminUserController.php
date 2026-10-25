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
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

final class AdminUserController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(): View
    {
        return view('admin.admins.index', [
            'admins' => User::query()->role([Role::SUPER_ADMIN->value, Role::ADMIN->value])->with('roles')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', Rule::in([Role::ADMIN->value, Role::SUPER_ADMIN->value])],
            'password' => ['required', Password::min(12)->letters()->mixedCase()->numbers()],
        ]);
        $admin = User::query()->create(['name' => $data['name'], 'email' => mb_strtolower($data['email']), 'password' => $data['password'], 'is_active' => true]);
        $admin->forceFill(['email_verified_at' => now()])->save();
        $admin->assignRole($data['role']);
        $actor = $request->user();
        $this->audit->log('admin.admin_created', $actor instanceof User ? $actor : null, null, ['email' => $admin->email, 'role' => $data['role']], AuditSeverity::CRITICAL);

        return back()->with('status', "{$admin->name} can now sign in. Ask them to enable 2FA.");
    }

    public function toggle(Request $request, User $admin): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($admin->isPlatformAdmin(), 404);
        if ($actor instanceof User && $actor->id === $admin->id) {
            return back()->with('error', 'You cannot deactivate yourself.');
        }
        $admin->update(['is_active' => ! $admin->is_active]);
        if (! $admin->is_active) {
            DB::table('sessions')->where('user_id', $admin->id)->delete();
        }
        $this->audit->log('admin.admin_toggled', $actor instanceof User ? $actor : null, null, ['email' => $admin->email, 'active' => $admin->is_active], AuditSeverity::CRITICAL);

        return back()->with('status', $admin->is_active ? 'Admin reactivated.' : 'Admin deactivated.');
    }
}
