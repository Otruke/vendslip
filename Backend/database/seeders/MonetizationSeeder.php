<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\PlanTier;
use App\Models\CreditPack;
use App\Models\SubscriptionPlan;
use Illuminate\Database\Seeder;

class MonetizationSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'tier' => PlanTier::FREE, 'name' => 'Free', 'tagline' => 'For new sellers testing the waters.',
                'monthly_price_minor' => 0, 'yearly_price_minor' => 0, 'monthly_quota' => 15,
                'features' => [
                    'custom_branding' => false, 'remove_watermark' => false, 'sales_summaries' => false, 'export' => false,
                    'multi_user' => false, 'payment_links' => false, 'inventory' => false, 'debt_reminders' => false,
                    'max_team_members' => 1,
                ],
                'highlights' => ['15 slips every month', 'Standard templates', 'WhatsApp & Telegram bots', 'QR verification', '"Powered by NetSlip" watermark'],
                'sort_order' => 1,
            ],
            [
                'tier' => PlanTier::PRO, 'name' => 'Pro Merchant', 'tagline' => 'For busy shops that sell every day.',
                'monthly_price_minor' => 250000, 'yearly_price_minor' => 2400000, 'monthly_quota' => null,
                'features' => [
                    'custom_branding' => true, 'remove_watermark' => true, 'sales_summaries' => true, 'export' => true,
                    'multi_user' => false, 'payment_links' => false, 'inventory' => false, 'debt_reminders' => false,
                    'max_team_members' => 1,
                ],
                'highlights' => ['Unlimited receipts & invoices', 'Your logo & brand colours', 'No watermark', 'Daily sales summaries in chat', 'CSV export'],
                'sort_order' => 2,
            ],
            [
                'tier' => PlanTier::BUSINESS_PLUS, 'name' => 'Business Plus', 'tagline' => 'For teams with stock and staff.',
                'monthly_price_minor' => 600000, 'yearly_price_minor' => 6000000, 'monthly_quota' => null,
                'features' => [
                    'custom_branding' => true, 'remove_watermark' => true, 'sales_summaries' => true, 'export' => true,
                    'multi_user' => true, 'payment_links' => true, 'inventory' => true, 'debt_reminders' => true,
                    'max_team_members' => 10,
                ],
                'highlights' => ['Everything in Pro', 'Up to 10 staff with roles', 'Flutterwave & crypto pay links', 'SKU inventory tracking', 'Automated debt reminders'],
                'sort_order' => 3,
            ],
        ];

        foreach ($plans as $plan) {
            SubscriptionPlan::query()->updateOrCreate(['tier' => $plan['tier']->value], [...$plan, 'currency' => 'NGN', 'is_active' => true]);
        }

        CreditPack::query()->updateOrCreate(['name' => 'Starter Pack'], [
            'credits' => 50, 'price_minor' => 100000, 'currency' => 'NGN', 'is_active' => true, 'sort_order' => 1,
        ]);
    }
}
