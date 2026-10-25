<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('templates', function (Blueprint $table): void {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('description', 500)->nullable();
            // 'blade' = shipped view (resources/views/pdf/templates/*), 'html' = admin-authored tokenised HTML
            $table->string('engine', 16)->default('blade');
            $table->string('blade_view')->nullable();
            $table->longText('html')->nullable();
            $table->boolean('supports_receipt')->default(true);
            $table->boolean('supports_invoice')->default(true);
            $table->string('min_plan_tier', 32)->default('FREE');
            $table->jsonb('default_tokens_json')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('templates');
    }
};
