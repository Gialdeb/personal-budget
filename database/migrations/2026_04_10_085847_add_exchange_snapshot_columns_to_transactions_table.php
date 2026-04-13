<?php

use Carbon\CarbonImmutable;
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
        Schema::table('transactions', function (Blueprint $table) {
            $table->string('currency_code', 3)->nullable()->after('currency');
            $table->string('base_currency_code', 3)->nullable()->after('currency_code');
            $table->decimal('exchange_rate', 18, 8)->nullable()->after('base_currency_code');
            $table->date('exchange_rate_date')->nullable()->after('exchange_rate');
            $table->decimal('converted_base_amount', 14, 2)->nullable()->after('exchange_rate_date');
            $table->string('exchange_rate_source', 50)->nullable()->after('converted_base_amount');

            $table->index(
                ['currency_code', 'base_currency_code', 'exchange_rate_date'],
                'transactions_exchange_snapshot_lookup_index',
            );
        });

        DB::table('transactions')
            ->leftJoin('accounts', 'accounts.id', '=', 'transactions.account_id')
            ->leftJoin('users', 'users.id', '=', 'transactions.user_id')
            ->select([
                'transactions.id as transaction_id',
                'transactions.amount',
                'transactions.transaction_date',
                'transactions.currency',
                'accounts.currency_code as account_currency_code',
                'accounts.currency as account_currency',
                'users.base_currency_code as user_base_currency_code',
            ])
            ->orderBy('transactions.id')
            ->chunkById(200, function ($transactions): void {
                foreach ($transactions as $transaction) {
                    $currencyCode = strtoupper((string) (
                        $transaction->account_currency_code
                        ?: $transaction->account_currency
                        ?: $transaction->currency
                        ?: $transaction->user_base_currency_code
                        ?: config('currencies.default', 'EUR')
                    ));
                    $baseCurrencyCode = strtoupper((string) (
                        $transaction->user_base_currency_code
                        ?: $currencyCode
                    ));
                    $exchangeRateDate = $transaction->transaction_date !== null
                        ? CarbonImmutable::parse($transaction->transaction_date)->toDateString()
                        : null;
                    $isIdentitySnapshot = $currencyCode === $baseCurrencyCode;

                    DB::table('transactions')
                        ->where('id', $transaction->transaction_id)
                        ->update([
                            'currency_code' => $currencyCode,
                            'base_currency_code' => $baseCurrencyCode,
                            'exchange_rate' => $isIdentitySnapshot ? '1.00000000' : null,
                            'exchange_rate_date' => $exchangeRateDate,
                            'converted_base_amount' => $isIdentitySnapshot ? $transaction->amount : null,
                            'exchange_rate_source' => $isIdentitySnapshot ? 'legacy_identity' : null,
                        ]);
                }
            }, 'transactions.id', 'transaction_id');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex('transactions_exchange_snapshot_lookup_index');
            $table->dropColumn([
                'currency_code',
                'base_currency_code',
                'exchange_rate',
                'exchange_rate_date',
                'converted_base_amount',
                'exchange_rate_source',
            ]);
        });
    }
};
