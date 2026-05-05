<?php

namespace App\Services\Reports;

use App\Enums\TransactionDirectionEnum;
use App\Enums\TransactionKindEnum;
use App\Enums\TransactionStatusEnum;
use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Accounts\AccessibleAccountsQuery;
use App\Services\Transactions\OperationalLedgerAnalyticsService;
use App\Services\UserYearService;
use App\Support\Banks\BankNamePresenter;
use App\Supports\PeriodOptions;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use NumberFormatter;

class AccountVisionReportService
{
    protected const PERIOD_ANNUAL = 'annual';

    protected const PERIOD_MONTHLY = 'monthly';

    protected const PERIOD_LAST_THREE_MONTHS = 'last_3_months';

    protected const PERIOD_LAST_SIX_MONTHS = 'last_6_months';

    protected const PERIOD_YTD = 'ytd';

    /**
     * @var list<string>
     */
    protected array $palette = ['#ef4444', '#22c55e', '#6366f1', '#f59e0b', '#06b6d4', '#a855f7'];

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
        $referenceMonth = $this->normalizeReferenceMonth($input['month'] ?? null, $defaultMonth, $selectedYear);
        $accounts = $this->accessibleAccountsQuery->get($user);
        $selectedAccountUuid = $this->normalizeAccountUuid($accounts, $input['account_uuid'] ?? null);
        $baseCurrency = strtoupper(trim((string) ($user->base_currency_code ?: 'EUR')));
        $periodDefinition = $this->buildPeriodDefinition($selectedYear, $selectedPeriod, $referenceMonth);
        $previousPeriodDefinition = $this->previousPeriodDefinition($periodDefinition);
        $accountCards = $this->accountCards($user, $accounts, $periodDefinition, $previousPeriodDefinition, $baseCurrency);
        $selectedCard = collect($accountCards)->firstWhere('uuid', $selectedAccountUuid) ?? $accountCards[0] ?? null;
        $selectedAccountTransactions = $this->operationalLedgerAnalyticsService->transactionsForPeriod(
            $user,
            $periodDefinition['start'],
            $periodDefinition['end'],
            $selectedAccountUuid,
        );
        $selectedTotals = $this->operationalLedgerAnalyticsService->summarize(
            $selectedAccountTransactions,
            $baseCurrency,
        );
        $previousSelectedTotals = $this->operationalLedgerAnalyticsService->summarize(
            $this->operationalLedgerAnalyticsService->transactionsForPeriod(
                $user,
                $previousPeriodDefinition['start'],
                $previousPeriodDefinition['end'],
                $selectedAccountUuid,
            ),
            $baseCurrency,
        );

        $totalBalanceRaw = round((float) collect($accountCards)->sum('current_balance_raw'), 2);

        return [
            'currency' => $baseCurrency,
            'meta' => [
                'period_label' => $this->periodLabel($periodDefinition),
                'scope_label' => $selectedAccountUuid === null
                    ? __('reports.accounts.allAccounts')
                    : ($selectedCard['name'] ?? __('reports.accounts.allAccounts')),
                'previous_period_label' => $this->periodLabel($previousPeriodDefinition),
                'granularity' => $periodDefinition['granularity'],
            ],
            'filters' => [
                'year' => $selectedYear,
                'month' => $selectedPeriod === self::PERIOD_ANNUAL ? null : $referenceMonth,
                'period' => $selectedPeriod,
                'account_uuid' => $selectedAccountUuid,
                'available_years' => PeriodOptions::yearOptions($availableYears),
                'month_options' => array_values(array_filter(
                    PeriodOptions::monthOptions(false),
                    fn (array $option): bool => $this->isMonthInsidePeriodYear((int) $option['value'], $selectedYear),
                )),
                'period_options' => $this->periodOptions(),
                'account_options' => $this->accountOptions($accounts),
                'show_month_filter' => $selectedPeriod !== self::PERIOD_ANNUAL,
            ],
            'summary' => [
                'total_balance' => $this->formatMoney($totalBalanceRaw, $baseCurrency),
                'total_balance_raw' => $totalBalanceRaw,
                'active_accounts_count' => count($accountCards),
                'selected_account_uuid' => $selectedAccountUuid,
                'selected_account_name' => $selectedCard['name'] ?? null,
                'selected_account_type' => $selectedCard['type_label'] ?? null,
                'selected_account_balance' => $selectedCard['current_balance'] ?? $this->formatMoney(0.0, $baseCurrency),
                'selected_account_balance_raw' => (float) ($selectedCard['current_balance_raw'] ?? 0.0),
                'selected_account_share_label' => $totalBalanceRaw > 0
                    ? $this->formatPercentage(((float) ($selectedCard['current_balance_raw'] ?? 0.0) / $totalBalanceRaw) * 100)
                    : $this->formatPercentage(0.0),
                'selected_account_opening_balance' => $selectedCard['opening_balance'] ?? $this->formatMoney(0.0, $baseCurrency),
            ],
            'kpis' => [
                'income' => $this->metric($selectedTotals['income'], $previousSelectedTotals['income'], $baseCurrency),
                'expense' => $this->metric($selectedTotals['expense'], $previousSelectedTotals['expense'], $baseCurrency),
                'net' => $this->metric($selectedTotals['net'], $previousSelectedTotals['net'], $baseCurrency),
                'best_period' => $this->bestPeriod($selectedAccountTransactions, $periodDefinition, $baseCurrency),
            ],
            'accounts' => $accountCards,
            'balance_trend' => $this->balanceTrend($accounts, $periodDefinition, $baseCurrency, $selectedAccountUuid),
            'cash_flow' => $this->cashFlow($selectedAccountTransactions, $periodDefinition, $baseCurrency),
            'distribution' => $this->distribution($accountCards, $totalBalanceRaw),
            'top_categories' => $this->topCategories($selectedAccountTransactions, $baseCurrency),
            'recent_transactions' => $this->recentTransactions($user, $periodDefinition, $selectedAccountUuid, $baseCurrency),
            'comparison_rows' => $this->comparisonRows($accountCards),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function accountCards(User $user, Collection $accounts, array $periodDefinition, array $previousPeriodDefinition, string $currency): array
    {
        return $accounts
            ->values()
            ->map(function (Account $account, int $index) use ($user, $periodDefinition, $previousPeriodDefinition, $currency): array {
                $accountUuid = (string) $account->uuid;
                $transactions = $this->operationalLedgerAnalyticsService->transactionsForPeriod(
                    $user,
                    $periodDefinition['start'],
                    $periodDefinition['end'],
                    $accountUuid,
                );
                $previousTransactions = $this->operationalLedgerAnalyticsService->transactionsForPeriod(
                    $user,
                    $previousPeriodDefinition['start'],
                    $previousPeriodDefinition['end'],
                    $accountUuid,
                );
                $totals = $this->operationalLedgerAnalyticsService->summarize($transactions, $currency);
                $previousTotals = $this->operationalLedgerAnalyticsService->summarize($previousTransactions, $currency);
                $balanceRaw = $this->balanceAt($account, $periodDefinition['end']);
                $previousBalanceRaw = $this->balanceAt($account, $previousPeriodDefinition['end']);
                $color = $this->palette[$index % count($this->palette)];

                return [
                    'uuid' => $accountUuid,
                    'name' => $account->name,
                    'bank_name' => BankNamePresenter::forAccount($account),
                    'type_label' => $this->accountTypeLabel($account),
                    'type_code' => $account->accountType?->code,
                    'currency' => $account->currency_code ?: $account->currency ?: $currency,
                    'color' => $color,
                    'initials' => $this->initials($account->name),
                    'current_balance_raw' => $balanceRaw,
                    'current_balance' => $this->formatMoney($balanceRaw, $currency),
                    'opening_balance_raw' => round((float) ($account->opening_balance ?? 0.0), 2),
                    'opening_balance' => $this->formatMoney((float) ($account->opening_balance ?? 0.0), $currency),
                    'income_raw' => $totals['income'],
                    'income' => $this->formatMoney($totals['income'], $currency),
                    'expense_raw' => $totals['expense'],
                    'expense' => $this->formatMoney($totals['expense'], $currency),
                    'net_raw' => $totals['net'],
                    'net' => $this->formatMoney($totals['net'], $currency),
                    'share_percentage' => 0.0,
                    'share_label' => $this->formatPercentage(0.0),
                    'previous_balance_raw' => $previousBalanceRaw,
                    'delta_percentage' => $this->percentageDelta($balanceRaw, $previousBalanceRaw),
                    'delta_label' => $this->percentageDeltaLabel($balanceRaw, $previousBalanceRaw),
                    'period_delta_raw' => round($totals['net'] - $previousTotals['net'], 2),
                    'sparkline' => $this->sparklineForAccount($account, $periodDefinition),
                ];
            })
            ->tap(function (Collection $cards): void {
                $total = round((float) $cards->sum('current_balance_raw'), 2);

                $cards->transform(function (array $card) use ($total): array {
                    $share = $total > 0 ? round(((float) $card['current_balance_raw'] / $total) * 100, 1) : 0.0;

                    return [
                        ...$card,
                        'share_percentage' => $share,
                        'share_label' => $this->formatPercentage($share),
                    ];
                });
            })
            ->values()
            ->all();
    }

    protected function normalizeAccountUuid(Collection $accounts, ?string $requestedUuid): ?string
    {
        if ($requestedUuid === null || $requestedUuid === '' || $requestedUuid === 'all') {
            return null;
        }

        $selected = $accounts->firstWhere('uuid', $requestedUuid);

        return $selected instanceof Account ? (string) $selected->uuid : null;
    }

    /**
     * @return array<string, mixed>
     */
    protected function balanceTrend(Collection $accounts, array $periodDefinition, string $currency, ?string $selectedAccountUuid): array
    {
        $labels = array_values(array_map(
            fn (array $bucket): string => $bucket['label'],
            $this->makeBucketMap($periodDefinition),
        ));

        return [
            'labels' => $labels,
            'granularity' => $periodDefinition['granularity'],
            'selected_account_uuid' => $selectedAccountUuid,
            'series' => $accounts->values()->map(function (Account $account, int $index) use ($periodDefinition, $currency): array {
                return [
                    'uuid' => $account->uuid,
                    'name' => $account->name,
                    'color' => $this->palette[$index % count($this->palette)],
                    'values' => $this->sparklineForAccount($account, $periodDefinition),
                    'current' => $this->formatMoney($this->balanceAt($account, $periodDefinition['end']), $currency),
                ];
            })->all(),
        ];
    }

    /**
     * @return array{labels: array<int, string>, income_values: array<int, float>, expense_values: array<int, float>}
     */
    protected function cashFlow(Collection $transactions, array $periodDefinition, string $baseCurrency): array
    {
        $bucketMap = $this->makeBucketMap($periodDefinition);
        $resolvedTransactionsCount = 0;

        foreach ($transactions as $transaction) {
            $date = CarbonImmutable::parse($transaction->transaction_date, config('app.timezone'));
            $key = $this->bucketKeyForDate($date, $periodDefinition['granularity']);

            if (! isset($bucketMap[$key])) {
                continue;
            }

            $amount = $this->operationalLedgerAnalyticsService->resolveAggregateAmountForTransaction(
                $transaction,
                $baseCurrency,
            );

            if ($amount === null) {
                continue;
            }

            $resolvedTransactionsCount++;
            $totals = [
                'income' => 0.0,
                'expense' => 0.0,
                'resolved_count' => 0,
                'unresolved_count' => 0,
            ];
            $this->operationalLedgerAnalyticsService->applyTransactionToTotals($totals, $transaction, $amount);

            $bucketMap[$key]['income_raw'] += $totals['income'];
            $bucketMap[$key]['expense_raw'] += $totals['expense'];
        }

        $incomeValues = collect($bucketMap)->pluck('income_raw')->map(fn ($value): float => round((float) $value, 2))->all();
        $expenseValues = collect($bucketMap)->pluck('expense_raw')->map(fn ($value): float => round((float) $value, 2))->all();

        return [
            'labels' => collect($bucketMap)->pluck('label')->all(),
            'income_values' => $incomeValues,
            'expense_values' => $expenseValues,
            'has_data' => $resolvedTransactionsCount > 0
                && (collect($incomeValues)->merge($expenseValues)->some(fn (float $value): bool => abs($value) > 0.005)),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function distribution(array $accountCards, float $totalBalanceRaw): array
    {
        return collect($accountCards)
            ->map(fn (array $card): array => [
                'uuid' => $card['uuid'],
                'name' => $card['name'],
                'color' => $card['color'],
                'value_raw' => $card['current_balance_raw'],
                'value' => $card['current_balance'],
                'share_percentage' => $totalBalanceRaw > 0
                    ? round(((float) $card['current_balance_raw'] / $totalBalanceRaw) * 100, 1)
                    : 0.0,
                'share_label' => $totalBalanceRaw > 0
                    ? $this->formatPercentage(((float) $card['current_balance_raw'] / $totalBalanceRaw) * 100)
                    : $this->formatPercentage(0.0),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function topCategories(Collection $transactions, string $currency): array
    {
        return $transactions
            ->filter(fn (Transaction $transaction): bool => $transaction->direction === TransactionDirectionEnum::EXPENSE)
            ->loadMissing('category:id,name,name_is_custom,slug,foundation_key,color')
            ->groupBy(fn (Transaction $transaction): string => $transaction->category?->displayName() ?? __('reports.accounts.uncategorized'))
            ->map(function (Collection $group, string $label) use ($currency): array {
                $total = round((float) $group->sum(fn (Transaction $transaction): float => abs((float) $transaction->amount)), 2);
                $first = $group->first();

                return [
                    'label' => $label,
                    'color' => $first?->category?->color ?: '#64748b',
                    'total_raw' => $total,
                    'total' => $this->formatMoney($total, $currency),
                ];
            })
            ->sortByDesc('total_raw')
            ->take(5)
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function recentTransactions(User $user, array $periodDefinition, ?string $accountUuid, string $currency): array
    {
        if ($accountUuid === null) {
            return [];
        }

        $accountIds = $this->accessibleAccountsQuery->ids($user, 'all', $accountUuid);

        return Transaction::query()
            ->with('category:id,name,name_is_custom,slug,foundation_key,color')
            ->whereIn('account_id', $accountIds !== [] ? $accountIds : [0])
            ->where('status', TransactionStatusEnum::CONFIRMED->value)
            ->where('kind', '!=', TransactionKindEnum::OPENING_BALANCE->value)
            ->whereBetween('transaction_date', [
                $periodDefinition['start']->toDateString(),
                $periodDefinition['end']->toDateString(),
            ])
            ->orderByDesc('transaction_date')
            ->limit(6)
            ->get()
            ->map(fn (Transaction $transaction): array => [
                'uuid' => $transaction->uuid,
                'date_label' => CarbonImmutable::parse($transaction->transaction_date)->translatedFormat('d M'),
                'description' => $transaction->description ?: __('reports.accounts.movement'),
                'category_label' => $transaction->category?->displayName() ?? __('reports.accounts.uncategorized'),
                'amount_raw' => $transaction->direction === TransactionDirectionEnum::EXPENSE
                    ? round(abs((float) $transaction->amount) * -1, 2)
                    : round(abs((float) $transaction->amount), 2),
                'amount' => $this->formatMoney(
                    $transaction->direction === TransactionDirectionEnum::EXPENSE
                        ? abs((float) $transaction->amount) * -1
                        : abs((float) $transaction->amount),
                    $currency,
                ),
                'color' => $transaction->category?->color ?: '#64748b',
                'direction' => $transaction->direction?->value,
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function comparisonRows(array $accountCards): array
    {
        return collect($accountCards)
            ->sortByDesc('current_balance_raw')
            ->values()
            ->all();
    }

    protected function metric(float $current, float $previous, string $currency): array
    {
        $delta = round($current - $previous, 2);
        $deltaPercentage = $this->percentageDelta($current, $previous);

        return [
            'value_raw' => round($current, 2),
            'value' => $this->formatMoney($current, $currency),
            'previous_raw' => round($previous, 2),
            'previous' => $this->formatMoney($previous, $currency),
            'delta_raw' => $delta,
            'delta' => $this->formatMoney($delta, $currency),
            'delta_percentage' => $deltaPercentage,
            'delta_percentage_label' => $deltaPercentage === null ? null : sprintf('%+.1f%%', $deltaPercentage),
            'comparison_available' => abs($previous) >= 0.005 && $deltaPercentage !== null,
        ];
    }

    protected function bestPeriod(Collection $transactions, array $periodDefinition, string $currency): array
    {
        $buckets = $this->cashFlow($transactions, $periodDefinition, $currency);
        $periods = collect($buckets['labels'])
            ->map(fn (string $label, int $index): array => [
                'label' => $label,
                'net' => round(($buckets['income_values'][$index] ?? 0.0) - ($buckets['expense_values'][$index] ?? 0.0), 2),
            ]);
        $best = $buckets['has_data'] ? $periods->sortByDesc('net')->first() : null;
        $worst = $buckets['has_data'] ? $periods->sortBy('net')->first() : null;

        return [
            'label' => $best['label'] ?? null,
            'value_raw' => (float) ($best['net'] ?? 0.0),
            'value' => $best === null ? null : $this->formatMoney((float) $best['net'], $currency),
            'summary' => $best === null ? null : sprintf('%s · %s', $best['label'], $this->formatMoney((float) $best['net'], $currency)),
            'worst_label' => $worst['label'] ?? null,
            'worst_value_raw' => (float) ($worst['net'] ?? 0.0),
            'worst_value' => $worst === null ? null : $this->formatMoney((float) $worst['net'], $currency),
        ];
    }

    /**
     * @return array<int, float>
     */
    protected function sparklineForAccount(Account $account, array $periodDefinition): array
    {
        $runningBalance = $this->balanceAt($account, $periodDefinition['start']->subDay());
        $bucketMap = $this->makeBucketMap($periodDefinition);
        $transactions = Transaction::query()
            ->where('account_id', $account->id)
            ->where('status', TransactionStatusEnum::CONFIRMED->value)
            ->where('kind', '!=', TransactionKindEnum::OPENING_BALANCE->value)
            ->whereBetween('transaction_date', [
                $periodDefinition['start']->toDateString(),
                $periodDefinition['end']->toDateString(),
            ])
            ->orderBy('transaction_date')
            ->get(['transaction_date', 'direction', 'amount']);

        foreach ($bucketMap as $bucketKey => $bucket) {
            foreach ($transactions as $transaction) {
                $transactionKey = $this->bucketKeyForDate(
                    CarbonImmutable::parse($transaction->transaction_date),
                    $periodDefinition['granularity'],
                );

                if ($transactionKey !== $bucketKey) {
                    continue;
                }

                $runningBalance += $this->signedTransactionAmount($transaction);
            }

            $bucketMap[$bucketKey]['balance_raw'] = round($runningBalance, 2);
        }

        return collect($bucketMap)->pluck('balance_raw')->map(fn ($value): float => (float) $value)->all();
    }

    protected function balanceAt(Account $account, CarbonImmutable $date): float
    {
        $lastBalance = Transaction::query()
            ->where('account_id', $account->id)
            ->whereNotNull('balance_after')
            ->whereDate('transaction_date', '<=', $date->toDateString())
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->value('balance_after');

        if ($lastBalance !== null) {
            return round((float) $lastBalance, 2);
        }

        if ($account->current_balance !== null && $date->isFuture()) {
            return round((float) $account->current_balance, 2);
        }

        $balance = (float) ($account->opening_balance ?? 0.0);
        $transactions = Transaction::query()
            ->where('account_id', $account->id)
            ->where('status', TransactionStatusEnum::CONFIRMED->value)
            ->where('kind', '!=', TransactionKindEnum::OPENING_BALANCE->value)
            ->whereDate('transaction_date', '<=', $date->toDateString())
            ->get(['direction', 'amount']);

        foreach ($transactions as $transaction) {
            $balance += $this->signedTransactionAmount($transaction);
        }

        return round($balance, 2);
    }

    protected function signedTransactionAmount(Transaction $transaction): float
    {
        $amount = abs((float) $transaction->amount);

        return $transaction->direction === TransactionDirectionEnum::EXPENSE
            ? $amount * -1
            : $amount;
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
                'income_raw' => 0.0,
                'expense_raw' => 0.0,
                'balance_raw' => 0.0,
            ];

            $cursor = $periodDefinition['granularity'] === 'day'
                ? $cursor->addDay()
                : $cursor->addMonth();
        }

        return $buckets;
    }

    protected function bucketKeyForDate(CarbonImmutable $date, string $granularity): string
    {
        return $granularity === 'day' ? $date->format('Y-m-d') : $date->format('Y-m');
    }

    protected function bucketLabelForDate(CarbonImmutable $date, string $granularity): string
    {
        return $granularity === 'day' ? $date->translatedFormat('d M') : $date->translatedFormat('M');
    }

    /**
     * @return array{period: string, start: CarbonImmutable, end: CarbonImmutable, granularity: 'day'|'month', reference_month: int}
     */
    protected function buildPeriodDefinition(int $year, string $period, int $referenceMonth): array
    {
        $referenceDate = CarbonImmutable::create($year, $referenceMonth, 1, 0, 0, 0, config('app.timezone'));

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

    protected function normalizePeriod(?string $requestedPeriod): string
    {
        return in_array($requestedPeriod, [
            self::PERIOD_ANNUAL,
            self::PERIOD_MONTHLY,
            self::PERIOD_LAST_THREE_MONTHS,
            self::PERIOD_LAST_SIX_MONTHS,
            self::PERIOD_YTD,
        ], true) ? $requestedPeriod : self::PERIOD_ANNUAL;
    }

    protected function normalizeReferenceMonth(?int $requestedMonth, ?int $defaultMonth, int $selectedYear): int
    {
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
     * @param  array<int, int>  $availableYears
     */
    protected function normalizeYear(?int $requestedYear, array $availableYears, int $fallbackYear): int
    {
        if ($requestedYear !== null && in_array($requestedYear, $availableYears, true)) {
            return $requestedYear;
        }

        return in_array($fallbackYear, $availableYears, true) ? $fallbackYear : max($availableYears);
    }

    /**
     * @return array<int, int>
     */
    protected function availableYears(User $user, int $defaultYear): array
    {
        $years = $this->userYearService->availableYears($user);

        return $years === [] ? [$defaultYear] : $years;
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    protected function periodOptions(): array
    {
        return [
            ['value' => self::PERIOD_ANNUAL, 'label' => __('reports.filters.periods.annual')],
            ['value' => self::PERIOD_MONTHLY, 'label' => __('reports.filters.periods.monthly')],
            ['value' => self::PERIOD_LAST_THREE_MONTHS, 'label' => __('reports.filters.periods.lastThreeMonths')],
            ['value' => self::PERIOD_LAST_SIX_MONTHS, 'label' => __('reports.filters.periods.lastSixMonths')],
            ['value' => self::PERIOD_YTD, 'label' => __('reports.filters.periods.ytd')],
        ];
    }

    protected function periodLabel(array $periodDefinition): string
    {
        return match ($periodDefinition['period']) {
            self::PERIOD_ANNUAL => (string) $periodDefinition['start']->year,
            self::PERIOD_MONTHLY => $periodDefinition['start']->translatedFormat('F Y'),
            self::PERIOD_LAST_THREE_MONTHS => __('reports.filters.periodSummaries.lastThreeMonths', [
                'month' => $periodDefinition['end']->translatedFormat('F'),
                'year' => $periodDefinition['end']->year,
            ]),
            self::PERIOD_LAST_SIX_MONTHS => __('reports.filters.periodSummaries.lastSixMonths', [
                'month' => $periodDefinition['end']->translatedFormat('F'),
                'year' => $periodDefinition['end']->year,
            ]),
            self::PERIOD_YTD => __('reports.filters.periodSummaries.ytd', [
                'month' => $periodDefinition['end']->translatedFormat('F'),
                'year' => $periodDefinition['end']->year,
            ]),
            default => (string) $periodDefinition['start']->year,
        };
    }

    protected function isMonthInsidePeriodYear(int $month, int $year): bool
    {
        $now = CarbonImmutable::now(config('app.timezone'));

        return $year < $now->year || $month <= $now->month;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function accountOptions(Collection $accounts): array
    {
        return $accounts->map(fn (Account $account): array => [
            'value' => $account->uuid,
            'label' => $account->name,
            'bank_name' => BankNamePresenter::forAccount($account),
            'account_type_code' => $account->accountType?->code,
            'is_owned' => (bool) $account->getAttribute('is_owned'),
            'is_shared' => (bool) $account->getAttribute('is_shared'),
            'membership_role' => $account->getAttribute('membership_role'),
            'membership_status' => $account->getAttribute('membership_status'),
            'can_view' => (bool) $account->getAttribute('can_view'),
            'can_edit' => (bool) $account->getAttribute('can_edit'),
        ])->values()->all();
    }

    protected function accountTypeLabel(Account $account): string
    {
        return match ($account->accountType?->code) {
            'credit_card' => __('reports.accounts.types.credit_card'),
            'cash_account' => __('reports.accounts.types.cash'),
            default => __('reports.accounts.types.current'),
        };
    }

    protected function initials(string $name): string
    {
        $words = preg_split('/\s+/', trim($name)) ?: [];

        return strtoupper(substr(collect($words)->map(fn (string $word): string => $word[0] ?? '')->join(''), 0, 2));
    }

    protected function percentageDelta(float $current, float $previous): ?float
    {
        if (abs($previous) < 0.005) {
            return abs($current) < 0.005 ? 0.0 : null;
        }

        return round((($current - $previous) / abs($previous)) * 100, 1);
    }

    protected function percentageDeltaLabel(float $current, float $previous): ?string
    {
        $delta = $this->percentageDelta($current, $previous);

        return $delta === null ? null : sprintf('%+.1f%%', $delta);
    }

    protected function formatPercentage(float $value): string
    {
        return number_format($value, 1, ',', '.').'%';
    }

    protected function formatMoney(float $value, string $currency): string
    {
        $formatter = new NumberFormatter(app()->getLocale() === 'it' ? 'it_IT' : 'en_US', NumberFormatter::CURRENCY);

        return $formatter->formatCurrency($value, $currency);
    }
}
