<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * - Per-line list price (the normal price) next to the selling price, so discounts given on a single
 *   item are traceable; the slip keeps the total of those line discounts.
 * - How a business is registered (RC = company, BN = business name, or not registered) plus where it
 *   is, for VAT presets and anonymous "a vendor in Lagos just…" activity.
 * - Inventory: blocking oversells becomes its own switch, separate from tracking stock.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_items', function (Blueprint $table): void {
            $table->bigInteger('list_price_minor')->nullable()->after('unit_price_minor');
        });
        Schema::table('documents', function (Blueprint $table): void {
            $table->bigInteger('line_discount_minor')->default(0)->after('discount_minor');
        });
        Schema::table('inventory_skus', function (Blueprint $table): void {
            $table->boolean('block_overselling')->default(true)->after('track_stock');
        });
        Schema::table('merchants', function (Blueprint $table): void {
            $table->string('registration_type', 16)->nullable()->after('rc_number');
            $table->char('country_code', 2)->nullable()->after('address');
            $table->string('state', 80)->nullable()->after('country_code');
            $table->string('city', 80)->nullable()->after('state');
        });
        // Existing RC numbers keep meaning "company registration".
        DB::table('merchants')->whereNotNull('rc_number')->update(['registration_type' => 'RC']);
    }

    public function down(): void
    {
        Schema::table('merchants', fn (Blueprint $table) => $table->dropColumn(['registration_type', 'country_code', 'state', 'city']));
        Schema::table('inventory_skus', fn (Blueprint $table) => $table->dropColumn('block_overselling'));
        Schema::table('documents', fn (Blueprint $table) => $table->dropColumn('line_discount_minor'));
        Schema::table('document_items', fn (Blueprint $table) => $table->dropColumn('list_price_minor'));
    }
};
