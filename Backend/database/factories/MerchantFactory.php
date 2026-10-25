<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\MerchantStatus;
use App\Enums\MutabilityMode;
use App\Enums\PlanTier;
use App\Models\Merchant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Merchant> */
class MerchantFactory extends Factory
{
    protected $model = Merchant::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $name = fake()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(5)),
            'email' => fake()->companyEmail(),
            'phone' => '+23480'.fake()->numerify('########'),
            'address' => fake()->streetAddress().', Lagos',
            'currency' => 'NGN',
            'vat_rate_bps' => 750,
            'vat_inclusive' => false,
            'plan_tier' => PlanTier::FREE,
            'record_mutability_mode' => MutabilityMode::IMMUTABLE,
            'status' => MerchantStatus::ACTIVE,
            'invoice_due_days' => 7,
        ];
    }

    public function tier(PlanTier $tier): static
    {
        return $this->state(fn (): array => [
            'plan_tier' => $tier,
            'plan_expires_at' => $tier === PlanTier::FREE ? null : now()->addMonth(),
        ]);
    }

    public function mutability(MutabilityMode $mode): static
    {
        return $this->state(fn (): array => ['record_mutability_mode' => $mode]);
    }
}
