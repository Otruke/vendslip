<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('merchant_id')->constrained('merchants')->cascadeOnDelete();
            $table->string('name');
            $table->string('phone', 32)->nullable();
            $table->string('email')->nullable();
            $table->string('address', 500)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['merchant_id', 'phone']);
            $table->index(['merchant_id', 'name']);
        });

        Schema::create('inventory_skus', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('merchant_id')->constrained('merchants')->cascadeOnDelete();
            $table->string('sku', 64);
            $table->string('name');
            $table->string('description', 500)->nullable();
            $table->bigInteger('unit_price_minor')->default(0);
            $table->integer('stock_quantity')->default(0);
            $table->unsignedInteger('low_stock_threshold')->default(5);
            $table->boolean('track_stock')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['merchant_id', 'sku']);
            $table->index(['merchant_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_skus');
        Schema::dropIfExists('customers');
    }
};
