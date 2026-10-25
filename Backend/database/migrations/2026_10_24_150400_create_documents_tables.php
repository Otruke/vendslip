<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Gapless per-merchant numbering: rows are locked with SELECT ... FOR UPDATE on issuance.
        Schema::create('document_sequences', function (Blueprint $table): void {
            $table->foreignUuid('merchant_id')->constrained('merchants')->cascadeOnDelete();
            $table->string('doc_type', 16);
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('last_number')->default(0);
            $table->primary(['merchant_id', 'doc_type', 'year']);
        });

        Schema::create('documents', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('merchant_id')->constrained('merchants')->cascadeOnDelete();
            $table->foreignUuid('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('template_id')->nullable()->constrained('templates')->nullOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->uuid('parent_document_id')->nullable();
            $table->string('doc_type', 16);
            $table->string('doc_number', 32);
            $table->string('public_uid', 32)->unique();
            $table->string('status', 16);
            $table->string('channel', 16)->default('WEB');
            $table->char('currency', 3);

            // Customer snapshot at time of issue (customer record may change later)
            $table->string('customer_name');
            $table->string('customer_phone', 32)->nullable();
            $table->string('customer_email')->nullable();
            $table->string('customer_address', 500)->nullable();

            // All money is stored as integer minor units (kobo / cents)
            $table->bigInteger('subtotal_minor');
            $table->bigInteger('discount_minor')->default(0);
            $table->unsignedInteger('vat_rate_bps')->default(0);
            $table->boolean('vat_inclusive')->default(false);
            $table->bigInteger('tax_minor')->default(0);
            $table->bigInteger('shipping_minor')->default(0);
            $table->bigInteger('total_minor');
            $table->bigInteger('amount_paid_minor')->default(0);
            $table->bigInteger('balance_due_minor')->default(0);

            $table->boolean('consumed_paid_credit')->default(false);
            $table->boolean('has_watermark')->default(false);
            $table->unsignedInteger('revision_number')->default(1);
            $table->char('verification_hash', 64)->unique();
            $table->string('verification_code', 20)->unique();
            $table->string('pdf_storage_path')->nullable();
            $table->text('notes')->nullable();
            $table->date('due_date')->nullable();
            $table->timestamp('issued_at');
            $table->timestamp('voided_at')->nullable();
            $table->string('void_reason', 500)->nullable();
            $table->timestamps();

            $table->unique(['merchant_id', 'doc_number']);
            $table->index(['merchant_id', 'doc_type', 'status']);
            $table->index(['merchant_id', 'issued_at']);
        });

        Schema::table('documents', function (Blueprint $table): void {
            $table->foreign('parent_document_id')->references('id')->on('documents')->nullOnDelete();
        });

        DB::statement('ALTER TABLE documents ADD CONSTRAINT documents_money_non_negative CHECK (subtotal_minor >= 0 AND discount_minor >= 0 AND tax_minor >= 0 AND shipping_minor >= 0 AND total_minor >= 0 AND amount_paid_minor >= 0 AND balance_due_minor >= 0)');

        Schema::create('document_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('document_id')->constrained('documents')->cascadeOnDelete();
            $table->foreignUuid('sku_id')->nullable()->constrained('inventory_skus')->nullOnDelete();
            $table->string('description', 500);
            $table->unsignedInteger('quantity');
            $table->bigInteger('unit_price_minor');
            $table->bigInteger('line_total_minor');
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('document_revisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('document_id')->constrained('documents')->cascadeOnDelete();
            $table->unsignedInteger('revision_number');
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 32)->default('EDIT'); // EDIT | VOID | CREDIT_NOTE | PAYMENT
            $table->text('edit_reason')->nullable();
            $table->jsonb('before_snapshot_json');
            $table->jsonb('after_snapshot_json');
            $table->char('previous_verification_hash', 64)->nullable();
            $table->string('actor_ip', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['document_id', 'revision_number']);
            $table->index('previous_verification_hash');
        });

        Schema::create('document_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('document_id')->constrained('documents')->cascadeOnDelete();
            $table->bigInteger('amount_minor');
            $table->string('method', 32);
            $table->string('reference', 120)->nullable();
            $table->timestamp('paid_at');
            $table->foreignId('recorded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->uuid('payment_transaction_id')->nullable();
            $table->foreignUuid('receipt_document_id')->nullable()->constrained('documents')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_payments');
        Schema::dropIfExists('document_revisions');
        Schema::dropIfExists('document_items');
        Schema::dropIfExists('documents');
        Schema::dropIfExists('document_sequences');
    }
};
