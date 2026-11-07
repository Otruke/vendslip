<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Due-date reminders: what each business wants, and a log so no reminder is ever sent twice. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('merchants', function (Blueprint $table): void {
            $table->jsonb('reminder_settings')->nullable()->after('alert_settings');
        });
        Schema::create('invoice_reminders', function (Blueprint $table): void {
            $table->id();
            $table->uuid('document_id');
            $table->foreign('document_id')->references('id')->on('documents')->cascadeOnDelete();
            $table->string('stage', 20); // BEFORE_DUE | DUE_TODAY | OVERDUE_1 … OVERDUE_n
            $table->string('recipient', 255);
            $table->timestamp('sent_at');
            $table->unique(['document_id', 'stage']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_reminders');
        Schema::table('merchants', fn (Blueprint $table) => $table->dropColumn('reminder_settings'));
    }
};
