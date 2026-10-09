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
            $table->foreignId('shared_expense_counterparty_id')->nullable()->after('tracked_item_id')->constrained('tracked_items')->nullOnDelete();
            $table->index(['user_id', 'shared_expense_counterparty_id', 'transaction_date']);
        });
        Schema::table('recurring_entries', function (Blueprint $table) {
            $table->foreignId('shared_expense_counterparty_id')->nullable()->after('tracked_item_id')->constrained('tracked_items')->nullOnDelete();
            $table->index(['user_id', 'shared_expense_counterparty_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'shared_expense_counterparty_id', 'transaction_date']);
            $table->dropConstrainedForeignId('shared_expense_counterparty_id');
        });
        Schema::table('recurring_entries', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'shared_expense_counterparty_id']);
            $table->dropConstrainedForeignId('shared_expense_counterparty_id');
        });
    }
};
