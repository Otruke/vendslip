<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Businesses can keep their (anonymous, city-level) activity out of the website's live feed. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('merchants', function (Blueprint $table): void {
            $table->boolean('show_in_activity_feed')->default(true)->after('city');
        });
    }

    public function down(): void
    {
        Schema::table('merchants', fn (Blueprint $table) => $table->dropColumn('show_in_activity_feed'));
    }
};
