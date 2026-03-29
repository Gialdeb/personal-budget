<?php

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('currency exchange rates migration creates the expected foundation schema', function () {
    expect(Schema::hasTable('currency_exchange_rates'))->toBeTrue()
        ->and(Schema::hasColumns('currency_exchange_rates', [
            'id',
            'base_currency_code',
            'quote_currency_code',
            'rate',
            'rate_date',
            'source',
            'created_at',
            'updated_at',
        ]))->toBeTrue();
});

test('currency exchange rates migration prevents duplicate rates for the same pair and day', function () {
    DB::table('currency_exchange_rates')->insert([
        'base_currency_code' => 'EUR',
        'quote_currency_code' => 'USD',
        'rate' => 1.08450000,
        'rate_date' => '2026-03-27',
        'source' => 'manual',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(fn () => DB::table('currency_exchange_rates')->insert([
        'base_currency_code' => 'EUR',
        'quote_currency_code' => 'USD',
        'rate' => 1.08450000,
        'rate_date' => '2026-03-27',
        'source' => 'manual',
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});
