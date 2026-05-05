<?php

namespace App\Services\Dashboard;

use App\Enums\TransactionDirectionEnum;
use App\Enums\TransactionKindEnum;
use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Accounts\AccessibleAccountsQuery;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class MonthlyFinancialRecapService
{
    public function __construct(
        protected AccessibleAccountsQuery $accessibleAccountsQuery,
        protected FinancialInsightService $financialInsightService,
    ) {}

    public function previousClosedMonth(User $user, string $accountScope = 'all', ?string $accountUuid = null): array
    {
        $period = CarbonImmutable::now()->subMonthNoOverflow();

        return $this->forPeriod($user, (int) $period->year, (int) $period->month, $accountScope, $accountUuid);
    }

    public function forPeriod(
        User $user,
        int $year,
        int $month,
        string $accountScope = 'all',
        ?string $accountUuid = null,
    ): array {
        $accountContext = $this->resolveAccountContext($user, $accountScope, $accountUuid);
        $periodStart = CarbonImmutable::create($year, $month, 1)->startOfMonth();
        $periodEnd = $periodStart->endOfMonth();
        $previousPeriodStart = $periodStart->subMonthNoOverflow()->startOfMonth();
        $previousPeriodEnd = $previousPeriodStart->endOfMonth();
        $currentSummary = $this->summarizePeriod($accountContext, $periodStart, $periodEnd);
        $previousSummary = $this->summarizePeriod($accountContext, $previousPeriodStart, $previousPeriodEnd);
        $netDelta = round($currentSummary['net_total_raw'] - $previousSummary['net_total_raw'], 2);
        $netDeltaPercentage = abs($previousSummary['net_total_raw']) >= 0.01
            ? round(($netDelta / abs($previousSummary['net_total_raw'])) * 100, 2)
            : null;
        $flowTotal = $currentSummary['income_total_raw'] + $currentSummary['expense_total_raw'];
        $currency = $accountContext['base_currency'];

        $recap = [
            'available' => $currentSummary['transactions_count'] > 0,
            'empty_reason' => $currentSummary['transactions_count'] > 0 ? null : 'no_closed_month_transactions',
            'period' => [
                'year' => $year,
                'month' => $month,
                'key' => $periodStart->format('Y-m'),
                'label' => $this->monthLabel($month),
                'starts_at' => $periodStart->toDateString(),
                'ends_at' => $periodEnd->toDateString(),
            ],
            'previous_period' => [
                'year' => (int) $previousPeriodStart->year,
                'month' => (int) $previousPeriodStart->month,
                'key' => $previousPeriodStart->format('Y-m'),
                'label' => $this->monthLabel((int) $previousPeriodStart->month),
            ],
            'currency' => $currency,
            'scope' => [
                'account_scope' => $accountContext['scope'],
                'account_uuid' => $accountContext['account_uuid'],
                'accounts_count' => $accountContext['accounts']->where('is_active', true)->count(),
            ],
            'totals' => [
                ...$this->formatPeriodSummary($currentSummary, $currency),
                'net_vs_previous' => $this->formatMoney($netDelta, $currency),
                'net_vs_previous_raw' => $netDelta,
                'net_vs_previous_percentage' => $netDeltaPercentage,
                'income_share' => $flowTotal > 0.0 ? round(($currentSummary['income_total_raw'] / $flowTotal) * 100, 1) : 0.0,
                'expense_share' => $flowTotal > 0.0 ? round(($currentSummary['expense_total_raw'] / $flowTotal) * 100, 1) : 0.0,
            ],
            'previous_totals' => $this->formatPeriodSummary($previousSummary, $currency),
            'top_expense_categories' => $this->formatCategoryRows(
                $this->topExpenseCategories(
                    $accountContext,
                    $periodStart,
                    $periodEnd,
                    $currentSummary['expense_total_raw'],
                ),
                $currency,
            ),
            'top_movements' => $this->formatMovementRows(
                $this->topMovements($accountContext, $periodStart, $periodEnd),
                $currency,
            ),
            'insights' => [],
        ];

        $recap['insights'] = $recap['available']
            ? $this->financialInsightService->forMonthlyRecap($recap)
            : [];

        return $recap;
    }

    protected function resolveAccountContext(User $user, string $scope, ?string $accountUuid): array
    {
        $normalizedScope = in_array($scope, ['all', 'owned', 'shared'], true) ? $scope : 'all';

        if (
            $normalizedScope === 'shared'
            && $this->accessibleAccountsQuery->get($user, 'shared')->isEmpty()
        ) {
            $normalizedScope = 'owned';
        }

        $accounts = $this->accessibleAccountsQuery->get($user, $normalizedScope, $accountUuid);

        if ($accountUuid !== null && $accounts->isEmpty()) {
            $accountUuid = null;
            $accounts = $this->accessibleAccountsQuery->get($user, $normalizedScope);
        }

        return [
            'scope' => $normalizedScope,
            'account_uuid' => $accountUuid,
            'accounts' => $accounts,
            'base_currency' => $this->normalizeCurrencyCode($user->base_currency_code, 'EUR'),
            'account_ids' => $accounts->pluck('id')->map(fn ($id): int => (int) $id)->values()->all(),
            'owner_ids' => $accounts->pluck('user_id')->map(fn ($id): int => (int) $id)->unique()->values()->all(),
        ];
    }

    protected function summarizePeriod(array $accountContext, CarbonImmutable $periodStart, CarbonImmutable $periodEnd): array
    {
        $transactions = $this->baseTransactionPeriodQuery($accountContext, $periodStart, $periodEnd)->get();
        $incomeTotal = $this->sumResolvedAmounts($transactions, $accountContext['base_currency'], TransactionDirectionEnum::INCOME);
        $expenseTotal = $this->sumResolvedAmounts($transactions, $accountContext['base_currency'], TransactionDirectionEnum::EXPENSE);

        return [
            'starting_balance_total_raw' => $this->balanceTotalAt($accountContext, $periodStart->subDay()),
            'ending_balance_total_raw' => $this->balanceTotalAt($accountContext, $periodEnd),
            'income_total_raw' => $incomeTotal,
            'expense_total_raw' => $expenseTotal,
            'net_total_raw' => round($incomeTotal - $expenseTotal, 2),
            'transactions_count' => $transactions->count(),
        ];
    }

    protected function baseTransactionPeriodQuery(array $accountContext, CarbonImmutable $periodStart, CarbonImmutable $periodEnd): Builder
    {
        $query = Transaction::query()
            ->whereIn('transactions.account_id', $accountContext['account_ids'] !== [] ? $accountContext['account_ids'] : [0])
            ->where('transactions.kind', TransactionKindEnum::MANUAL->value)
            ->where('transactions.is_transfer', false)
            ->whereDate('transactions.transaction_date', '>=', $periodStart->toDateString())
            ->whereDate('transactions.transaction_date', '<=', $periodEnd->toDateString());

        $this->applyTrackedItemOwnershipConstraintForOwners($query, 'transactions.tracked_item_id', $accountContext['owner_ids']);

        return $query;
    }

    protected function balanceTotalAt(array $accountContext, CarbonImmutable $date): float
    {
        return round($accountContext['accounts']
            ->where('is_active', true)
            ->sum(fn (Account $account): float => $this->resolveAggregatedAccountBalanceAt(
                $account,
                $date,
                $accountContext['base_currency'],
            ) ?? 0.0), 2);
    }

    /**
     * @param  Collection<int, Transaction>  $transactions
     */
    protected function sumResolvedAmounts(Collection $transactions, string $baseCurrency, TransactionDirectionEnum $direction): float
    {
        return round($transactions->sum(function (Transaction $transaction) use ($baseCurrency, $direction): float {
            if ($transaction->direction !== $direction) {
                return 0.0;
            }

            return $this->resolveAggregateAmountForTransaction($transaction, $baseCurrency) ?? 0.0;
        }), 2);
    }

    protected function topExpenseCategories(array $accountContext, CarbonImmutable $periodStart, CarbonImmutable $periodEnd, float $expenseTotal): array
    {
        return $this->baseTransactionPeriodQuery($accountContext, $periodStart, $periodEnd)
            ->where('transactions.direction', TransactionDirectionEnum::EXPENSE->value)
            ->with('category:id,name,name_is_custom,slug,foundation_key')
            ->get()
            ->groupBy(fn (Transaction $transaction): string => (string) ($transaction->category_id ?? 0))
            ->map(function (Collection $group) use ($accountContext): array {
                /** @var Transaction|null $sample */
                $sample = $group->first();

                return [
                    'category_id' => $sample?->category_id,
                    'category_name' => $sample?->category?->displayName() ?? __('dashboard.agenda.unspecified'),
                    'transactions_count' => $group->count(),
                    'total_amount_raw' => round($this->sumResolvedAmounts(
                        $group,
                        $accountContext['base_currency'],
                        TransactionDirectionEnum::EXPENSE,
                    ), 2),
                ];
            })
            ->map(function (array $row) use ($expenseTotal): array {
                $row['share'] = $expenseTotal > 0.0
                    ? round(($row['total_amount_raw'] / $expenseTotal) * 100, 1)
                    : 0.0;

                return $row;
            })
            ->filter(fn (array $row): bool => $row['total_amount_raw'] > 0)
            ->sortByDesc('total_amount_raw')
            ->take(6)
            ->values()
            ->all();
    }

    protected function topMovements(array $accountContext, CarbonImmutable $periodStart, CarbonImmutable $periodEnd): array
    {
        return $this->baseTransactionPeriodQuery($accountContext, $periodStart, $periodEnd)
            ->with(['category:id,name,name_is_custom,slug,foundation_key', 'merchant:id,name', 'trackedItem:id,name'])
            ->get()
            ->map(function (Transaction $transaction) use ($accountContext): array {
                $resolvedAmount = $this->resolveAggregateAmountForTransaction(
                    $transaction,
                    $accountContext['base_currency'],
                ) ?? 0.0;

                return [
                    'date' => $transaction->transaction_date?->format('d/m'),
                    'description' => $this->movementLabel($transaction),
                    'direction' => $transaction->direction->value,
                    'amount_raw' => round(
                        $transaction->direction === TransactionDirectionEnum::INCOME
                            ? $resolvedAmount
                            : -abs($resolvedAmount),
                        2,
                    ),
                ];
            })
            ->sortByDesc(fn (array $movement): float => abs((float) $movement['amount_raw']))
            ->take(5)
            ->values()
            ->all();
    }

    protected function movementLabel(Transaction $transaction): string
    {
        foreach ([
            $transaction->merchant?->name,
            $transaction->trackedItem?->name,
            $transaction->description,
            $transaction->category?->displayName(),
        ] as $value) {
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return __('dashboard.agenda.unspecified');
    }

    protected function resolveAggregateAmountForTransaction(Transaction $transaction, string $baseCurrency): ?float
    {
        $normalizedBaseCurrency = $this->normalizeCurrencyCode($baseCurrency, 'EUR');
        $transactionBaseCurrency = $this->normalizeCurrencyCode($transaction->base_currency_code, $normalizedBaseCurrency);

        if ($transaction->converted_base_amount !== null && $transactionBaseCurrency === $normalizedBaseCurrency) {
            return round(abs((float) $transaction->converted_base_amount), 2);
        }

        $transactionCurrency = $this->normalizeCurrencyCode($transaction->currency_code ?: $transaction->currency, $normalizedBaseCurrency);

        if ($transactionCurrency === $normalizedBaseCurrency) {
            return round(abs((float) $transaction->amount), 2);
        }

        return null;
    }

    protected function resolveAggregatedAccountBalanceAt(Account $account, CarbonImmutable $date, string $baseCurrency): ?float
    {
        $accountCurrency = $this->normalizeCurrencyCode($account->currency_code ?: $account->currency, $baseCurrency);

        if ($accountCurrency === $this->normalizeCurrencyCode($baseCurrency, 'EUR')) {
            return $this->resolveAccountBalanceAt($account, $date);
        }

        $transactions = Transaction::query()
            ->where('account_id', $account->id)
            ->whereDate('transaction_date', '<=', $date->toDateString())
            ->orderBy('transaction_date')
            ->get(['direction', 'amount', 'currency', 'currency_code', 'base_currency_code', 'converted_base_amount']);

        if ($transactions->isEmpty()) {
            return abs((float) ($account->opening_balance ?? 0)) < 0.005 ? 0.0 : null;
        }

        $total = 0.0;

        foreach ($transactions as $transaction) {
            $resolvedAmount = $this->resolveAggregateAmountForTransaction($transaction, $baseCurrency);

            if ($resolvedAmount === null) {
                return null;
            }

            $total += $transaction->direction === TransactionDirectionEnum::INCOME
                ? $resolvedAmount
                : -abs($resolvedAmount);
        }

        return round($total, 2);
    }

    protected function resolveAccountBalanceAt(Account $account, CarbonImmutable $date): float
    {
        $snapshotBalance = $account->balanceSnapshots()
            ->whereDate('snapshot_date', '<=', $date->toDateString())
            ->orderByDesc('snapshot_date')
            ->orderByDesc('id')
            ->value('balance');

        if ($snapshotBalance !== null) {
            return (float) $snapshotBalance;
        }

        $openingBalanceDate = $account->openingBalances()
            ->whereDate('balance_date', '<=', $date->toDateString())
            ->orderByDesc('balance_date')
            ->orderByDesc('id')
            ->value('balance_date');

        return $this->resolveAccountOpeningBalance($account, $date) + $this->sumNetTransactionsForAccount(
            $account->user_id,
            $account->id,
            $openingBalanceDate !== null ? CarbonImmutable::parse((string) $openingBalanceDate) : null,
            $date,
        );
    }

    protected function resolveAccountOpeningBalance(Account $account, CarbonImmutable $date): float
    {
        $openingBalanceTransaction = Transaction::query()
            ->where('account_id', $account->id)
            ->where('kind', TransactionKindEnum::OPENING_BALANCE->value)
            ->whereDate('transaction_date', '<=', $date->toDateString())
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->first();

        if ($openingBalanceTransaction instanceof Transaction) {
            $amount = (float) $openingBalanceTransaction->amount;

            return $openingBalanceTransaction->direction === TransactionDirectionEnum::EXPENSE ? $amount * -1 : $amount;
        }

        $openingBalance = $account->openingBalances()
            ->whereDate('balance_date', '<=', $date->toDateString())
            ->orderByDesc('balance_date')
            ->orderByDesc('id')
            ->value('amount');

        if ($openingBalance !== null) {
            return (float) $openingBalance;
        }

        return (float) ($account->opening_balance ?? 0);
    }

    protected function sumNetTransactionsForAccount(int $userId, int $accountId, ?CarbonImmutable $fromDate, CarbonImmutable $toDate): float
    {
        $query = Transaction::query()
            ->where('user_id', $userId)
            ->where('account_id', $accountId)
            ->where('kind', '!=', TransactionKindEnum::OPENING_BALANCE->value)
            ->whereDate('transaction_date', '<=', $toDate->toDateString());

        $this->applyTrackedItemOwnershipConstraintForOwners($query, 'transactions.tracked_item_id', [$userId]);

        if ($fromDate !== null) {
            $query->whereDate('transaction_date', '>=', $fromDate->toDateString());
        }

        return (float) $query
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN direction = ? THEN amount WHEN direction = ? THEN -amount ELSE 0 END), 0) as net_total',
                [TransactionDirectionEnum::INCOME->value, TransactionDirectionEnum::EXPENSE->value],
            )
            ->value('net_total');
    }

    protected function applyTrackedItemOwnershipConstraintForOwners(Builder $query, string $qualifiedColumn, array $ownerIds, string $relation = 'trackedItem'): Builder
    {
        return $query->where(function (Builder $trackedItemQuery) use ($qualifiedColumn, $relation, $ownerIds): void {
            $trackedItemQuery->whereNull($qualifiedColumn)
                ->orWhereHas($relation, function (Builder $ownedTrackedItemQuery) use ($ownerIds): void {
                    $ownedTrackedItemQuery->whereIn('user_id', $ownerIds !== [] ? $ownerIds : [0]);
                });
        });
    }

    protected function formatPeriodSummary(array $summary, string $currency): array
    {
        return [
            'starting_balance_total' => $this->formatMoney($summary['starting_balance_total_raw'], $currency),
            'starting_balance_total_raw' => $summary['starting_balance_total_raw'],
            'ending_balance_total' => $this->formatMoney($summary['ending_balance_total_raw'], $currency),
            'ending_balance_total_raw' => $summary['ending_balance_total_raw'],
            'income_total' => $this->formatMoney($summary['income_total_raw'], $currency),
            'income_total_raw' => $summary['income_total_raw'],
            'expense_total' => $this->formatMoney($summary['expense_total_raw'], $currency),
            'expense_total_raw' => $summary['expense_total_raw'],
            'net_total' => $this->formatMoney($summary['net_total_raw'], $currency),
            'net_total_raw' => $summary['net_total_raw'],
            'transactions_count' => $summary['transactions_count'],
        ];
    }

    protected function formatCategoryRows(array $rows, string $currency): array
    {
        return array_map(fn (array $row): array => [
            ...$row,
            'total_amount' => $this->formatMoney($row['total_amount_raw'], $currency),
        ], $rows);
    }

    protected function formatMovementRows(array $rows, string $currency): array
    {
        return array_map(fn (array $row): array => [
            ...$row,
            'amount' => $this->formatSignedMoney((float) $row['amount_raw'], $currency),
        ], $rows);
    }

    protected function monthLabel(int $month): string
    {
        return (string) __('dashboard.months.'.$month);
    }

    protected function normalizeCurrencyCode(?string $currencyCode, string $fallback): string
    {
        $normalizedCurrencyCode = strtoupper(trim((string) $currencyCode));

        return $normalizedCurrencyCode !== '' ? $normalizedCurrencyCode : strtoupper(trim($fallback));
    }

    protected function formatMoney(float $value, string $currency): string
    {
        $formatter = new \NumberFormatter(app()->getLocale() === 'en' ? 'en_GB' : 'it_IT', \NumberFormatter::CURRENCY);
        $formatted = $formatter->formatCurrency($value, $currency);

        return $formatted !== false ? $formatted : number_format($value, 2, ',', '.')." {$currency}";
    }

    protected function formatSignedMoney(float $value, string $currency): string
    {
        $formatted = $this->formatMoney(abs($value), $currency);

        return ($value >= 0.0 ? '+' : '-').$formatted;
    }
}
