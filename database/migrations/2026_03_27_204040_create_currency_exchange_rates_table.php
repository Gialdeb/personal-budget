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
        Schema::create('currency_exchange_rates', function (Blueprint $table) {
            $table->id();
            $table->string('base_currency_code', 3);
            $table->string('quote_currency_code', 3);
            $table->decimal('rate', 18, 8);
            $table->date('rate_date');
            $table->string('source')->nullable();
            $table->timestamps();

            $table->unique([
                'base_currency_code',
                'quote_currency_code',
                'rate_date',
            ], 'currency_exchange_rates_unique_pair_per_day');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('currency_exchange_rates');
    }
};
