<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Enums\PlanTier;
use App\Exceptions\DomainRuleException;
use App\Models\Merchant;
use App\Models\SubscriptionPlan;

/**
 * Feature gating driven by subscription_plans.features (editable in /admin/monetization).
 */
final class PlanEntitlements
{
    public const string CUSTOM_BRANDING = 'custom_branding';

    public const string REMOVE_WATERMARK = 'remove_watermark';

    public const string SALES_SUMMARIES = 'sales_summaries';

    public const string EXPORT = 'export';

    public const string MULTI_USER = 'multi_user';

    public const string PAYMENT_LINKS = 'payment_links';

    public const string INVENTORY = 'inventory';

    public const string DEBT_REMINDERS = 'debt_reminders';

    public const array LABELS = [
        self::CUSTOM_BRANDING => 'custom logo & brand colours',
        self::REMOVE_WATERMARK => 'watermark-free slips',
        self::SALES_SUMMARIES => 'automated sales summaries',
        self::EXPORT => 'CSV export',
        self::MULTI_USER => 'team members',
        self::PAYMENT_LINKS => 'payment links',
        self::INVENTORY => 'inventory tracking',
        self::DEBT_REMINDERS => 'debt reminders',
    ];

    /** @var array<string, SubscriptionPlan|null> per-request memo (models are never put in the cache store) */
    private array $plans = [];

    public function plan(PlanTier $tier): ?SubscriptionPlan
    {
        if (! array_key_exists($tier->value, $this->plans)) {
            $this->plans[$tier->value] = SubscriptionPlan::forTier($tier);
        }

        return $this->plans[$tier->value];
    }

    public function forget(): void
    {
        $this->plans = [];
    }

    public function has(Merchant $merchant, string $feature): bool
    {
        return $this->plan($merchant->effectiveTier())?->hasFeature($feature) ?? false;
    }

    public function ensure(Merchant $merchant, string $feature): void
    {
        if (! $this->has($merchant, $feature)) {
            throw DomainRuleException::featureLocked(self::LABELS[$feature] ?? $feature);
        }
    }

    /** @return array<string, bool> */
    public function featureMap(Merchant $merchant): array
    {
        $map = [];
        foreach (array_keys(self::LABELS) as $feature) {
            $map[$feature] = $this->has($merchant, $feature);
        }

        return $map;
    }

    public function monthlyQuota(Merchant $merchant): ?int
    {
        $plan = $this->plan($merchant->effectiveTier());

        return $plan === null ? 15 : $plan->monthly_quota;
    }

    public function maxTeamMembers(Merchant $merchant): ?int
    {
        $value = $this->plan($merchant->effectiveTier())?->features['max_team_members'] ?? null;

        return is_int($value) ? $value : null;
    }
}
