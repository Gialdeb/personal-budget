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
        Schema::table('transactions', function (Blueprint $table) {
            $table->index('recurring_entry_occurrence_id', 'transactions_recurring_occurrence_index');
            $table->dropUnique('transactions_recurring_occurrence_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->unique('recurring_entry_occurrence_id', 'transactions_recurring_occurrence_unique');
            $table->dropIndex('transactions_recurring_occurrence_index');
        });
    }
};
