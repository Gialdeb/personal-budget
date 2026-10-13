<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('recurring_entry_occurrences')
            ->whereIn('status', ['pending', 'generated'])
            ->where('expected_amount', '>', 0)
            ->whereNull('converted_transaction_id')
            ->whereNull('matched_transaction_id')
            ->whereExists(function ($query): void {
                $query->selectRaw('1')->from('shared_expense_ledger_entries')
                    ->whereColumn('recurring_entry_occurrence_id', 'recurring_entry_occurrences.id')
                    ->whereIn('status', ['recorded', 'posted'])->where('paid_by', 'counterparty')
                    ->whereRaw('total_cents >= ROUND(recurring_entry_occurrences.expected_amount * 100)');
            })->update(['status' => 'completed']);
    }

    /**
     * Reconciled payments must remain settled when rolling back application code.
     */
    public function down(): void {}
};
