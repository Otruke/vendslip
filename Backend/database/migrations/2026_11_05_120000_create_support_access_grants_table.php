<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Super-Admin "enter this business's dashboard" requests. The business owner receives a one-time
 * code by email; only with that code does the Super-Admin get a short, audited support session.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_access_grants', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('merchant_id')->constrained('merchants')->cascadeOnDelete();
            $table->foreignId('admin_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('target_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('recipients');
            $table->string('otp_hash', 64);
            $table->timestamp('otp_expires_at');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('verified_at')->nullable();
            $table->string('handoff_token_hash', 64)->nullable()->unique();
            $table->timestamp('handoff_expires_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->string('end_reason', 32)->nullable();
            $table->timestamps();

            $table->index(['merchant_id', 'admin_user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_access_grants');
    }
};
