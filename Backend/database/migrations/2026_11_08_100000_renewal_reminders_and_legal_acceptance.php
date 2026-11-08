<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * - Plan renewal reminders: a log (so each reminder goes once per paid term) and an optional
 *   per-merchant timing that overrides the platform default.
 * - Legal: a record of who accepted which version of the Terms and Privacy Policy, when, where
 *   from and in what context (sign-up, invitation, update, checkout). Kept as evidence of consent.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('merchants', function (Blueprint $table): void {
            // e.g. "14,7,1,0,-3": days before (positive) or after (negative) the plan's end date.
            $table->string('renewal_reminder_days', 60)->nullable()->after('plan_expires_at');
        });
        Schema::create('plan_renewal_reminders', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('merchant_id')->constrained()->cascadeOnDelete();
            $table->timestamp('term_ends_at'); // the paid-until date this reminder belongs to
            $table->smallInteger('offset_days');
            $table->timestamp('sent_at');
            $table->unique(['merchant_id', 'term_ends_at', 'offset_days']);
        });
        Schema::create('legal_acceptances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('merchant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('document', 20); // TERMS | PRIVACY
            $table->string('version', 20);
            $table->string('context', 20); // SIGNUP | INVITATION | CHECKOUT | UPDATE
            $table->string('reference', 64)->nullable(); // e.g. the payment reference at checkout
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamp('accepted_at');
            $table->index(['user_id', 'document', 'version']);
            $table->index(['merchant_id', 'accepted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legal_acceptances');
        Schema::dropIfExists('plan_renewal_reminders');
        Schema::table('merchants', fn (Blueprint $table) => $table->dropColumn('renewal_reminder_days'));
    }
};
