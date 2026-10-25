<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BillingCycle;
use App\Enums\PlanTier;
use Illuminate\Database\Eloquent\Model;

/**
 * Feature flags stored in `features`:
 *  - custom_branding, remove_watermark, sales_summaries, export, multi_user,
 *    payment_links, inventory, debt_reminders (all bool), max_team_members (int|null)
 *
 * @property int $id
 * @property PlanTier $tier
 * @property string $name
 * @property string|null $tagline
 * @property string $currency
 * @property int $monthly_price_minor
 * @property int $yearly_price_minor
 * @property int|null $monthly_quota
 * @property array<string, bool|int|null> $features
 * @property list<string>|null $highlights
 * @property bool $is_active
 * @property int $sort_order
 */
class SubscriptionPlan extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'tier', 'name', 'tagline', 'currency', 'monthly_price_minor', 'yearly_price_minor', 'monthly_quota',
        'features', 'highlights', 'is_active', 'sort_order',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'tier' => PlanTier::class,
            'monthly_price_minor' => 'integer',
            'yearly_price_minor' => 'integer',
            'monthly_quota' => 'integer',
            'features' => 'array',
            'highlights' => 'array',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function priceFor(BillingCycle $cycle): int
    {
        return $cycle === BillingCycle::YEARLY ? $this->yearly_price_minor : $this->monthly_price_minor;
    }

    public function hasFeature(string $feature): bool
    {
        return (bool) ($this->features[$feature] ?? false);
    }

    public static function forTier(PlanTier $tier): ?self
    {
        return self::query()->where('tier', $tier->value)->first();
    }
}
