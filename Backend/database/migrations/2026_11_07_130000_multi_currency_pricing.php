<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Multi-currency pricing: plans and credit packs keep a base price (USD recommended) plus optional
 * fixed prices per currency; every other currency is converted with cached exchange rates.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fx_rates', function (Blueprint $table): void {
            $table->char('currency', 3)->primary();
            $table->decimal('per_usd', 24, 10); // units of this currency for 1 USD
            $table->string('source', 40);
            $table->timestamp('fetched_at');
        });
        Schema::table('subscription_plans', function (Blueprint $table): void {
            // {"NGN": {"monthly": 500000, "yearly": 4800000}, …} in minor units
            $table->jsonb('price_overrides')->nullable()->after('yearly_price_minor');
        });
        Schema::table('credit_packs', function (Blueprint $table): void {
            // {"NGN": 100000, …} in minor units
            $table->jsonb('price_overrides')->nullable()->after('price_minor');
        });
    }

    public function down(): void
    {
        Schema::table('credit_packs', fn (Blueprint $table) => $table->dropColumn('price_overrides'));
        Schema::table('subscription_plans', fn (Blueprint $table) => $table->dropColumn('price_overrides'));
        Schema::dropIfExists('fx_rates');
    }
};
