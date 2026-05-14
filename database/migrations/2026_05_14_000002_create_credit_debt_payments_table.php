<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_debt_payments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('credit_debt_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 14, 2);
            $table->string('currency_code', 3);
            $table->date('paid_at');
            $table->text('note')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id']);
            $table->index(['credit_debt_item_id']);
            $table->index(['transaction_id']);
            $table->index(['account_id']);
            $table->index(['paid_at']);
            $table->index(['user_id', 'credit_debt_item_id']);
            $table->index(['credit_debt_item_id', 'paid_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_debt_payments');
    }
};
