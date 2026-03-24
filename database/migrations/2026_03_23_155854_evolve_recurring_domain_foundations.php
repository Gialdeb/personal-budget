<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recurring_entries', function (Blueprint $table) {
            $table->string('entry_type', 20)->default('recurring')->after('currency');
            $table->string('status', 20)->default('active')->after('entry_type');
            $table->string('end_mode', 30)->nullable()->after('end_date');
            $table->unsignedInteger('occurrences_limit')->nullable()->after('end_mode');
            $table->decimal('total_amount', 14, 2)->nullable()->after('expected_amount');
            $table->unsignedInteger('installments_count')->nullable()->after('total_amount');
            $table->date('next_occurrence_date')->nullable()->after('end_date');

            $table->index(['entry_type', 'status']);
            $table->index(['status', 'next_occurrence_date']);
        });

        DB::table('recurring_entries')->update([
            'entry_type' => DB::raw("coalesce(entry_type, 'recurring')"),
            'status' => DB::raw("case when is_active is false then coalesce(status, 'paused') else coalesce(status, 'active') end"),
            'end_mode' => DB::raw("case when end_date is not null then 'until_date' else 'never' end"),
            'next_occurrence_date' => DB::raw('coalesce(next_occurrence_date, start_date)'),
        ]);

        Schema::table('recurring_entry_occurrences', function (Blueprint $table) {
            $table->unsignedInteger('sequence_number')->nullable()->after('recurring_entry_id');
            $table->index('recurring_entry_id');
        });

        $occurrences = DB::table('recurring_entry_occurrences')
            ->select(['id', 'recurring_entry_id'])
            ->orderBy('recurring_entry_id')
            ->orderBy('expected_date')
            ->orderBy('id')
            ->get();

        $sequenceCounters = [];

        foreach ($occurrences as $occurrence) {
            $entryId = (int) $occurrence->recurring_entry_id;
            $sequenceCounters[$entryId] = ($sequenceCounters[$entryId] ?? 0) + 1;

            DB::table('recurring_entry_occurrences')
                ->where('id', $occurrence->id)
                ->update(['sequence_number' => $sequenceCounters[$entryId]]);
        }

        DB::table('recurring_entry_occurrences')
            ->whereIn('status', ['planned', 'due'])
            ->update(['status' => 'pending']);

        DB::table('recurring_entry_occurrences')
            ->where('status', 'matched')
            ->update(['status' => 'generated']);

        DB::table('recurring_entry_occurrences')
            ->where('status', 'converted')
            ->update(['status' => 'completed']);

        Schema::table('recurring_entry_occurrences', function (Blueprint $table) {
            $table->unsignedInteger('sequence_number')->default(1)->nullable(false)->change();
            $table->unique(['recurring_entry_id', 'sequence_number'], 'reo_entry_sequence_unique');
            $table->unique('converted_transaction_id', 'reo_converted_transaction_unique');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('recurring_entry_occurrence_id')
                ->nullable()
                ->after('related_transaction_id')
                ->constrained('recurring_entry_occurrences')
                ->nullOnDelete();

            $table->foreignId('refunded_transaction_id')
                ->nullable()
                ->after('recurring_entry_occurrence_id')
                ->constrained('transactions')
                ->nullOnDelete();

            $table->unique('recurring_entry_occurrence_id', 'transactions_recurring_occurrence_unique');
            $table->unique('refunded_transaction_id', 'transactions_refunded_transaction_unique');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropUnique('transactions_recurring_occurrence_unique');
            $table->dropUnique('transactions_refunded_transaction_unique');
            $table->dropConstrainedForeignId('recurring_entry_occurrence_id');
            $table->dropConstrainedForeignId('refunded_transaction_id');
        });

        Schema::table('recurring_entry_occurrences', function (Blueprint $table) {
            $table->dropUnique('reo_entry_sequence_unique');
            $table->dropUnique('reo_converted_transaction_unique');
            $table->dropIndex(['recurring_entry_id']);
            $table->dropColumn('sequence_number');
        });

        Schema::table('recurring_entries', function (Blueprint $table) {
            $table->dropIndex(['entry_type', 'status']);
            $table->dropIndex(['status', 'next_occurrence_date']);
            $table->dropColumn([
                'entry_type',
                'status',
                'end_mode',
                'occurrences_limit',
                'total_amount',
                'installments_count',
                'next_occurrence_date',
            ]);
        });
    }
};
