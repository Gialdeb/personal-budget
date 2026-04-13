<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\ExchangeRate;
use App\Supports\Currency\CurrencySupport;
use App\Supports\Currency\ExchangeRateSourceResolver;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ExchangeRateController extends Controller
{
    public function __construct(
        protected CurrencySupport $currencySupport,
        protected ExchangeRateSourceResolver $exchangeRateSourceResolver,
    ) {}

    public function edit(Request $request): Response
    {
        $validated = $request->validate([
            'rate_date' => ['nullable', 'date'],
            'base_currency_code' => ['nullable', 'string', 'size:3'],
            'quote_currency_code' => ['nullable', 'string', 'size:3'],
        ]);

        $baseCurrencyCode = $this->currencySupport->normalize((string) ($validated['base_currency_code'] ?? ''));
        $quoteCurrencyCode = $this->currencySupport->normalize((string) ($validated['quote_currency_code'] ?? ''));
        $rateDate = filled($validated['rate_date'] ?? null)
            ? (string) $validated['rate_date']
            : null;

        $exchangeRates = ExchangeRate::query()
            ->when($rateDate !== null, fn ($query) => $query->whereDate('rate_date', $rateDate))
            ->when($baseCurrencyCode !== null, fn ($query) => $query->where('base_currency_code', $baseCurrencyCode))
            ->when($quoteCurrencyCode !== null, fn ($query) => $query->where('quote_currency_code', $quoteCurrencyCode))
            ->orderByDesc('rate_date')
            ->orderBy('base_currency_code')
            ->orderBy('quote_currency_code')
            ->paginate(25)
            ->withQueryString()
            ->through(function (ExchangeRate $exchangeRate): array {
                $source = $this->exchangeRateSourceResolver->resolve((string) $exchangeRate->source);

                return [
                    'id' => $exchangeRate->id,
                    'rate_date' => $exchangeRate->rate_date?->toDateString(),
                    'base_currency_code' => $exchangeRate->base_currency_code,
                    'quote_currency_code' => $exchangeRate->quote_currency_code,
                    'rate' => (string) $exchangeRate->rate,
                    'source' => [
                        'key' => (string) $exchangeRate->source,
                        'label' => $source['label'],
                        'url' => $source['url'],
                    ],
                    'fetched_at' => $exchangeRate->fetched_at?->toIso8601String(),
                ];
            });

        return Inertia::render('settings/ExchangeRates', [
            'filters' => [
                'rate_date' => $rateDate,
                'base_currency_code' => $baseCurrencyCode,
                'quote_currency_code' => $quoteCurrencyCode,
            ],
            'exchange_rates' => $exchangeRates,
            'options' => [
                'currencies' => collect($this->currencySupport->options())
                    ->map(fn (array $currency): array => [
                        'code' => $currency['code'],
                        'label' => sprintf(
                            '%s — %s (%s)',
                            $currency['code'],
                            $currency['name'],
                            $currency['symbol'],
                        ),
                    ])
                    ->values()
                    ->all(),
            ],
        ]);
    }
}
