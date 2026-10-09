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
        Schema::create('recurring_occurrence_payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('occurrence_id')->constrained('recurring_entry_occurrences')->cascadeOnDelete();
            $table->foreignId('transaction_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('amount_cents');
            $table->unique(['occurrence_id', 'transaction_id'], 'occurrence_payment_unique');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recurring_occurrence_payment_allocations');
    }
};
