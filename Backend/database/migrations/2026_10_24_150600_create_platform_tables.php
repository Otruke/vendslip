<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_transactions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('merchant_id')->nullable()->constrained('merchants')->nullOnDelete();
            $table->foreignUuid('document_id')->nullable()->constrained('documents')->nullOnDelete();
            $table->foreignId('initiated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('purpose', 32);
            $table->string('gateway', 32);
            $table->string('status', 16)->default('PENDING');
            $table->string('reference', 64)->unique();
            $table->string('gateway_reference', 128)->nullable();
            $table->bigInteger('amount_minor');
            $table->char('currency', 3);
            $table->unsignedInteger('credits')->nullable();
            $table->string('plan_tier', 32)->nullable();
            $table->string('billing_cycle', 16)->nullable();
            $table->text('checkout_url')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['merchant_id', 'status']);
            $table->index(['gateway', 'gateway_reference']);
        });

        Schema::create('idempotency_keys', function (Blueprint $table): void {
            $table->id();
            $table->string('scope', 64);
            $table->string('key', 191);
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['scope', 'key']);
        });

        Schema::create('security_audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('merchant_id')->nullable()->constrained('merchants')->nullOnDelete();
            $table->string('event', 96);
            $table->string('severity', 16)->default('INFO');
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->jsonb('context')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['event', 'created_at']);
            $table->index(['merchant_id', 'created_at']);
        });

        Schema::create('platform_settings', function (Blueprint $table): void {
            $table->string('key', 128)->primary();
            $table->text('value')->nullable();
            $table->boolean('is_secret')->default(false);
            $table->timestamps();
        });

        Schema::create('staff_invitations', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('merchant_id')->constrained('merchants')->cascadeOnDelete();
            $table->string('email');
            $table->string('role', 32);
            $table->char('token_hash', 64)->unique();
            $table->foreignId('invited_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();

            $table->index(['merchant_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_invitations');
        Schema::dropIfExists('platform_settings');
        Schema::dropIfExists('security_audit_logs');
        Schema::dropIfExists('idempotency_keys');
        Schema::dropIfExists('payment_transactions');
    }
};
