<?php

declare(strict_types=1);

use App\Support\ShortCode;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A short public code per slip: the one link a business sends its customer
 * (FRONTEND_URL/s/{code}) to view, download and, for invoices, pay. Existing slips get one now.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table): void {
            $table->string('short_code', 16)->nullable()->unique();
        });

        DB::table('documents')->whereNull('short_code')->orderBy('id')->select('id')->chunkById(500, function ($rows): void {
            foreach ($rows as $row) {
                do {
                    $code = ShortCode::make();
                } while (DB::table('documents')->where('short_code', $code)->exists());
                DB::table('documents')->where('id', $row->id)->update(['short_code' => $code]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table): void {
            $table->dropUnique(['short_code']);
            $table->dropColumn('short_code');
        });
    }
};
