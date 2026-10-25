<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Data\DocumentDraftData;
use App\Data\DocumentItemData;
use App\Enums\Channel;
use App\Enums\DocumentType;
use App\Enums\MutabilityMode;
use App\Enums\PaymentMethod;
use App\Enums\PlanTier;
use App\Enums\Role;
use App\Models\InventorySku;
use App\Models\Merchant;
use App\Models\User;
use App\Services\Documents\DocumentIssuanceService;
use App\Services\Documents\DocumentLifecycleService;
use Illuminate\Database\Seeder;

/** Local demo data: owner@aminaluxury.test / password */
class DemoMerchantSeeder extends Seeder
{
    public function run(DocumentIssuanceService $issuance, DocumentLifecycleService $lifecycle): void
    {
        if (Merchant::query()->where('slug', 'amina-luxury-couture')->exists()) {
            return;
        }

        $merchant = Merchant::query()->create([
            'name' => 'Amina Luxury Couture',
            'slug' => 'amina-luxury-couture',
            'email' => 'hello@aminaluxury.test',
            'phone' => '+2348031234567',
            'address' => '14 Adeola Odeku St, Victoria Island, Lagos',
            'rc_number' => '2048102',
            'currency' => 'NGN',
            'vat_rate_bps' => 750,
            'bank_name' => 'Providus Bank',
            'bank_account_number' => '0192837465',
            'bank_account_name' => 'Amina Luxury Couture',
            'plan_tier' => PlanTier::BUSINESS_PLUS,
            'plan_expires_at' => now()->addYear(),
            'paid_credits_balance' => 50,
            'record_mutability_mode' => MutabilityMode::MUTABLE_WITH_AUDIT,
            'mutable_window_hours' => 72,
            'onboarding_completed_at' => now(),
        ]);

        $owner = User::query()->create([
            'merchant_id' => $merchant->id, 'name' => 'Amina Lawal', 'email' => 'owner@aminaluxury.test',
            'phone' => '+2348031234567', 'password' => 'password', 'is_active' => true,
        ]);
        $owner->email_verified_at = now();
        $owner->save();
        $owner->assignRole(Role::OWNER->value);

        $agent = User::query()->create([
            'merchant_id' => $merchant->id, 'name' => 'Tobi Sales', 'email' => 'sales@aminaluxury.test',
            'password' => 'password', 'is_active' => true,
        ]);
        $agent->assignRole(Role::SALES_AGENT->value);

        $sneakers = InventorySku::query()->create([
            'merchant_id' => $merchant->id, 'sku' => 'SKU-SNK-02', 'name' => 'Leather Minimalist Sneakers',
            'unit_price_minor' => 2850000, 'stock_quantity' => 24, 'low_stock_threshold' => 5,
        ]);
        InventorySku::query()->create([
            'merchant_id' => $merchant->id, 'sku' => 'SKU-BLT-01', 'name' => 'Hand-stitched Leather Belt',
            'unit_price_minor' => 1200000, 'stock_quantity' => 4, 'low_stock_threshold' => 5,
        ]);
        InventorySku::query()->create([
            'merchant_id' => $merchant->id, 'sku' => 'SKU-ADR-07', 'name' => 'Adire Silk Kaftan',
            'unit_price_minor' => 4500000, 'stock_quantity' => 11,
        ]);

        $customers = [
            ['Chief Emeka Nwosu', '+2348039981122', '42 Wuse II Crescent, Abuja'],
            ['Funke Adebayo', '+2348025550199', '7 Allen Avenue, Ikeja'],
            ['Kelechi Obi', '+2347061234455', '3 Trans-Amadi Rd, Port Harcourt'],
            ['Zainab Bello', '+2348134446677', '19 Ahmadu Bello Way, Kaduna'],
        ];
        $channels = [Channel::WHATSAPP, Channel::TELEGRAM, Channel::INSTAGRAM, Channel::WEB];

        foreach ($customers as $i => [$name, $phone, $address]) {
            $issuance->issue($merchant, $owner, DocumentDraftData::from([
                'type' => DocumentType::RECEIPT,
                'customer_name' => $name,
                'customer_phone' => $phone,
                'customer_address' => $address,
                'items' => [DocumentItemData::from(['description' => $sneakers->name, 'quantity' => 1 + ($i % 2), 'unit_price_minor' => $sneakers->unit_price_minor, 'sku_id' => $sneakers->id])],
            ]), $channels[$i]);
        }

        $invoice = $issuance->issue($merchant, $owner, DocumentDraftData::from([
            'type' => DocumentType::INVOICE,
            'customer_name' => 'Chief Emeka Nwosu',
            'customer_phone' => '+2348039981122',
            'customer_address' => '42 Wuse II Crescent, Abuja',
            'items' => [
                DocumentItemData::from(['description' => 'Adire Silk Kaftan', 'quantity' => 2, 'unit_price_minor' => 4500000]),
                DocumentItemData::from(['description' => 'Tailoring & fitting', 'quantity' => 1, 'unit_price_minor' => 1500000]),
            ],
            'shipping_minor' => 350000,
            'notes' => 'Deliver via Abuja express courier.',
        ]), Channel::TELEGRAM);
        $lifecycle->recordPayment($invoice, 2000000, PaymentMethod::BANK_TRANSFER, 'MONIE-88421', $owner);

        $issuance->issue($merchant, $owner, DocumentDraftData::from([
            'type' => DocumentType::INVOICE,
            'customer_name' => 'Zainab Bello',
            'customer_phone' => '+2348134446677',
            'items' => [DocumentItemData::from(['description' => 'Bridal Aso-Oke Set', 'quantity' => 1, 'unit_price_minor' => 18500000])],
            'due_date' => now()->subDays(3)->toDateString(),
        ]), Channel::WHATSAPP);
    }
}
