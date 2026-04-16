<?php

namespace App\Services\Dashboard;

use App\Enums\CategoryDirectionTypeEnum;
use App\Enums\CategoryGroupTypeEnum;
use App\Enums\RecurringOccurrenceStatusEnum;
use App\Enums\TransactionDirectionEnum;
use App\Enums\TransactionKindEnum;
use App\Models\Account;
use App\Models\Budget;
use App\Models\Category;
use App\Models\RecurringEntryOccurrence;
use App\Models\Scope;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserYear;
use App\Services\Accounts\AccessibleAccountsQuery;
use App\Services\Categories\SharedAccountCategoryTaxonomyService;
use App\Services\CreditCards\CreditCardAutopayService;
use App\Services\Transactions\OperationalTransactionCategoryResolver;
use App\Services\UserYearService;
use App\Support\Banks\BankNamePresenter;
use App\Supports\CategoryHierarchy;
use App\Supports\HierarchyOptionLabel;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class MonthlyTransactionSheetService
{
    public function __construct(
        protected UserYearService $userYearService,
        protected AccessibleAccountsQuery $accessibleAccountsQuery,
        protected OperationalTransactionCategoryResolver $operationalTransactionCategoryResolver,
        protected SharedAccountCategoryTaxonomyService $sharedAccountCategoryTaxonomyService,
        protected CreditCardAutopayService $creditCardAutopayService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(User $user, int $year, int $month): array
    {
        $baseCurrency = $this->normalizeCurrencyCode($user->base_currency_code, 'EUR');
        $availableYears = $this->userYearService->availableYears($user);
        $accessibleAccounts = $this->accessibleAccountsQuery->get($user);
        $accessibleAccounts->each(function (Account $account): void {
            $this->sharedAccountCategoryTaxonomyService->ensureForAccount($account);
        });
        $editableAccountIds = $this->accessibleAccountsQuery->editableIds($user);
        $selectedYear = UserYear::query()
            ->where('user_id', $user->id)
            ->where('year', $year)
            ->first();

        $transactions = $this->getMonthlyTransactions($user, $year, $month);
        $deletedTransactions = $this->getDeletedMonthlyTransactions($user, $year, $month);
        $budgets = $this->getMonthlyBudgets($user->id, $year, $month);
        $transactionList = $this->buildTransactionsList($user, $year, $month, $transactions, $editableAccountIds);
        $deletedTransactionList = $this->buildDeletedTransactionsList($deletedTransactions, $editableAccountIds);
        $plannedOccurrences = $this->buildPlannedOccurrencesList($user, $year, $month);
        $transactionFilterPool = $transactions->concat($deletedTransactions->all());

        $transactionsByCategory = $this->groupTransactionsByCategory($transactions, $baseCurrency);
        $budgetsByCategory = $this->groupBudgetsByCategory($budgets);
        $periodEnd = CarbonImmutable::create($year, $month, 1)->endOfMonth();
        $periodEndingBalances = $this->resolvePeriodEndingBalances($accessibleAccounts, $periodEnd);

        $accessibleOwnerIds = $accessibleAccounts->pluck('user_id')->map(fn ($id): int => (int) $id)->unique()->values()->all();
        $accessibleAccountIds = $accessibleAccounts->pluck('id')->map(fn ($id): int => (int) $id)->unique()->values()->all();
        $categories = $this->getRelevantCategories($accessibleOwnerIds, $accessibleAccountIds, $transactionsByCategory, $budgetsByCategory);
        $sections = $this->buildSections($categories, $transactionsByCategory, $budgetsByCategory);

        $transactionTotals = $this->calculateTransactionTotalsFromTransactions($transactions, $baseCurrency);
        $budgetTotals = $this->calculateBudgetTotals($sections);

        $isClosedYear = $selectedYear?->is_closed ?? false;

        return [
            'filters' => [
                'year' => $year,
                'month' => $month,
                'available_years' => collect($availableYears)
                    ->map(fn (int $availableYear): array => [
                        'value' => $availableYear,
                        'label' => (string) $availableYear,
                    ])
                    ->values()
                    ->all(),
                'group_options' => $this->buildGroupOptions($sections),
                'category_options' => $this->buildCategoryOptions($transactionFilterPool),
                'account_options' => $this->buildAccountOptions($user, $transactionFilterPool),
            ],
            'settings' => [
                'active_year' => $user->settings?->active_year,
                'base_currency' => $baseCurrency,
            ],
            'period' => [
                'year' => $year,
                'month' => $month,
                'month_label' => $this->getMonthLabel($month),
                'is_current_month' => $year === now()->year && $month === now()->month,
            ],
            'summary_cards' => $this->buildSummaryCards($sections, $transactionTotals, $budgetTotals),
            'transactions' => $transactionList,
            'deleted_transactions' => $deletedTransactionList,
            'planned_occurrences' => $plannedOccurrences,
            'editor' => [
                'can_edit' => ! $isClosedYear && $editableAccountIds !== [],
                'group_options' => $this->buildEditorGroupOptions($accessibleOwnerIds),
                'type_options' => $this->buildEditorTypeOptions(),
                'default_account_uuid' => Account::query()
                    ->defaultOwnedBy($user->id)
                    ->value('uuid'),
                'accounts' => $this->buildEditorAccountOptions($user, $transactionFilterPool),
                'categories' => $this->buildEditorCategoryOptions($user, $transactionFilterPool),
                'category_overview_items' => $this->buildEditorCategoryOverviewItems($user, $year, $month, $transactions),
                'scopes' => $this->buildEditorScopeOptions($user, $transactionFilterPool),
                'tracked_items' => $this->buildEditorTrackedItemOptions($user, $transactionFilterPool),
            ],
            'overview' => [
                'groups' => $this->buildOverviewGroups($sections),
                'categories' => $this->buildOverviewCategories($sections),
            ],
            'sections' => $sections,
            'totals' => [
                'actual_income_raw' => $transactionTotals['income'] ?? 0,
                'actual_expense_raw' => abs($transactionTotals['expense'] ?? 0),
                'budgeted_income_raw' => $budgetTotals['income'] ?? 0,
                'budgeted_expense_raw' => $budgetTotals['expense'] ?? 0,
                'net_actual_raw' => ($transactionTotals['income'] ?? 0) - abs($transactionTotals['expense'] ?? 0),
                'net_budgeted_raw' => ($budgetTotals['income'] ?? 0) - abs($budgetTotals['expense'] ?? 0),
            ],
            'meta' => [
                'year_is_closed' => $isClosedYear,
                'closed_year_message' => $isClosedYear
                    ? __('transactions.closed_year_message', ['year' => $year])
                    : null,
                'transactions_count' => count($transactionList),
                'deleted_transactions_count' => count($deletedTransactionList),
                'planned_occurrences_count' => count($plannedOccurrences),
                'last_balance_raw' => $this->sumPeriodEndingBalances(
                    $periodEndingBalances,
                    $accessibleAccounts,
                    $periodEnd,
                    $baseCurrency,
                ),
                'last_recorded_at' => $transactionList[0]['date']
                    ?? collect($periodEndingBalances)
                        ->pluck('last_recorded_at')
                        ->filter()
                        ->sort()
                        ->last(),
                'period_ending_balances' => array_values($periodEndingBalances),
                'has_budget_data' => $budgets->isNotEmpty(),
            ],
        ];
    }

    /**
     * @return Collection<int, Transaction>
     */
    protected function getMonthlyTransactions(User $user, int $year, int $month): Collection
    {
        return Transaction::query()
            ->whereIn('account_id', $this->accessibleAccountsQuery->ids($user))
            ->whereYear('transaction_date', $year)
            ->whereMonth('transaction_date', $month)
            ->with([
                'category.parent',
                'merchant',
                'account.accountType',
                'scope',
                'trackedItem',
                'refundTransaction',
                'refundedTransaction',
                'relatedTransaction.account',
                'recurringOccurrence.recurringEntry',
                'createdByUser:id,uuid,name,email',
                'updatedByUser:id,uuid,name,email',
            ])
            ->orderBy('transaction_date', 'desc')
            ->orderByRaw(
                'case when kind = ? then 0 else 1 end asc',
                [TransactionKindEnum::OPENING_BALANCE->value]
            )
            ->orderBy('created_at', 'desc')
            ->get()
            ->filter(fn (Transaction $transaction): bool => $this->shouldDisplayTransactionInUserList($transaction))
            ->values();
    }

    /**
     * @return Collection<int, Transaction>
     */
    protected function getDeletedMonthlyTransactions(User $user, int $year, int $month): Collection
    {
        return Transaction::onlyTrashed()
            ->whereIn('account_id', $this->accessibleAccountsQuery->ids($user))
            ->whereYear('transaction_date', $year)
            ->whereMonth('transaction_date', $month)
            ->with([
                'category.parent',
                'merchant',
                'account.accountType',
                'scope',
                'trackedItem',
                'refundTransaction',
                'refundedTransaction',
                'relatedTransaction.account',
                'recurringOccurrence.recurringEntry',
                'createdByUser:id,uuid,name,email',
                'updatedByUser:id,uuid,name,email',
            ])
            ->orderByDesc('deleted_at')
            ->orderBy('transaction_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->get()
            ->filter(fn (Transaction $transaction): bool => $this->shouldDisplayTransactionInUserList($transaction))
            ->values();
    }

    protected function shouldDisplayTransactionInUserList(Transaction $transaction): bool
    {
        return ! ($transaction->isCreditCardSettlement() && $transaction->account?->isCreditCard());
    }

    /**
     * @return Collection<int, Budget>
     */
    protected function getMonthlyBudgets(int $userId, int $year, int $month): Collection
    {
        return Budget::query()
            ->where('user_id', $userId)
            ->where('year', $year)
            ->where('month', $month)
            ->whereNull('scope_id')
            ->whereNull('tracked_item_id')
            ->with(['category'])
            ->get();
    }

    /**
     * @param  Collection<int, Transaction>  $transactions
     * @return array<int, array{income: float, expense: float, count: int}>
     */
    protected function groupTransactionsByCategory(Collection $transactions, string $baseCurrency): array
    {
        $grouped = [];

        foreach ($transactions as $transaction) {
            if ($transaction->kind === TransactionKindEnum::OPENING_BALANCE) {
                continue;
            }

            $categoryId = $transaction->category_id ?? 0;

            if (! isset($grouped[$categoryId])) {
                $grouped[$categoryId] = ['income' => 0, 'expense' => 0, 'count' => 0];
            }

            $amount = $this->resolveAggregateAmountForTransaction($transaction, $baseCurrency);

            if ($amount === null) {
                continue;
            }

            if ($transaction->direction === TransactionDirectionEnum::INCOME) {
                $grouped[$categoryId]['income'] += $amount;
            } else {
                $grouped[$categoryId]['expense'] += abs($amount);
            }
            $grouped[$categoryId]['count']++;
        }

        return $grouped;
    }

    /**
     * @param  Collection<int, Budget>  $budgets
     * @return array<int, float>
     */
    protected function groupBudgetsByCategory(Collection $budgets): array
    {
        return $budgets->mapWithKeys(fn (Budget $budget) => [
            (int) $budget->category_id => (float) $budget->amount,
        ])->all();
    }

    /**
     * @param  array<int, array{income: float, expense: float, count: int}>  $transactionsByCategory
     * @param  array<int, float>  $budgetsByCategory
     * @return Collection<int, Category>
     */
    protected function getRelevantCategories(array $ownerIds, array $accountIds, array $transactionsByCategory, array $budgetsByCategory): Collection
    {
        $relevantCategoryIds = collect(array_keys($transactionsByCategory))
            ->merge(array_keys($budgetsByCategory))
            ->filter()
            ->unique()
            ->values()
            ->all();

        return Category::query()
            ->where(function (Builder $query) use ($ownerIds, $accountIds): void {
                $query->where(function (Builder $ownedQuery) use ($ownerIds): void {
                    $ownedQuery
                        ->whereIn('user_id', $ownerIds !== [] ? $ownerIds : [0])
                        ->whereNull('account_id');
                });

                if ($accountIds !== []) {
                    $query->orWhereIn('account_id', $accountIds);
                }
            })
            ->withCount('children')
            ->where(function (Builder $query): void {
                $query->whereNull('group_type')
                    ->orWhere('group_type', '!=', CategoryGroupTypeEnum::TRANSFER->value);
            })
            ->where(function (Builder $query) use ($relevantCategoryIds): void {
                $query->where('is_active', true)
                    ->orWhereIn('id', $relevantCategoryIds);
            })
            ->get([
                'id',
                'user_id',
                'uuid',
                'parent_id',
                'name',
                'slug',
                'icon',
                'color',
                'direction_type',
                'group_type',
                'sort_order',
                'is_active',
                'is_selectable',
            ])
            ->sortBy([
                ['sort_order', 'asc'],
                ['name', 'asc'],
            ])
            ->values();
    }

    /**
     * @param  Collection<int, Category>  $categories
     * @param  array<int, array{income: float, expense: float, count: int}>  $transactionsByCategory
     * @param  array<int, float>  $budgetsByCategory
     * @return array<int, array<string, mixed>>
     */
    protected function buildSections(Collection $categories, array $transactionsByCategory, array $budgetsByCategory): array
    {
        $childrenByParent = $categories->groupBy('parent_id');
        $sectionOrder = [
            CategoryGroupTypeEnum::INCOME->value,
            CategoryGroupTypeEnum::EXPENSE->value,
            CategoryGroupTypeEnum::BILL->value,
            CategoryGroupTypeEnum::DEBT->value,
            CategoryGroupTypeEnum::SAVING->value,
            CategoryGroupTypeEnum::TAX->value,
            CategoryGroupTypeEnum::INVESTMENT->value,
            'other',
        ];

        $sections = collect($childrenByParent->get(null, collect()))
            ->groupBy(fn (Category $category): string => $this->sectionKeyForCategory($category))
            ->map(function (Collection $rootCategories, string $sectionKey) use ($childrenByParent, $transactionsByCategory, $budgetsByCategory): array {
                $rows = $rootCategories
                    ->map(fn (Category $category): array => $this->buildRow(
                        $category,
                        $childrenByParent,
                        $transactionsByCategory,
                        $budgetsByCategory
                    ))
                    ->values()
                    ->all();

                $sectionTotals = $this->calculateSectionTotals($rows);

                return [
                    'key' => $sectionKey,
                    'label' => $this->sectionLabel($sectionKey),
                    'description' => $this->sectionDescription($sectionKey),
                    'rows' => $rows,
                    'flat_rows' => $this->flattenRows($rows),
                    'totals' => $sectionTotals,
                ];
            })
            ->sortBy(function (array $section) use ($sectionOrder): int {
                $index = array_search($section['key'], $sectionOrder, true);

                return $index === false ? 999 : $index;
            })
            ->values()
            ->all();

        return $sections;
    }

    /**
     * @param  Collection<int, Collection<int, Category>>  $childrenByParent
     * @param  array<int, array{income: float, expense: float, count: int}>  $transactionsByCategory
     * @param  array<int, float>  $budgetsByCategory
     * @param  array<int, int>  $ancestorIds
     * @param  array<int, string>  $ancestorNames
     * @return array<string, mixed>
     */
    protected function buildRow(
        Category $category,
        Collection $childrenByParent,
        array $transactionsByCategory,
        array $budgetsByCategory,
        array $ancestorNames = [],
        array $ancestorUuids = []
    ): array {
        $children = $childrenByParent->get($category->id, collect())
            ->filter(function (Category $child) use ($ancestorUuids): bool {
                // Prevent infinite loops by checking if this category is already in the path
                return ! in_array($child->uuid, $ancestorUuids, true);
            })
            ->map(fn (Category $child): array => $this->buildRow(
                $child,
                $childrenByParent,
                $transactionsByCategory,
                $budgetsByCategory,
                [...$ancestorNames, $category->name],
                [...$ancestorUuids, $category->uuid]
            ))
            ->values()
            ->all();

        $directData = $transactionsByCategory[$category->id] ?? ['income' => 0, 'expense' => 0, 'count' => 0];
        $budgetAmount = $budgetsByCategory[$category->id] ?? 0;

        $aggregatedData = $children !== []
            ? $this->aggregateChildrenData($children, $directData)
            : $directData;

        $fullPath = implode(' > ', [...$ancestorNames, $category->name]);

        return [
            'uuid' => $category->uuid,
            'parent_uuid' => $ancestorUuids !== [] ? $ancestorUuids[count($ancestorUuids) - 1] : null,
            'name' => $category->name,
            'full_path' => $fullPath,
            'depth' => count($ancestorUuids),
            'group_type' => $category->group_type?->value,
            'direction_type' => $category->direction_type?->value,
            'icon' => $category->icon,
            'color' => $category->color,
            'is_active' => $category->is_active,
            'is_selectable' => $category->is_selectable,
            'has_children' => $children !== [],
            'ancestor_uuids' => $ancestorUuids,
            'actual_income_raw' => round($aggregatedData['income'], 2),
            'actual_expense_raw' => round($aggregatedData['expense'], 2),
            'actual_net_raw' => round($aggregatedData['income'] - $aggregatedData['expense'], 2),
            'budgeted_amount_raw' => round($budgetAmount, 2),
            'variance_raw' => round(($aggregatedData['income'] - $aggregatedData['expense']) - $budgetAmount, 2),
            'transaction_count' => $aggregatedData['count'],
            'direct_income_raw' => round($directData['income'], 2),
            'direct_expense_raw' => round($directData['expense'], 2),
            'children' => $children,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $children
     * @param  array{income: float, expense: float, count: int}  $directData
     * @return array{income: float, expense: float, count: int}
     */
    protected function aggregateChildrenData(array $children, array $directData): array
    {
        $totals = $directData;

        foreach ($children as $child) {
            $totals['income'] += $child['actual_income_raw'];
            $totals['expense'] += $child['actual_expense_raw'];
            $totals['count'] += $child['transaction_count'];
        }

        return $totals;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    protected function flattenRows(array $rows, array $visited = []): array
    {
        $items = [];

        foreach ($rows as $row) {
            // Prevent infinite loops by checking for cycles
            $rowId = $row['uuid'] ?? $row['id'] ?? json_encode($row);
            if (in_array($rowId, $visited, true)) {
                continue;
            }

            $visited[] = $rowId;
            $items[] = collect($row)->except('children')->all();

            if (isset($row['children']) && is_array($row['children']) && $row['children'] !== []) {
                $items = [...$items, ...$this->flattenRows($row['children'], $visited)];
            }
        }

        return $items;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{income: float, expense: float, net: float, budget: float, variance: float, count: int}
     */
    protected function calculateSectionTotals(array $rows): array
    {
        $totals = ['income' => 0, 'expense' => 0, 'net' => 0, 'budget' => 0, 'variance' => 0, 'count' => 0];

        foreach ($rows as $row) {
            $totals['income'] += $row['actual_income_raw'];
            $totals['expense'] += $row['actual_expense_raw'];
            $totals['net'] += $row['actual_net_raw'];
            $totals['budget'] += $row['budgeted_amount_raw'];
            $totals['variance'] += $row['variance_raw'];
            $totals['count'] += $row['transaction_count'];
        }

        return array_map(fn ($value) => round($value, 2), $totals);
    }

    /**
     * @param  array<int, array<string, mixed>>  $sections
     * @return array{income: float, expense: float}
     */
    protected function calculateTransactionTotalsFromTransactions(Collection $transactions, string $baseCurrency): array
    {
        $totals = ['income' => 0, 'expense' => 0];

        foreach ($transactions as $transaction) {
            if ($transaction->kind === TransactionKindEnum::OPENING_BALANCE) {
                continue;
            }

            if ((bool) $transaction->is_transfer) {
                continue;
            }

            $amount = $this->resolveAggregateAmountForTransaction($transaction, $baseCurrency);

            if ($amount === null) {
                continue;
            }

            $this->applyTransactionToTotals($totals, $transaction, $amount);
        }

        return [
            'income' => round($totals['income'], 2),
            'expense' => round($totals['expense'], 2),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $sections
     * @return array{income: float, expense: float}
     */
    protected function calculateBudgetTotals(array $sections): array
    {
        $totals = ['income' => 0, 'expense' => 0];

        foreach ($sections as $section) {
            if ($section['key'] === CategoryGroupTypeEnum::INCOME->value) {
                $totals['income'] += $section['totals']['budget'];
            } else {
                $totals['expense'] += $section['totals']['budget'];
            }
        }

        return $totals;
    }

    /**
     * @param  array<int, array<string, mixed>>  $sections
     * @return array<int, array<string, mixed>>
     */
    protected function buildSummaryCards(array $sections, array $transactionTotals, array $budgetTotals): array
    {
        $sectionTotals = collect($sections)->mapWithKeys(
            fn (array $section): array => [$section['key'] => $section['totals']]
        );

        $incomeActual = $transactionTotals['income'] ?? 0;
        $expenseActual = $transactionTotals['expense'] ?? 0;
        $billActual = $sectionTotals[CategoryGroupTypeEnum::BILL->value]['expense'] ?? 0;
        $savingActual = $sectionTotals[CategoryGroupTypeEnum::SAVING->value]['expense'] ?? 0;

        $incomeBudget = $budgetTotals['income'] ?? 0;
        $expenseBudget = $budgetTotals['expense'] ?? 0;
        $billBudget = $sectionTotals[CategoryGroupTypeEnum::BILL->value]['budget'] ?? 0;
        $savingBudget = $sectionTotals[CategoryGroupTypeEnum::SAVING->value]['budget'] ?? 0;

        return [
            $this->summaryCard('income', __('app.enums.category_groups.income'), $incomeActual, $incomeBudget),
            $this->summaryCard('expense', __('app.enums.category_groups.expense'), $expenseActual, $expenseBudget),
            $this->summaryCard('bill', __('app.enums.category_groups.bill'), $billActual, $billBudget),
            $this->summaryCard('saving', __('app.enums.category_groups.saving'), $savingActual, $savingBudget),
            $this->summaryCard('net', __('transactions.monthly.totals.netBalance'), $incomeActual - $expenseActual, $incomeBudget - $expenseBudget),
        ];
    }

    /**
     * @param  Collection<int, Transaction>  $transactions
     * @return array<int, array{value: string, label: string}>
     */
    protected function buildCategoryOptions(Collection $transactions): array
    {
        return $transactions
            ->filter(fn (Transaction $transaction): bool => $transaction->category !== null)
            ->map(fn (Transaction $transaction): array => [
                'value' => (string) $transaction->category?->uuid,
                'uuid' => $transaction->category?->uuid,
                'label' => $transaction->category?->name ?? __('app.common.uncategorized'),
            ])
            ->unique('value')
            ->sortBy('label')
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, Transaction>  $transactions
     * @return array<int, array<string, bool|string|null>>
     */
    protected function buildAccountOptions(User $user, Collection $transactions): array
    {
        $usedAccountIds = $transactions
            ->pluck('account_id')
            ->filter(fn ($id): bool => $id !== null)
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        return $this->accessibleAccountsQuery->get($user)
            ->filter(function (Account $account) use ($usedAccountIds): bool {
                if ((bool) $account->is_active) {
                    return true;
                }

                return in_array((int) $account->id, $usedAccountIds, true);
            })
            ->map(fn (Account $account): array => $this->mapAccessibleAccountOption($account))
            ->unique('value')
            ->sortBy('label')
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, Transaction>  $transactions
     * @return array<int, array<string, int|float|string|null>>
     */
    protected function buildTransactionsList(User $user, int $year, int $month, Collection $transactions, array $editableAccountIds): array
    {
        $items = $transactions
            ->map(fn (Transaction $transaction): array => $this->mapTransactionListItem($transaction, $editableAccountIds))
            ->values()
            ->all();

        if ($month !== 1) {
            return $items;
        }

        $items = [
            ...$items,
            ...$this->buildDerivedYearOpeningRows($user, $year, $transactions),
        ];

        usort($items, function (array $left, array $right): int {
            $dateComparison = strcmp((string) $right['date'], (string) $left['date']);

            if ($dateComparison !== 0) {
                return $dateComparison;
            }

            return ($right['is_opening_balance'] ?? false) <=> ($left['is_opening_balance'] ?? false);
        });

        return array_values($items);
    }

    /**
     * @param  Collection<int, Transaction>  $transactions
     * @return array<int, array<string, int|float|string|bool|null>>
     */
    protected function buildDeletedTransactionsList(Collection $transactions, array $editableAccountIds): array
    {
        return $transactions
            ->map(fn (Transaction $transaction): array => $this->mapTransactionListItem($transaction, $editableAccountIds))
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, Transaction>  $transactions
     * @return array<int, array<string, int|float|string|bool|null>>
     */
    protected function buildDerivedYearOpeningRows(User $user, int $year, Collection $transactions): array
    {
        $accounts = Account::query()
            ->whereIn('id', $this->accessibleAccountsQuery->ids($user))
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'uuid', 'name', 'currency', 'opening_balance', 'opening_balance_date']);

        return $accounts
            ->filter(function (Account $account) use ($transactions): bool {
                return ! $transactions->contains(
                    fn (Transaction $transaction): bool => $transaction->account_id === $account->id
                        && $transaction->kind === TransactionKindEnum::OPENING_BALANCE
                );
            })
            ->map(function (Account $account) use ($year): ?array {
                $actualOpeningDate = $this->resolveActualOpeningDate($account);

                if ($actualOpeningDate !== null) {
                    if ($year < $actualOpeningDate->year) {
                        return null;
                    }

                    if ($year === $actualOpeningDate->year && ! $actualOpeningDate->isStartOfYear()) {
                        return null;
                    }
                }

                $amount = $this->resolveDerivedYearOpeningBalance($account, $year);

                if (abs($amount) < 0.005) {
                    return null;
                }

                $date = sprintf('%04d-01-01', $year);

                return [
                    'uuid' => sprintf('opening-%d-%d', $account->id, $year),
                    'date' => $date,
                    'date_label' => CarbonImmutable::parse($date)->translatedFormat('d M'),
                    'type' => $amount >= 0
                        ? $this->sectionLabel(CategoryGroupTypeEnum::INCOME->value)
                        : $this->sectionLabel(CategoryGroupTypeEnum::EXPENSE->value),
                    'type_key' => $amount >= 0
                        ? CategoryGroupTypeEnum::INCOME->value
                        : CategoryGroupTypeEnum::EXPENSE->value,
                    'kind' => TransactionKindEnum::OPENING_BALANCE->value,
                    'kind_label' => TransactionKindEnum::OPENING_BALANCE->label(),
                    'is_opening_balance' => true,
                    'is_deleted' => false,
                    'deleted_at' => null,
                    'is_projected_recurring' => false,
                    'is_recurring_transaction' => false,
                    'is_transfer' => false,
                    'direction' => $amount >= 0
                        ? TransactionDirectionEnum::INCOME->value
                        : TransactionDirectionEnum::EXPENSE->value,
                    'direction_label' => $amount >= 0
                        ? TransactionDirectionEnum::INCOME->label()
                        : TransactionDirectionEnum::EXPENSE->label(),
                    'category_uuid' => null,
                    'category_label' => __('transactions.opening_balance.row_label', ['year' => $year]),
                    'category_path' => __('transactions.opening_balance.path_label'),
                    'description' => __('transactions.opening_balance.kind_label'),
                    'detail' => __('transactions.opening_balance.detail'),
                    'notes' => null,
                    'account_uuid' => $account->uuid,
                    'account_label' => $account->name,
                    'related_transaction_uuid' => null,
                    'related_account_uuid' => null,
                    'related_account_label' => null,
                    'tracked_item_uuid' => null,
                    'tracked_item_label' => null,
                    'recurring_occurrence_uuid' => null,
                    'recurring_entry_uuid' => null,
                    'recurring_entry_show_url' => null,
                    'amount_value_raw' => round(abs($amount), 2),
                    'amount_raw' => round($amount, 2),
                    'balance_after_raw' => round($amount, 2),
                    'status' => null,
                    'source_type' => null,
                    'created_at' => null,
                    'updated_at' => null,
                    'last_modified_at' => null,
                    'created_by' => null,
                    'updated_by' => null,
                    'can_edit' => false,
                    'can_delete' => false,
                    'can_restore' => false,
                    'can_force_delete' => false,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return array<string, int|float|string|bool|null>
     */
    protected function mapTransactionListItem(Transaction $transaction, array $editableAccountIds): array
    {
        $sectionKey = $transaction->category instanceof Category
            ? $this->sectionKeyForCategory($transaction->category)
            : 'other';

        if ($transaction->is_transfer) {
            $sectionKey = CategoryGroupTypeEnum::TRANSFER->value;
        }

        if ($transaction->kind === TransactionKindEnum::OPENING_BALANCE) {
            $sectionKey = $transaction->direction === TransactionDirectionEnum::INCOME
                ? CategoryGroupTypeEnum::INCOME->value
                : CategoryGroupTypeEnum::EXPENSE->value;
        }

        if ($transaction->kind === TransactionKindEnum::BALANCE_ADJUSTMENT) {
            $sectionKey = TransactionKindEnum::BALANCE_ADJUSTMENT->value;
        }

        $detail = $transaction->merchant?->name
            ?? $transaction->counterparty_name
            ?? $transaction->description
            ?? $transaction->bank_description_clean
            ?? $transaction->bank_description_raw;
        $creditCardCycle = $this->creditCardCycleForTransaction($transaction);

        $isDeleteAllowedKind = in_array($transaction->kind, [TransactionKindEnum::MANUAL, TransactionKindEnum::BALANCE_ADJUSTMENT], true);
        $canDelete = ! $transaction->trashed() && $isDeleteAllowedKind;
        $canRestore = $transaction->trashed() && $isDeleteAllowedKind;
        $canForceDelete = $transaction->trashed() && $isDeleteAllowedKind;
        $recurringEntryUuid = $transaction->recurringOccurrence?->recurringEntry?->uuid;
        $canMutate = in_array((int) $transaction->account_id, $editableAccountIds, true);
        $canRefund = $canMutate
            && ! $transaction->trashed()
            && ! $transaction->is_transfer
            && $transaction->refundTransaction === null
            && ! in_array($transaction->kind, [
                TransactionKindEnum::OPENING_BALANCE,
                TransactionKindEnum::BALANCE_ADJUSTMENT,
                TransactionKindEnum::SCHEDULED,
                TransactionKindEnum::REFUND,
                TransactionKindEnum::CREDIT_CARD_SETTLEMENT,
            ], true);
        $hasLinkedRefund = $transaction->refundTransaction !== null;
        $canUndoRefund = $canMutate
            && ! $transaction->trashed()
            && $transaction->kind === TransactionKindEnum::REFUND
            && $transaction->refunded_transaction_id !== null;

        return [
            'uuid' => $transaction->uuid,
            'date' => $transaction->transaction_date?->toDateString(),
            'date_label' => $transaction->transaction_date?->translatedFormat('d M'),
            'type' => $transaction->kind === TransactionKindEnum::REFUND
                ? __('transactions.enums.kind.refund')
                : $this->sectionLabel($sectionKey),
            'type_key' => $sectionKey,
            'kind' => $transaction->kind?->value,
            'kind_label' => $transaction->kind?->label(),
            'is_opening_balance' => $transaction->kind === TransactionKindEnum::OPENING_BALANCE,
            'is_deleted' => $transaction->trashed(),
            'deleted_at' => $transaction->deleted_at?->toDateTimeString(),
            'is_projected_recurring' => false,
            'is_recurring_transaction' => $transaction->recurringOccurrence !== null,
            'is_transfer' => (bool) $transaction->is_transfer,
            'direction' => $transaction->direction?->value,
            'direction_label' => $transaction->direction?->label(),
            'category_uuid' => $transaction->category?->uuid,
            'category_label' => $transaction->kind === TransactionKindEnum::BALANCE_ADJUSTMENT
                ? __('transactions.balance_adjustment.row_label')
                : ($transaction->kind === TransactionKindEnum::OPENING_BALANCE
                    ? __('transactions.opening_balance.row_label', ['year' => $transaction->transaction_date?->year ?? now()->year])
                    : ($transaction->is_transfer
                        ? $this->transferCategoryLabel($transaction)
                        : ($transaction->category?->name ?? __('app.common.uncategorized')))),
            'category_path' => $transaction->kind === TransactionKindEnum::BALANCE_ADJUSTMENT
                ? __('transactions.balance_adjustment.path_label')
                : ($transaction->kind === TransactionKindEnum::OPENING_BALANCE
                    ? __('transactions.opening_balance.path_label')
                    : ($transaction->is_transfer
                        ? $this->transferCategoryPath($transaction)
                        : $this->resolveCategoryPath($transaction->category))),
            'description' => $transaction->kind === TransactionKindEnum::BALANCE_ADJUSTMENT
                ? __('transactions.balance_adjustment.kind_label')
                : ($transaction->kind === TransactionKindEnum::OPENING_BALANCE
                    ? __('transactions.opening_balance.kind_label')
                    : $transaction->description),
            'detail' => $transaction->kind === TransactionKindEnum::BALANCE_ADJUSTMENT
                ? ($detail ?? __('transactions.balance_adjustment.detail'))
                : ($transaction->kind === TransactionKindEnum::OPENING_BALANCE
                    ? __('transactions.opening_balance.detail')
                    : $detail),
            'notes' => $transaction->notes,
            'account_uuid' => $transaction->account?->uuid,
            'account_label' => $transaction->account?->name ?? 'Conto sconosciuto',
            'related_transaction_uuid' => $transaction->relatedTransaction?->uuid,
            'related_account_uuid' => $transaction->relatedTransaction?->account?->uuid,
            'related_account_label' => $transaction->relatedTransaction?->account?->name,
            'scope_uuid' => $transaction->scope?->uuid,
            'scope_label' => $transaction->scope?->name,
            'tracked_item_uuid' => $transaction->trackedItem?->uuid,
            'tracked_item_label' => $transaction->trackedItem?->name,
            'is_credit_card_transaction' => $creditCardCycle !== null,
            'credit_card_cycle_end_date' => $creditCardCycle !== null
                ? $creditCardCycle['cycle_end_date']->toDateString()
                : null,
            'credit_card_payment_due_date' => $creditCardCycle !== null
                ? $creditCardCycle['payment_due_date']->toDateString()
                : null,
            'recurring_occurrence_uuid' => $transaction->recurringOccurrence?->uuid,
            'recurring_entry_uuid' => $recurringEntryUuid,
            'recurring_entry_show_url' => $recurringEntryUuid !== null
                ? route('recurring-entries.show', $recurringEntryUuid)
                : null,
            'currency_code' => $transaction->currency_code ?: $transaction->currency,
            'base_currency_code' => $transaction->base_currency_code,
            'converted_base_amount_raw' => $transaction->converted_base_amount !== null
                ? round((float) $transaction->converted_base_amount, 2)
                : null,
            'exchange_rate' => $transaction->exchange_rate !== null
                ? (string) $transaction->exchange_rate
                : null,
            'exchange_rate_date' => $transaction->exchange_rate_date?->toDateString(),
            'exchange_rate_source' => $transaction->exchange_rate_source,
            'is_multi_currency' => $transaction->currency_code !== null
                && $transaction->base_currency_code !== null
                && $transaction->currency_code !== $transaction->base_currency_code,
            'amount_value_raw' => round((float) $transaction->amount, 2),
            'amount_raw' => $transaction->direction === TransactionDirectionEnum::INCOME
                ? round((float) $transaction->amount, 2)
                : round((float) $transaction->amount * -1, 2),
            'balance_after_raw' => $transaction->balance_after !== null
                ? round((float) $transaction->balance_after, 2)
                : null,
            'status' => $transaction->status?->value,
            'source_type' => $transaction->source_type?->value,
            'is_refunded' => $transaction->refundTransaction !== null,
            'can_refund' => $canRefund,
            'can_undo_refund' => $canUndoRefund,
            'refund_transaction' => $transaction->refundTransaction === null ? null : [
                'uuid' => $transaction->refundTransaction->uuid,
                'transaction_date' => $transaction->refundTransaction->transaction_date?->toDateString(),
            ],
            'refunded_transaction' => $transaction->refundedTransaction === null ? null : [
                'uuid' => $transaction->refundedTransaction->uuid,
                'transaction_date' => $transaction->refundedTransaction->transaction_date?->toDateString(),
            ],
            'created_at' => $transaction->created_at?->toJSON(),
            'updated_at' => $transaction->updated_at?->toJSON(),
            'last_modified_at' => $transaction->updated_at?->toJSON(),
            'created_by' => $this->mapAuditActor($transaction->createdByUser),
            'updated_by' => $this->mapAuditActor($transaction->updatedByUser),
            'can_edit' => $canMutate
                && ! $transaction->trashed()
                && ! in_array($transaction->kind, [TransactionKindEnum::OPENING_BALANCE, TransactionKindEnum::BALANCE_ADJUSTMENT, TransactionKindEnum::SCHEDULED, TransactionKindEnum::REFUND], true),
            'can_delete' => $canMutate && $canDelete && ! $hasLinkedRefund,
            'can_restore' => $canMutate && $canRestore,
            'can_force_delete' => $canMutate && $canForceDelete && ! $hasLinkedRefund,
        ];
    }

    /**
     * @return array{
     *     cycle_start_date: CarbonImmutable,
     *     cycle_end_date: CarbonImmutable,
     *     payment_due_date: CarbonImmutable,
     *     statement_closing_day: int,
     *     payment_day: int
     * }|null
     */
    protected function creditCardCycleForTransaction(Transaction $transaction): ?array
    {
        if (
            ! ($transaction->account instanceof Account)
            || ! $transaction->account->isCreditCard()
            || $transaction->transaction_date === null
            || $transaction->isCreditCardSettlement()
        ) {
            return null;
        }

        return $this->creditCardAutopayService->resolveCycleForTransactionDate(
            $transaction->account,
            CarbonImmutable::parse($transaction->transaction_date->toDateString()),
        );
    }

    /**
     * @return array<int, array<string, int|float|string|bool|null>>
     */
    protected function buildPlannedOccurrencesList(User $user, int $year, int $month): array
    {
        $periodStart = CarbonImmutable::create($year, $month, 1)->startOfMonth()->toDateString();
        $periodEnd = CarbonImmutable::create($year, $month, 1)->endOfMonth()->toDateString();

        $occurrences = RecurringEntryOccurrence::query()
            ->whereNull('converted_transaction_id')
            ->whereIn('status', [
                RecurringOccurrenceStatusEnum::PENDING->value,
                RecurringOccurrenceStatusEnum::GENERATED->value,
            ])
            ->where(function (Builder $query) use ($periodStart, $periodEnd): void {
                $query->whereBetween('due_date', [$periodStart, $periodEnd])
                    ->orWhere(function (Builder $fallback) use ($periodStart, $periodEnd): void {
                        $fallback->whereNull('due_date')
                            ->whereBetween('expected_date', [$periodStart, $periodEnd]);
                    });
            })
            ->whereHas('recurringEntry', function (Builder $query) use ($user): void {
                $query->where('user_id', $user->id);
            })
            ->with([
                'recurringEntry.category.parent',
                'recurringEntry.account',
                'recurringEntry.trackedItem',
            ])
            ->get();

        $items = $occurrences->map(function (RecurringEntryOccurrence $occurrence): array {
            $entry = $occurrence->recurringEntry;
            $scheduledDate = $occurrence->due_date ?? $occurrence->expected_date;
            $sectionKey = $entry?->category instanceof Category
                ? $this->sectionKeyForCategory($entry->category)
                : (($entry?->direction === TransactionDirectionEnum::INCOME)
                    ? CategoryGroupTypeEnum::INCOME->value
                    : CategoryGroupTypeEnum::EXPENSE->value);
            $description = $entry?->description ?: $entry?->title;

            return [
                'uuid' => 'planned-'.$occurrence->uuid,
                'date' => $scheduledDate?->toDateString(),
                'date_label' => $scheduledDate?->translatedFormat('d M'),
                'type' => $this->sectionLabel($sectionKey),
                'type_key' => $sectionKey,
                'kind' => null,
                'kind_label' => null,
                'is_opening_balance' => false,
                'is_projected_recurring' => true,
                'is_recurring_transaction' => false,
                'is_transfer' => false,
                'direction' => $entry?->direction?->value,
                'direction_label' => $entry?->direction?->label(),
                'category_uuid' => $entry?->category?->uuid,
                'category_label' => $entry?->category?->name ?? __('app.common.uncategorized'),
                'category_path' => $entry?->category instanceof Category
                    ? $this->resolveCategoryPath($entry->category)
                    : __('transactions.recurring.preview.path_label'),
                'description' => $description,
                'detail' => $description ?? __('transactions.recurring.preview.detail'),
                'notes' => $occurrence->notes ?? $entry?->notes,
                'account_uuid' => $entry?->account?->uuid,
                'account_label' => $entry?->account?->name ?? 'Conto sconosciuto',
                'related_transaction_uuid' => null,
                'related_account_uuid' => null,
                'related_account_label' => null,
                'tracked_item_uuid' => $entry?->trackedItem?->uuid,
                'tracked_item_label' => $entry?->trackedItem?->name,
                'recurring_occurrence_uuid' => $occurrence->uuid,
                'recurring_entry_uuid' => $entry?->uuid,
                'recurring_entry_show_url' => $entry?->uuid !== null
                    ? route('recurring-entries.show', $entry->uuid)
                    : null,
                'amount_value_raw' => round((float) ($occurrence->expected_amount ?? 0), 2),
                'amount_raw' => $entry?->direction === TransactionDirectionEnum::INCOME
                    ? round((float) ($occurrence->expected_amount ?? 0), 2)
                    : round((float) ($occurrence->expected_amount ?? 0) * -1, 2),
                'balance_after_raw' => null,
                'status' => $occurrence->status?->value,
                'source_type' => null,
                'created_at' => null,
                'updated_at' => null,
                'last_modified_at' => null,
                'created_by' => null,
                'updated_by' => null,
                'can_edit' => false,
                'can_delete' => false,
                'can_restore' => false,
                'can_force_delete' => false,
            ];
        })->all();

        usort($items, function (array $left, array $right): int {
            $dateComparison = strcmp((string) $right['date'], (string) $left['date']);

            if ($dateComparison !== 0) {
                return $dateComparison;
            }

            return strcmp((string) $left['uuid'], (string) $right['uuid']);
        });

        return array_values($items);
    }

    protected function resolveActualOpeningDate(Account $account): ?CarbonImmutable
    {
        $openingTransactionDate = Transaction::query()
            ->where('account_id', $account->id)
            ->where('kind', TransactionKindEnum::OPENING_BALANCE->value)
            ->orderBy('transaction_date')
            ->orderBy('id')
            ->value('transaction_date');

        if ($openingTransactionDate instanceof \DateTimeInterface) {
            return CarbonImmutable::instance($openingTransactionDate);
        }

        if (is_string($openingTransactionDate)) {
            return CarbonImmutable::parse($openingTransactionDate);
        }

        if ($account->opening_balance_date !== null) {
            return CarbonImmutable::parse($account->opening_balance_date);
        }

        return null;
    }

    protected function resolveDerivedYearOpeningBalance(Account $account, int $year): float
    {
        $yearStart = CarbonImmutable::create($year, 1, 1);
        $openingTransaction = Transaction::query()
            ->where('account_id', $account->id)
            ->where('kind', TransactionKindEnum::OPENING_BALANCE->value)
            ->whereYear('transaction_date', $year)
            ->orderBy('transaction_date')
            ->orderBy('id')
            ->first();

        if ($openingTransaction instanceof Transaction) {
            $amount = (float) $openingTransaction->amount;

            return $openingTransaction->direction === TransactionDirectionEnum::EXPENSE
                ? $amount * -1
                : $amount;
        }

        $latestOpeningBeforeYear = Transaction::query()
            ->where('account_id', $account->id)
            ->where('kind', TransactionKindEnum::OPENING_BALANCE->value)
            ->whereDate('transaction_date', '<', $yearStart->toDateString())
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->first();

        $baseAmount = $latestOpeningBeforeYear instanceof Transaction
            ? ($latestOpeningBeforeYear->direction === TransactionDirectionEnum::EXPENSE
                ? (float) $latestOpeningBeforeYear->amount * -1
                : (float) $latestOpeningBeforeYear->amount)
            : (float) ($account->opening_balance ?? 0);
        $netBeforeYearSql = 'COALESCE(SUM(
                CASE
                    WHEN direction = ? THEN amount
                    WHEN direction = ? THEN -amount
                    ELSE 0
                END
            ), 0) as net_total';

        $netBeforeYear = (float) Transaction::query()
            ->where('account_id', $account->id)
            ->where('kind', '!=', TransactionKindEnum::OPENING_BALANCE->value)
            ->when(
                $latestOpeningBeforeYear instanceof Transaction,
                fn ($query) => $query->whereDate('transaction_date', '>=', $latestOpeningBeforeYear->transaction_date->toDateString())
            )
            ->whereDate('transaction_date', '<', $yearStart->toDateString())
            // noinspection SqlNoDataSourceInspection
            // noinspection SqlResolveInspection
            ->selectRaw(
                $netBeforeYearSql,
                [
                    TransactionDirectionEnum::INCOME->value,
                    TransactionDirectionEnum::EXPENSE->value,
                ]
            )
            ->value('net_total');

        return round($baseAmount + $netBeforeYear, 2);
    }

    /**
     * @param  Collection<int, Transaction>  $transactions
     * @return array<int, array{value: string, label: string, group_keys: array<int, string>, category_ids: array<int, int>}>
     */
    protected function buildEditorScopeOptions(User $user, Collection $transactions): array
    {
        $usedScopeIds = $transactions
            ->pluck('scope_id')
            ->filter(fn ($id): bool => $id !== null)
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        return $this->accessibleAccountsQuery->editable($user)
            ->get(['accounts.*'])
            ->flatMap(
                fn (Account $account): Collection => $this->operationalTransactionCategoryResolver->scopesForAccount(
                    $account,
                    $usedScopeIds,
                )
            )
            ->unique('id')
            ->sortBy('name')
            ->values()
            ->map(fn (Scope $scope): array => [
                'id' => (int) $scope->id,
                'value' => $scope->uuid,
                'uuid' => $scope->uuid,
                'label' => $scope->name,
                'owner_user_id' => (int) $scope->user_id,
            ])
            ->all();
    }

    /**
     * @param  Collection<int, Transaction>  $transactions
     * @return array<int, array{value: string, label: string, group_keys: array<int, string>, category_ids: array<int, int>}>
     */
    protected function buildEditorTrackedItemOptions(User $user, Collection $transactions): array
    {
        $usedTrackedItemIds = $transactions
            ->pluck('tracked_item_id')
            ->filter(fn ($id): bool => $id !== null)
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        return $this->accessibleAccountsQuery->editable($user)
            ->get(['accounts.*'])
            ->flatMap(
                fn (Account $account): Collection => $this->operationalTransactionCategoryResolver->trackedItemsForAccount(
                    $account,
                    $usedTrackedItemIds,
                )
            )
            ->unique('id')
            ->sortBy('name')
            ->values()
            ->pipe(
                fn (Collection $trackedItems): array => $this->operationalTransactionCategoryResolver->trackedItemOptionsFromCollection($trackedItems)
            );
    }

    /**
     * @param  Collection<int, Transaction>  $transactions
     */
    /**
     * @param  Collection<int, Account>  $accounts
     * @return array<int, array{account_id: int, account_uuid: string, balance_raw: float, last_recorded_at: string|null}>
     */
    protected function resolvePeriodEndingBalances(Collection $accounts, CarbonImmutable $periodEnd): array
    {
        $accountsById = $accounts->keyBy('id');
        $balances = [];

        $transactions = Transaction::query()
            ->whereIn('account_id', $accountsById->keys()->all())
            ->whereDate('transaction_date', '<=', $periodEnd->toDateString())
            ->whereNotNull('balance_after')
            ->orderBy('transaction_date', 'desc')
            ->orderBy('id', 'desc')
            ->get(['account_id', 'transaction_date', 'balance_after']);

        foreach ($transactions as $transaction) {
            if ($transaction->account_id === null || $transaction->balance_after === null) {
                continue;
            }

            $accountId = (int) $transaction->account_id;

            if (array_key_exists($accountId, $balances) || ! $accountsById->has($accountId)) {
                continue;
            }

            $balances[$accountId] = [
                'account_id' => $accountId,
                'account_uuid' => (string) $accountsById[$accountId]->uuid,
                'balance_raw' => round((float) $transaction->balance_after, 2),
                'last_recorded_at' => $transaction->transaction_date?->toDateString(),
            ];
        }

        foreach ($accountsById as $accountId => $account) {
            if (array_key_exists((int) $accountId, $balances)) {
                continue;
            }

            $openingDate = $this->resolveActualOpeningDate($account);

            if ($openingDate === null || $openingDate->gt($periodEnd)) {
                continue;
            }

            $balances[(int) $accountId] = [
                'account_id' => (int) $accountId,
                'account_uuid' => (string) $account->uuid,
                'balance_raw' => round((float) ($account->opening_balance ?? 0), 2),
                'last_recorded_at' => $openingDate->toDateString(),
            ];
        }

        return $balances;
    }

    protected function resolveAccountBalanceAt(Account $account, CarbonImmutable $date): float
    {
        $latestBalance = Transaction::query()
            ->where('account_id', $account->id)
            ->whereDate('transaction_date', '<=', $date->toDateString())
            ->whereNotNull('balance_after')
            ->orderBy('transaction_date', 'desc')
            ->orderBy('id', 'desc')
            ->value('balance_after');

        if ($latestBalance !== null) {
            return round((float) $latestBalance, 2);
        }

        $openingDate = $this->resolveActualOpeningDate($account);

        if ($openingDate === null || $openingDate->gt($date)) {
            return 0.0;
        }

        return round((float) ($account->opening_balance ?? 0), 2);
    }

    /**
     * @param  array<int, array{account_id: int, account_uuid: string, balance_raw: float, last_recorded_at: string|null}>  $periodEndingBalances
     */
    protected function sumPeriodEndingBalances(
        array $periodEndingBalances,
        Collection $accounts,
        CarbonImmutable $periodEnd,
        string $baseCurrency,
    ): ?float {
        if ($periodEndingBalances === []) {
            return null;
        }

        return round($accounts->sum(function (Account $account) use ($periodEnd, $baseCurrency): float {
            return $this->resolveAggregatedAccountBalanceAt($account, $periodEnd, $baseCurrency) ?? 0.0;
        }), 2);
    }

    protected function resolveAggregateAmountForTransaction(Transaction $transaction, string $baseCurrency): ?float
    {
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

    /**
     * @param  array{income: float, expense: float}  $totals
     */
    protected function applyTransactionToTotals(array &$totals, Transaction $transaction, float $amount): void
    {
        if ($transaction->kind === TransactionKindEnum::REFUND) {
            if ($transaction->direction === TransactionDirectionEnum::INCOME) {
                $totals['expense'] -= $amount;

                return;
            }

            $totals['income'] -= $amount;

            return;
        }

        if ($transaction->direction === TransactionDirectionEnum::INCOME) {
            $totals['income'] += $amount;

            return;
        }

        $totals['expense'] += $amount;
    }

    protected function resolveAggregatedAccountBalanceAt(
        Account $account,
        CarbonImmutable $date,
        string $baseCurrency,
    ): ?float {
        $accountCurrency = $this->normalizeCurrencyCode(
            $account->currency_code ?: $account->currency,
            $baseCurrency,
        );

        if ($accountCurrency === $this->normalizeCurrencyCode($baseCurrency, 'EUR')) {
            return $this->resolveAccountBalanceAt($account, $date);
        }

        $transactions = Transaction::query()
            ->where('account_id', $account->id)
            ->whereDate('transaction_date', '<=', $date->toDateString())
            ->orderBy('transaction_date')
            ->get([
                'direction',
                'amount',
                'currency',
                'currency_code',
                'base_currency_code',
                'converted_base_amount',
            ]);

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

    protected function normalizeCurrencyCode(?string $currencyCode, string $fallback): string
    {
        $normalizedCurrencyCode = strtoupper(trim((string) $currencyCode));

        return $normalizedCurrencyCode !== '' ? $normalizedCurrencyCode : strtoupper(trim($fallback));
    }

    /**
     * @param  Collection<int, Transaction>  $transactions
     * @return array<int, array<string, bool|string|null>>
     */
    protected function buildEditorAccountOptions(User $user, Collection $transactions): array
    {
        $usedAccountIds = $transactions
            ->pluck('account_id')
            ->filter(fn ($id): bool => $id !== null)
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        return $this->accessibleAccountsQuery->editable($user)
            ->where(function (Builder $query) use ($usedAccountIds): void {
                $query->where('is_active', true);

                if ($usedAccountIds !== []) {
                    $query->orWhereIn('accounts.id', $usedAccountIds);
                }
            })
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->with([
                'bank:id,uuid,name',
                'userBank.bank:id,uuid,name',
            ])
            ->get(['accounts.*'])
            ->map(function (Account $account): array {
                return [
                    ...$this->mapAccessibleAccountOption($account),
                    'currency' => $account->currency,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<string, bool|string|null>
     */
    protected function mapAccessibleAccountOption(Account $account): array
    {
        return [
            'value' => $account->uuid,
            'uuid' => $account->uuid,
            'label' => $account->name,
            'account_type_code' => $account->accountType?->code,
            'owner_user_id' => (int) $account->user_id,
            'is_default' => (bool) $account->is_default,
            'category_contributor_user_ids' => $this->operationalTransactionCategoryResolver->contributorUserIdsForAccount($account),
            'scope_contributor_user_ids' => $this->operationalTransactionCategoryResolver->contributorUserIdsForAccount($account),
            'tracked_item_contributor_user_ids' => $this->operationalTransactionCategoryResolver->contributorUserIdsForAccount($account),
            'bank_name' => BankNamePresenter::forAccount($account),
            'is_owned' => (bool) $account->getAttribute('is_owned'),
            'is_shared' => (bool) $account->getAttribute('is_shared'),
            'membership_role' => $account->getAttribute('membership_role'),
            'membership_status' => $account->getAttribute('membership_status'),
            'can_edit' => (bool) $account->getAttribute('can_edit'),
        ];
    }

    /**
     * @return array<int, array<string, int|string|bool|null>>
     */
    protected function buildEditorCategoryOptions(User $user, Collection $transactions): array
    {
        $usedCategoryIds = $transactions
            ->pluck('category_id')
            ->filter(fn ($id): bool => $id !== null)
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        return $this->accessibleAccountsQuery->editable($user)
            ->get(['accounts.*'])
            ->mapWithKeys(fn (Account $account): array => [
                $account->uuid => $this->buildEditorCategoryOptionsForAccount($account, $usedCategoryIds),
            ])
            ->all();
    }

    /**
     * @param  array<int, int>  $usedCategoryIds
     * @return array<int, array<string, mixed>>
     */
    protected function buildEditorCategoryOptionsForAccount(Account $account, array $usedCategoryIds): array
    {
        $categories = $this->operationalTransactionCategoryResolver->categoriesForAccount(
            $account,
            $usedCategoryIds,
        )
            ->values();

        $categoriesById = $categories->keyBy('id');

        return HierarchyOptionLabel::withDisambiguatedLabels(
            collect(CategoryHierarchy::buildFlat($categories))
                ->map(function (array $category) use ($categoriesById, $account): array {
                    $sourceCategory = $categoriesById->get($category['id']);

                    return [
                        'id' => $category['id'],
                        'value' => $category['uuid'],
                        'uuid' => $category['uuid'],
                        'full_path' => $category['full_path'],
                        'slug' => $category['slug'],
                        'account_uuid' => $account->uuid,
                        'owner_user_id' => $sourceCategory instanceof Category
                            ? (int) $sourceCategory->user_id
                            : null,
                        'icon' => $category['icon'] ?? null,
                        'color' => $category['color'] ?? null,
                        'type_key' => $category['group_type']
                            ?: ($category['direction_type'] === TransactionDirectionEnum::INCOME->value
                                ? CategoryGroupTypeEnum::INCOME->value
                                : CategoryGroupTypeEnum::EXPENSE->value),
                        'direction_type' => $category['direction_type'],
                        'group_type' => $category['group_type'],
                        'is_active' => (bool) $category['is_active'],
                        'is_selectable' => (bool) ($category['is_selectable'] ?? false),
                        'sort_order' => isset($category['sort_order']) ? (int) $category['sort_order'] : null,
                        'ancestor_ids' => collect($category['ancestor_ids'] ?? [])
                            ->map(fn ($value): int => (int) $value)
                            ->values()
                            ->all(),
                        'ancestor_uuids' => collect($category['ancestor_uuids'] ?? [])
                            ->filter(fn ($value): bool => is_string($value) && $value !== '')
                            ->values()
                            ->all(),
                    ];
                })
        )
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{value: string, label: string, create_only?: bool}>
     */
    protected function buildEditorGroupOptions(array $ownerIds): array
    {
        $availableGroupKeys = Category::query()
            ->whereIn('user_id', $ownerIds !== [] ? $ownerIds : [0])
            ->where('is_active', true)
            ->where('is_selectable', true)
            ->get(['group_type', 'direction_type'])
            ->map(function (Category $category): ?string {
                if ($category->group_type !== null) {
                    return $category->group_type->value;
                }

                return $category->direction_type === CategoryDirectionTypeEnum::INCOME
                    ? CategoryGroupTypeEnum::INCOME->value
                    : ($category->direction_type === CategoryDirectionTypeEnum::EXPENSE
                        ? CategoryGroupTypeEnum::EXPENSE->value
                        : null);
            })
            ->filter(fn (?string $group): bool => $group !== null)
            ->map(fn ($group): string => (string) $group)
            ->unique()
            ->values()
            ->all();

        $preferredOrder = [
            CategoryGroupTypeEnum::INCOME->value,
            CategoryGroupTypeEnum::EXPENSE->value,
            CategoryGroupTypeEnum::BILL->value,
            CategoryGroupTypeEnum::DEBT->value,
            CategoryGroupTypeEnum::SAVING->value,
            CategoryGroupTypeEnum::TAX->value,
            CategoryGroupTypeEnum::INVESTMENT->value,
            CategoryGroupTypeEnum::TRANSFER->value,
        ];

        $options = collect($preferredOrder)
            ->map(fn (string $key): array => [
                'value' => $key,
                'label' => $this->sectionLabel($key),
            ])
            ->values()
            ->all();

        $options[] = [
            'value' => TransactionKindEnum::BALANCE_ADJUSTMENT->value,
            'label' => __('transactions.balance_adjustment.kind_label'),
            'create_only' => true,
        ];

        return $options;
    }

    /**
     * @return array<int, array{value: string, label: string, create_only?: bool}>
     */
    protected function buildEditorTypeOptions(): array
    {
        return [
            [
                'value' => CategoryGroupTypeEnum::INCOME->value,
                'label' => $this->sectionLabel(CategoryGroupTypeEnum::INCOME->value),
            ],
            [
                'value' => CategoryGroupTypeEnum::EXPENSE->value,
                'label' => $this->sectionLabel(CategoryGroupTypeEnum::EXPENSE->value),
            ],
            [
                'value' => CategoryGroupTypeEnum::BILL->value,
                'label' => $this->sectionLabel(CategoryGroupTypeEnum::BILL->value),
            ],
            [
                'value' => CategoryGroupTypeEnum::DEBT->value,
                'label' => $this->sectionLabel(CategoryGroupTypeEnum::DEBT->value),
            ],
            [
                'value' => CategoryGroupTypeEnum::SAVING->value,
                'label' => $this->sectionLabel(CategoryGroupTypeEnum::SAVING->value),
            ],
            [
                'value' => CategoryGroupTypeEnum::TRANSFER->value,
                'label' => $this->sectionLabel(CategoryGroupTypeEnum::TRANSFER->value),
            ],
            [
                'value' => TransactionKindEnum::BALANCE_ADJUSTMENT->value,
                'label' => __('transactions.balance_adjustment.kind_label'),
                'create_only' => true,
            ],
        ];
    }

    /**
     * @param  Collection<int, Transaction>  $transactions
     * @return array<int, array<string, int|float|string|null>>
     */
    protected function buildEditorCategoryOverviewItems(User $user, int $year, int $month, Collection $transactions): array
    {
        $usedCategoryIds = $transactions
            ->pluck('category_id')
            ->filter(fn ($id): bool => $id !== null)
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        $editableAccounts = $this->accessibleAccountsQuery->editable($user)
            ->get(['accounts.*']);

        $categoriesByAccount = $editableAccounts
            ->mapWithKeys(fn (Account $account): array => [
                $account->id => $this->operationalTransactionCategoryResolver->categoriesForAccount($account, $usedCategoryIds),
            ]);

        $categories = $categoriesByAccount
            ->flatMap(fn (Collection $accountCategories): Collection => $accountCategories)
            ->unique('id')
            ->values();

        $budgetByCategoryId = [];

        $budgets = Budget::query()
            ->with('category.parent')
            ->whereIn(
                'user_id',
                $editableAccounts
                    ->flatMap(fn (Account $account): array => $this->operationalTransactionCategoryResolver->contributorUserIdsForAccount($account))
                    ->unique()
                    ->values()
                    ->all()
            )
            ->where('year', $year)
            ->where('month', $month)
            ->whereNull('scope_id')
            ->whereNull('tracked_item_id')
            ->get(['id', 'user_id', 'category_id', 'amount']);

        $editableAccounts->each(function (Account $account) use ($budgets, &$budgetByCategoryId): void {
            $contributorUserIds = $this->operationalTransactionCategoryResolver->contributorUserIdsForAccount($account);

            $budgets
                ->filter(fn (Budget $budget): bool => in_array((int) $budget->user_id, $contributorUserIds, true))
                ->each(function (Budget $budget) use ($account, &$budgetByCategoryId): void {
                    $sourceCategory = $budget->category;

                    if (! $sourceCategory instanceof Category) {
                        return;
                    }

                    $canonicalCategory = $account->getAttribute('is_shared')
                        ? $this->sharedAccountCategoryTaxonomyService->findCategoryForAccount($account, (int) $sourceCategory->id)
                        : $sourceCategory;

                    if (! $canonicalCategory instanceof Category) {
                        return;
                    }

                    $canonicalCategoryId = (int) $canonicalCategory->id;
                    $budgetByCategoryId[$canonicalCategoryId] = round(
                        (float) ($budgetByCategoryId[$canonicalCategoryId] ?? 0) + (float) $budget->amount,
                        2,
                    );
                });
        });

        $actualByCategory = $transactions
            ->filter(fn (Transaction $transaction): bool => $transaction->category_id !== null
                && $transaction->kind !== TransactionKindEnum::OPENING_BALANCE
                && ! (bool) $transaction->is_transfer)
            ->mapToGroups(function (Transaction $transaction): array {
                $amount = $transaction->direction === TransactionDirectionEnum::INCOME
                    ? (float) $transaction->amount
                    : abs((float) $transaction->amount);

                return [(int) $transaction->category_id => $amount];
            })
            ->map(fn (Collection $amounts): float => round((float) $amounts->sum(), 2));

        return collect(CategoryHierarchy::buildFlat($categories))
            ->filter(fn (array $category): bool => (bool) $category['is_selectable'])
            ->map(function (array $category) use ($budgetByCategoryId, $actualByCategory): array {
                $categoryId = (int) $category['id'];
                $budget = round((float) ($budgetByCategoryId[$categoryId] ?? 0), 2);
                $actual = round((float) ($actualByCategory[$categoryId] ?? 0), 2);

                return [
                    'uuid' => (string) $category['uuid'],
                    'key' => 'editor-category:'.$category['uuid'],
                    'label' => (string) ($category['full_path'] ?? $category['name'] ?? 'Categoria'),
                    'group_key' => $category['group_type']
                        ?: ($category['direction_type'] === TransactionDirectionEnum::INCOME->value
                            ? CategoryGroupTypeEnum::INCOME->value
                            : CategoryGroupTypeEnum::EXPENSE->value),
                    'actual_raw' => $actual,
                    'budget_raw' => $budget,
                    'progress_percentage' => $budget > 0 ? round(($actual / $budget) * 100, 1) : 0.0,
                    'remaining_raw' => round(max($budget - $actual, 0), 2),
                    'excess_raw' => round(max($actual - $budget, 0), 2),
                    'count' => 0,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array{uuid: string, name: string, email: string}|null
     */
    protected function mapAuditActor(?User $user): ?array
    {
        if (! $user instanceof User) {
            return null;
        }

        return [
            'uuid' => $user->uuid,
            'name' => $user->name,
            'email' => $user->email,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $sections
     * @return array<int, array<string, int|float|string|null>>
     */
    protected function buildOverviewGroups(array $sections): array
    {
        $sectionMap = collect($sections)->mapWithKeys(
            fn (array $section): array => [$section['key'] => $section]
        );

        $keys = [
            CategoryGroupTypeEnum::INCOME->value,
            CategoryGroupTypeEnum::EXPENSE->value,
            CategoryGroupTypeEnum::BILL->value,
            CategoryGroupTypeEnum::DEBT->value,
            CategoryGroupTypeEnum::SAVING->value,
        ];

        return collect($keys)
            ->map(function (string $key) use ($sectionMap): array {
                $section = $sectionMap[$key] ?? null;
                $actual = $key === CategoryGroupTypeEnum::INCOME->value
                    ? (float) ($section['totals']['income'] ?? 0)
                    : (float) ($section['totals']['expense'] ?? 0);
                $budget = (float) ($section['totals']['budget'] ?? 0);

                return [
                    'key' => $key,
                    'label' => $this->sectionLabel($key),
                    'actual_raw' => round($actual, 2),
                    'budget_raw' => round($budget, 2),
                    'progress_percentage' => $budget > 0 ? round(($actual / $budget) * 100, 1) : 0.0,
                    'remaining_raw' => round(max($budget - $actual, 0), 2),
                    'excess_raw' => round(max($actual - $budget, 0), 2),
                    'count' => (int) ($section['totals']['count'] ?? 0),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $sections
     * @return array<int, array<string, int|float|string|null>>
     */
    protected function buildOverviewCategories(array $sections): array
    {
        return collect($sections)
            ->flatMap(function (array $section): Collection {
                return collect($section['flat_rows'])->map(function (array $row) use ($section): array {
                    $actual = $section['key'] === CategoryGroupTypeEnum::INCOME->value
                        ? (float) ($row['actual_income_raw'] ?? 0)
                        : (float) ($row['actual_expense_raw'] ?? 0);
                    $budget = (float) ($row['budgeted_amount_raw'] ?? 0);

                    return [
                        'uuid' => (string) $row['uuid'],
                        'key' => 'category:'.$row['uuid'],
                        'label' => (string) ($row['full_path'] ?? $row['name'] ?? 'Categoria'),
                        'group_key' => (string) $section['key'],
                        'actual_raw' => round($actual, 2),
                        'budget_raw' => round($budget, 2),
                        'progress_percentage' => $budget > 0 ? round(($actual / $budget) * 100, 1) : 0.0,
                        'remaining_raw' => round(max($budget - $actual, 0), 2),
                        'excess_raw' => round(max($actual - $budget, 0), 2),
                        'count' => (int) ($row['transaction_count'] ?? 0),
                    ];
                });
            })
            ->values()
            ->all();
    }

    protected function resolveCategoryPath(?Category $category): string
    {
        if (! $category instanceof Category) {
            return __('app.common.uncategorized');
        }

        $segments = [$category->name];
        $parent = $category->parent;
        $visited = [$category->id];

        while ($parent instanceof Category) {
            // Prevent infinite loops in category hierarchy
            if (in_array($parent->id, $visited, true)) {
                break;
            }

            $visited[] = $parent->id;
            array_unshift($segments, $parent->name);
            $parent = $parent->parent;
        }

        return implode(' > ', $segments);
    }

    /**
     * @param  array<int, array<string, mixed>>  $sections
     * @return array<int, array<string, string>>
     */
    protected function buildGroupOptions(array $sections): array
    {
        $options = [['value' => 'all', 'label' => __('app.common.all_groups')]];

        foreach ($sections as $section) {
            $options[] = [
                'value' => $section['key'],
                'label' => $section['label'],
            ];
        }

        return $options;
    }

    /**
     * @return array<string, mixed>
     */
    protected function summaryCard(string $key, string $label, float $actual, float $budget): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'actual_raw' => round($actual, 2),
            'budgeted_raw' => round($budget, 2),
            'variance_raw' => round($actual - $budget, 2),
            'variance_percentage' => $budget > 0 ? round((($actual - $budget) / $budget) * 100, 1) : null,
        ];
    }

    protected function getMonthLabel(int $month): string
    {
        $labels = [
            1 => __('dashboard.months.1'), 2 => __('dashboard.months.2'), 3 => __('dashboard.months.3'), 4 => __('dashboard.months.4'),
            5 => __('dashboard.months.5'), 6 => __('dashboard.months.6'), 7 => __('dashboard.months.7'), 8 => __('dashboard.months.8'),
            9 => __('dashboard.months.9'), 10 => __('dashboard.months.10'), 11 => __('dashboard.months.11'), 12 => __('dashboard.months.12'),
        ];

        return $labels[$month] ?? __('dashboard.period.unknown_month');
    }

    protected function sectionKeyForCategory(Category $category): string
    {
        if ($category->group_type instanceof CategoryGroupTypeEnum) {
            return $category->group_type->value;
        }

        return match ($category->direction_type?->value) {
            'income' => CategoryGroupTypeEnum::INCOME->value,
            'expense' => CategoryGroupTypeEnum::EXPENSE->value,
            default => 'other',
        };
    }

    protected function sectionLabel(string $sectionKey): string
    {
        return match ($sectionKey) {
            CategoryGroupTypeEnum::INCOME->value => __('app.enums.category_groups.income'),
            CategoryGroupTypeEnum::EXPENSE->value => __('app.enums.category_groups.expense'),
            CategoryGroupTypeEnum::BILL->value => __('app.enums.category_groups.bill'),
            CategoryGroupTypeEnum::DEBT->value => __('app.enums.category_groups.debt'),
            CategoryGroupTypeEnum::SAVING->value => __('app.enums.category_groups.saving'),
            CategoryGroupTypeEnum::TAX->value => __('app.enums.category_groups.tax'),
            CategoryGroupTypeEnum::INVESTMENT->value => __('app.enums.category_groups.investment'),
            CategoryGroupTypeEnum::TRANSFER->value => __('app.enums.transaction_directions.transfer'),
            TransactionKindEnum::BALANCE_ADJUSTMENT->value => __('transactions.balance_adjustment.kind_label'),
            default => __('app.enums.category_groups.other'),
        };
    }

    protected function transferCategoryLabel(Transaction $transaction): string
    {
        if ($transaction->kind === TransactionKindEnum::CREDIT_CARD_SETTLEMENT) {
            return __('transactions.credit_card.settlement.row_label');
        }

        return __('transactions.transfer_between_accounts.row_label');
    }

    protected function transferCategoryPath(Transaction $transaction): string
    {
        if ($transaction->kind === TransactionKindEnum::CREDIT_CARD_SETTLEMENT) {
            return __('transactions.credit_card.settlement.path_label');
        }

        return __('transactions.transfer_between_accounts.path_label');
    }

    protected function sectionDescription(string $sectionKey): string
    {
        return match ($sectionKey) {
            CategoryGroupTypeEnum::INCOME->value => __('dashboard.sections.income'),
            CategoryGroupTypeEnum::EXPENSE->value => __('dashboard.sections.expense'),
            CategoryGroupTypeEnum::BILL->value => __('dashboard.sections.bill'),
            CategoryGroupTypeEnum::DEBT->value => __('dashboard.sections.debt'),
            CategoryGroupTypeEnum::SAVING->value => __('dashboard.sections.saving'),
            CategoryGroupTypeEnum::TAX->value => __('dashboard.sections.tax'),
            CategoryGroupTypeEnum::INVESTMENT->value => __('dashboard.sections.investment'),
            CategoryGroupTypeEnum::TRANSFER->value => __('dashboard.sections.transfer'),
            default => __('dashboard.sections.other'),
        };
    }
}
