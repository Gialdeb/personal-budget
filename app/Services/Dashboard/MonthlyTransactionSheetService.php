<?php

namespace App\Services\Dashboard;

use App\Enums\CategoryDirectionTypeEnum;
use App\Enums\CategoryGroupTypeEnum;
use App\Enums\TransactionDirectionEnum;
use App\Enums\TransactionKindEnum;
use App\Models\Account;
use App\Models\Budget;
use App\Models\Category;
use App\Models\TrackedItem;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserYear;
use App\Services\UserYearService;
use App\Supports\CategoryHierarchy;
use App\Supports\TrackedItemHierarchy;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class MonthlyTransactionSheetService
{
    public function __construct(
        protected UserYearService $userYearService
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(User $user, int $year, int $month): array
    {
        $availableYears = $this->userYearService->availableYears($user);
        $selectedYear = UserYear::query()
            ->where('user_id', $user->id)
            ->where('year', $year)
            ->first();

        $transactions = $this->getMonthlyTransactions($user->id, $year, $month);
        $budgets = $this->getMonthlyBudgets($user->id, $year, $month);
        $transactionList = $this->buildTransactionsList($user, $year, $month, $transactions);

        $transactionsByCategory = $this->groupTransactionsByCategory($transactions);
        $budgetsByCategory = $this->groupBudgetsByCategory($budgets);

        $categories = $this->getRelevantCategories($user->id, $transactionsByCategory, $budgetsByCategory);
        $sections = $this->buildSections($categories, $transactionsByCategory, $budgetsByCategory);

        $transactionTotals = $this->calculateTransactionTotals($sections);
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
                'category_options' => $this->buildCategoryOptions($transactions),
                'account_options' => $this->buildAccountOptions($transactions),
            ],
            'settings' => [
                'active_year' => $user->settings?->active_year,
                'base_currency' => $user->base_currency_code,
            ],
            'period' => [
                'year' => $year,
                'month' => $month,
                'month_label' => $this->getMonthLabel($month),
                'is_current_month' => $year === now()->year && $month === now()->month,
            ],
            'summary_cards' => $this->buildSummaryCards($sections),
            'transactions' => $transactionList,
            'editor' => [
                'can_edit' => ! $isClosedYear,
                'group_options' => $this->buildEditorGroupOptions($user->id),
                'accounts' => $this->buildEditorAccountOptions($user->id, $transactions),
                'categories' => $this->buildEditorCategoryOptions($user->id, $transactionsByCategory),
                'tracked_items' => $this->buildEditorTrackedItemOptions($user->id, $transactions),
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
                'net_actual_raw' => ($transactionTotals['income'] ?? 0) + ($transactionTotals['expense'] ?? 0),
                'net_budgeted_raw' => ($budgetTotals['income'] ?? 0) - abs($budgetTotals['expense'] ?? 0),
            ],
            'meta' => [
                'year_is_closed' => $isClosedYear,
                'closed_year_message' => $isClosedYear
                    ? __('transactions.closed_year_message', ['year' => $year])
                    : null,
                'transactions_count' => count($transactionList),
                'last_balance_raw' => $this->resolveLastBalance($transactions),
                'last_recorded_at' => $transactionList[0]['date'] ?? null,
                'has_budget_data' => $budgets->isNotEmpty(),
            ],
        ];
    }

    /**
     * @return Collection<int, Transaction>
     */
    protected function getMonthlyTransactions(int $userId, int $year, int $month): Collection
    {
        return Transaction::query()
            ->where('user_id', $userId)
            ->whereYear('transaction_date', $year)
            ->whereMonth('transaction_date', $month)
            ->with(['category.parent', 'merchant', 'account', 'trackedItem', 'relatedTransaction.account'])
            ->orderBy('transaction_date', 'desc')
            ->orderByRaw(
                'case when kind = ? then 0 else 1 end asc',
                [TransactionKindEnum::OPENING_BALANCE->value]
            )
            ->orderBy('created_at', 'desc')
            ->get();
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
    protected function groupTransactionsByCategory(Collection $transactions): array
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

            $amount = (float) $transaction->amount;
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
    protected function getRelevantCategories(int $userId, array $transactionsByCategory, array $budgetsByCategory): Collection
    {
        $relevantCategoryIds = collect(array_keys($transactionsByCategory))
            ->merge(array_keys($budgetsByCategory))
            ->filter()
            ->unique()
            ->values()
            ->all();

        return Category::query()
            ->ownedBy($userId)
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
    protected function calculateTransactionTotals(array $sections): array
    {
        $totals = ['income' => 0, 'expense' => 0];

        foreach ($sections as $section) {
            $totals['income'] += $section['totals']['income'];
            $totals['expense'] += $section['totals']['expense'];
        }

        return $totals;
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
    protected function buildSummaryCards(array $sections): array
    {
        $sectionTotals = collect($sections)->mapWithKeys(
            fn (array $section): array => [$section['key'] => $section['totals']]
        );

        $incomeActual = $sectionTotals[CategoryGroupTypeEnum::INCOME->value]['income'] ?? 0;
        $expenseActual = $sectionTotals[CategoryGroupTypeEnum::EXPENSE->value]['expense'] ?? 0;
        $billActual = $sectionTotals[CategoryGroupTypeEnum::BILL->value]['expense'] ?? 0;
        $savingActual = $sectionTotals[CategoryGroupTypeEnum::SAVING->value]['expense'] ?? 0;

        $incomeBudget = $sectionTotals[CategoryGroupTypeEnum::INCOME->value]['budget'] ?? 0;
        $expenseBudget = $sectionTotals[CategoryGroupTypeEnum::EXPENSE->value]['budget'] ?? 0;
        $billBudget = $sectionTotals[CategoryGroupTypeEnum::BILL->value]['budget'] ?? 0;
        $savingBudget = $sectionTotals[CategoryGroupTypeEnum::SAVING->value]['budget'] ?? 0;

        return [
            $this->summaryCard('income', __('app.enums.category_groups.income'), $incomeActual, $incomeBudget),
            $this->summaryCard('expense', __('app.enums.category_groups.expense'), $expenseActual, $expenseBudget),
            $this->summaryCard('bill', __('app.enums.category_groups.bill'), $billActual, $billBudget),
            $this->summaryCard('saving', __('app.enums.category_groups.saving'), $savingActual, $savingBudget),
            $this->summaryCard('net', __('transactions.monthly.totals.netBalance'), $incomeActual - $expenseActual - $billActual, $incomeBudget - $expenseBudget - $billBudget),
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
     * @return array<int, array{value: string, label: string}>
     */
    protected function buildAccountOptions(Collection $transactions): array
    {
        return $transactions
            ->filter(fn (Transaction $transaction): bool => $transaction->account !== null)
            ->map(fn (Transaction $transaction): array => [
                'value' => (string) $transaction->account?->uuid,
                'uuid' => $transaction->account?->uuid,
                'label' => $transaction->account?->name ?? 'Conto sconosciuto',
            ])
            ->unique('value')
            ->sortBy('label')
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, Transaction>  $transactions
     * @return array<int, array<string, int|float|string|null>>
     */
    protected function buildTransactionsList(User $user, int $year, int $month, Collection $transactions): array
    {
        $items = $transactions
            ->map(function (Transaction $transaction): array {
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

                $detail = $transaction->merchant?->name
                    ?? $transaction->counterparty_name
                    ?? $transaction->description
                    ?? $transaction->bank_description_clean
                    ?? $transaction->bank_description_raw;

                return [
                    'uuid' => $transaction->uuid,
                    'date' => $transaction->transaction_date?->toDateString(),
                    'date_label' => $transaction->transaction_date?->translatedFormat('d M'),
                    'type' => $this->sectionLabel($sectionKey),
                    'type_key' => $sectionKey,
                    'kind' => $transaction->kind?->value,
                    'kind_label' => $transaction->kind?->label(),
                    'is_opening_balance' => $transaction->kind === TransactionKindEnum::OPENING_BALANCE,
                    'is_transfer' => (bool) $transaction->is_transfer,
                    'direction' => $transaction->direction?->value,
                    'direction_label' => $transaction->direction?->label(),
                    'category_uuid' => $transaction->category?->uuid,
                    'category_label' => $transaction->kind === TransactionKindEnum::OPENING_BALANCE
                        ? __('transactions.opening_balance.row_label', ['year' => $transaction->transaction_date?->year ?? now()->year])
                        : ($transaction->is_transfer
                            ? __('app.enums.transaction_directions.transfer')
                            : ($transaction->category?->name ?? __('app.common.uncategorized'))),
                    'category_path' => $transaction->kind === TransactionKindEnum::OPENING_BALANCE
                        ? __('transactions.opening_balance.path_label')
                        : ($transaction->is_transfer
                        ? __('dashboard.sections.transfer')
                        : $this->resolveCategoryPath($transaction->category)),
                    'description' => $transaction->kind === TransactionKindEnum::OPENING_BALANCE
                        ? __('transactions.opening_balance.kind_label')
                        : $transaction->description,
                    'detail' => $transaction->kind === TransactionKindEnum::OPENING_BALANCE
                        ? __('transactions.opening_balance.detail')
                        : $detail,
                    'notes' => $transaction->notes,
                    'account_uuid' => $transaction->account?->uuid,
                    'account_label' => $transaction->account?->name ?? 'Conto sconosciuto',
                    'related_transaction_uuid' => $transaction->relatedTransaction?->uuid,
                    'related_account_uuid' => $transaction->relatedTransaction?->account?->uuid,
                    'related_account_label' => $transaction->relatedTransaction?->account?->name,
                    'tracked_item_uuid' => $transaction->trackedItem?->uuid,
                    'tracked_item_label' => $transaction->trackedItem?->name,
                    'amount_value_raw' => round((float) $transaction->amount, 2),
                    'amount_raw' => $transaction->direction === TransactionDirectionEnum::INCOME
                        ? round((float) $transaction->amount, 2)
                        : round((float) $transaction->amount * -1, 2),
                    'balance_after_raw' => $transaction->balance_after !== null
                        ? round((float) $transaction->balance_after, 2)
                        : null,
                    'status' => $transaction->status?->value,
                    'source_type' => $transaction->source_type?->value,
                    'can_edit' => $transaction->kind !== TransactionKindEnum::OPENING_BALANCE,
                    'can_delete' => $transaction->kind !== TransactionKindEnum::OPENING_BALANCE,
                ];
            })
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
    protected function buildDerivedYearOpeningRows(User $user, int $year, Collection $transactions): array
    {
        $accounts = Account::query()
            ->ownedBy($user->id)
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
                    'amount_value_raw' => round(abs($amount), 2),
                    'amount_raw' => round($amount, 2),
                    'balance_after_raw' => round($amount, 2),
                    'status' => null,
                    'source_type' => null,
                    'can_edit' => false,
                    'can_delete' => false,
                ];
            })
            ->filter()
            ->values()
            ->all();
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

        return round($baseAmount + $netBeforeYear, 2);
    }

    /**
     * @param  Collection<int, Transaction>  $transactions
     * @return array<int, array{value: string, label: string, group_keys: array<int, string>, category_ids: array<int, int>}>
     */
    protected function buildEditorTrackedItemOptions(int $userId, Collection $transactions): array
    {
        $usedTrackedItemIds = $transactions
            ->pluck('tracked_item_id')
            ->filter(fn ($id): bool => $id !== null)
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        $trackedItems = TrackedItem::query()
            ->ownedBy($userId)
            ->with('compatibleCategories:id,uuid')
            ->withCount('children')
            ->where(function (Builder $query) use ($usedTrackedItemIds): void {
                $query->where('is_active', true);

                if ($usedTrackedItemIds !== []) {
                    $query->orWhereIn('id', $usedTrackedItemIds);
                }
            })
            ->orderBy('name')
            ->get([
                'id',
                'uuid',
                'parent_id',
                'name',
                'slug',
                'type',
                'is_active',
                'settings',
            ]);

        return collect(TrackedItemHierarchy::buildFlat($trackedItems))
            ->map(fn (array $trackedItem): array => [
                'id' => $trackedItem['id'],
                'value' => $trackedItem['uuid'],
                'uuid' => $trackedItem['uuid'],
                'label' => $trackedItem['full_path'],
                'group_keys' => collect($trackedItem['settings']['transaction_group_keys'] ?? [])
                    ->filter(fn ($value): bool => is_string($value) && $value !== '')
                    ->values()
                    ->all(),
                'category_ids' => collect($trackedItem['compatible_category_ids'] ?? [])
                    ->map(fn ($value): int => (int) $value)
                    ->values()
                    ->all(),
                'category_uuids' => collect($trackedItem['compatible_category_uuids'] ?? [])
                    ->filter(fn ($value): bool => is_string($value) && $value !== '')
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, Transaction>  $transactions
     */
    protected function resolveLastBalance(Collection $transactions): ?float
    {
        $lastTransactionWithBalance = $transactions->first(
            fn (Transaction $transaction): bool => $transaction->balance_after !== null
        );

        if (! $lastTransactionWithBalance) {
            return null;
        }

        return round((float) $lastTransactionWithBalance->balance_after, 2);
    }

    /**
     * @param  Collection<int, Transaction>  $transactions
     * @return array<int, array{value: string, label: string, currency: string}>
     */
    protected function buildEditorAccountOptions(int $userId, Collection $transactions): array
    {
        $usedAccountIds = $transactions
            ->pluck('account_id')
            ->filter(fn ($id): bool => $id !== null)
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        return Account::query()
            ->ownedBy($userId)
            ->where(function (Builder $query) use ($usedAccountIds): void {
                $query->where('is_active', true);

                if ($usedAccountIds !== []) {
                    $query->orWhereIn('id', $usedAccountIds);
                }
            })
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get(['id', 'uuid', 'name', 'currency'])
            ->map(fn (Account $account): array => [
                'value' => $account->uuid,
                'uuid' => $account->uuid,
                'label' => $account->name,
                'currency' => $account->currency,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array{income: float, expense: float, count: int}>  $transactionsByCategory
     * @return array<int, array<string, int|string|bool|null>>
     */
    protected function buildEditorCategoryOptions(int $userId, array $transactionsByCategory): array
    {
        $usedCategoryIds = collect(array_keys($transactionsByCategory))
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();

        $categories = Category::query()
            ->ownedBy($userId)
            ->withCount('children')
            ->where(function (Builder $query) use ($usedCategoryIds): void {
                $query->where('is_active', true);

                if ($usedCategoryIds !== []) {
                    $query->orWhereIn('id', $usedCategoryIds);
                }
            })
            ->where(function (Builder $query): void {
                $query->whereNull('group_type')
                    ->orWhere('group_type', '!=', CategoryGroupTypeEnum::TRANSFER->value);
            })
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get([
                'id',
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
            ]);

        return collect(CategoryHierarchy::buildFlat($categories))
            ->filter(fn (array $category): bool => (bool) $category['is_selectable'])
            ->map(fn (array $category): array => [
                'id' => $category['id'],
                'value' => $category['uuid'],
                'uuid' => $category['uuid'],
                'label' => $category['full_path'],
                'type_key' => $category['group_type']
                    ?: ($category['direction_type'] === TransactionDirectionEnum::INCOME->value
                        ? CategoryGroupTypeEnum::INCOME->value
                        : CategoryGroupTypeEnum::EXPENSE->value),
                'direction_type' => $category['direction_type'],
                'group_type' => $category['group_type'],
                'is_active' => (bool) $category['is_active'],
                'ancestor_ids' => collect($category['ancestor_ids'] ?? [])
                    ->map(fn ($value): int => (int) $value)
                    ->values()
                    ->all(),
                'ancestor_uuids' => collect($category['ancestor_uuids'] ?? [])
                    ->filter(fn ($value): bool => is_string($value) && $value !== '')
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    protected function buildEditorGroupOptions(int $userId): array
    {
        $availableGroupKeys = Category::query()
            ->ownedBy($userId)
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

        return collect($preferredOrder)
            ->filter(fn (string $key): bool => in_array($key, $availableGroupKeys, true))
            ->map(fn (string $key): array => [
                'value' => $key,
                'label' => $this->sectionLabel($key),
            ])
            ->values()
            ->all();
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
            default => __('app.enums.category_groups.other'),
        };
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
