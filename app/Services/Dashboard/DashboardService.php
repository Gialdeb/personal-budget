<?php

namespace App\Services\Dashboard;

use App\Enums\RecurringOccurrenceStatusEnum;
use App\Enums\ScheduledEntryStatusEnum;
use App\Enums\TransactionDirectionEnum;
use App\Enums\TransactionStatusEnum;
use App\Models\Account;
use App\Models\Budget;
use App\Models\RecurringEntryOccurrence;
use App\Models\ScheduledEntry;
use App\Models\Transaction;
use App\Models\User;
use App\Supports\PeriodOptions;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    public function build(User $user, int $year, ?int $month = null): array
    {
        return [
            'filters' => $this->getFiltersData($user, $year, $month),
            'settings' => $this->getSettingsData($user),
            'overview' => $this->getOverview($user, $year, $month),
            'monthly_trend' => $this->getMonthlyTrend($user, $year, $month),
            'expense_by_category' => $this->getExpenseByCategory($user, $year, $month),
            'budget_vs_actual' => $this->getBudgetVsActual($user, $year, $month),
            'accounts_summary' => $this->getAccountsSummary($user, $year, $month),
            'recurring_summary' => $this->getRecurringSummary($user, $year, $month),
            'scheduled_summary' => $this->getScheduledSummary($user, $year, $month),
            'income_by_category' => $this->getIncomeByCategory($user, $year, $month),
            'merchant_breakdown' => $this->getMerchantBreakdown($user, $year, $month),
            'notifications' => $this->getNotifications($user, $year, $month),
        ];
    }

    protected function getFiltersData(User $user, int $year, ?int $month): array
    {
        $yearExpression = $this->datePartExpression('year', 'transaction_date');

        $availableYears = $user->years()
            ->orderBy('year')
            ->pluck('year')
            ->map(fn ($item) => (int) $item)
            ->all();

        if (empty($availableYears)) {
            $availableYears = [$year];
        }

        return [
            'year' => $year,
            'month' => $month,
            'available_years' => PeriodOptions::yearOptions($availableYears),
            'month_options' => PeriodOptions::monthOptions(),
        ];
    }

    protected function getSettingsData(User $user): array
    {
        $settings = $user->settings;

        return [
            'active_year' => $settings?->active_year,
            'base_currency' => $settings?->base_currency ?? 'EUR',
            'dashboard' => $settings?->settings['dashboard'] ?? [],
        ];
    }

    protected function getOverview(User $user, int $year, ?int $month): array
    {
        $incomeQuery = $this->baseTransactionPeriodQuery($user->id, $year, $month)
            ->where('transactions.direction', TransactionDirectionEnum::INCOME->value);

        $expenseQuery = $this->baseTransactionPeriodQuery($user->id, $year, $month)
            ->where('transactions.direction', TransactionDirectionEnum::EXPENSE->value);

        $incomeTotal = (float) $incomeQuery->sum('amount');
        $expenseTotal = (float) $expenseQuery->sum('amount');
        $netTotal = $incomeTotal - $expenseTotal;

        $budgetQuery = Budget::query()
            ->where('user_id', $user->id)
            ->where('year', $year);

        if ($month !== null) {
            $budgetQuery->where('month', $month);
        }

        $budgetTotal = (float) $budgetQuery->sum('amount');

        $balanceSnapshot = $this->getBalanceSnapshot($user, $year, $month);

        $savingsMode = $user->settings?->settings['dashboard']['savings_mode'] ?? 'net_remaining';

        $savingsRate = 0.0;

        if ($incomeTotal > 0) {
            if ($savingsMode === 'allocated_savings') {
                $allocatedSavings = (float) $this->baseTransactionPeriodQuery($user->id, $year, $month)
                    ->whereHas('category', function ($query) {
                        $query->where('group_type', 'saving');
                    })
                    ->sum('amount');

                $savingsRate = round(($allocatedSavings / $incomeTotal) * 100, 2);
            } else {
                $savingsRate = round((($incomeTotal - $expenseTotal) / $incomeTotal) * 100, 2);
            }
        }

        return [
            'income_total' => round($incomeTotal, 2),
            'expense_total' => round($expenseTotal, 2),
            'net_total' => round($netTotal, 2),
            'budget_total' => round($budgetTotal, 2),
            'current_balance_total' => round($balanceSnapshot['current_balance_total'], 2),
            'previous_balance_total' => round($balanceSnapshot['previous_balance_total'], 2),
            'actual_vs_budget_delta' => round($budgetTotal - $expenseTotal, 2),
            'transactions_count' => $this->baseTransactionPeriodQuery($user->id, $year, $month)->count(),
            'active_accounts_count' => $user->accounts()->where('is_active', true)->count(),
            'savings_rate' => $savingsRate,
            'savings_mode' => $savingsMode,
        ];
    }

    protected function getMonthlyTrend(User $user, int $year, ?int $month): array
    {
        if ($month !== null) {
            $dayExpression = $this->datePartExpression('day', 'transaction_date');

            $rows = $this->baseTransactionPeriodQuery($user->id, $year, $month)
                ->selectRaw("{$dayExpression} as day")
                ->selectRaw("
                    SUM(CASE WHEN direction = 'income' THEN amount ELSE 0 END) as income_total
                ")
                ->selectRaw("
                    SUM(CASE WHEN direction = 'expense' THEN amount ELSE 0 END) as expense_total
                ")
                ->groupBy('day')
                ->orderBy('day')
                ->get();

            return $rows->map(function ($row) {
                $income = (float) $row->income_total;
                $expense = (float) $row->expense_total;

                return [
                    'label' => (int) $row->day,
                    'income_total' => round($income, 2),
                    'expense_total' => round($expense, 2),
                    'net_total' => round($income - $expense, 2),
                ];
            })->values()->all();
        }

        $monthExpression = $this->datePartExpression('month', 'transaction_date');

        $rows = Transaction::query()
            ->where('user_id', $user->id)
            ->whereYear('transaction_date', $year)
            ->selectRaw("{$monthExpression} as month")
            ->selectRaw("
                SUM(CASE WHEN direction = 'income' THEN amount ELSE 0 END) as income_total
            ")
            ->selectRaw("
                SUM(CASE WHEN direction = 'expense' THEN amount ELSE 0 END) as expense_total
            ")
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->keyBy('month');

        $result = [];

        foreach (range(1, 12) as $m) {
            $income = isset($rows[$m]) ? (float) $rows[$m]->income_total : 0.0;
            $expense = isset($rows[$m]) ? (float) $rows[$m]->expense_total : 0.0;

            $result[] = [
                'label' => $m,
                'income_total' => round($income, 2),
                'expense_total' => round($expense, 2),
                'net_total' => round($income - $expense, 2),
            ];
        }

        return $result;
    }

    protected function getExpenseByCategory(User $user, int $year, ?int $month): array
    {
        $rows = $this->baseTransactionPeriodQuery($user->id, $year, $month)
            ->where('transactions.direction', TransactionDirectionEnum::EXPENSE->value)
            ->leftJoin('categories', 'transactions.category_id', '=', 'categories.id')
            ->select(
                'transactions.category_id',
                DB::raw("COALESCE(categories.name, 'Senza categoria') as category_name"),
                DB::raw('SUM(transactions.amount) as total_amount')
            )
            ->groupBy('transactions.category_id', 'categories.name')
            ->orderByDesc('total_amount')
            ->get();

        return $rows->map(function ($row) {
            return [
                'category_id' => $row->category_id,
                'category_name' => $row->category_name,
                'total_amount' => round((float) $row->total_amount, 2),
            ];
        })->values()->all();
    }

    protected function getBudgetVsActual(User $user, int $year, ?int $month): array
    {
        $budgetQuery = Budget::query()
            ->where('budgets.user_id', $user->id)
            ->where('budgets.year', $year)
            ->leftJoin('categories', 'budgets.category_id', '=', 'categories.id')
            ->leftJoin('scopes', 'budgets.scope_id', '=', 'scopes.id')
            ->select(
                'budgets.category_id',
                'budgets.scope_id',
                DB::raw("COALESCE(categories.name, 'Senza categoria') as category_name"),
                DB::raw("COALESCE(scopes.name, 'Generale') as scope_name"),
                DB::raw('SUM(budgets.amount) as budget_total')
            )
            ->groupBy('budgets.category_id', 'budgets.scope_id', 'categories.name', 'scopes.name');

        if ($month !== null) {
            $budgetQuery->where('budgets.month', $month);
        }

        $budgetRows = $budgetQuery->get();

        $actualRows = $this->baseTransactionPeriodQuery($user->id, $year, $month)
            ->where('transactions.direction', TransactionDirectionEnum::EXPENSE->value)
            ->select(
                'transactions.category_id',
                'transactions.scope_id',
                DB::raw('SUM(transactions.amount) as actual_total')
            )
            ->groupBy('transactions.category_id', 'transactions.scope_id')
            ->get();

        $actualMap = $actualRows->keyBy(function ($row) {
            return ($row->category_id ?? 'null').'|'.($row->scope_id ?? 'null');
        });

        return $budgetRows->map(function ($row) use ($actualMap) {
            $key = ($row->category_id ?? 'null').'|'.($row->scope_id ?? 'null');
            $actual = (float) ($actualMap[$key]->actual_total ?? 0);
            $budget = (float) $row->budget_total;

            return [
                'category_id' => $row->category_id,
                'scope_id' => $row->scope_id,
                'category_name' => $row->category_name,
                'scope_name' => $row->scope_name,
                'budget_total' => round($budget, 2),
                'actual_total' => round($actual, 2),
                'delta' => round($budget - $actual, 2),
                'percentage_used' => $budget > 0 ? round(($actual / $budget) * 100, 2) : 0.0,
            ];
        })->values()->all();
    }

    protected function getAccountsSummary(User $user, int $year, ?int $month): array
    {
        $accounts = Account::query()
            ->where('accounts.user_id', $user->id)
            ->where('accounts.is_active', true)
            ->leftJoin('banks', 'accounts.bank_id', '=', 'banks.id')
            ->select(
                'accounts.id',
                'accounts.name',
                'accounts.currency',
                'accounts.current_balance',
                'accounts.opening_balance',
                DB::raw('banks.name as bank_name')
            )
            ->orderBy('accounts.name')
            ->get();

        return $accounts->map(function ($account) use ($user, $year, $month) {
            $transactions = $this->baseTransactionPeriodQuery($user->id, $year, $month)
                ->where('transactions.account_id', $account->id);

            $income = (float) (clone $transactions)
                ->where('transactions.direction', TransactionDirectionEnum::INCOME->value)
                ->sum('amount');

            $expense = (float) (clone $transactions)
                ->where('transactions.direction', TransactionDirectionEnum::EXPENSE->value)
                ->sum('amount');

            $transactionsCount = (clone $transactions)->count();

            return [
                'account_id' => $account->id,
                'account_name' => $account->name,
                'bank_name' => $account->bank_name,
                'currency' => $account->currency,
                'opening_balance' => round((float) ($account->opening_balance ?? 0), 2),
                'current_balance' => round((float) ($account->current_balance ?? 0), 2),
                'income_total' => round($income, 2),
                'expense_total' => round($expense, 2),
                'net_total' => round($income - $expense, 2),
                'transactions_count' => $transactionsCount,
            ];
        })->values()->all();
    }

    protected function getRecurringSummary(User $user, int $year, ?int $month): array
    {
        $query = RecurringEntryOccurrence::query()
            ->whereHas('recurringEntry', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->whereYear('expected_date', $year);

        if ($month !== null) {
            $query->whereMonth('expected_date', $month);
        }

        $occurrences = $query->get();

        $planned = $occurrences->where('status', RecurringOccurrenceStatusEnum::PLANNED)->count();
        $due = $occurrences->where('status', RecurringOccurrenceStatusEnum::DUE)->count();
        $matched = $occurrences->where('status', RecurringOccurrenceStatusEnum::MATCHED)->count();
        $converted = $occurrences->where('status', RecurringOccurrenceStatusEnum::CONVERTED)->count();
        $cancelled = $occurrences->where('status', RecurringOccurrenceStatusEnum::CANCELLED)->count();
        $skipped = $occurrences->where('status', RecurringOccurrenceStatusEnum::SKIPPED)->count();

        $overdueOccurrences = $occurrences->filter(function ($occurrence) {
            return in_array($occurrence->status->value, [
                RecurringOccurrenceStatusEnum::PLANNED->value,
                RecurringOccurrenceStatusEnum::DUE->value,
            ], true) && $occurrence->expected_date->isPast();
        });

        $overdueTotal = (float) $overdueOccurrences->sum(function ($occurrence) {
            return (float) ($occurrence->expected_amount ?? 0);
        });

        return [
            'planned_count' => $planned,
            'due_count' => $due,
            'matched_count' => $matched,
            'converted_count' => $converted,
            'cancelled_count' => $cancelled,
            'skipped_count' => $skipped,
            'overdue_count' => $overdueOccurrences->count(),
            'overdue_total' => round($overdueTotal, 2),
        ];
    }

    protected function getScheduledSummary(User $user, int $year, ?int $month): array
    {
        $query = ScheduledEntry::query()
            ->where('user_id', $user->id)
            ->whereYear('scheduled_date', $year);

        if ($month !== null) {
            $query->whereMonth('scheduled_date', $month);
        }

        $entries = $query->get();

        $planned = $entries->where('status', ScheduledEntryStatusEnum::PLANNED)->count();
        $due = $entries->where('status', ScheduledEntryStatusEnum::DUE)->count();
        $matched = $entries->where('status', ScheduledEntryStatusEnum::MATCHED)->count();
        $converted = $entries->where('status', ScheduledEntryStatusEnum::CONVERTED)->count();
        $cancelled = $entries->where('status', ScheduledEntryStatusEnum::CANCELLED)->count();

        $upcoming = $query->whereDate('scheduled_date', '>=', now()->toDateString())
            ->orderBy('scheduled_date')
            ->limit(5)
            ->get()
            ->map(function ($entry) {
                return [
                    'id' => $entry->id,
                    'title' => $entry->title,
                    'scheduled_date' => $entry->scheduled_date->toDateString(),
                    'expected_amount' => round((float) ($entry->expected_amount ?? 0), 2),
                    'status' => $entry->status->value,
                ];
            })
            ->values()
            ->all();

        return [
            'planned_count' => $planned,
            'due_count' => $due,
            'matched_count' => $matched,
            'converted_count' => $converted,
            'cancelled_count' => $cancelled,
            'upcoming' => $upcoming,
        ];
    }

    protected function getBalanceSnapshot(User $user, int $year, ?int $month): array
    {
        [$periodStart, $periodEnd] = $this->resolvePeriodBounds($year, $month);

        $activeAccountIds = Account::query()
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->pluck('id');

        if ($activeAccountIds->isEmpty()) {
            return [
                'current_balance_total' => 0.0,
                'previous_balance_total' => 0.0,
            ];
        }

        $openingBalanceTotal = (float) Account::query()
            ->whereIn('id', $activeAccountIds)
            ->sum('opening_balance');

        $currentBalanceTotal = $openingBalanceTotal + $this->sumNetTransactionsUntil(
            $user->id,
            $activeAccountIds->all(),
            $periodEnd
        );

        $previousBalanceTotal = $openingBalanceTotal + $this->sumNetTransactionsBefore(
            $user->id,
            $activeAccountIds->all(),
            $periodStart
        );

        return [
            'current_balance_total' => $currentBalanceTotal,
            'previous_balance_total' => $previousBalanceTotal,
        ];
    }

    protected function baseTransactionPeriodQuery(int $userId, int $year, ?int $month): Builder
    {
        $query = Transaction::query()
            ->where('transactions.user_id', $userId)
            ->whereYear('transactions.transaction_date', $year);

        if ($month !== null) {
            $query->whereMonth('transactions.transaction_date', $month);
        }

        return $query;
    }

    protected function getIncomeByCategory(User $user, int $year, ?int $month): array
    {
        $rows = $this->baseTransactionPeriodQuery($user->id, $year, $month)
            ->where('transactions.direction', TransactionDirectionEnum::INCOME->value)
            ->leftJoin('categories', 'transactions.category_id', '=', 'categories.id')
            ->select(
                'transactions.category_id',
                DB::raw("COALESCE(categories.name, 'Senza categoria') as category_name"),
                DB::raw('SUM(transactions.amount) as total_amount')
            )
            ->groupBy('transactions.category_id', 'categories.name')
            ->orderByDesc('total_amount')
            ->get();

        return $rows->map(fn ($row) => [
            'category_id' => $row->category_id,
            'category_name' => $row->category_name,
            'total_amount' => round((float) $row->total_amount, 2),
        ])->values()->all();
    }

    protected function getMerchantBreakdown(User $user, int $year, ?int $month): array
    {
        $rows = $this->baseTransactionPeriodQuery($user->id, $year, $month)
            ->where('transactions.direction', TransactionDirectionEnum::EXPENSE->value)
            ->leftJoin('merchants', 'transactions.merchant_id', '=', 'merchants.id')
            ->select(
                'transactions.merchant_id',
                DB::raw("COALESCE(merchants.name, 'Senza merchant') as merchant_name"),
                DB::raw('SUM(transactions.amount) as total_amount'),
                DB::raw('COUNT(transactions.id) as transactions_count')
            )
            ->groupBy('transactions.merchant_id', 'merchants.name')
            ->orderByDesc('total_amount')
            ->limit(10)
            ->get();

        return $rows->map(fn ($row) => [
            'merchant_id' => $row->merchant_id,
            'merchant_name' => $row->merchant_name,
            'total_amount' => round((float) $row->total_amount, 2),
            'transactions_count' => (int) $row->transactions_count,
        ])->values()->all();
    }

    protected function getNotifications(User $user, int $year, ?int $month): array
    {
        $recurringSummary = $this->getRecurringSummary($user, $year, $month);
        $scheduledSummary = $this->getScheduledSummary($user, $year, $month);

        $reviewNeededCount = $this->baseTransactionPeriodQuery($user->id, $year, $month)
            ->where('transactions.status', TransactionStatusEnum::REVIEW_NEEDED->value)
            ->count();

        return [
            'review_needed_count' => $reviewNeededCount,
            'overdue_recurring_count' => $recurringSummary['overdue_count'] ?? 0,
            'overdue_recurring_total' => $recurringSummary['overdue_total'] ?? 0.0,
            'planned_scheduled_count' => $scheduledSummary['planned_count'] ?? 0,
            'due_scheduled_count' => $scheduledSummary['due_count'] ?? 0,
        ];
    }

    protected function resolvePeriodBounds(int $year, ?int $month): array
    {
        $periodStart = $month === null
            ? CarbonImmutable::create($year, 1, 1)->startOfYear()
            : CarbonImmutable::create($year, $month, 1)->startOfMonth();

        $periodEnd = $month === null
            ? $periodStart->endOfYear()
            : $periodStart->endOfMonth();

        return [$periodStart, $periodEnd];
    }

    protected function sumNetTransactionsUntil(int $userId, array $accountIds, CarbonImmutable $date): float
    {
        return (float) Transaction::query()
            ->where('user_id', $userId)
            ->whereIn('account_id', $accountIds)
            ->whereDate('transaction_date', '<=', $date->toDateString())
            ->selectRaw(
                <<<'SQL'
                    COALESCE(SUM(
                        CASE
                            WHEN direction = ? THEN amount
                            WHEN direction = ? THEN -amount
                            ELSE 0
                        END
                    ), 0) as net_total
                SQL,
                [
                    TransactionDirectionEnum::INCOME->value,
                    TransactionDirectionEnum::EXPENSE->value,
                ]
            )
            ->value('net_total');
    }

    protected function sumNetTransactionsBefore(int $userId, array $accountIds, CarbonImmutable $date): float
    {
        return (float) Transaction::query()
            ->where('user_id', $userId)
            ->whereIn('account_id', $accountIds)
            ->whereDate('transaction_date', '<', $date->toDateString())
            ->selectRaw(
                <<<'SQL'
                    COALESCE(SUM(
                        CASE
                            WHEN direction = ? THEN amount
                            WHEN direction = ? THEN -amount
                            ELSE 0
                        END
                    ), 0) as net_total
                SQL,
                [
                    TransactionDirectionEnum::INCOME->value,
                    TransactionDirectionEnum::EXPENSE->value,
                ]
            )
            ->value('net_total');
    }

    protected function datePartExpression(string $part, string $column): string
    {
        $driver = DB::connection()->getDriverName();

        return match ($driver) {
            'sqlite' => match ($part) {
                'year' => "CAST(strftime('%Y', {$column}) AS INTEGER)",
                'month' => "CAST(strftime('%m', {$column}) AS INTEGER)",
                'day' => "CAST(strftime('%d', {$column}) AS INTEGER)",
            },
            'mysql', 'mariadb' => match ($part) {
                'year' => "YEAR({$column})",
                'month' => "MONTH({$column})",
                'day' => "DAY({$column})",
            },
            default => 'EXTRACT('.strtoupper($part)." FROM {$column})::int",
        };
    }
}
