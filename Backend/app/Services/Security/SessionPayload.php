<?php

declare(strict_types=1);

namespace App\Services\Security;

use App\Http\Resources\MerchantResource;
use App\Models\User;
use App\Services\Account\AvatarStore;
use App\Services\Billing\PlanEntitlements;
use App\Services\Billing\QuotaService;
use Carbon\CarbonImmutable;

/** The "who am I" payload the SPA bootstraps from (GET /api/v1/auth/me). */
final class SessionPayload
{
    public function __construct(
        private readonly PlanEntitlements $entitlements,
        private readonly QuotaService $quota,
        private readonly AvatarStore $avatars,
    ) {}

    /** @return array<string, mixed> */
    public function for(User $user): array
    {
        $merchant = $user->merchant;

        return [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'avatar_url' => $this->avatars->apiUrl($user),
                'role' => $user->merchantRole()?->value,
                'role_label' => $user->merchantRole()?->label(),
                'permissions' => $user->getAllPermissions()->pluck('name')->values(),
                'two_factor_enabled' => $user->hasTwoFactorEnabled(),
                'is_platform_admin' => $user->isPlatformAdmin(),
                'last_login_at' => $user->last_login_at?->toIso8601String(),
            ],
            'merchant' => $merchant !== null ? (new MerchantResource($merchant))->resolve() : null,
            'support' => $this->support($merchant?->id),
            'entitlements' => $merchant !== null ? $this->entitlements->featureMap($merchant) : [],
            'usage' => $merchant !== null ? [
                'monthly_quota' => $this->entitlements->monthlyQuota($merchant),
                'free_used' => $this->quota->freeUsedThisPeriod($merchant),
                'free_remaining' => $this->quota->remainingFree($merchant),
                'paid_credits' => $merchant->paid_credits_balance,
                'period' => $this->quota->currentPeriod(),
            ] : null,
        ];
    }

    /**
     * Present while a Super-Admin is in this account through a support session.
     *
     * @return array{admin_name: string, expires_at: string, return_url: string}|null
     */
    private function support(?string $merchantId): ?array
    {
        $support = SupportAccess::current(request());
        if ($support === null) {
            return null;
        }

        return [
            'admin_name' => (string) $support['admin_name'],
            'expires_at' => CarbonImmutable::createFromTimestamp((int) $support['expires_at'])->toIso8601String(),
            'return_url' => rtrim((string) config('app.url'), '/').'/admin/merchants/'.($merchantId ?? $support['merchant_id']),
        ];
    }
}
