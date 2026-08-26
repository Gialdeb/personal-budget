<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('credit_debt_items')
            ->whereNotNull('reference_id')
            ->whereNotIn('reference_id', DB::table('tracked_items')->select('id'))
            ->update(['reference_id' => null]);

        Schema::table('credit_debt_items', function (Blueprint $table) {
            $table->foreign('reference_id')
                ->references('id')
                ->on('tracked_items')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('credit_debt_items', function (Blueprint $table) {
            $table->dropForeign(['reference_id']);
        });
    }
};
