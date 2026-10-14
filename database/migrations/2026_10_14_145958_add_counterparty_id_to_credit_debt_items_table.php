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
        Schema::table('credit_debt_items', function (Blueprint $table) {
            $table->foreignId('counterparty_id')->nullable()->constrained('tracked_items')->restrictOnDelete();
            $table->index(['user_id', 'counterparty_id', 'currency_code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('credit_debt_items', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'counterparty_id', 'currency_code']);
            $table->dropConstrainedForeignId('counterparty_id');
        });
    }
};
