<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained('accounts')->cascadeOnDelete();

            $table->foreignId('import_id')->nullable()->constrained('imports')->nullOnDelete();
            $table->foreignId('import_row_id')->nullable()->constrained('import_rows')->nullOnDelete();

            $table->foreignId('scope_id')->nullable()->constrained('scopes')->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->foreignId('merchant_id')->nullable()->constrained('merchants')->nullOnDelete();

            $table->date('transaction_date');
            $table->date('value_date')->nullable();
            $table->timestamp('posted_at')->nullable();

            $table->string('direction', 20);
            $table->decimal('amount', 14, 2);
            $table->string('currency', 3)->default('EUR');

            $table->text('description')->nullable();
            $table->text('bank_description_raw')->nullable();
            $table->text('bank_description_clean')->nullable();
            $table->string('bank_operation_type', 100)->nullable();
            $table->string('counterparty_name', 255)->nullable();
            $table->string('reference_code', 255)->nullable();

            $table->decimal('balance_after', 14, 2)->nullable();

            $table->string('source_type', 20);
            $table->string('status', 30);

            $table->unsignedBigInteger('matched_rule_id')->nullable();
            $table->unsignedBigInteger('matched_sample_id')->nullable();

            $table->string('match_strategy', 50)->nullable();
            $table->decimal('confidence_score', 5, 2)->nullable();

            $table->string('external_hash', 255)->nullable();
            $table->string('reconciliation_key', 255)->nullable();

            $table->boolean('is_transfer')->default(false);
            $table->foreignId('related_transaction_id')->nullable()->constrained('transactions')->nullOnDelete();

            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['account_id', 'transaction_date']);
            $table->index(['user_id', 'transaction_date']);
            $table->index('category_id');
            $table->index('merchant_id');
            $table->index('status');
            $table->index('source_type');
            $table->index('direction');
            $table->index('reference_code');
            $table->index('external_hash');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
