<?php

declare(strict_types=1);

namespace Tests;

use App\Enums\MutabilityMode;
use App\Enums\PlanTier;
use App\Enums\Role;
use App\Models\Merchant;
use App\Models\User;
use Database\Seeders\MonetizationSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\TemplatesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Storage;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');
        $this->seed([RolesAndPermissionsSeeder::class, TemplatesSeeder::class, MonetizationSeeder::class]);
    }

    /** @return array{0: Merchant, 1: User} */
    protected function merchantWithOwner(
        PlanTier $tier = PlanTier::FREE,
        MutabilityMode $mode = MutabilityMode::IMMUTABLE,
        int $credits = 0,
        Role $role = Role::OWNER,
    ): array {
        $merchant = Merchant::factory()->tier($tier)->mutability($mode)->create(['paid_credits_balance' => $credits]);
        $user = User::factory()->create(['merchant_id' => $merchant->id]);
        $user->assignRole($role->value);

        return [$merchant, $user];
    }

    /** @return array<string, mixed> */
    protected function receiptPayload(array $overrides = []): array
    {
        return array_replace([
            'type' => 'RECEIPT',
            'customer_name' => 'Ada Obi',
            'customer_phone' => '+2348031112222',
            'items' => [
                ['description' => 'Leather Sneakers', 'quantity' => 2, 'unit_price_minor' => 1500000],
            ],
        ], $overrides);
    }
}
