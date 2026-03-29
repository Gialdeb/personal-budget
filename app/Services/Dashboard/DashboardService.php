<?php

namespace App\Services\Dashboard;

use App\Enums\CategoryGroupTypeEnum;
use App\Enums\RecurringOccurrenceStatusEnum;
use App\Enums\ScheduledEntryStatusEnum;
use App\Enums\TransactionDirectionEnum;
use App\Enums\TransactionKindEnum;
use App\Enums\TransactionStatusEnum;
use App\Models\Account;
use App\Models\Budget;
use App\Models\Category;
use App\Models\RecurringEntryOccurrence;
use App\Models\ScheduledEntry;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Accounts\AccessibleAccountsQuery;
use App\Services\Categories\SharedAccountCategoryTaxonomyService;
use App\Services\UserYearService;
use App\Supports\PeriodOptions;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    public function __construct(
        protected UserYearService $userYearService,
        protected AccessibleAccountsQuery $accessibleAccountsQuery,
        protected SharedAccountCategoryTaxonomyService $sharedAccountCategoryTaxonomyService,
    ) {}

    public function build(
        User $user,
        int $year,
        ?int $month = null,
        string $accountScope = 'all',
        ?string $accountUuid = null,
    ): array {
        $accountContext = $this->resolveAccountContext($user, $accountScope, $accountUuid);

        $dashboard = [
            'filters' => $this->getFiltersData($user, $year, $month, $accountContext),
            'settings' => $this->getSettingsData($user),
            'overview' => $this->getOverview($user, $year, $month, $accountContext),
            'pending_actions' => $this->getPendingActions($year, $month, $accountContext),
            'monthly_trend' => $this->getMonthlyTrend($year, $month, $accountContext),
            'expense_by_category' => $this->getExpenseByCategory($year, $month, $accountContext),
            'budget_vs_actual' => $this->getBudgetVsActual($year, $month, $accountContext),
            'parent_category_budget_status' => $this->getParentCategoryBudgetStatus($year, $month, $accountContext),
            'accounts_summary' => $this->getAccountsSummary($year, $month, $accountContext),
            'recurring_summary' => $this->getRecurringSummary($year, $month, $accountContext),
            'scheduled_summary' => $this->getScheduledSummary($year, $month, $accountContext),
            'income_by_category' => $this->getIncomeByCategory($year, $month, $accountContext),
            'merchant_breakdown' => $this->getMerchantBreakdown($year, $month, $accountContext),
            'notifications' => $this->getNotifications($user, $year, $month, $accountContext),
            'year_suggestion' => $this->userYearService->buildNextYearSuggestion($user, $year),
        ];

        return $this->formatDashboardPayload(
            $dashboard,
            $dashboard['settings']['base_currency'] ?? 'EUR'
        );
    }

    protected function getFiltersData(User $user, int $year, ?int $month, array $accountContext): array
    {
        $availableYears = $this->userYearService->availableYears($user);
        $hasSharedAccessibleAccounts = $this->accessibleAccountsQuery
            ->get($user, 'shared')
            ->isNotEmpty();

        if (empty($availableYears)) {
            $availableYears = [$year];
        }

        return [
            'year' => $year,
            'month' => $month,
            'available_years' => PeriodOptions::yearOptions($availableYears),
            'month_options' => PeriodOptions::monthOptions(),
            'account_scope' => $accountContext['scope'],
            'account_uuid' => $accountContext['account_uuid'],
            'show_account_scope_filter' => $hasSharedAccessibleAccounts,
            'account_scope_options' => $this->accessibleAccountsQuery->dashboardScopeOptions(),
            'account_options' => $this->accessibleAccountsQuery->dashboardFilterOptions($user),
        ];
    }

    protected function getSettingsData(User $user): array
    {
        $settings = $user->settings;

        return [
            'active_year' => $settings?->active_year,
            'base_currency' => $user->base_currency_code,
            'dashboard' => $settings?->settings['dashboard'] ?? [],
        ];
    }

    protected function getOverview(User $user, int $year, ?int $month, array $accountContext): array
    {
        $incomeQuery = $this->baseTransactionPeriodQuery(
            $accountContext['account_ids'],
            $accountContext['owner_ids'],
            $year,
            $month,
        )
            ->where('transactions.direction', TransactionDirectionEnum::INCOME->value);

        $expenseQuery = $this->baseTransactionPeriodQuery(
            $accountContext['account_ids'],
            $accountContext['owner_ids'],
            $year,
            $month,
        )
            ->where('transactions.direction', TransactionDirectionEnum::EXPENSE->value);

        $incomeTotal = (float) $incomeQuery->sum('amount');
        $expenseTotal = (float) $expenseQuery->sum('amount');
        $netTotal = $incomeTotal - $expenseTotal;

        $budgetTotal = (float) $this->resolvedBudgetComparisonRows($accountContext, $year, $month)
            ->sum('budget_total');

        $balanceSnapshot = $this->getBalanceSnapshot($year, $month, $accountContext);

        $savingsMode = $user->settings?->settings['dashboard']['savings_mode'] ?? 'net_remaining';

        $savingsRate = 0.0;

        if ($incomeTotal > 0) {
            if ($savingsMode === 'allocated_savings') {
                $allocatedSavings = (float) $this->baseTransactionPeriodQuery(
                    $accountContext['account_ids'],
                    $accountContext['owner_ids'],
                    $year,
                    $month,
                )
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
            'transactions_count' => $this->baseTransactionPeriodQuery(
                $accountContext['account_ids'],
                $accountContext['owner_ids'],
                $year,
                $month,
            )->count(),
            'active_accounts_count' => $accountContext['accounts']
                ->where('is_active', true)
                ->count(),
            'savings_rate' => $savingsRate,
            'savings_mode' => $savingsMode,
        ];
    }

    protected function getPendingActions(int $year, ?int $month, array $accountContext): array
    {
        $today = CarbonImmutable::today();

        $recurringItems = $this->baseRecurringOccurrencePeriodQuery(
            $accountContext['account_ids'],
            $accountContext['owner_ids'],
            $year,
            $month,
        )
            ->with([
                'recurringEntry.merchant:id,name',
                'recurringEntry.trackedItem:id,name',
                'recurringEntry.category:id,name',
                'recurringEntry.account:id,uuid',
            ])
            ->whereIn('status', [
                RecurringOccurrenceStatusEnum::PENDING->value,
                RecurringOccurrenceStatusEnum::GENERATED->value,
            ])
            ->orderBy('expected_date')
            ->get()
            ->map(function (RecurringEntryOccurrence $occurrence) use ($today): array {
                return [
                    'id' => 'recurring-'.$occurrence->id,
                    'title' => $this->resolveAgendaRecurringLabel($occurrence),
                    'date' => $occurrence->expected_date?->toDateString(),
                    'amount' => round((float) ($occurrence->expected_amount ?? 0), 2),
                    'status_key' => $this->resolvePendingActionStatusKey(
                        $occurrence->expected_date,
                        $occurrence->status->value,
                        'recurring',
                        $today,
                    ),
                    'action_url' => route('recurring-entries.show', $occurrence->recurringEntry->uuid),
                    'entry_kind' => 'recurring',
                ];
            });

        $scheduledItems = $this->baseScheduledPeriodQuery(
            $accountContext['account_ids'],
            $accountContext['owner_ids'],
            $year,
            $month,
        )
            ->with(['merchant:id,name', 'trackedItem:id,name', 'category:id,name', 'account:id,uuid'])
            ->whereIn('status', [
                ScheduledEntryStatusEnum::PLANNED->value,
                ScheduledEntryStatusEnum::DUE->value,
            ])
            ->orderBy('scheduled_date')
            ->get()
            ->map(function (ScheduledEntry $entry) use ($year, $month, $today): array {
                return [
                    'id' => 'scheduled-'.$entry->id,
                    'title' => $this->resolveAgendaScheduledLabel($entry),
                    'date' => $entry->scheduled_date?->toDateString(),
                    'amount' => round((float) ($entry->expected_amount ?? 0), 2),
                    'status_key' => $this->resolvePendingActionStatusKey(
                        $entry->scheduled_date,
                        $entry->status->value,
                        'scheduled',
                        $today,
                    ),
                    'action_url' => route('recurring-entries.index', array_filter([
                        'year' => $year,
                        'month' => $month,
                        'account_uuid' => $entry->account?->uuid,
                    ])),
                    'entry_kind' => 'scheduled',
                ];
            });

        $items = $scheduledItems
            ->concat($recurringItems)
            ->filter(fn (array $item): bool => filled($item['date']))
            ->sortBy('date')
            ->values();

        return [
            'total_count' => $items->count(),
            'items' => $items->take(3)->all(),
        ];
    }

    protected function getMonthlyTrend(int $year, ?int $month, array $accountContext): array
    {
        if ($month !== null) {
            $dayExpression = $this->datePartExpression('day', 'transaction_date');

            $rows = $this->baseTransactionPeriodQuery(
                $accountContext['account_ids'],
                $accountContext['owner_ids'],
                $year,
                $month,
            )
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

        $rows = $this->baseTransactionPeriodQuery(
            $accountContext['account_ids'],
            $accountContext['owner_ids'],
            $year,
            null,
        )
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

    protected function getExpenseByCategory(int $year, ?int $month, array $accountContext): array
    {
        $rows = $this->baseTransactionPeriodQuery(
            $accountContext['account_ids'],
            $accountContext['owner_ids'],
            $year,
            $month,
        )
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

    protected function getBudgetVsActual(int $year, ?int $month, array $accountContext): array
    {
        $rows = $this->resolvedBudgetComparisonRows($accountContext, $year, $month)
            ->map(function (array $row): array {
                $budget = (float) $row['budget_total'];
                $actual = (float) $row['actual_total'];

                return [
                    'category_id' => $row['category_id'],
                    'scope_id' => $row['scope_id'],
                    'category_name' => $row['category_name'],
                    'scope_name' => $row['scope_name'],
                    'parent_id' => $row['parent_id'],
                    'group_type' => $row['group_type'],
                    'budget_total' => round($budget, 2),
                    'actual_total' => round($actual, 2),
                    'delta' => round($budget - $actual, 2),
                    'percentage_used' => $budget > 0 ? round(($actual / $budget) * 100, 2) : 0.0,
                ];
            })
            ->values()
            ->all();

        return $this->mergeSemanticRootBudgetComparisonRows($rows, $accountContext);
    }

    protected function getAccountsSummary(int $year, ?int $month, array $accountContext): array
    {
        [$periodStart, $periodEnd] = $this->resolvePeriodBounds($year, $month);

        $accounts = $accountContext['accounts']->where('is_active', true)->values();

        return $accounts->map(function ($account) use ($periodEnd, $periodStart, $accountContext, $year, $month) {
            $bankName = $account->userBank?->name ?? $account->bank?->name;
            $transactions = $this->baseTransactionPeriodQuery(
                $accountContext['account_ids'],
                $accountContext['owner_ids'],
                $year,
                $month,
            )
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
                'bank_name' => $bankName,
                'currency' => $account->currency,
                'opening_balance' => round($this->resolveAccountOpeningBalance($account, $periodStart), 2),
                'current_balance' => round($this->resolveAccountBalanceAt($account, $periodEnd), 2),
                'income_total' => round($income, 2),
                'expense_total' => round($expense, 2),
                'net_total' => round($income - $expense, 2),
                'transactions_count' => $transactionsCount,
            ];
        })->values()->all();
    }

    protected function getParentCategoryBudgetStatus(int $year, ?int $month, array $accountContext): array
    {
        $rows = $this->resolvedBudgetComparisonRows($accountContext, $year, $month)
            ->filter(fn (array $row): bool => in_array(
                $row['group_type'],
                [
                    CategoryGroupTypeEnum::EXPENSE->value,
                    CategoryGroupTypeEnum::BILL->value,
                    CategoryGroupTypeEnum::DEBT->value,
                    CategoryGroupTypeEnum::TAX->value,
                    CategoryGroupTypeEnum::INVESTMENT->value,
                    CategoryGroupTypeEnum::SAVING->value,
                ],
                true,
            ))
            ->groupBy(fn (array $row): string => (string) $row['parent_semantic_key'])
            ->map(function (Collection $group): array {
                $first = $group->first();
                $budgetTotal = round((float) $group->sum('budget_total'), 2);
                $actualTotal = round((float) $group->sum('actual_total'), 2);

                return [
                    'category_id' => $first['parent_category_id'],
                    'category_name' => $first['parent_category_name'],
                    'budget_total' => $budgetTotal,
                    'actual_total' => $actualTotal,
                    'delta' => round($budgetTotal - $actualTotal, 2),
                    'percentage_used' => $budgetTotal > 0
                        ? round(($actualTotal / $budgetTotal) * 100, 2)
                        : ($actualTotal > 0 ? 100.0 : 0.0),
                ];
            })
            ->values()
            ->all();

        return $this->mergeSemanticRootParentCategoryRows($rows, collect(), $accountContext);
    }

    protected function getRecurringSummary(int $year, ?int $month, array $accountContext): array
    {
        $query = $this->baseRecurringOccurrencePeriodQuery(
            $accountContext['account_ids'],
            $accountContext['owner_ids'],
            $year,
            $month,
        );

        $occurrences = $query->get();
        [$referenceDate, $dueSoonLimit] = $this->resolveAgendaWindow($year, $month);
        $upcomingRecurringOccurrences = RecurringEntryOccurrence::query()
            ->whereHas('recurringEntry', function (Builder $query) use ($accountContext) {
                $query->whereIn('account_id', $accountContext['account_ids'] !== [] ? $accountContext['account_ids'] : [0]);

                $this->applyTrackedItemOwnershipConstraintForOwners(
                    $query,
                    'recurring_entries.tracked_item_id',
                    $accountContext['owner_ids'],
                );
            })
            ->whereIn('status', [
                RecurringOccurrenceStatusEnum::PENDING->value,
                RecurringOccurrenceStatusEnum::GENERATED->value,
            ])
            ->whereDate('expected_date', '>=', $referenceDate->toDateString())
            ->whereDate('expected_date', '<=', $dueSoonLimit->toDateString())
            ->count();

        $pending = $occurrences->where('status', RecurringOccurrenceStatusEnum::PENDING)->count();
        $generated = $occurrences->where('status', RecurringOccurrenceStatusEnum::GENERATED)->count();
        $completed = $occurrences->where('status', RecurringOccurrenceStatusEnum::COMPLETED)->count();
        $cancelled = $occurrences->where('status', RecurringOccurrenceStatusEnum::CANCELLED)->count();
        $skipped = $occurrences->where('status', RecurringOccurrenceStatusEnum::SKIPPED)->count();
        $refunded = $occurrences->where('status', RecurringOccurrenceStatusEnum::REFUNDED)->count();

        $overdueOccurrences = $occurrences->filter(function ($occurrence) {
            return $occurrence->status->value === RecurringOccurrenceStatusEnum::PENDING->value
                && $occurrence->expected_date->isPast();
        });

        $overdueTotal = (float) $overdueOccurrences->sum(function ($occurrence) {
            return (float) ($occurrence->expected_amount ?? 0);
        });

        return [
            'pending_count' => $pending,
            'generated_count' => $generated,
            'completed_count' => $completed,
            'cancelled_count' => $cancelled,
            'skipped_count' => $skipped,
            'refunded_count' => $refunded,
            'planned_count' => $upcomingRecurringOccurrences,
            'due_count' => $overdueOccurrences->count(),
            'matched_count' => $generated,
            'converted_count' => $completed,
            'overdue_count' => $overdueOccurrences->count(),
            'overdue_total' => round($overdueTotal, 2),
        ];
    }

    protected function getScheduledSummary(int $year, ?int $month, array $accountContext): array
    {
        $query = $this->baseScheduledPeriodQuery(
            $accountContext['account_ids'],
            $accountContext['owner_ids'],
            $year,
            $month,
        );

        $entries = $query->get();
        [$referenceDate, $dueSoonLimit] = $this->resolveAgendaWindow($year, $month);

        $planned = $entries->where('status', ScheduledEntryStatusEnum::PLANNED)->count();
        $due = $entries->where('status', ScheduledEntryStatusEnum::DUE)->count();
        $matched = $entries->where('status', ScheduledEntryStatusEnum::MATCHED)->count();
        $converted = $entries->where('status', ScheduledEntryStatusEnum::CONVERTED)->count();
        $cancelled = $entries->where('status', ScheduledEntryStatusEnum::CANCELLED)->count();
        $upcomingScheduledEntries = ScheduledEntry::query()
            ->with(['merchant:id,name', 'trackedItem:id,name', 'category:id,name'])
            ->whereIn('scheduled_entries.account_id', $accountContext['account_ids'] !== [] ? $accountContext['account_ids'] : [0])
            ->whereIn('scheduled_entries.status', [
                ScheduledEntryStatusEnum::PLANNED->value,
                ScheduledEntryStatusEnum::DUE->value,
            ])
            ->whereDate('scheduled_entries.scheduled_date', '>=', $referenceDate->toDateString())
            ->orderBy('scheduled_entries.scheduled_date')
            ->limit(10)
            ->get()
            ->map(function (ScheduledEntry $entry): array {
                return [
                    'id' => 'scheduled-'.$entry->id,
                    'display_label' => $this->resolveAgendaScheduledLabel($entry),
                    'scheduled_date' => $entry->scheduled_date?->toDateString(),
                    'expected_amount' => round((float) ($entry->expected_amount ?? 0), 2),
                    'status' => $entry->status->value,
                    'entry_kind' => 'scheduled',
                ];
            });

        $upcomingRecurringOccurrences = RecurringEntryOccurrence::query()
            ->with([
                'recurringEntry.merchant:id,name',
                'recurringEntry.trackedItem:id,name',
                'recurringEntry.category:id,name',
            ])
            ->whereHas('recurringEntry', function (Builder $query) use ($accountContext) {
                $query->whereIn('account_id', $accountContext['account_ids'] !== [] ? $accountContext['account_ids'] : [0]);
            })
            ->whereIn('status', [
                RecurringOccurrenceStatusEnum::PENDING->value,
                RecurringOccurrenceStatusEnum::GENERATED->value,
            ])
            ->whereDate('expected_date', '>=', $referenceDate->toDateString())
            ->orderBy('expected_date')
            ->limit(10)
            ->get()
            ->map(function (RecurringEntryOccurrence $occurrence): array {
                return [
                    'id' => 'recurring-'.$occurrence->id,
                    'display_label' => $this->resolveAgendaRecurringLabel($occurrence),
                    'scheduled_date' => $occurrence->expected_date?->toDateString(),
                    'expected_amount' => round((float) ($occurrence->expected_amount ?? 0), 2),
                    'status' => $occurrence->status->value,
                    'entry_kind' => 'recurring',
                ];
            });

        $upcoming = $upcomingScheduledEntries
            ->concat($upcomingRecurringOccurrences)
            ->filter(fn (array $item): bool => filled($item['scheduled_date']))
            ->sortBy('scheduled_date')
            ->take(5)
            ->values()
            ->all();

        $dueSoonCount = $upcomingScheduledEntries
            ->concat($upcomingRecurringOccurrences)
            ->filter(function (array $item) use ($referenceDate, $dueSoonLimit): bool {
                $date = CarbonImmutable::parse((string) $item['scheduled_date']);

                return $date->betweenIncluded($referenceDate, $dueSoonLimit);
            })
            ->count();

        return [
            'planned_count' => $planned,
            'due_count' => $dueSoonCount,
            'matched_count' => $matched,
            'converted_count' => $converted,
            'cancelled_count' => $cancelled,
            'upcoming' => $upcoming,
        ];
    }

    protected function getBalanceSnapshot(int $year, ?int $month, array $accountContext): array
    {
        [$periodStart, $periodEnd] = $this->resolvePeriodBounds($year, $month);

        $activeAccounts = $accountContext['accounts']->where('is_active', true)->values();

        if ($activeAccounts->isEmpty()) {
            return [
                'current_balance_total' => 0.0,
                'previous_balance_total' => 0.0,
            ];
        }

        $currentBalanceTotal = $activeAccounts->sum(
            fn (Account $account): float => $this->resolveAccountBalanceAt($account, $periodEnd)
        );
        $previousBalanceTotal = $activeAccounts->sum(
            fn (Account $account): float => $this->resolveAccountBalanceAt(
                $account,
                $periodStart->subDay()
            )
        );

        return [
            'current_balance_total' => $currentBalanceTotal,
            'previous_balance_total' => $previousBalanceTotal,
        ];
    }

    protected function baseTransactionPeriodQuery(
        array $accountIds,
        array $ownerIds,
        int $year,
        ?int $month,
    ): Builder {
        $query = Transaction::query()
            ->whereIn('transactions.account_id', $accountIds !== [] ? $accountIds : [0])
            ->where('transactions.kind', TransactionKindEnum::MANUAL->value)
            ->where('transactions.is_transfer', false)
            ->whereYear('transactions.transaction_date', $year);

        $this->applyTrackedItemOwnershipConstraintForOwners(
            $query,
            'transactions.tracked_item_id',
            $ownerIds
        );

        if ($month !== null) {
            $query->whereMonth('transactions.transaction_date', $month);
        }

        return $query;
    }

    protected function getIncomeByCategory(int $year, ?int $month, array $accountContext): array
    {
        $rows = $this->baseTransactionPeriodQuery(
            $accountContext['account_ids'],
            $accountContext['owner_ids'],
            $year,
            $month,
        )
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

    protected function getMerchantBreakdown(int $year, ?int $month, array $accountContext): array
    {
        $transactions = $this->baseTransactionPeriodQuery(
            $accountContext['account_ids'],
            $accountContext['owner_ids'],
            $year,
            $month,
        )
            ->where('transactions.direction', TransactionDirectionEnum::EXPENSE->value)
            ->with(['merchant:id,name', 'trackedItem:id,name', 'category:id,name'])
            ->get();

        return $transactions
            ->groupBy(function (Transaction $transaction): string {
                return $this->resolveDashboardPayeeLabel($transaction);
            })
            ->map(function (Collection $group, string $label): array {
                /** @var Transaction|null $sample */
                $sample = $group->first();

                return [
                    'merchant_id' => $sample?->merchant_id,
                    'merchant_name' => $label,
                    'display_label' => $label,
                    'total_amount' => round((float) $group->sum('amount'), 2),
                    'transactions_count' => $group->count(),
                ];
            })
            ->sortByDesc('total_amount')
            ->take(10)
            ->values()
            ->all();
    }

    protected function getNotifications(User $user, int $year, ?int $month, array $accountContext): array
    {
        $recurringSummary = $this->getRecurringSummary($year, $month, $accountContext);
        $scheduledSummary = $this->getScheduledSummary($year, $month, $accountContext);

        $reviewNeededCount = $this->baseTransactionPeriodQuery(
            $accountContext['account_ids'],
            $accountContext['owner_ids'],
            $year,
            $month,
        )
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

    protected function resolveAvailableYears(User $user): array
    {
        $years = $user->years()
            ->pluck('year')
            ->merge($this->pluckYearValues(
                $this->baseTransactionYearsQuery($user->id),
                'transaction_date'
            ))
            ->merge(
                $this->baseBudgetYearsQuery($user->id)
                    ->pluck('year')
            )
            ->merge($this->pluckYearValues(
                $this->baseScheduledYearsQuery($user->id),
                'scheduled_date'
            ))
            ->merge($this->pluckYearValues(
                $this->baseRecurringOccurrenceYearsQuery($user->id),
                'expected_date'
            ));

        return $years
            ->filter()
            ->map(fn ($year) => (int) $year)
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    protected function baseBudgetPeriodQuery(array $ownerIds, int $year, ?int $month): Builder
    {
        return Budget::query()
            ->whereIn('budgets.user_id', $ownerIds !== [] ? $ownerIds : [0])
            ->where('budgets.year', $year)
            ->when($month !== null, fn (Builder $query) => $query->where('budgets.month', $month));
    }

    /**
     * @param  array<int>  $accountIds
     * @param  array<int>  $ownerIds
     */
    protected function baseRecurringOccurrencePeriodQuery(array $accountIds, array $ownerIds, int $year, ?int $month): Builder
    {
        $query = RecurringEntryOccurrence::query()
            ->whereHas('recurringEntry', function (Builder $query) use ($accountIds, $ownerIds) {
                $query->whereIn('account_id', $accountIds !== [] ? $accountIds : [0]);

                $this->applyTrackedItemOwnershipConstraintForOwners(
                    $query,
                    'recurring_entries.tracked_item_id',
                    $ownerIds
                );
            })
            ->whereYear('expected_date', $year);

        if ($month !== null) {
            $query->whereMonth('expected_date', $month);
        }

        return $query;
    }

    /**
     * @param  array<int>  $accountIds
     * @param  array<int>  $ownerIds
     */
    protected function baseScheduledPeriodQuery(array $accountIds, array $ownerIds, int $year, ?int $month): Builder
    {
        $query = ScheduledEntry::query()
            ->whereIn('scheduled_entries.account_id', $accountIds !== [] ? $accountIds : [0])
            ->whereYear('scheduled_entries.scheduled_date', $year);

        $this->applyTrackedItemOwnershipConstraintForOwners(
            $query,
            'scheduled_entries.tracked_item_id',
            $ownerIds
        );

        if ($month !== null) {
            $query->whereMonth('scheduled_entries.scheduled_date', $month);
        }

        return $query;
    }

    protected function resolveAgendaWindow(int $year, ?int $month): array
    {
        [$periodStart] = $this->resolvePeriodBounds($year, $month);
        $today = CarbonImmutable::today();
        $referenceDate = $periodStart->greaterThan($today) ? $periodStart : $today;

        return [$referenceDate, $referenceDate->addDays(30)];
    }

    protected function resolvePendingActionStatusKey(
        ?CarbonImmutable $date,
        string $status,
        string $entryKind,
        CarbonImmutable $today,
    ): string {
        if ($entryKind === 'scheduled' && $status === ScheduledEntryStatusEnum::DUE->value) {
            return 'to_record';
        }

        if ($date === null) {
            return 'upcoming';
        }

        if ($date->isSameDay($today)) {
            return 'today';
        }

        if ($date->lt($today)) {
            return 'overdue';
        }

        return 'upcoming';
    }

    protected function resolveDashboardPayeeLabel(Transaction $transaction): string
    {
        return $this->resolveFallbackLabel(
            $transaction->merchant?->name,
            $transaction->trackedItem?->name,
            $transaction->description,
            $transaction->category?->name,
        );
    }

    protected function resolveAgendaScheduledLabel(ScheduledEntry $entry): string
    {
        return $this->resolveFallbackLabel(
            $entry->merchant?->name,
            $entry->trackedItem?->name,
            $entry->title ?: $entry->description,
            $entry->category?->name,
        );
    }

    protected function resolveAgendaRecurringLabel(RecurringEntryOccurrence $occurrence): string
    {
        $entry = $occurrence->recurringEntry;

        return $this->resolveFallbackLabel(
            $entry?->merchant?->name,
            $entry?->trackedItem?->name,
            $entry?->title ?: $entry?->description,
            $entry?->category?->name,
        );
    }

    protected function resolveFallbackLabel(
        ?string $merchantName,
        ?string $trackedItemName,
        ?string $detail,
        ?string $categoryName,
    ): string {
        foreach ([$merchantName, $trackedItemName, $detail, $categoryName] as $value) {
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return __('dashboard.agenda.unspecified');
    }

    protected function baseTransactionYearsQuery(int $userId): Builder
    {
        $query = Transaction::query()
            ->where('transactions.user_id', $userId);

        $this->applyTrackedItemOwnershipConstraint(
            $query,
            'transactions.tracked_item_id',
            $userId
        );

        return $query;
    }

    protected function baseBudgetYearsQuery(int $userId): Builder
    {
        $query = Budget::query()
            ->where('budgets.user_id', $userId);

        $this->applyTrackedItemOwnershipConstraint(
            $query,
            'budgets.tracked_item_id',
            $userId
        );

        return $query;
    }

    protected function baseScheduledYearsQuery(int $userId): Builder
    {
        $query = ScheduledEntry::query()
            ->where('scheduled_entries.user_id', $userId);

        $this->applyTrackedItemOwnershipConstraint(
            $query,
            'scheduled_entries.tracked_item_id',
            $userId
        );

        return $query;
    }

    protected function baseRecurringOccurrenceYearsQuery(int $userId): Builder
    {
        return RecurringEntryOccurrence::query()
            ->whereHas('recurringEntry', function (Builder $query) use ($userId) {
                $query->where('user_id', $userId);

                $this->applyTrackedItemOwnershipConstraint(
                    $query,
                    'recurring_entries.tracked_item_id',
                    $userId
                );
            });
    }

    protected function pluckYearValues(Builder $query, string $column): array
    {
        $yearExpression = $this->datePartExpression('year', $column);

        return $query->selectRaw("{$yearExpression} as year")
            ->distinct()
            ->pluck('year')
            ->all();
    }

    protected function applyTrackedItemOwnershipConstraintForOwners(
        Builder $query,
        string $qualifiedColumn,
        array $ownerIds,
        string $relation = 'trackedItem'
    ): Builder {
        return $query->where(function (Builder $trackedItemQuery) use (
            $qualifiedColumn,
            $relation,
            $ownerIds
        ) {
            $trackedItemQuery->whereNull($qualifiedColumn)
                ->orWhereHas($relation, function (Builder $ownedTrackedItemQuery) use ($ownerIds) {
                    $ownedTrackedItemQuery->whereIn('user_id', $ownerIds !== [] ? $ownerIds : [0]);
                });
        });
    }

    protected function applyTrackedItemOwnershipConstraint(
        Builder $query,
        string $qualifiedColumn,
        int $userId,
        string $relation = 'trackedItem'
    ): Builder {
        return $this->applyTrackedItemOwnershipConstraintForOwners(
            $query,
            $qualifiedColumn,
            [$userId],
            $relation,
        );
    }

    protected function resolveAccountContext(User $user, string $scope, ?string $accountUuid): array
    {
        $normalizedScope = in_array($scope, ['all', 'owned', 'shared'], true)
            ? $scope
            : 'all';

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
            'viewer_user_id' => $user->id,
            'owned_account_ids' => $accounts
                ->filter(fn (Account $account): bool => (bool) $account->getAttribute('is_owned'))
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->values()
                ->all(),
            'shared_account_ids' => $accounts
                ->filter(fn (Account $account): bool => (bool) $account->getAttribute('is_shared'))
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->values()
                ->all(),
            'account_ids' => $accounts->pluck('id')->map(fn ($id): int => (int) $id)->values()->all(),
            'owner_ids' => $accounts->pluck('user_id')->map(fn ($id): int => (int) $id)->unique()->values()->all(),
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    protected function resolvedBudgetComparisonRows(array $accountContext, int $year, ?int $month): Collection
    {
        $budgetRows = $this->resolvedVisibleBudgetRows($accountContext, $year, $month)->keyBy('key');
        $actualRows = $this->resolvedActualComparisonRows($accountContext, $year, $month)->keyBy('key');

        return $budgetRows->keys()
            ->merge($actualRows->keys())
            ->unique()
            ->map(function (string $key) use ($actualRows, $budgetRows): array {
                $budgetRow = $budgetRows->get($key);
                $actualRow = $actualRows->get($key);
                $reference = $budgetRow ?? $actualRow;

                return [
                    'key' => $key,
                    'category_id' => $reference['category_id'],
                    'scope_id' => $reference['scope_id'],
                    'category_name' => $reference['category_name'],
                    'scope_name' => $reference['scope_name'],
                    'parent_id' => $reference['parent_id'],
                    'group_type' => $reference['group_type'],
                    'parent_category_id' => $reference['parent_category_id'],
                    'parent_category_name' => $reference['parent_category_name'],
                    'parent_semantic_key' => $reference['parent_semantic_key'],
                    'budget_total' => round((float) ($budgetRow['budget_total'] ?? 0), 2),
                    'actual_total' => round((float) ($actualRow['actual_total'] ?? 0), 2),
                ];
            })
            ->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    protected function resolvedVisibleBudgetRows(array $accountContext, int $year, ?int $month): Collection
    {
        $personalRows = $this->baseBudgetPeriodQuery([$accountContext['viewer_user_id']], $year, $month)
            ->with(['category', 'scope', 'trackedItem'])
            ->whereHas('category', function (Builder $query): void {
                $query->whereNull('account_id');
            })
            ->get()
            ->filter(fn (Budget $budget): bool => $this->budgetUsesVisibleTrackedItem($budget, $accountContext))
            ->map(fn (Budget $budget): ?array => $this->mapBudgetRowForReferenceCategory(
                $budget,
                $budget->category
            ))
            ->filter();

        $sharedReferenceCategoryIds = $this->sharedReferenceCategoryIdsFromTransactions(
            $accountContext,
            $year,
            $month,
        );

        if ($sharedReferenceCategoryIds === []) {
            return $this->groupBudgetRowsBySemanticKey($personalRows);
        }

        $sharedFallbackRows = $this->baseBudgetPeriodQuery($accountContext['owner_ids'], $year, $month)
            ->with(['category', 'scope', 'trackedItem'])
            ->whereHas('category', function (Builder $query) use ($sharedReferenceCategoryIds): void {
                $query->whereNull('account_id')
                    ->whereIn('id', $sharedReferenceCategoryIds);
            })
            ->get()
            ->filter(fn (Budget $budget): bool => $this->budgetUsesVisibleTrackedItem($budget, $accountContext))
            ->map(fn (Budget $budget): ?array => $this->mapBudgetRowForReferenceCategory(
                $budget,
                $budget->category
            ))
            ->filter();

        $primaryRows = $this->groupBudgetRowsBySemanticKey($personalRows)->keyBy('key');
        $fallbackRows = $this->groupBudgetRowsBySemanticKey($sharedFallbackRows);

        foreach ($fallbackRows as $row) {
            if (! $primaryRows->has($row['key'])) {
                $primaryRows->put($row['key'], $row);
            }
        }

        return $primaryRows->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    protected function resolvedActualComparisonRows(array $accountContext, int $year, ?int $month): Collection
    {
        return $this->baseTransactionPeriodQuery(
            $accountContext['account_ids'],
            $accountContext['owner_ids'],
            $year,
            $month,
        )
            ->where('transactions.direction', TransactionDirectionEnum::EXPENSE->value)
            ->with(['account', 'category', 'scope'])
            ->get()
            ->map(function (Transaction $transaction): ?array {
                $referenceCategory = $this->referenceCategoryForTransaction($transaction);

                if (! $referenceCategory instanceof Category) {
                    return null;
                }

                return $this->buildComparisonRowMetadata(
                    $referenceCategory,
                    $transaction->scope_id,
                    $transaction->scope?->name,
                    [
                        'actual_total' => round((float) $transaction->amount, 2),
                    ],
                );
            })
            ->filter()
            ->groupBy('key')
            ->map(function (Collection $group): array {
                $first = $group->first();

                return [
                    ...$first,
                    'actual_total' => round((float) $group->sum('actual_total'), 2),
                ];
            })
            ->values();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return Collection<int, array<string, mixed>>
     */
    protected function groupBudgetRowsBySemanticKey(Collection $rows): Collection
    {
        return $rows
            ->groupBy('key')
            ->map(function (Collection $group): array {
                $first = $group->first();

                return [
                    ...$first,
                    'budget_total' => round((float) $group->sum('budget_total'), 2),
                ];
            })
            ->values();
    }

    protected function mapBudgetRowForReferenceCategory(Budget $budget, ?Category $referenceCategory): ?array
    {
        if (! $referenceCategory instanceof Category) {
            return null;
        }

        return $this->buildComparisonRowMetadata(
            $referenceCategory,
            $budget->scope_id,
            $budget->scope?->name,
            [
                'budget_total' => round((float) $budget->amount, 2),
            ],
        );
    }

    protected function budgetUsesVisibleTrackedItem(Budget $budget, array $accountContext): bool
    {
        if ($budget->tracked_item_id === null) {
            return true;
        }

        $trackedItem = $budget->trackedItem;

        if ($trackedItem === null) {
            return false;
        }

        if ($trackedItem->account_id !== null) {
            return in_array((int) $trackedItem->account_id, $accountContext['shared_account_ids'], true);
        }

        return (int) $trackedItem->user_id === (int) $accountContext['viewer_user_id'];
    }

    protected function referenceCategoryForTransaction(Transaction $transaction): ?Category
    {
        $category = $transaction->category;

        if (! $category instanceof Category) {
            return null;
        }

        if ($category->account_id === null) {
            return $category;
        }

        $account = $transaction->account;

        if (! $account instanceof Account) {
            return null;
        }

        return $this->sharedAccountCategoryTaxonomyService
            ->findSourceCategoryForSharedCategory($account, $category);
    }

    /**
     * @return array<int, int>
     */
    protected function sharedReferenceCategoryIdsFromTransactions(array $accountContext, int $year, ?int $month): array
    {
        if ($accountContext['shared_account_ids'] === []) {
            return [];
        }

        return Transaction::query()
            ->whereIn('account_id', $accountContext['shared_account_ids'])
            ->where('kind', TransactionKindEnum::MANUAL->value)
            ->where('is_transfer', false)
            ->whereYear('transaction_date', $year)
            ->when($month !== null, fn (Builder $query) => $query->whereMonth('transaction_date', $month))
            ->with(['account', 'category'])
            ->get()
            ->map(fn (Transaction $transaction): ?int => $this->referenceCategoryForTransaction($transaction)?->id)
            ->filter()
            ->map(fn ($categoryId): int => (int) $categoryId)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    protected function buildComparisonRowMetadata(
        Category $category,
        ?int $scopeId,
        ?string $scopeName,
        array $extra = [],
    ): array {
        [$parentCategoryId, $parentCategoryName, $parentSemanticKey] = $this->parentDescriptorForCategory($category);

        return [
            'key' => $this->categorySemanticKey($category).'|scope:'.($scopeId ?? 'null'),
            'category_id' => $category->id,
            'scope_id' => $scopeId,
            'category_name' => $category->name,
            'scope_name' => $scopeName ?: __('dashboard.budgetVsActual.generalScope'),
            'parent_id' => $category->parent_id,
            'group_type' => $category->group_type?->value,
            'parent_category_id' => $parentCategoryId,
            'parent_category_name' => $parentCategoryName,
            'parent_semantic_key' => $parentSemanticKey,
            ...$extra,
        ];
    }

    /**
     * @return array{0:int,1:string,2:string}
     */
    protected function parentDescriptorForCategory(Category $category): array
    {
        $parent = $category->parent()->first();

        if ($parent instanceof Category) {
            return [
                $parent->id,
                $parent->name,
                'parent|'.$this->categorySemanticKey($parent),
            ];
        }

        $groupType = $category->group_type?->value ?? CategoryGroupTypeEnum::EXPENSE->value;

        return [
            $this->virtualSemanticCategoryId($groupType),
            $this->semanticRootLabel($groupType),
            'root|'.$groupType,
        ];
    }

    protected function categorySemanticKey(Category $category): string
    {
        $segments = [];
        $current = $category;
        $visited = [];

        while ($current instanceof Category && ! in_array($current->id, $visited, true)) {
            $visited[] = $current->id;
            $segments[] = mb_strtolower($current->name);
            $current = $current->parent()->first();
        }

        return sprintf(
            '%s|%s',
            $category->group_type?->value ?? CategoryGroupTypeEnum::EXPENSE->value,
            implode('>', array_reverse($segments)),
        );
    }

    protected function shouldMergeSemanticRoots(array $accountContext): bool
    {
        return $accountContext['account_uuid'] === null
            && collect($accountContext['account_ids'])->unique()->count() > 1;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    protected function mergeSemanticRootBudgetComparisonRows(array $rows, array $accountContext): array
    {
        if (! $this->shouldMergeSemanticRoots($accountContext)) {
            return array_map(function (array $row): array {
                unset($row['parent_id'], $row['group_type']);

                return $row;
            }, $rows);
        }

        return collect($rows)
            ->groupBy(function (array $row): string {
                if ($this->isSemanticRootCategoryRow($row['parent_id'] ?? null, $row['group_type'] ?? null)) {
                    return 'semantic-root|'.($row['group_type'] ?? 'expense').'|'.($row['scope_id'] ?? 'null');
                }

                return 'category|'.($row['category_id'] ?? 'null').'|'.($row['scope_id'] ?? 'null');
            })
            ->map(function (Collection $group): array {
                $first = $group->first();
                $budgetTotal = round((float) $group->sum('budget_total'), 2);
                $actualTotal = round((float) $group->sum('actual_total'), 2);
                $delta = round($budgetTotal - $actualTotal, 2);

                return [
                    'category_id' => $this->isSemanticRootCategoryRow($first['parent_id'] ?? null, $first['group_type'] ?? null)
                        ? $this->virtualSemanticCategoryId((string) $first['group_type'])
                        : $first['category_id'],
                    'scope_id' => $first['scope_id'],
                    'category_name' => $this->isSemanticRootCategoryRow($first['parent_id'] ?? null, $first['group_type'] ?? null)
                        ? $this->semanticRootLabel((string) $first['group_type'])
                        : $first['category_name'],
                    'scope_name' => $first['scope_name'],
                    'budget_total' => $budgetTotal,
                    'actual_total' => $actualTotal,
                    'delta' => $delta,
                    'percentage_used' => $budgetTotal > 0 ? round(($actualTotal / $budgetTotal) * 100, 2) : 0.0,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    protected function mergeSemanticRootParentCategoryRows(
        array $rows,
        Collection $categories,
        array $accountContext
    ): array {
        return $rows;
    }

    protected function isSemanticRootCategoryRow(mixed $parentId, mixed $groupType): bool
    {
        return $parentId === null
            && is_string($groupType)
            && $groupType !== '';
    }

    protected function semanticRootLabel(string $groupType): string
    {
        return CategoryGroupTypeEnum::from($groupType)->label();
    }

    protected function virtualSemanticCategoryId(string $groupType): int
    {
        $supportedGroups = [
            CategoryGroupTypeEnum::INCOME->value,
            CategoryGroupTypeEnum::EXPENSE->value,
            CategoryGroupTypeEnum::BILL->value,
            CategoryGroupTypeEnum::DEBT->value,
            CategoryGroupTypeEnum::SAVING->value,
            CategoryGroupTypeEnum::TAX->value,
            CategoryGroupTypeEnum::INVESTMENT->value,
        ];

        return -1000 - (array_search($groupType, $supportedGroups, true) ?: 0);
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

            return $openingBalanceTransaction->direction === TransactionDirectionEnum::EXPENSE
                ? $amount * -1
                : $amount;
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

        $openingBalance = $this->resolveAccountOpeningBalance($account, $date);

        return $openingBalance + $this->sumNetTransactionsForAccount(
            $account->user_id,
            $account->id,
            $openingBalanceDate !== null
                ? CarbonImmutable::parse((string) $openingBalanceDate)
                : null,
            $date
        );
    }

    protected function sumNetTransactionsForAccount(
        int $userId,
        int $accountId,
        ?CarbonImmutable $fromDate,
        CarbonImmutable $toDate
    ): float {
        $query = Transaction::query()
            ->where('user_id', $userId)
            ->where('account_id', $accountId)
            ->where('kind', '!=', TransactionKindEnum::OPENING_BALANCE->value)
            ->whereDate('transaction_date', '<=', $toDate->toDateString());

        $this->applyTrackedItemOwnershipConstraint(
            $query,
            'transactions.tracked_item_id',
            $userId
        );

        if ($fromDate !== null) {
            $query->whereDate('transaction_date', '>=', $fromDate->toDateString());
        }

        // noinspection SqlNoDataSourceInspection
        // noinspection SqlResolveInspection
        return (float) $query->selectRaw(
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
        )->value('net_total');
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

    protected function formatDashboardPayload(array $dashboard, string $currency): array
    {
        $dashboard['overview'] = $this->withFormattedMoney(
            $dashboard['overview'],
            [
                'income_total',
                'expense_total',
                'net_total',
                'budget_total',
                'current_balance_total',
                'previous_balance_total',
                'actual_vs_budget_delta',
            ],
            $currency
        );

        $dashboard['monthly_trend'] = array_map(
            fn (array $point): array => $this->withFormattedMoney(
                $point,
                ['income_total', 'expense_total', 'net_total'],
                $currency
            ),
            $dashboard['monthly_trend']
        );

        $dashboard['expense_by_category'] = array_map(
            fn (array $item): array => $this->withFormattedMoney(
                $item,
                ['total_amount'],
                $currency
            ),
            $dashboard['expense_by_category']
        );

        $dashboard['income_by_category'] = array_map(
            fn (array $item): array => $this->withFormattedMoney(
                $item,
                ['total_amount'],
                $currency
            ),
            $dashboard['income_by_category']
        );

        $dashboard['budget_vs_actual'] = array_map(
            fn (array $item): array => $this->withFormattedMoney(
                $item,
                ['budget_total', 'actual_total', 'delta'],
                $currency
            ),
            $dashboard['budget_vs_actual']
        );

        $dashboard['parent_category_budget_status'] = array_map(
            fn (array $item): array => $this->withFormattedMoney(
                $item,
                ['budget_total', 'actual_total', 'delta'],
                $currency
            ),
            $dashboard['parent_category_budget_status']
        );

        $dashboard['accounts_summary'] = array_map(
            fn (array $item): array => $this->withFormattedMoney(
                $item,
                [
                    'opening_balance',
                    'current_balance',
                    'income_total',
                    'expense_total',
                    'net_total',
                ],
                $item['currency'] ?? $currency
            ),
            $dashboard['accounts_summary']
        );

        $dashboard['pending_actions']['items'] = array_map(
            fn (array $item): array => $this->withFormattedMoney(
                $item,
                ['amount'],
                $currency
            ),
            $dashboard['pending_actions']['items']
        );

        $dashboard['recurring_summary'] = $this->withFormattedMoney(
            $dashboard['recurring_summary'],
            ['overdue_total'],
            $currency
        );

        $dashboard['scheduled_summary']['upcoming'] = array_map(
            fn (array $item): array => $this->withFormattedMoney(
                $item,
                ['expected_amount'],
                $currency
            ),
            $dashboard['scheduled_summary']['upcoming']
        );

        $dashboard['merchant_breakdown'] = array_map(
            fn (array $item): array => $this->withFormattedMoney(
                $item,
                ['total_amount'],
                $currency
            ),
            $dashboard['merchant_breakdown']
        );

        $dashboard['notifications'] = $this->withFormattedMoney(
            $dashboard['notifications'],
            ['overdue_recurring_total'],
            $currency
        );

        return $dashboard;
    }

    protected function withFormattedMoney(array $payload, array $keys, string $currency): array
    {
        foreach ($keys as $key) {
            if (! array_key_exists($key, $payload)) {
                continue;
            }

            $rawValue = (float) $payload[$key];

            $payload["{$key}_raw"] = round($rawValue, 2);
            $payload[$key] = $this->formatMoney($rawValue, $currency);
        }

        return $payload;
    }

    protected function formatMoney(float $value, string $currency): string
    {
        $formatter = new \NumberFormatter('it_IT', \NumberFormatter::CURRENCY);

        $formatted = $formatter->formatCurrency($value, $currency);

        return $formatted !== false
            ? $formatted
            : number_format($value, 2, ',', '.')." {$currency}";
    }
}
