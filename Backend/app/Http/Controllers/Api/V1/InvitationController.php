<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\StaffInvitation;
use App\Models\User;
use App\Services\Security\AuditLogger;
use App\Services\Security\SessionPayload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

final class InvitationController extends Controller
{
    public function __construct(
        private readonly SessionPayload $session,
        private readonly AuditLogger $audit,
    ) {}

    public function show(string $token): JsonResponse
    {
        $invitation = $this->find($token);

        return response()->json([
            'email' => $invitation->email,
            'role' => $invitation->role->value,
            'role_label' => $invitation->role->label(),
            'merchant_name' => $invitation->merchant->name,
        ]);
    }

    public function accept(Request $request, string $token): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = DB::transaction(function () use ($token, $data): User {
            $invitation = StaffInvitation::query()->where('token_hash', hash('sha256', $token))->lockForUpdate()->first();
            if ($invitation === null || ! $invitation->isPending()) {
                throw ValidationException::withMessages(['token' => 'This invitation is invalid or has expired.']);
            }
            if (User::query()->where('email', $invitation->email)->exists()) {
                throw ValidationException::withMessages(['email' => 'An account with this email already exists.']);
            }
            $user = User::query()->create([
                'merchant_id' => $invitation->merchant_id,
                'name' => $data['name'],
                'email' => $invitation->email,
                'password' => $data['password'],
                'is_active' => true,
            ]);
            $user->forceFill(['email_verified_at' => now()])->save();
            $user->assignRole($invitation->role->value);
            $invitation->update(['accepted_at' => now()]);

            return $user;
        });

        Auth::guard('web')->login($user);
        $request->session()->regenerate();
        $this->audit->log('team.invitation_accepted', $user);

        return response()->json($this->session->for($user), 201);
    }

    private function find(string $token): StaffInvitation
    {
        $invitation = StaffInvitation::query()->where('token_hash', hash('sha256', $token))->first();
        abort_if($invitation === null || ! $invitation->isPending(), 404, 'This invitation is invalid or has expired.');

        return $invitation;
    }
}
