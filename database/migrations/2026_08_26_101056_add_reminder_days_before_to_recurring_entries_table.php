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
        Schema::table('recurring_entries', function (Blueprint $table) {
            $table->json('reminder_days_before')->nullable()->after('notes');
        });

        Schema::table('recurring_entry_occurrences', function (Blueprint $table) {
            $table->index(
                ['status', 'due_date'],
                'recurring_occurrences_status_due_date_idx',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('recurring_entry_occurrences', function (Blueprint $table) {
            $table->dropIndex('recurring_occurrences_status_due_date_idx');
        });

        Schema::table('recurring_entries', function (Blueprint $table) {
            $table->dropColumn('reminder_days_before');
        });
    }
};
