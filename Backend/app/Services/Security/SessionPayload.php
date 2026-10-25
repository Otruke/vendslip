<?php

declare(strict_types=1);

namespace App\Services\Security;

use App\Http\Resources\MerchantResource;
use App\Models\User;
use App\Services\Billing\PlanEntitlements;
use App\Services\Billing\QuotaService;

/** The "who am I" payload the SPA bootstraps from (GET /api/v1/auth/me). */
final class SessionPayload
{
    public function __construct(
        private readonly PlanEntitlements $entitlements,
        private readonly QuotaService $quota,
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
                'role' => $user->merchantRole()?->value,
                'role_label' => $user->merchantRole()?->label(),
                'permissions' => $user->getAllPermissions()->pluck('name')->values(),
                'two_factor_enabled' => $user->hasTwoFactorEnabled(),
                'is_platform_admin' => $user->isPlatformAdmin(),
            ],
            'merchant' => $merchant !== null ? (new MerchantResource($merchant))->resolve() : null,
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
}
