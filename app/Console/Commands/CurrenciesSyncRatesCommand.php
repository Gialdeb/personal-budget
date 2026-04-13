<?php

namespace App\Console\Commands;

use App\Supports\Currency\CurrencySupport;
use App\Supports\Currency\ExchangeRateService;
use App\Supports\Currency\ExchangeRateSyncTelegramAlertService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

#[Signature('currencies:sync-rates {--date=} {--base=} {--currencies=}')]
#[Description('Sync and persist daily exchange rates for the configured currency catalog')]
class CurrenciesSyncRatesCommand extends Command
{
    public function __construct(
        private readonly ExchangeRateService $exchangeRateService,
        private readonly CurrencySupport $currencySupport,
        private readonly ExchangeRateSyncTelegramAlertService $telegramAlertService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $rateDate = CarbonImmutable::parse(
            (string) ($this->option('date') ?: now()->toDateString()),
        );
        $syncPlans = $this->resolveSyncPlans();

        if ($syncPlans === null) {
            return self::FAILURE;
        }

        $this->newLine();
        $this->info(sprintf(
            'Currency sync for %s completed.',
            $rateDate->toDateString(),
        ));

        if ($syncPlans === []) {
            $this->warn('No relevant currency pairs were found to sync.');

            return self::SUCCESS;
        }

        $results = [];
        $failedAlerts = [];

        foreach ($syncPlans as $syncPlan) {
            $baseCurrencyCode = $syncPlan['base_currency_code'];
            $quoteCurrencyCodes = $syncPlan['quote_currency_codes'];

            if ($quoteCurrencyCodes === []) {
                Log::info('Currency sync skipped because no quote currencies were relevant for the base currency.', [
                    'command' => 'currencies:sync-rates',
                    'rate_date' => $rateDate->toDateString(),
                    'base_currency_code' => $baseCurrencyCode,
                ]);

                continue;
            }

            try {
                $baseResults = $this->exchangeRateService->sync(
                    $baseCurrencyCode,
                    $quoteCurrencyCodes,
                    $rateDate,
                );
            } catch (Throwable $exception) {
                Log::error('Currency sync command crashed for a base currency.', [
                    'command' => 'currencies:sync-rates',
                    'rate_date' => $rateDate->toDateString(),
                    'base_currency_code' => $baseCurrencyCode,
                    'message' => $exception->getMessage(),
                ]);

                $baseResults = collect($quoteCurrencyCodes)
                    ->map(fn (string $quoteCurrencyCode): array => [
                        'quote_currency_code' => $quoteCurrencyCode,
                        'status' => 'failed',
                        'source' => null,
                        'resolved_from' => null,
                        'rate' => null,
                        'error' => $exception->getMessage(),
                    ])
                    ->all();
            }

            $results = [
                ...$results,
                ...collect($baseResults)
                    ->map(fn (array $result): array => [
                        'base_currency_code' => $baseCurrencyCode,
                        ...$result,
                    ])
                    ->all(),
            ];

            if (collect($baseResults)->contains(fn (array $result): bool => $result['status'] === 'failed')) {
                $failedAlerts[] = [
                    'base_currency_code' => $baseCurrencyCode,
                    'results' => $baseResults,
                ];
            }
        }

        $this->table(
            ['Base', 'Quote', 'Status', 'Rate', 'Source', 'Resolved from', 'Fallback used'],
            collect($results)
                ->map(fn (array $result): array => [
                    $result['base_currency_code'],
                    $result['quote_currency_code'],
                    $result['status'],
                    $result['rate'] ?? '-',
                    $result['source'] ?? '-',
                    $result['resolved_from'] ?? '-',
                    ($result['source'] ?? null) === 'fawaz' ? 'yes' : 'no',
                ])
                ->all(),
        );

        foreach ($results as $result) {
            if ($result['status'] === 'failed') {
                Log::warning('Currency sync rate failed.', [
                    'command' => 'currencies:sync-rates',
                    'rate_date' => $rateDate->toDateString(),
                    'base_currency_code' => $baseCurrencyCode,
                    'quote_currency_code' => $result['quote_currency_code'],
                    'error' => $result['error'] ?? 'unknown error',
                    'source' => $result['source'],
                    'resolved_from' => $result['resolved_from'],
                ]);

                $this->warn(sprintf(
                    'Failed syncing %s/%s: %s',
                    $baseCurrencyCode,
                    $result['quote_currency_code'],
                    $result['error'] ?? 'unknown error',
                ));
            }
        }

        $hasFailures = collect($results)->contains(fn (array $result): bool => $result['status'] === 'failed');

        if ($hasFailures) {
            foreach ($failedAlerts as $failedAlert) {
                $this->telegramAlertService->sendFailureAlert(
                    'currencies:sync-rates',
                    $rateDate,
                    $failedAlert['base_currency_code'],
                    $failedAlert['results'],
                );
            }
        } else {
            Log::info('Currency sync completed successfully.', [
                'command' => 'currencies:sync-rates',
                'rate_date' => $rateDate->toDateString(),
                'base_currency_codes' => collect($syncPlans)->pluck('base_currency_code')->values()->all(),
                'quotes_count' => count($results),
                'sources' => collect($results)->pluck('source')->filter()->unique()->values()->all(),
            ]);
        }

        return $hasFailures ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return array<int, array{base_currency_code: string, quote_currency_codes: array<int, string>}>
     */
    protected function resolveSyncPlans(): ?array
    {
        $explicitBaseCurrencyCode = $this->option('base');
        $explicitQuoteCurrencyCodes = $this->option('currencies');

        if (is_string($explicitBaseCurrencyCode) && trim($explicitBaseCurrencyCode) !== '') {
            $baseCurrencyCode = $this->currencySupport->normalize($explicitBaseCurrencyCode);

            if ($baseCurrencyCode === null) {
                $this->error('Invalid base currency code.');

                return null;
            }

            $quoteCurrencyCodes = is_string($explicitQuoteCurrencyCodes) && trim($explicitQuoteCurrencyCodes) !== ''
                ? array_map('trim', explode(',', $explicitQuoteCurrencyCodes))
                : $this->currencySupport->relevantSyncQuoteCurrencyCodes($baseCurrencyCode);

            return [[
                'base_currency_code' => $baseCurrencyCode,
                'quote_currency_codes' => collect($quoteCurrencyCodes)
                    ->map(fn (mixed $code): ?string => is_string($code) ? $this->currencySupport->normalize($code) : null)
                    ->filter()
                    ->reject(fn (string $quoteCurrencyCode): bool => $quoteCurrencyCode === $baseCurrencyCode)
                    ->unique()
                    ->values()
                    ->all(),
            ]];
        }

        return $this->currencySupport->relevantSyncPlans();
    }
}
