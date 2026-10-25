<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merchants', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('email')->nullable();
            $table->string('phone', 32)->nullable();
            $table->string('address', 500)->nullable();
            $table->string('rc_number', 64)->nullable();
            $table->string('tax_id', 64)->nullable();
            $table->char('currency', 3)->default('NGN');
            $table->unsignedInteger('vat_rate_bps')->default(750);
            $table->boolean('vat_inclusive')->default(false);
            $table->string('primary_color', 7)->default('#377581');
            $table->string('secondary_color', 7)->default('#265363');
            $table->string('logo_path')->nullable();
            $table->string('receipt_footer', 280)->nullable();
            $table->string('bank_name', 120)->nullable();
            $table->string('bank_account_number', 32)->nullable();
            $table->string('bank_account_name', 160)->nullable();
            $table->unsignedSmallInteger('invoice_due_days')->default(7);
            $table->foreignId('default_receipt_template_id')->nullable()->constrained('templates')->nullOnDelete();
            $table->foreignId('default_invoice_template_id')->nullable()->constrained('templates')->nullOnDelete();

            $table->string('plan_tier', 32)->default('FREE');
            $table->string('billing_cycle', 16)->nullable();
            $table->timestamp('plan_expires_at')->nullable();
            $table->unsignedInteger('free_quota_used')->default(0);
            $table->char('free_quota_period', 7)->nullable(); // YYYY-MM
            $table->unsignedInteger('paid_credits_balance')->default(0);

            $table->string('record_mutability_mode', 32)->default('IMMUTABLE');
            $table->boolean('allow_staff_edits')->default(false);
            $table->unsignedInteger('mutable_window_hours')->default(0); // 0 = no time limit

            // Merchant-owned gateway credentials (encrypted at rest via Eloquent casts)
            $table->text('flutterwave_public_key')->nullable();
            $table->text('flutterwave_secret_key')->nullable();
            $table->text('flutterwave_secret_hash')->nullable();
            $table->text('nowpayments_api_key')->nullable();
            $table->text('nowpayments_ipn_secret')->nullable();

            $table->string('status', 16)->default('ACTIVE');
            $table->string('suspension_reason', 500)->nullable();
            $table->timestamp('onboarding_completed_at')->nullable();
            $table->timestamps();

            $table->index('plan_tier');
            $table->index('status');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->foreignUuid('merchant_id')->nullable()->after('id')->constrained('merchants')->nullOnDelete();
            $table->string('phone', 32)->nullable()->after('email');
            $table->text('two_factor_secret')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_login_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('merchant_id');
            $table->dropColumn(['phone', 'two_factor_secret', 'two_factor_confirmed_at', 'is_active', 'last_login_at']);
        });
        Schema::dropIfExists('merchants');
    }
};
