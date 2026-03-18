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
        Schema::create('recurring_entry_occurrences', function (Blueprint $table) {
            $table->id();

            $table->foreignId('recurring_entry_id')->constrained('recurring_entries')->cascadeOnDelete();

            $table->date('expected_date');
            $table->date('due_date')->nullable();
            $table->decimal('expected_amount', 14, 2)->nullable();

            $table->string('status', 20);

            $table->foreignId('matched_transaction_id')->nullable()->constrained('transactions')->nullOnDelete();
            $table->foreignId('converted_transaction_id')->nullable()->constrained('transactions')->nullOnDelete();

            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['recurring_entry_id', 'expected_date']);
            $table->index(['status', 'expected_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recurring_entry_occurrences');
    }
};
