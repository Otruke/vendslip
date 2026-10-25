<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Maps a chat-platform sender (WhatsApp number, Telegram user id, IG/FB scoped id) to a NetSlip user.
        Schema::create('channel_identities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('merchant_id')->constrained('merchants')->cascadeOnDelete();
            $table->string('channel', 16);
            $table->string('external_user_id', 191);
            $table->string('display_name')->nullable();
            $table->timestamp('linked_at');
            $table->timestamps();

            $table->unique(['channel', 'external_user_id']);
        });

        Schema::create('channel_link_codes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('merchant_id')->constrained('merchants')->cascadeOnDelete();
            $table->string('code', 16)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
        });

        Schema::create('bot_draft_sessions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('merchant_id')->constrained('merchants')->cascadeOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('channel', 16);
            $table->string('external_chat_id', 191);
            $table->string('external_user_id', 191);
            $table->string('status', 32)->default('COLLECTING');
            $table->jsonb('draft_payload_json');
            $table->string('ephemeral_message_id', 64)->nullable();
            $table->string('confirmation_message_id', 64)->nullable();
            $table->unsignedInteger('saved_outbound_calls_count')->default(0);
            $table->foreignUuid('document_id')->nullable()->constrained('documents')->nullOnDelete();
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index(['channel', 'external_chat_id', 'external_user_id', 'status'], 'bot_draft_sessions_lookup_idx');
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bot_draft_sessions');
        Schema::dropIfExists('channel_link_codes');
        Schema::dropIfExists('channel_identities');
    }
};
