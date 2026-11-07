<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * In-app notifications (Laravel's standard table) and, per business, who gets which alert by role
 * and whether it is also emailed. Security alerts always reach the owner (enforced in code).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->index(['notifiable_type', 'notifiable_id', 'read_at']);
        });
        Schema::table('merchants', function (Blueprint $table): void {
            $table->jsonb('alert_settings')->nullable()->after('mutable_window_hours');
        });
    }

    public function down(): void
    {
        Schema::table('merchants', fn (Blueprint $table) => $table->dropColumn('alert_settings'));
        Schema::dropIfExists('notifications');
    }
};
