<?php

namespace App\Supports\Currency;

use App\Enums\UserStatusEnum;
use App\Models\Account;
use App\Models\RecurringEntry;
use App\Models\Transaction;
use App\Models\User;

class CurrencySupport
{
    public function default(): string
    {
        return (string) config('currencies.default', 'EUR');
    }

    public function supported(): array
    {
        return (array) config('currencies.supported', []);
    }

    public function codes(): array
    {
        return array_keys($this->supported());
    }

    public function isSupported(string $code): bool
    {
        return array_key_exists(strtoupper($code), $this->supported());
    }

    public function normalize(string $code): ?string
    {
        $code = strtoupper(trim($code));

        return $this->isSupported($code) ? $code : null;
    }

    public function options(): array
    {
        return array_values($this->supported());
    }

    public function syncBaseCurrencyCode(): string
    {
        return $this->normalize((string) config('currencies.sync.base_currency_code', $this->default()))
            ?? $this->default();
    }

    /**
     * @return array<int, string>
     */
    public function syncCoreCurrencyCodes(): array
    {
        return $this->normalizeCurrencyCodes([
            $this->default(),
            $this->syncBaseCurrencyCode(),
            ...(array) config('currencies.sync.core_currency_codes', []),
            ...(array) config('currencies.sync.quote_currency_codes', []),
        ]);
    }

    public function syncQuoteCurrencyCodes(?string $baseCurrencyCode = null): array
    {
        $baseCurrencyCode = $this->normalize($baseCurrencyCode ?? $this->syncBaseCurrencyCode())
            ?? $this->default();

        return collect($this->syncCoreCurrencyCodes())
            ->reject(fn (string $code): bool => $code === $baseCurrencyCode)
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public function relevantSyncBaseCurrencyCodes(): array
    {
        return $this->normalizeCurrencyCodes([
            $this->default(),
            $this->syncBaseCurrencyCode(),
            ...User::query()
                ->where('status', UserStatusEnum::ACTIVE->value)
                ->whereNotNull('base_currency_code')
                ->pluck('base_currency_code')
                ->all(),
            ...Transaction::query()
                ->whereNotNull('base_currency_code')
                ->pluck('base_currency_code')
                ->all(),
        ]);
    }

    /**
     * @return array<int, string>
     */
    public function relevantSyncCurrencyCodes(): array
    {
        return $this->normalizeCurrencyCodes([
            ...$this->syncCoreCurrencyCodes(),
            ...$this->relevantSyncBaseCurrencyCodes(),
            ...Account::query()
                ->whereNotNull('currency_code')
                ->pluck('currency_code')
                ->all(),
            ...Transaction::query()
                ->whereNotNull('currency_code')
                ->pluck('currency_code')
                ->all(),
            ...RecurringEntry::query()
                ->whereNotNull('currency')
                ->pluck('currency')
                ->all(),
        ]);
    }

    /**
     * @return array<int, string>
     */
    public function relevantSyncQuoteCurrencyCodes(?string $baseCurrencyCode = null): array
    {
        $baseCurrencyCode = $baseCurrencyCode !== null
            ? $this->normalize($baseCurrencyCode)
            : null;

        return collect($this->relevantSyncCurrencyCodes())
            ->reject(fn (string $code): bool => $baseCurrencyCode !== null && $code === $baseCurrencyCode)
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{base_currency_code: string, quote_currency_codes: array<int, string>}>
     */
    public function relevantSyncPlans(): array
    {
        $quoteCurrencyCodes = $this->relevantSyncCurrencyCodes();

        return collect($this->relevantSyncBaseCurrencyCodes())
            ->map(fn (string $baseCurrencyCode): array => [
                'base_currency_code' => $baseCurrencyCode,
                'quote_currency_codes' => collect($quoteCurrencyCodes)
                    ->reject(fn (string $quoteCurrencyCode): bool => $quoteCurrencyCode === $baseCurrencyCode)
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();
    }

    public function for(string $code): ?array
    {
        $code = strtoupper(trim($code));

        return $this->supported()[$code] ?? null;
    }

    /**
     * @param  iterable<int, mixed>  $codes
     * @return array<int, string>
     */
    protected function normalizeCurrencyCodes(iterable $codes): array
    {
        return collect($codes)
            ->map(fn (mixed $code): ?string => is_string($code) ? $this->normalize($code) : null)
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();
    }
}
