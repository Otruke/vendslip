<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Several bank accounts and crypto wallets per business, printed on slips. The merchant's old single
 * bank fields stay as a mirror of the default bank account so existing templates keep working.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payout_accounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('merchant_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 10); // BANK | CRYPTO
            $table->string('label', 60)->nullable();
            $table->string('bank_name', 120)->nullable();
            $table->string('account_number', 34)->nullable();
            $table->string('account_name', 160)->nullable();
            $table->char('currency', 3)->nullable();
            $table->string('asset', 10)->nullable();
            $table->string('network', 16)->nullable();
            $table->string('wallet_address', 128)->nullable();
            $table->boolean('show_on_slips')->default(true);
            $table->boolean('is_default')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['merchant_id', 'sort_order']);
        });

        $now = now();
        DB::table('merchants')->whereNotNull('bank_account_number')->orderBy('id')
            ->select(['id', 'bank_name', 'bank_account_number', 'bank_account_name', 'name'])
            ->each(function (object $m) use ($now): void {
                DB::table('payout_accounts')->insert([
                    'merchant_id' => $m->id, 'kind' => 'BANK', 'bank_name' => $m->bank_name,
                    'account_number' => $m->bank_account_number, 'account_name' => $m->bank_account_name ?: $m->name,
                    'show_on_slips' => true, 'is_default' => true, 'sort_order' => 0, 'created_at' => $now, 'updated_at' => $now,
                ]);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('payout_accounts');
    }
};
