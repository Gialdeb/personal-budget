<?php

namespace App\Services\Dashboard;

use App\Enums\CategoryDirectionTypeEnum;
use App\Enums\CategoryGroupTypeEnum;
use App\Enums\RecurringOccurrenceStatusEnum;
use App\Enums\ScheduledEntryStatusEnum;
use App\Enums\TransactionDirectionEnum;
use App\Enums\TransactionStatusEnum;
use App\Models\Account;
use App\Models\Budget;
use App\Models\Category;
use App\Models\RecurringEntryOccurrence;
use App\Models\ScheduledEntry;
use App\Models\Transaction;
use App\Models\User;
use App\Services\UserYearService;
use App\Supports\PeriodOptions;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    public function __construct(
        protected UserYearService $userYearService
    ) {}

    public function build(User $user, int $year, ?int $month = null): array
    {
        $dashboard = [
            'filters' => $this->getFiltersData($user, $year, $month),
            'settings' => $this->getSettingsData($user),
            'overview' => $this->getOverview($user, $year, $month),
            'monthly_trend' => $this->getMonthlyTrend($user, $year, $month),
            'expense_by_category' => $this->getExpenseByCategory($user, $year, $month),
            'budget_vs_actual' => $this->getBudgetVsActual($user, $year, $month),
            'parent_category_budget_status' => $this->getParentCategoryBudgetStatus($user, $year, $month),
            'accounts_summary' => $this->getAccountsSummary($user, $year, $month),
            'recurring_summary' => $this->getRecurringSummary($user, $year, $month),
            'scheduled_summary' => $this->getScheduledSummary($user, $year, $month),
            'income_by_category' => $this->getIncomeByCategory($user, $year, $month),
            'merchant_breakdown' => $this->getMerchantBreakdown($user, $year, $month),
            'notifications' => $this->getNotifications($user, $year, $month),
            'year_suggestion' => $this->userYearService->buildNextYearSuggestion($user, $year),
        ];

        return $this->formatDashboardPayload(
            $dashboard,
            $dashboard['settings']['base_currency'] ?? 'EUR'
        );
    }

    protected function getFiltersData(User $user, int $year, ?int $month): array
    {
        $availableYears = $this->userYearService->availableYears($user);

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

        $budgetQuery = $this->baseBudgetPeriodQuery($user->id, $year, $month);

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

        $rows = $this->baseTransactionPeriodQuery($user->id, $year, null)
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
        $budgetQuery = $this->baseBudgetPeriodQuery($user->id, $year, $month)
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
        [$periodStart, $periodEnd] = $this->resolvePeriodBounds($year, $month);

        $accounts = Account::query()
            ->where('accounts.user_id', $user->id)
            ->where('accounts.is_active', true)
            ->leftJoin('user_banks', 'accounts.user_bank_id', '=', 'user_banks.id')
            ->leftJoin('banks', 'accounts.bank_id', '=', 'banks.id')
            ->select(
                'accounts.id',
                'accounts.user_id',
                'accounts.name',
                'accounts.currency',
                'accounts.current_balance',
                'accounts.opening_balance',
                DB::raw('COALESCE(user_banks.name, banks.name) as bank_name')
            )
            ->orderBy('accounts.name')
            ->get();

        return $accounts->map(function ($account) use ($periodEnd, $periodStart, $user, $year, $month) {
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
                'opening_balance' => round($this->resolveAccountOpeningBalance($account, $periodStart), 2),
                'current_balance' => round($this->resolveAccountBalanceAt($account, $periodEnd), 2),
                'income_total' => round($income, 2),
                'expense_total' => round($expense, 2),
                'net_total' => round($income - $expense, 2),
                'transactions_count' => $transactionsCount,
            ];
        })->values()->all();
    }

    protected function getParentCategoryBudgetStatus(User $user, int $year, ?int $month): array
    {
        $categories = Category::query()
            ->where('user_id', $user->id)
            ->whereIn('direction_type', [
                CategoryDirectionTypeEnum::EXPENSE->value,
                CategoryDirectionTypeEnum::MIXED->value,
            ])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get([
                'id',
                'parent_id',
                'name',
                'direction_type',
                'group_type',
                'sort_order',
            ]);

        $childrenByParentId = $categories->groupBy('parent_id');

        $parentCategories = $categories->filter(
            fn (Category $category): bool => $childrenByParentId->has($category->id)
        );

        $budgetTotals = $this->baseBudgetPeriodQuery($user->id, $year, $month)
            ->whereNotNull('budgets.category_id')
            ->select(
                'budgets.category_id',
                DB::raw('SUM(budgets.amount) as budget_total')
            )
            ->groupBy('budgets.category_id')
            ->pluck('budget_total', 'budgets.category_id');

        $actualTotals = $this->baseTransactionPeriodQuery($user->id, $year, $month)
            ->where('transactions.direction', TransactionDirectionEnum::EXPENSE->value)
            ->whereNotNull('transactions.category_id')
            ->select(
                'transactions.category_id',
                DB::raw('SUM(transactions.amount) as actual_total')
            )
            ->groupBy('transactions.category_id')
            ->pluck('actual_total', 'transactions.category_id');

        if ($parentCategories->isEmpty()) {
            return $this->buildVirtualParentCategoryBudgetStatus(
                $categories,
                $budgetTotals->all(),
                $actualTotals->all()
            );
        }

        $descendantIdsCache = [];

        $resolveDescendantIds = function (int $categoryId) use (
            &$descendantIdsCache,
            &$resolveDescendantIds,
            $childrenByParentId
        ): array {
            if (array_key_exists($categoryId, $descendantIdsCache)) {
                return $descendantIdsCache[$categoryId];
            }

            $descendantIds = [];

            foreach ($childrenByParentId->get($categoryId, collect()) as $childCategory) {
                $descendantIds[] = $childCategory->id;
                $descendantIds = array_merge(
                    $descendantIds,
                    $resolveDescendantIds($childCategory->id)
                );
            }

            $descendantIdsCache[$categoryId] = array_values(array_unique($descendantIds));

            return $descendantIdsCache[$categoryId];
        };

        return $parentCategories->map(function (Category $category) use (
            $actualTotals,
            $budgetTotals,
            $resolveDescendantIds
        ) {
            $categoryIds = [
                $category->id,
                ...$resolveDescendantIds($category->id),
            ];

            $budgetTotal = collect($categoryIds)->sum(
                fn (int $categoryId): float => (float) ($budgetTotals[$categoryId] ?? 0)
            );
            $actualTotal = collect($categoryIds)->sum(
                fn (int $categoryId): float => (float) ($actualTotals[$categoryId] ?? 0)
            );
            $delta = $budgetTotal - $actualTotal;
            $percentageUsed = $budgetTotal > 0
                ? round(($actualTotal / $budgetTotal) * 100, 2)
                : ($actualTotal > 0 ? 100.0 : 0.0);

            return [
                'category_id' => $category->id,
                'category_name' => $category->name,
                'budget_total' => round($budgetTotal, 2),
                'actual_total' => round($actualTotal, 2),
                'delta' => round($delta, 2),
                'percentage_used' => $percentageUsed,
            ];
        })->values()->all();
    }

    protected function buildVirtualParentCategoryBudgetStatus(
        Collection $categories,
        array $budgetTotals,
        array $actualTotals
    ): array {
        $supportedGroups = [
            CategoryGroupTypeEnum::EXPENSE->value,
            CategoryGroupTypeEnum::BILL->value,
            CategoryGroupTypeEnum::DEBT->value,
            CategoryGroupTypeEnum::TAX->value,
            CategoryGroupTypeEnum::INVESTMENT->value,
            CategoryGroupTypeEnum::SAVING->value,
        ];

        return $categories
            ->filter(fn (Category $category): bool => in_array($category->group_type?->value, $supportedGroups, true))
            ->groupBy(fn (Category $category): string => $category->group_type?->value ?? CategoryGroupTypeEnum::EXPENSE->value)
            ->map(function (Collection $groupCategories, string $groupType) use ($actualTotals, $budgetTotals, $supportedGroups) {
                $budgetTotal = $groupCategories->sum(
                    fn (Category $category): float => (float) ($budgetTotals[$category->id] ?? 0)
                );
                $actualTotal = $groupCategories->sum(
                    fn (Category $category): float => (float) ($actualTotals[$category->id] ?? 0)
                );
                $delta = $budgetTotal - $actualTotal;
                $percentageUsed = $budgetTotal > 0
                    ? round(($actualTotal / $budgetTotal) * 100, 2)
                    : ($actualTotal > 0 ? 100.0 : 0.0);

                return [
                    'category_id' => -1 * (array_search($groupType, $supportedGroups, true) + 1),
                    'category_name' => CategoryGroupTypeEnum::from($groupType)->label(),
                    'budget_total' => round($budgetTotal, 2),
                    'actual_total' => round($actualTotal, 2),
                    'delta' => round($delta, 2),
                    'percentage_used' => $percentageUsed,
                ];
            })
            ->values()
            ->all();
    }

    protected function getRecurringSummary(User $user, int $year, ?int $month): array
    {
        $query = $this->baseRecurringOccurrencePeriodQuery($user->id, $year, $month);

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
        $query = $this->baseScheduledPeriodQuery($user->id, $year, $month);

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

        $activeAccounts = Account::query()
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->get();

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

    protected function baseTransactionPeriodQuery(int $userId, int $year, ?int $month): Builder
    {
        $query = Transaction::query()
            ->where('transactions.user_id', $userId)
            ->whereYear('transactions.transaction_date', $year);

        $this->applyTrackedItemOwnershipConstraint(
            $query,
            'transactions.tracked_item_id',
            $userId
        );

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

    protected function baseBudgetPeriodQuery(int $userId, int $year, ?int $month): Builder
    {
        $query = Budget::query()
            ->where('budgets.user_id', $userId)
            ->where('budgets.year', $year);

        $this->applyTrackedItemOwnershipConstraint(
            $query,
            'budgets.tracked_item_id',
            $userId
        );

        if ($month !== null) {
            $query->where('budgets.month', $month);
        }

        return $query;
    }

    protected function baseScheduledPeriodQuery(int $userId, int $year, ?int $month): Builder
    {
        $query = ScheduledEntry::query()
            ->where('scheduled_entries.user_id', $userId)
            ->whereYear('scheduled_entries.scheduled_date', $year);

        $this->applyTrackedItemOwnershipConstraint(
            $query,
            'scheduled_entries.tracked_item_id',
            $userId
        );

        if ($month !== null) {
            $query->whereMonth('scheduled_entries.scheduled_date', $month);
        }

        return $query;
    }

    protected function baseRecurringOccurrencePeriodQuery(int $userId, int $year, ?int $month): Builder
    {
        $query = RecurringEntryOccurrence::query()
            ->whereHas('recurringEntry', function (Builder $query) use ($userId) {
                $query->where('user_id', $userId);

                $this->applyTrackedItemOwnershipConstraint(
                    $query,
                    'recurring_entries.tracked_item_id',
                    $userId
                );
            })
            ->whereYear('expected_date', $year);

        if ($month !== null) {
            $query->whereMonth('expected_date', $month);
        }

        return $query;
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

    protected function applyTrackedItemOwnershipConstraint(
        Builder $query,
        string $qualifiedColumn,
        int $userId,
        string $relation = 'trackedItem'
    ): Builder {
        return $query->where(function (Builder $trackedItemQuery) use (
            $qualifiedColumn,
            $relation,
            $userId
        ) {
            $trackedItemQuery->whereNull($qualifiedColumn)
                ->orWhereHas($relation, function (Builder $ownedTrackedItemQuery) use ($userId) {
                    $ownedTrackedItemQuery->where('user_id', $userId);
                });
        });
    }

    protected function resolveAccountOpeningBalance(Account $account, CarbonImmutable $date): float
    {
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
            ->whereDate('transaction_date', '<=', $toDate->toDateString());

        $this->applyTrackedItemOwnershipConstraint(
            $query,
            'transactions.tracked_item_id',
            $userId
        );

        if ($fromDate !== null) {
            $query->whereDate('transaction_date', '>=', $fromDate->toDateString());
        }

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
