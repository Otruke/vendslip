<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\Permission;
use App\Enums\Role;
use App\Exceptions\DomainRuleException;
use App\Models\StaffInvitation;
use App\Models\User;
use App\Notifications\StaffInvitationNotification;
use App\Services\Billing\PlanEntitlements;
use App\Services\Security\AuditLogger;
use App\Support\Urls;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

final class TeamController extends ApiController
{
    private const array INVITABLE = ['Manager', 'SalesAgent', 'Accountant'];

    public function __construct(
        private readonly PlanEntitlements $entitlements,
        private readonly AuditLogger $audit,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $merchant = $this->merchant($request);
        $members = User::query()->where('merchant_id', $merchant->id)->with('roles')->orderBy('created_at')->get();
        $invitations = StaffInvitation::query()->forMerchant($merchant)->whereNull('accepted_at')
            ->where('expires_at', '>', now())->orderByDesc('created_at')->get();

        return response()->json([
            'members' => $members->map(static fn (User $u): array => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'phone' => $u->phone,
                'role' => $u->merchantRole()?->value,
                'role_label' => $u->merchantRole()?->label(),
                'is_active' => $u->is_active,
                'two_factor_enabled' => $u->hasTwoFactorEnabled(),
                'last_login_at' => $u->last_login_at?->toIso8601String(),
            ]),
            'invitations' => $invitations->map(static fn (StaffInvitation $i): array => [
                'id' => $i->id,
                'email' => $i->email,
                'role' => $i->role->value,
                'role_label' => $i->role->label(),
                'expires_at' => $i->expires_at->toIso8601String(),
            ]),
            'seats' => [
                'used' => $members->where('is_active', true)->count() + $invitations->count(),
                'max' => $this->entitlements->maxTeamMembers($merchant),
                'can_invite' => $this->entitlements->has($merchant, PlanEntitlements::MULTI_USER),
            ],
            'roles' => array_map(static fn (string $r): array => ['value' => $r, 'label' => Role::from($r)->label()], self::INVITABLE),
        ]);
    }

    public function invite(Request $request): JsonResponse
    {
        $this->authorizePermission($request, Permission::TEAM_MANAGE);
        $merchant = $this->merchant($request);
        $this->entitlements->ensure($merchant, PlanEntitlements::MULTI_USER);
        $data = $request->validate([
            'email' => ['required', 'email:rfc', 'max:255', Rule::unique('users', 'email')],
            'role' => ['required', Rule::in(self::INVITABLE)],
        ]);
        $email = mb_strtolower($data['email']);

        $max = $this->entitlements->maxTeamMembers($merchant);
        $used = User::query()->where('merchant_id', $merchant->id)->where('is_active', true)->count()
            + StaffInvitation::query()->forMerchant($merchant)->whereNull('accepted_at')->where('expires_at', '>', now())->count();
        if ($max !== null && $used >= $max) {
            throw new DomainRuleException("Your plan allows {$max} team members.", 'seat_limit', 403);
        }

        $token = Str::random(48);
        $invitation = StaffInvitation::query()->updateOrCreate(
            ['merchant_id' => $merchant->id, 'email' => $email, 'accepted_at' => null],
            ['role' => $data['role'], 'token_hash' => hash('sha256', $token), 'invited_by_user_id' => $this->user($request)->id, 'expires_at' => now()->addDays(7)],
        );
        $url = Urls::frontend('accept-invite/'.$token);
        Notification::route('mail', $email)->notify(new StaffInvitationNotification($merchant->name, Role::from($data['role'])->label(), $url));
        $this->audit->log('team.invited', $this->user($request), $merchant, ['email' => $email, 'role' => $data['role']]);

        return response()->json(['id' => $invitation->id, 'invite_url' => $url], 201);
    }

    public function revokeInvitation(Request $request, int $id): JsonResponse
    {
        $this->authorizePermission($request, Permission::TEAM_MANAGE);
        StaffInvitation::query()->forMerchant($this->merchant($request))->whereNull('accepted_at')->findOrFail($id)->delete();

        return response()->json(['ok' => true]);
    }

    public function updateMember(Request $request, int $id): JsonResponse
    {
        $this->authorizePermission($request, Permission::TEAM_MANAGE);
        $member = $this->member($request, $id);
        $data = $request->validate([
            'role' => ['sometimes', Rule::in(self::INVITABLE)],
            'is_active' => ['sometimes', 'boolean'],
        ]);
        if (isset($data['role'])) {
            $member->syncRoles([$data['role']]);
        }
        if (array_key_exists('is_active', $data)) {
            $member->update(['is_active' => (bool) $data['is_active']]);
            if (! $data['is_active']) {
                DB::table('sessions')->where('user_id', $member->id)->delete();
                $member->tokens()->delete();
            }
        }
        $this->audit->log('team.member_updated', $this->user($request), $member->merchant_id, ['member_id' => $member->id, ...$data]);

        return response()->json(['ok' => true]);
    }

    public function removeMember(Request $request, int $id): JsonResponse
    {
        $this->authorizePermission($request, Permission::TEAM_MANAGE);
        $member = $this->member($request, $id);
        $merchantId = $member->merchant_id;
        DB::transaction(static function () use ($member): void {
            DB::table('sessions')->where('user_id', $member->id)->delete();
            $member->tokens()->delete();
            $member->channelIdentities()->delete();
            $member->syncRoles([]);
            // Keep the user row for the audit trail (documents.created_by_user_id), just detach it.
            $member->forceFill(['merchant_id' => null, 'is_active' => false])->save();
        });
        $this->audit->log('team.member_removed', $this->user($request), $merchantId, ['member_id' => $member->id, 'email' => $member->email]);

        return response()->json(['ok' => true]);
    }

    private function member(Request $request, int $id): User
    {
        $actor = $this->user($request);
        $member = User::query()->where('merchant_id', $this->merchant($request)->id)->findOrFail($id);
        if ($member->id === $actor->id) {
            throw new DomainRuleException('You cannot change your own access.', 'self_change', 403);
        }
        if ($member->hasRole(Role::OWNER->value)) {
            throw new DomainRuleException('The owner cannot be changed or removed.', 'owner_locked', 403);
        }

        return $member;
    }
}
