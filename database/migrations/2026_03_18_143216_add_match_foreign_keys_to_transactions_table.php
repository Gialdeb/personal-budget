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
            $table->foreign('matched_rule_id')
                ->references('id')
                ->on('transaction_matchers')
                ->nullOnDelete();

            $table->foreign('matched_sample_id')
                ->references('id')
                ->on('transaction_training_samples')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign('matched_rule_id');
            $table->dropForeign('matched_sample_id');
        });
    }
};
