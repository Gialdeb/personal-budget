<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tracked_items', function (Blueprint $table): void {
            $table->boolean('is_shared_expense_counterpart')
                ->default(false)
                ->after('is_active');
        });

        DB::table('tracked_items')
            ->where('type', 'shared_expense_counterparty')
            ->update([
                'is_shared_expense_counterpart' => true,
                'type' => null,
            ]);
    }

    public function down(): void
    {
        DB::table('tracked_items')
            ->where('is_shared_expense_counterpart', true)
            ->whereNull('type')
            ->update(['type' => 'shared_expense_counterparty']);

        Schema::table('tracked_items', function (Blueprint $table): void {
            $table->dropColumn('is_shared_expense_counterpart');
        });
    }
};
