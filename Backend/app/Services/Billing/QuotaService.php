<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Exceptions\DomainRuleException;
use App\Models\Merchant;
use App\Services\Settings\PlatformSettings;
use Carbon\CarbonImmutable;

/**
 * Decides how a new slip is paid for:
 *   unlimited plan  -> free, watermark per plan feature
 *   quota plan      -> monthly free quota first (watermarked on Free), then non-expiring PAYG credits
 *   preferCredit    -> vendor explicitly spends 1 credit for a clean slip ("Spend 1 Credit")
 *
 * MUST be called inside a DB transaction with the merchant row locked (lockForUpdate).
 */
final class QuotaService
{
    public function __construct(
        private readonly PlanEntitlements $entitlements,
        private readonly PlatformSettings $settings,
    ) {}

    public function currentPeriod(?CarbonImmutable $now = null): string
    {
        return ($now ?? CarbonImmutable::now(config('netslip.timezone')))->format('Y-m');
    }

    public function freeUsedThisPeriod(Merchant $merchant): int
    {
        return $merchant->free_quota_period === $this->currentPeriod() ? $merchant->free_quota_used : 0;
    }

    public function remainingFree(Merchant $merchant): ?int
    {
        $quota = $this->entitlements->monthlyQuota($merchant);

        return $quota === null ? null : max(0, $quota - $this->freeUsedThisPeriod($merchant));
    }

    public function charge(Merchant $lockedMerchant, bool $preferPaidCredit = false): IssuanceCharge
    {
        if ($lockedMerchant->isSuspended()) {
            throw DomainRuleException::suspended();
        }

        $planRemovesWatermark = $this->entitlements->has($lockedMerchant, PlanEntitlements::REMOVE_WATERMARK);
        $creditRemovesWatermark = (bool) $this->settings->get('billing.remove_watermark_on_paid_credit', true);
        $quota = $this->entitlements->monthlyQuota($lockedMerchant);

        if ($quota === null) {
            return new IssuanceCharge(consumedPaidCredit: false, usedFreeQuota: false, hasWatermark: ! $planRemovesWatermark);
        }

        $period = $this->currentPeriod();
        if ($lockedMerchant->free_quota_period !== $period) {
            $lockedMerchant->free_quota_period = $period;
            $lockedMerchant->free_quota_used = 0;
        }

        $canUseCredit = $lockedMerchant->paid_credits_balance > 0;

        if ($preferPaidCredit && $canUseCredit) {
            return $this->spendCredit($lockedMerchant, $planRemovesWatermark || $creditRemovesWatermark);
        }

        if ($lockedMerchant->free_quota_used < $quota) {
            $lockedMerchant->free_quota_used++;
            $lockedMerchant->save();

            return new IssuanceCharge(consumedPaidCredit: false, usedFreeQuota: true, hasWatermark: ! $planRemovesWatermark);
        }

        if ($canUseCredit) {
            return $this->spendCredit($lockedMerchant, $planRemovesWatermark || $creditRemovesWatermark);
        }

        throw DomainRuleException::quotaExceeded();
    }

    private function spendCredit(Merchant $merchant, bool $clean): IssuanceCharge
    {
        $merchant->paid_credits_balance--;
        $merchant->save();

        return new IssuanceCharge(consumedPaidCredit: true, usedFreeQuota: false, hasWatermark: ! $clean);
    }
}
