<?php

namespace App\Services\Reports;

use App\Enums\TransactionDirectionEnum;
use App\Enums\TransactionKindEnum;
use App\Enums\TransactionStatusEnum;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Accounts\AccessibleAccountsQuery;
use App\Services\Transactions\OperationalLedgerAnalyticsService;
use App\Services\UserYearService;
use App\Supports\PeriodOptions;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use NumberFormatter;

class PeriodOverviewReportService
{
    protected const PERIOD_ANNUAL = 'annual';

    protected const PERIOD_MONTHLY = 'monthly';

    protected const PERIOD_LAST_THREE_MONTHS = 'last_3_months';

    protected const PERIOD_LAST_SIX_MONTHS = 'last_6_months';

    protected const PERIOD_YTD = 'ytd';

    public function __construct(
        protected AccessibleAccountsQuery $accessibleAccountsQuery,
        protected UserYearService $userYearService,
        protected OperationalLedgerAnalyticsService $operationalLedgerAnalyticsService,
    ) {}

    /**
     * @param  array{year?: int|null, month?: int|null, period?: string|null, account_uuid?: string|null}  $input
     * @return array<string, mixed>
     */
    public function build(User $user, int $defaultYear, ?int $defaultMonth, array $input = []): array
    {
        $availableYears = $this->availableYears($user, $defaultYear);
        $selectedYear = $this->normalizeYear($input['year'] ?? null, $availableYears, $defaultYear);
        $selectedPeriod = $this->normalizePeriod($input['period'] ?? null);
        $referenceMonth = $this->normalizeReferenceMonth(
            $input['month'] ?? null,
            $defaultMonth,
            $selectedYear,
        );

        $accountOptions = $this->accessibleAccountsQuery->dashboardFilterOptions($user);
        $selectedAccountUuid = $this->normalizeAccountUuid(
            $input['account_uuid'] ?? null,
            $accountOptions,
        );

        $baseCurrency = strtoupper(trim((string) ($user->base_currency_code ?: 'EUR')));
        $periodDefinition = $this->buildPeriodDefinition(
            $selectedYear,
            $selectedPeriod,
            $referenceMonth,
        );
        $previousPeriodDefinition = $this->previousPeriodDefinition($periodDefinition);

        $bucketMap = $this->makeBucketMap($periodDefinition);
        $transactions = $this->operationalLedgerAnalyticsService->transactionsForPeriod(
            $user,
            $periodDefinition['start'],
            $periodDefinition['end'],
            $selectedAccountUuid,
        );
        $previousTransactions = $this->operationalLedgerAnalyticsService->transactionsForPeriod(
            $user,
            $previousPeriodDefinition['start'],
            $previousPeriodDefinition['end'],
            $selectedAccountUuid,
        );

        $resolvedTransactionsCount = 0;
        $unresolvedTransactionsCount = 0;

        foreach ($transactions as $transaction) {
            $bucketKey = $this->bucketKeyForDate(
                CarbonImmutable::parse(
                    $transaction->transaction_date,
                    config('app.timezone'),
                ),
                $periodDefinition['granularity'],
            );

            if (! isset($bucketMap[$bucketKey])) {
                continue;
            }

            $resolvedAmount = $this->operationalLedgerAnalyticsService->resolveAggregateAmountForTransaction(
                $transaction,
                $baseCurrency,
            );

            if ($resolvedAmount === null) {
                $unresolvedTransactionsCount++;

                continue;
            }

            $resolvedTransactionsCount++;

            if ($transaction->direction === TransactionDirectionEnum::INCOME) {
                $bucketMap[$bucketKey]['income_total_raw'] += $resolvedAmount;
                $bucketMap[$bucketKey]['net_total_raw'] += $resolvedAmount;
            }

            if ($transaction->direction === TransactionDirectionEnum::EXPENSE) {
                $bucketMap[$bucketKey]['expense_total_raw'] += $resolvedAmount;
                $bucketMap[$bucketKey]['net_total_raw'] -= $resolvedAmount;
            }
        }

        $previousTotals = $this->aggregatePeriodTotals(
            $previousTransactions,
            $baseCurrency,
        );

        $buckets = collect($bucketMap)
            ->map(fn (array $bucket): array => $this->formatBucket($bucket, $baseCurrency))
            ->values();

        $incomeTotalRaw = round((float) $buckets->sum('income_total_raw'), 2);
        $expenseTotalRaw = round((float) $buckets->sum('expense_total_raw'), 2);
        $netTotalRaw = round((float) $buckets->sum('net_total_raw'), 2);
        $averageNetRaw = $buckets->count() > 0
            ? round($netTotalRaw / $buckets->count(), 2)
            : 0.0;

        /** @var array<string, mixed>|null $bestBucket */
        $bestBucket = $buckets->sortByDesc('net_total_raw')->first();
        /** @var array<string, mixed>|null $worstBucket */
        $worstBucket = $buckets->sortBy('net_total_raw')->first();

        return [
            'currency' => $baseCurrency,
            'meta' => [
                'period_label' => $this->periodLabel($periodDefinition),
                'scope_label' => $this->scopeLabel($selectedAccountUuid, $accountOptions),
                'granularity' => $periodDefinition['granularity'],
                'previous_period_label' => $this->periodLabel($previousPeriodDefinition),
                'unresolved_transactions_count' => $unresolvedTransactionsCount,
                'coverage_note' => $unresolvedTransactionsCount > 0
                    ? __('reports.overview.meta.coverageNote', ['count' => $unresolvedTransactionsCount])
                    : null,
            ],
            'filters' => [
                'year' => $selectedYear,
                'month' => $selectedPeriod === self::PERIOD_ANNUAL
                    ? null
                    : $referenceMonth,
                'period' => $selectedPeriod,
                'account_uuid' => $selectedAccountUuid,
                'available_years' => PeriodOptions::yearOptions($availableYears),
                'month_options' => array_values(array_filter(
                    PeriodOptions::monthOptions(false),
                    fn (array $option): bool => $this->isMonthInsidePeriodYear(
                        (int) $option['value'],
                        $selectedYear,
                    ),
                )),
                'period_options' => $this->periodOptions(),
                'account_options' => $accountOptions,
                'show_month_filter' => $selectedPeriod !== self::PERIOD_ANNUAL,
            ],
            'kpis' => [
                'income_total' => $this->formatMoney($incomeTotalRaw, $baseCurrency),
                'income_total_raw' => $incomeTotalRaw,
                'income_total_comparison' => $this->buildComparison(
                    $incomeTotalRaw,
                    $previousTotals['income_total_raw'],
                    $baseCurrency,
                ),
                'expense_total' => $this->formatMoney($expenseTotalRaw, $baseCurrency),
                'expense_total_raw' => $expenseTotalRaw,
                'expense_total_comparison' => $this->buildComparison(
                    $expenseTotalRaw,
                    $previousTotals['expense_total_raw'],
                    $baseCurrency,
                ),
                'net_total' => $this->formatMoney($netTotalRaw, $baseCurrency),
                'net_total_raw' => $netTotalRaw,
                'net_total_comparison' => $this->buildComparison(
                    $netTotalRaw,
                    $previousTotals['net_total_raw'],
                    $baseCurrency,
                ),
                'transactions_count' => $resolvedTransactionsCount,
                'transactions_count_comparison' => $this->buildCountComparison(
                    $resolvedTransactionsCount,
                    $previousTotals['transactions_count'],
                ),
                'average_net' => $this->formatMoney($averageNetRaw, $baseCurrency),
                'average_net_raw' => $averageNetRaw,
                'average_net_comparison' => $this->buildComparison(
                    $averageNetRaw,
                    $previousTotals['average_net_raw'],
                    $baseCurrency,
                ),
                'average_net_interval_label' => $periodDefinition['granularity'] === 'day'
                    ? __('reports.overview.kpis.averagePerDay')
                    : __('reports.overview.kpis.averagePerMonth'),
                'best_period_label' => $bestBucket['label'] ?? null,
                'best_period_value' => isset($bestBucket['net_total_raw'])
                    ? $this->formatMoney((float) $bestBucket['net_total_raw'], $baseCurrency)
                    : null,
                'best_period_value_raw' => $bestBucket['net_total_raw'] ?? null,
                'worst_period_label' => $worstBucket['label'] ?? null,
                'worst_period_value' => isset($worstBucket['net_total_raw'])
                    ? $this->formatMoney((float) $worstBucket['net_total_raw'], $baseCurrency)
                    : null,
                'worst_period_value_raw' => $worstBucket['net_total_raw'] ?? null,
            ],
            'trend' => [
                'labels' => $buckets->pluck('label')->all(),
                'income_values' => $buckets->pluck('income_total_raw')->all(),
                'expense_values' => $buckets->pluck('expense_total_raw')->all(),
                'net_values' => $buckets->pluck('net_total_raw')->all(),
                'granularity' => $periodDefinition['granularity'],
            ],
            'comparison' => [
                'labels' => $buckets->pluck('label')->all(),
                'income_values' => $buckets->pluck('income_total_raw')->all(),
                'expense_values' => $buckets->pluck('expense_total_raw')->all(),
                'net_values' => $buckets->pluck('net_total_raw')->all(),
            ],
            'buckets' => $buckets->all(),
        ];
    }

    /**
     * @return array<int, int>
     */
    protected function availableYears(User $user, int $defaultYear): array
    {
        $years = $this->userYearService->availableYears($user);

        if ($years === []) {
            return [$defaultYear];
        }

        return $years;
    }

    /**
     * @param  array<int, int>  $availableYears
     */
    protected function normalizeYear(?int $requestedYear, array $availableYears, int $fallbackYear): int
    {
        if ($requestedYear !== null && in_array($requestedYear, $availableYears, true)) {
            return $requestedYear;
        }

        if (in_array($fallbackYear, $availableYears, true)) {
            return $fallbackYear;
        }

        return max($availableYears);
    }

    protected function normalizePeriod(?string $requestedPeriod): string
    {
        return in_array($requestedPeriod, [
            self::PERIOD_ANNUAL,
            self::PERIOD_MONTHLY,
            self::PERIOD_LAST_THREE_MONTHS,
            self::PERIOD_LAST_SIX_MONTHS,
            self::PERIOD_YTD,
        ], true)
            ? $requestedPeriod
            : self::PERIOD_MONTHLY;
    }

    protected function normalizeReferenceMonth(
        ?int $requestedMonth,
        ?int $defaultMonth,
        int $selectedYear,
    ): int {
        if (PeriodOptions::isValidMonth($requestedMonth, false)) {
            return (int) $requestedMonth;
        }

        if (PeriodOptions::isValidMonth($defaultMonth, false)) {
            return (int) $defaultMonth;
        }

        $now = CarbonImmutable::now(config('app.timezone'));

        return $selectedYear === $now->year ? $now->month : 12;
    }

    /**
     * @param  array<int, array<string, mixed>>  $accountOptions
     */
    protected function normalizeAccountUuid(?string $accountUuid, array $accountOptions): ?string
    {
        if ($accountUuid === null) {
            return null;
        }

        return collect($accountOptions)->pluck('value')->contains($accountUuid)
            ? $accountUuid
            : null;
    }

    /**
     * @param  Collection<int, Transaction>  $transactions
     * @return array{
     *     income_total_raw: float,
     *     expense_total_raw: float,
     *     net_total_raw: float,
     *     transactions_count: int,
     *     average_net_raw: float
     * }
     */
    protected function aggregatePeriodTotals(Collection $transactions, string $baseCurrency): array
    {
        $incomeTotalRaw = 0.0;
        $expenseTotalRaw = 0.0;
        $transactionsCount = 0;

        foreach ($transactions as $transaction) {
            $resolvedAmount = $this->operationalLedgerAnalyticsService->resolveAggregateAmountForTransaction(
                $transaction,
                $baseCurrency,
            );

            if ($resolvedAmount === null) {
                continue;
            }

            $transactionsCount++;

            if ($transaction->direction === TransactionDirectionEnum::INCOME) {
                $incomeTotalRaw += $resolvedAmount;
            }

            if ($transaction->direction === TransactionDirectionEnum::EXPENSE) {
                $expenseTotalRaw += $resolvedAmount;
            }
        }

        $incomeTotalRaw = round($incomeTotalRaw, 2);
        $expenseTotalRaw = round($expenseTotalRaw, 2);
        $netTotalRaw = round($incomeTotalRaw - $expenseTotalRaw, 2);

        return [
            'income_total_raw' => $incomeTotalRaw,
            'expense_total_raw' => $expenseTotalRaw,
            'net_total_raw' => $netTotalRaw,
            'transactions_count' => $transactionsCount,
            'average_net_raw' => $transactionsCount > 0
                ? round($netTotalRaw / $transactionsCount, 2)
                : 0.0,
        ];
    }

    /**
     * @return array{period: string, start: CarbonImmutable, end: CarbonImmutable, granularity: 'day'|'month', reference_month: int}
     */
    protected function buildPeriodDefinition(
        int $year,
        string $period,
        int $referenceMonth,
    ): array {
        $referenceDate = CarbonImmutable::create(
            $year,
            $referenceMonth,
            1,
            0,
            0,
            0,
            config('app.timezone'),
        );

        return match ($period) {
            self::PERIOD_ANNUAL => [
                'period' => $period,
                'start' => $referenceDate->startOfYear(),
                'end' => $referenceDate->endOfYear(),
                'granularity' => 'month',
                'reference_month' => $referenceMonth,
            ],
            self::PERIOD_LAST_THREE_MONTHS => [
                'period' => $period,
                'start' => $referenceDate->startOfMonth()->subMonths(2),
                'end' => $referenceDate->endOfMonth(),
                'granularity' => 'month',
                'reference_month' => $referenceMonth,
            ],
            self::PERIOD_LAST_SIX_MONTHS => [
                'period' => $period,
                'start' => $referenceDate->startOfMonth()->subMonths(5),
                'end' => $referenceDate->endOfMonth(),
                'granularity' => 'month',
                'reference_month' => $referenceMonth,
            ],
            self::PERIOD_YTD => [
                'period' => $period,
                'start' => $referenceDate->startOfYear(),
                'end' => $referenceDate->endOfMonth(),
                'granularity' => 'month',
                'reference_month' => $referenceMonth,
            ],
            default => [
                'period' => self::PERIOD_MONTHLY,
                'start' => $referenceDate->startOfMonth(),
                'end' => $referenceDate->endOfMonth(),
                'granularity' => 'day',
                'reference_month' => $referenceMonth,
            ],
        };
    }

    /**
     * @param  array{period: string, start: CarbonImmutable, end: CarbonImmutable, granularity: 'day'|'month', reference_month: int}  $periodDefinition
     * @return array{period: string, start: CarbonImmutable, end: CarbonImmutable, granularity: 'day'|'month', reference_month: int}
     */
    protected function previousPeriodDefinition(array $periodDefinition): array
    {
        return match ($periodDefinition['period']) {
            self::PERIOD_ANNUAL => [
                ...$periodDefinition,
                'start' => $periodDefinition['start']->subYear()->startOfYear(),
                'end' => $periodDefinition['end']->subYear()->endOfYear(),
            ],
            self::PERIOD_MONTHLY => [
                ...$periodDefinition,
                'start' => $periodDefinition['start']->subMonth()->startOfMonth(),
                'end' => $periodDefinition['start']->subMonth()->endOfMonth(),
                'reference_month' => $periodDefinition['start']->subMonth()->month,
            ],
            self::PERIOD_LAST_THREE_MONTHS => [
                ...$periodDefinition,
                'start' => $periodDefinition['start']->subMonths(3)->startOfMonth(),
                'end' => $periodDefinition['end']->subMonths(3)->endOfMonth(),
                'reference_month' => $periodDefinition['end']->subMonths(3)->month,
            ],
            self::PERIOD_LAST_SIX_MONTHS => [
                ...$periodDefinition,
                'start' => $periodDefinition['start']->subMonths(6)->startOfMonth(),
                'end' => $periodDefinition['end']->subMonths(6)->endOfMonth(),
                'reference_month' => $periodDefinition['end']->subMonths(6)->month,
            ],
            self::PERIOD_YTD => [
                ...$periodDefinition,
                'start' => $periodDefinition['start']->subYear()->startOfYear(),
                'end' => $periodDefinition['end']->subYear()->endOfMonth(),
            ],
            default => $periodDefinition,
        };
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    protected function makeBucketMap(array $periodDefinition): array
    {
        $buckets = [];
        $cursor = $periodDefinition['granularity'] === 'day'
            ? $periodDefinition['start']
            : $periodDefinition['start']->startOfMonth();

        while ($cursor->lte($periodDefinition['end'])) {
            $key = $this->bucketKeyForDate($cursor, $periodDefinition['granularity']);

            $buckets[$key] = [
                'key' => $key,
                'label' => $this->bucketLabelForDate($cursor, $periodDefinition['granularity']),
                'income_total_raw' => 0.0,
                'expense_total_raw' => 0.0,
                'net_total_raw' => 0.0,
            ];

            $cursor = $periodDefinition['granularity'] === 'day'
                ? $cursor->addDay()
                : $cursor->addMonth();
        }

        return $buckets;
    }

    protected function bucketKeyForDate(CarbonImmutable $date, string $granularity): string
    {
        return $granularity === 'day'
            ? $date->format('Y-m-d')
            : $date->format('Y-m');
    }

    protected function bucketLabelForDate(CarbonImmutable $date, string $granularity): string
    {
        if ($granularity === 'day') {
            return $date->translatedFormat('d M');
        }

        return $date->translatedFormat('M y');
    }

    /**
     * @return Collection<int, Transaction>
     */
    protected function queryTransactions(
        array $accountIds,
        array $ownerIds,
        CarbonImmutable $start,
        CarbonImmutable $end,
    ): Collection {
        $query = Transaction::query()
            ->whereIn('account_id', $accountIds !== [] ? $accountIds : [0])
            ->where('kind', TransactionKindEnum::MANUAL->value)
            ->where('is_transfer', false)
            ->where('status', TransactionStatusEnum::CONFIRMED->value)
            ->whereBetween('transaction_date', [
                $start->toDateString(),
                $end->toDateString(),
            ]);

        $this->applyTrackedItemOwnershipConstraintForOwners(
            $query,
            'tracked_item_id',
            $ownerIds,
        );

        return $query->get([
            'transaction_date',
            'direction',
            'amount',
            'currency',
            'currency_code',
            'base_currency_code',
            'converted_base_amount',
        ]);
    }

    protected function applyTrackedItemOwnershipConstraintForOwners(
        Builder $query,
        string $qualifiedColumn,
        array $ownerIds,
        string $relation = 'trackedItem',
    ): Builder {
        return $query->where(function (Builder $trackedItemQuery) use (
            $qualifiedColumn,
            $relation,
            $ownerIds,
        ): void {
            $trackedItemQuery->whereNull($qualifiedColumn)
                ->orWhereHas($relation, function (Builder $ownedTrackedItemQuery) use ($ownerIds): void {
                    $ownedTrackedItemQuery->whereIn('user_id', $ownerIds !== [] ? $ownerIds : [0]);
                });
        });
    }

    protected function resolveAggregateAmountForTransaction(
        Transaction $transaction,
        string $baseCurrency,
    ): ?float {
        $normalizedBaseCurrency = $this->normalizeCurrencyCode($baseCurrency, 'EUR');
        $transactionBaseCurrency = $this->normalizeCurrencyCode(
            $transaction->base_currency_code,
            $normalizedBaseCurrency,
        );

        if (
            $transaction->converted_base_amount !== null
            && $transactionBaseCurrency === $normalizedBaseCurrency
        ) {
            return round(abs((float) $transaction->converted_base_amount), 2);
        }

        $transactionCurrency = $this->normalizeCurrencyCode(
            $transaction->currency_code ?: $transaction->currency,
            $normalizedBaseCurrency,
        );

        if ($transactionCurrency === $normalizedBaseCurrency) {
            return round(abs((float) $transaction->amount), 2);
        }

        return null;
    }

    protected function normalizeCurrencyCode(?string $currencyCode, string $fallback): string
    {
        $normalizedCurrencyCode = strtoupper(trim((string) $currencyCode));

        return $normalizedCurrencyCode !== ''
            ? $normalizedCurrencyCode
            : strtoupper(trim($fallback));
    }

    /**
     * @param  array<string, mixed>  $bucket
     * @return array<string, mixed>
     */
    protected function formatBucket(array $bucket, string $currency): array
    {
        return [
            ...$bucket,
            'income_total_raw' => round((float) $bucket['income_total_raw'], 2),
            'expense_total_raw' => round((float) $bucket['expense_total_raw'], 2),
            'net_total_raw' => round((float) $bucket['net_total_raw'], 2),
            'income_total' => $this->formatMoney((float) $bucket['income_total_raw'], $currency),
            'expense_total' => $this->formatMoney((float) $bucket['expense_total_raw'], $currency),
            'net_total' => $this->formatMoney((float) $bucket['net_total_raw'], $currency),
        ];
    }

    protected function formatMoney(float $value, string $currency): string
    {
        $formatter = new NumberFormatter(
            app()->getLocale() === 'it' ? 'it_IT' : 'en_US',
            NumberFormatter::CURRENCY,
        );

        $formatted = $formatter->formatCurrency($value, $currency);

        return $formatted !== false
            ? $formatted
            : number_format($value, 2, ',', '.')." {$currency}";
    }

    /**
     * @return array{
     *     previous_raw: float,
     *     previous_formatted: string,
     *     delta_raw: float,
     *     delta_formatted: string,
     *     delta_percentage: float|null,
     *     delta_percentage_label: string|null,
     *     direction: 'up'|'down'|'neutral'
     * }
     */
    protected function buildComparison(float $current, float $previous, string $currency): array
    {
        $delta = round($current - $previous, 2);
        $deltaPercentage = $previous !== 0.0
            ? round(($delta / $previous) * 100, 1)
            : null;

        return [
            'previous_raw' => round($previous, 2),
            'previous_formatted' => $this->formatMoney($previous, $currency),
            'delta_raw' => $delta,
            'delta_formatted' => $this->formatMoney($delta, $currency),
            'delta_percentage' => $deltaPercentage,
            'delta_percentage_label' => $deltaPercentage !== null
                ? number_format($deltaPercentage, 1, '.', '').'%'
                : null,
            'direction' => $delta > 0 ? 'up' : ($delta < 0 ? 'down' : 'neutral'),
        ];
    }

    /**
     * @return array{
     *     previous_raw: int,
     *     delta_raw: int,
     *     delta_label: string,
     *     delta_percentage: float|null,
     *     delta_percentage_label: string|null,
     *     direction: 'up'|'down'|'neutral'
     * }
     */
    protected function buildCountComparison(int $current, int $previous): array
    {
        $delta = $current - $previous;
        $deltaPercentage = $previous !== 0
            ? round(($delta / $previous) * 100, 1)
            : null;

        return [
            'previous_raw' => $previous,
            'delta_raw' => $delta,
            'delta_label' => ($delta > 0 ? '+' : '').$delta,
            'delta_percentage' => $deltaPercentage,
            'delta_percentage_label' => $deltaPercentage !== null
                ? number_format($deltaPercentage, 1, '.', '').'%'
                : null,
            'direction' => $delta > 0 ? 'up' : ($delta < 0 ? 'down' : 'neutral'),
        ];
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    protected function periodOptions(): array
    {
        return [
            [
                'value' => self::PERIOD_ANNUAL,
                'label' => __('reports.filters.periods.annual'),
            ],
            [
                'value' => self::PERIOD_MONTHLY,
                'label' => __('reports.filters.periods.monthly'),
            ],
            [
                'value' => self::PERIOD_LAST_THREE_MONTHS,
                'label' => __('reports.filters.periods.lastThreeMonths'),
            ],
            [
                'value' => self::PERIOD_LAST_SIX_MONTHS,
                'label' => __('reports.filters.periods.lastSixMonths'),
            ],
            [
                'value' => self::PERIOD_YTD,
                'label' => __('reports.filters.periods.ytd'),
            ],
        ];
    }

    protected function scopeLabel(?string $accountUuid, array $accountOptions): string
    {
        if ($accountUuid === null) {
            return __('reports.filters.allResources');
        }

        $account = collect($accountOptions)->firstWhere('value', $accountUuid);

        return is_array($account)
            ? (string) ($account['label'] ?? __('reports.filters.allResources'))
            : __('reports.filters.allResources');
    }

    protected function periodLabel(array $periodDefinition): string
    {
        /** @var CarbonImmutable $start */
        $start = $periodDefinition['start'];
        /** @var CarbonImmutable $end */
        $end = $periodDefinition['end'];

        return match ($periodDefinition['period']) {
            self::PERIOD_ANNUAL => __('reports.filters.periodSummaries.annual', [
                'year' => $start->year,
            ]),
            self::PERIOD_LAST_THREE_MONTHS => __('reports.filters.periodSummaries.lastThreeMonths', [
                'month' => $end->translatedFormat('F'),
                'year' => $end->year,
            ]),
            self::PERIOD_LAST_SIX_MONTHS => __('reports.filters.periodSummaries.lastSixMonths', [
                'month' => $end->translatedFormat('F'),
                'year' => $end->year,
            ]),
            self::PERIOD_YTD => __('reports.filters.periodSummaries.ytd', [
                'month' => $end->translatedFormat('F'),
                'year' => $end->year,
            ]),
            default => $start->translatedFormat('F Y'),
        };
    }

    protected function isMonthInsidePeriodYear(int $month, int $selectedYear): bool
    {
        return CarbonImmutable::create(
            $selectedYear,
            $month,
            1,
            0,
            0,
            0,
            config('app.timezone'),
        ) instanceof CarbonImmutable;
    }
}
