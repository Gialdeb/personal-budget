<?php

namespace App\Services\Transactions;

use App\Models\Account;
use App\Supports\Currency\CurrencySupport;
use App\Supports\Currency\ExchangeRateService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class TransactionExchangeSnapshotService
{
    public function __construct(
        protected ExchangeRateService $exchangeRateService,
        protected CurrencySupport $currencySupport,
    ) {}

    /**
     * @return array{
     *     currency_code: string,
     *     base_currency_code: string,
     *     exchange_rate: string,
     *     exchange_rate_date: string,
     *     converted_base_amount: string,
     *     exchange_rate_source: string
     * }
     */
    public function buildForAccount(Account $account, float $amount, string $transactionDate): array
    {
        $account->loadMissing('user:id,base_currency_code');

        $currencyCode = $this->resolvedAccountCurrencyCode($account);
        $baseCurrencyCode = $this->resolvedBaseCurrencyCode($account, $currencyCode);
        $normalizedTransactionDate = CarbonImmutable::parse($transactionDate)->toDateString();
        $normalizedAmount = round($amount, 2);

        if ($currencyCode === $baseCurrencyCode) {
            return [
                'currency_code' => $currencyCode,
                'base_currency_code' => $baseCurrencyCode,
                'exchange_rate' => '1.00000000',
                'exchange_rate_date' => $normalizedTransactionDate,
                'converted_base_amount' => number_format($normalizedAmount, 2, '.', ''),
                'exchange_rate_source' => 'identity',
            ];
        }

        try {
            $exchangeRate = $this->exchangeRateService->resolve(
                $currencyCode,
                $baseCurrencyCode,
                $normalizedTransactionDate,
            );
        } catch (Throwable $exception) {
            Log::error('Unable to resolve transaction exchange rate snapshot.', [
                'transaction_account_id' => $account->id,
                'transaction_owner_user_id' => $account->user_id,
                'from_currency_code' => $currencyCode,
                'to_currency_code' => $baseCurrencyCode,
                'transaction_date' => $normalizedTransactionDate,
                'amount' => $normalizedAmount,
                'message' => $exception->getMessage(),
            ]);

            throw ValidationException::withMessages([
                'transaction_date' => __('transactions.validation.exchange_rate_unavailable', [
                    'from' => $currencyCode,
                    'to' => $baseCurrencyCode,
                    'date' => $normalizedTransactionDate,
                ]),
            ]);
        }

        return [
            'currency_code' => $currencyCode,
            'base_currency_code' => $baseCurrencyCode,
            'exchange_rate' => $exchangeRate->rate,
            'exchange_rate_date' => $exchangeRate->date->toDateString(),
            'converted_base_amount' => number_format(
                round($normalizedAmount * (float) $exchangeRate->rate, 2),
                2,
                '.',
                '',
            ),
            'exchange_rate_source' => $exchangeRate->source,
        ];
    }

    protected function resolvedAccountCurrencyCode(Account $account): string
    {
        $currencyCode = $this->currencySupport->normalize(
            (string) ($account->currency_code ?: $account->currency),
        );

        return $currencyCode ?: $this->currencySupport->default();
    }

    protected function resolvedBaseCurrencyCode(Account $account, string $fallbackCurrencyCode): string
    {
        $currencyCode = $this->currencySupport->normalize(
            (string) ($account->user?->base_currency_code ?: $fallbackCurrencyCode),
        );

        return $currencyCode ?: $fallbackCurrencyCode;
    }
}
