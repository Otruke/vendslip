<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_plans', function (Blueprint $table): void {
            $table->id();
            $table->string('tier', 32)->unique();
            $table->string('name');
            $table->string('tagline')->nullable();
            $table->char('currency', 3)->default('NGN');
            $table->bigInteger('monthly_price_minor')->default(0);
            $table->bigInteger('yearly_price_minor')->default(0);
            // null = unlimited documents per month
            $table->unsignedInteger('monthly_quota')->nullable();
            $table->jsonb('features');
            $table->jsonb('highlights')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('credit_packs', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->unsignedInteger('credits');
            $table->bigInteger('price_minor');
            $table->char('currency', 3)->default('NGN');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_packs');
        Schema::dropIfExists('subscription_plans');
    }
};
