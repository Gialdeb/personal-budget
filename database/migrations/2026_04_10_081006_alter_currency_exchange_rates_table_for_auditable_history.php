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
        Schema::table('currency_exchange_rates', function (Blueprint $table) {
            $table->timestamp('fetched_at')->nullable()->after('source');
            $table->index('rate_date', 'currency_exchange_rates_rate_date_index');
            $table->index(
                ['base_currency_code', 'quote_currency_code'],
                'currency_exchange_rates_pair_index'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('currency_exchange_rates', function (Blueprint $table) {
            $table->dropIndex('currency_exchange_rates_rate_date_index');
            $table->dropIndex('currency_exchange_rates_pair_index');
            $table->dropColumn('fetched_at');
        });
    }
};
