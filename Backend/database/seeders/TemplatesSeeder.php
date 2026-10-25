<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\PlanTier;
use App\Models\Template;
use Illuminate\Database\Seeder;

class TemplatesSeeder extends Seeder
{
    public function run(): void
    {
        Template::query()->updateOrCreate(['slug' => Template::CLASSIC_EXECUTIVE], [
            'name' => 'NetSlip Classic Executive',
            'description' => 'A4 slip with a deep brand header, itemised table and QR proof.',
            'engine' => Template::ENGINE_BLADE,
            'blade_view' => 'pdf.templates.classic-executive',
            'supports_receipt' => true,
            'supports_invoice' => true,
            'min_plan_tier' => PlanTier::FREE,
            'is_active' => true,
            'is_default' => true,
            'sort_order' => 1,
        ]);

        Template::query()->updateOrCreate(['slug' => Template::MINIMALIST_SOCIAL], [
            'name' => 'NetSlip Minimalist Social Slip',
            'description' => 'Compact A5 slip that reads beautifully on a phone screen.',
            'engine' => Template::ENGINE_BLADE,
            'blade_view' => 'pdf.templates.minimalist-social',
            'supports_receipt' => true,
            'supports_invoice' => true,
            'min_plan_tier' => PlanTier::FREE,
            'is_active' => true,
            'is_default' => false,
            'sort_order' => 2,
        ]);
    }
}
