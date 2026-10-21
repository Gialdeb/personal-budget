<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recurring_plan_refunds', function (Blueprint $table): void {
            $table->id();
            $table->uuid('request_uuid')->unique();
            $table->uuid('plan_uuid')->index();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('transaction_id')->unique()->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('amount_cents');
            $table->timestamps();
        });
        Schema::create('recurring_plan_refund_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('recurring_plan_refund_id')->constrained()->restrictOnDelete();
            $table->foreignId('transaction_id')->constrained()->restrictOnDelete();
            $table->foreignId('occurrence_id')->constrained('recurring_entry_occurrences')->restrictOnDelete();
            $table->unsignedBigInteger('amount_cents');
            $table->timestamps();
            $table->unique(['recurring_plan_refund_id', 'transaction_id', 'occurrence_id'], 'plan_refund_payment_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recurring_plan_refund_payments');
        Schema::dropIfExists('recurring_plan_refunds');
    }
};
