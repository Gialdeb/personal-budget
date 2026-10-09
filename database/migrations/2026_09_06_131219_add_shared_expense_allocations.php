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
        foreach (['transactions', 'recurring_entries'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->string('shared_expense_paid_by', 20)->nullable()->after('shared_expense_counterparty_id');
                $table->unsignedBigInteger('shared_expense_my_share_cents')->nullable()->after('shared_expense_paid_by');
                $table->unsignedBigInteger('shared_expense_counterparty_share_cents')->nullable()->after('shared_expense_my_share_cents');
            });
        }

        Schema::table('recurring_entry_occurrences', function (Blueprint $table): void {
            $table->string('shared_expense_paid_by', 20)->nullable()->after('expected_amount');
            $table->unsignedBigInteger('shared_expense_my_share_cents')->nullable()->after('shared_expense_paid_by');
            $table->unsignedBigInteger('shared_expense_counterparty_share_cents')->nullable()->after('shared_expense_my_share_cents');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (['transactions', 'recurring_entries'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropColumn([
                    'shared_expense_paid_by',
                    'shared_expense_my_share_cents',
                    'shared_expense_counterparty_share_cents',
                ]);
            });
        }

        Schema::table('recurring_entry_occurrences', function (Blueprint $table): void {
            $table->dropColumn([
                'shared_expense_paid_by',
                'shared_expense_my_share_cents',
                'shared_expense_counterparty_share_cents',
            ]);
        });
    }
};
