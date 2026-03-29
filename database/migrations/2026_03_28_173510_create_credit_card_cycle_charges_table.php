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
        Schema::create('credit_card_cycle_charges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('credit_card_account_id')->constrained('accounts')->cascadeOnDelete();
            $table->foreignId('linked_payment_account_id')->constrained('accounts')->cascadeOnDelete();
            $table->foreignId('payment_transaction_id')->nullable()->constrained('transactions')->nullOnDelete();
            $table->foreignId('card_settlement_transaction_id')->nullable()->constrained('transactions')->nullOnDelete();
            $table->date('cycle_start_date');
            $table->date('cycle_end_date');
            $table->date('payment_due_date');
            $table->unsignedTinyInteger('statement_closing_day');
            $table->unsignedTinyInteger('payment_day');
            $table->decimal('balance_at_cycle_end', 14, 2)->default(0);
            $table->decimal('charged_amount', 14, 2)->default(0);
            $table->timestamp('processed_at');
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['credit_card_account_id', 'cycle_end_date'], 'credit_card_cycle_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('credit_card_cycle_charges');
    }
};
