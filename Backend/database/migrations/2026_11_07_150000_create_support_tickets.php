<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Support tickets from businesses, answered by VendSlip support agents in the admin portal. QA
 * reviewers read everything, leave internal notes and score the support given.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_tickets', function (Blueprint $table): void {
            $table->id();
            $table->string('reference', 16)->unique();
            $table->foreignUuid('merchant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('opened_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('subject', 160);
            $table->string('category', 20);
            $table->string('priority', 10)->default('NORMAL');
            $table->string('status', 20)->default('OPEN');
            $table->foreignId('assigned_to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('last_activity_at');
            $table->timestamp('first_response_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->unsignedTinyInteger('qa_score')->nullable();
            $table->foreignId('qa_reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('qa_reviewed_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'last_activity_at']);
            $table->index(['merchant_id', 'last_activity_at']);
            $table->index(['assigned_to_user_id', 'status']);
        });
        Schema::create('support_ticket_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ticket_id')->constrained('support_tickets')->cascadeOnDelete();
            $table->foreignId('author_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('author_kind', 10); // CUSTOMER | AGENT | SYSTEM
            $table->boolean('is_internal')->default(false); // staff-only notes, never shown to the business
            $table->text('body');
            $table->timestamp('created_at');
            $table->index(['ticket_id', 'id']);
        });

        // The two new platform roles, so `php artisan migrate` is all an existing install needs.
        foreach (['SupportAgent', 'QAReviewer'] as $name) {
            Role::findOrCreate($name, 'web');
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('support_ticket_messages');
        Schema::dropIfExists('support_tickets');
    }
};
