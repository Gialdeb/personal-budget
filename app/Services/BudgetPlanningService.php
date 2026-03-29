<?php

namespace App\Services;

use App\Enums\BudgetTypeEnum;
use App\Enums\CategoryDirectionTypeEnum;
use App\Enums\CategoryGroupTypeEnum;
use App\Models\Budget;
use App\Models\Category;
use App\Models\User;
use App\Models\UserYear;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BudgetPlanningService
{
    public function __construct(protected UserYearService $userYearService) {}

    /**
     * @return array<string, mixed>
     */
    public function build(User $user, int $year): array
    {
        $availableYears = $this->userYearService->availableYears($user);
        $selectedYear = UserYear::query()
            ->where('user_id', $user->id)
            ->where('year', $year)
            ->first();
        $budgetRows = $this->basePlanningBudgetQuery($user, $year)
            ->get([
                'budgets.category_id',
                'budgets.month',
                'budgets.amount',
            ]);

        $budgetCategoryIds = $budgetRows
            ->pluck('category_id')
            ->filter()
            ->map(fn ($categoryId): int => (int) $categoryId)
            ->unique()
            ->values()
            ->all();

        $categories = $this->planningCategories($user, $budgetCategoryIds);
        $budgetMatrix = $this->budgetMatrix($budgetRows);
        $sections = $this->buildSections($categories, $budgetMatrix);
        $columnTotals = $this->columnTotals($sections);
        $parentBudgetConflicts = $this->parentBudgetConflicts($sections);
        $sectionOptions = collect($sections)
            ->map(fn (array $section): array => [
                'value' => $section['key'],
                'label' => $section['label'],
            ])
            ->values()
            ->all();

        return [
            'filters' => [
                'year' => $year,
                'available_years' => collect($availableYears)
                    ->map(fn (int $availableYear): array => [
                        'value' => $availableYear,
                        'label' => (string) $availableYear,
                    ])
                    ->values()
                    ->all(),
                'group_options' => [
                    [
                        'value' => 'all',
                        'label' => __('app.common.all_groups'),
                    ],
                    ...$sectionOptions,
                ],
            ],
            'settings' => [
                'active_year' => $user->settings?->active_year,
                'base_currency' => $user->base_currency_code,
            ],
            'months' => $this->months(),
            'summary_cards' => $this->summaryCards($sections),
            'sections' => $sections,
            'column_totals_raw' => $columnTotals,
            'grand_total_raw' => round(array_sum($columnTotals), 2),
            'meta' => [
                'copy_previous_year_available' => in_array($year - 1, $availableYears, true),
                'previous_year' => $year - 1,
                'selectable_rows_count' => collect($sections)
                    ->sum(fn (array $section): int => collect($section['flat_rows'])
                        ->where('is_editable', true)
                        ->count()),
                'parent_budget_conflicts' => $parentBudgetConflicts,
                'year_is_closed' => $selectedYear?->is_closed ?? false,
                'closed_year_message' => $selectedYear?->is_closed
                    ? __('settings.years.closed_for_editing', ['year' => $year])
                    : null,
                'year_suggestion' => $this->userYearService->buildNextYearSuggestion($user, $year),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function updateCell(User $user, array $payload): array
    {
        $this->userYearService->ensureYearIsOpen($user, (int) $payload['year']);

        $category = Category::query()
            ->whereKey((int) $payload['category_id'])
            ->ownedBy($user->id)
            ->withCount('children')
            ->firstOrFail();

        if (! $category->is_selectable || $category->children_count > 0) {
            throw ValidationException::withMessages([
                'category_uuid' => __('planning.validation.leaf_only'),
            ]);
        }

        $amount = round((float) $payload['amount'], 2);
        $attributes = [
            'user_id' => $user->id,
            'scope_id' => null,
            'tracked_item_id' => null,
            'category_id' => $category->id,
            'year' => (int) $payload['year'],
            'month' => (int) $payload['month'],
            'budget_type' => $this->budgetTypeForCategory($category)->value,
        ];

        DB::transaction(function () use ($attributes, $amount): void {
            if ($amount === 0.0) {
                Budget::query()
                    ->where($attributes)
                    ->delete();

                return;
            }

            Budget::query()->updateOrCreate(
                $attributes,
                [
                    'amount' => $amount,
                    'notes' => null,
                ]
            );
        });

        return [
            'saved' => [
                'category_uuid' => $category->uuid,
                'year' => (int) $payload['year'],
                'month' => (int) $payload['month'],
                'amount_raw' => $amount,
                'budget_type' => $this->budgetTypeForCategory($category)->value,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function copyPreviousYear(User $user, int $year): array
    {
        $this->userYearService->ensureYearIsOpen($user, $year);

        $sourceYear = $year - 1;

        $sourceBudgets = $this->basePlanningBudgetQuery($user, $sourceYear)
            ->get([
                'budgets.category_id',
                'budgets.month',
                'budgets.amount',
                'budgets.budget_type',
            ]);

        if ($sourceBudgets->isEmpty()) {
            throw ValidationException::withMessages([
                'year' => __('planning.validation.copy_source_empty', ['year' => $sourceYear]),
            ]);
        }

        DB::transaction(function () use ($user, $year, $sourceBudgets): void {
            foreach ($sourceBudgets as $budget) {
                $category = Category::query()->find($budget->category_id);

                if (! $category instanceof Category) {
                    continue;
                }

                Budget::query()->updateOrCreate(
                    [
                        'user_id' => $user->id,
                        'scope_id' => null,
                        'tracked_item_id' => null,
                        'category_id' => $budget->category_id,
                        'year' => $year,
                        'month' => $budget->month,
                        'budget_type' => $budget->budget_type,
                    ],
                    [
                        'amount' => $budget->amount,
                        'notes' => null,
                    ]
                );
            }
        });

        return $this->build($user, $year);
    }

    /**
     * @return array<int, int>
     */
    public function resolveAvailableYears(User $user): array
    {
        return $this->userYearService->availableYears($user);
    }

    protected function basePlanningBudgetQuery(User $user, int $year): Builder
    {
        return Budget::query()
            ->join('categories', 'budgets.category_id', '=', 'categories.id')
            ->select('budgets.*')
            ->where('budgets.year', $year)
            ->whereNull('budgets.scope_id')
            ->whereNull('budgets.tracked_item_id')
            ->where('budgets.user_id', $user->id)
            ->whereNull('categories.account_id');
    }

    /**
     * @param  array<int, int>  $budgetCategoryIds
     * @return Collection<int, Category>
     */
    protected function planningCategories(User $user, array $budgetCategoryIds): Collection
    {
        return Category::query()
            ->ownedBy($user->id)
            ->withCount('children')
            ->where(function (Builder $query): void {
                $query->whereNull('group_type')
                    ->orWhere('group_type', '!=', CategoryGroupTypeEnum::TRANSFER->value);
            })
            ->where(function (Builder $query) use ($budgetCategoryIds): void {
                $query->where('is_active', true);

                if ($budgetCategoryIds !== []) {
                    $query->orWhereIn('id', $budgetCategoryIds);
                }
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
     * @param  Collection<int, Budget>  $budgetRows
     * @return array<int, array<int, float>>
     */
    protected function budgetMatrix(Collection $budgetRows): array
    {
        $matrix = [];

        foreach ($budgetRows as $budget) {
            if ($budget->category_id === null) {
                continue;
            }

            $matrix[(int) $budget->category_id][(int) $budget->month] = round((float) $budget->amount, 2);
        }

        return $matrix;
    }

    /**
     * @param  Collection<int, Category>  $categories
     * @param  array<int, array<int, float>>  $budgetMatrix
     * @return array<int, array<string, mixed>>
     */
    protected function buildSections(Collection $categories, array $budgetMatrix): array
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
            ->map(function (Collection $rootCategories, string $sectionKey) use ($childrenByParent, $budgetMatrix): array {
                $rows = $rootCategories
                    ->map(fn (Category $category): array => $this->buildRow(
                        $category,
                        $childrenByParent,
                        $budgetMatrix
                    ))
                    ->values()
                    ->all();

                $totalsByMonth = $this->sumRowsByMonth($rows);

                return [
                    'key' => $sectionKey,
                    'label' => $this->sectionLabel($sectionKey),
                    'description' => $this->sectionDescription($sectionKey),
                    'rows' => $rows,
                    'flat_rows' => $this->flattenRows($rows),
                    'totals_by_month_raw' => $totalsByMonth,
                    'total_raw' => round(array_sum($totalsByMonth), 2),
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
     * @param  array<int, array<int, float>>  $budgetMatrix
     * @param  array<int, int>  $ancestorIds
     * @param  array<int, string>  $ancestorNames
     * @return array<string, mixed>
     */
    protected function buildRow(
        Category $category,
        Collection $childrenByParent,
        array $budgetMatrix,
        array $ancestorIds = [],
        array $ancestorNames = [],
        array $ancestorUuids = []
    ): array {
        $children = $childrenByParent->get($category->id, collect())
            ->map(fn (Category $child): array => $this->buildRow(
                $child,
                $childrenByParent,
                $budgetMatrix,
                [...$ancestorIds, $category->id],
                [...$ancestorNames, $category->name],
                [...$ancestorUuids, $category->uuid]
            ))
            ->values()
            ->all();

        $monthlyAmounts = $children !== []
            ? $this->sumRowsByMonth($children)
            : $this->leafMonthlyAmounts($budgetMatrix[(int) $category->id] ?? []);
        $directMonthlyAmounts = $this->leafMonthlyAmounts($budgetMatrix[(int) $category->id] ?? []);

        $fullPath = implode(' > ', [...$ancestorNames, $category->name]);

        return [
            'uuid' => $category->uuid,
            'parent_uuid' => $ancestorUuids !== [] ? $ancestorUuids[count($ancestorUuids) - 1] : null,
            'name' => $category->name,
            'full_path' => $fullPath,
            'depth' => count($ancestorIds),
            'group_type' => $category->group_type?->value,
            'direction_type' => $category->direction_type?->value,
            'icon' => $category->icon,
            'color' => $category->color,
            'is_active' => $category->is_active,
            'is_selectable' => $category->is_selectable,
            'is_editable' => $children === [] && $category->is_selectable,
            'has_children' => $children !== [],
            'budget_type' => $this->budgetTypeForCategory($category)->value,
            'ancestor_uuids' => $ancestorUuids,
            'monthly_amounts_raw' => $monthlyAmounts,
            'row_total_raw' => round(array_sum($monthlyAmounts), 2),
            'direct_budget_total_raw' => round(array_sum($directMonthlyAmounts), 2),
            'children' => $children,
        ];
    }

    /**
     * @param  array<int, float>  $leafValues
     * @return array<int, float>
     */
    protected function leafMonthlyAmounts(array $leafValues): array
    {
        $values = [];

        foreach (range(1, 12) as $month) {
            $values[] = round((float) ($leafValues[$month] ?? 0), 2);
        }

        return $values;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, float>
     */
    protected function sumRowsByMonth(array $rows): array
    {
        $totals = array_fill(0, 12, 0.0);

        foreach ($rows as $row) {
            foreach ($row['monthly_amounts_raw'] as $index => $value) {
                $totals[$index] += (float) $value;
            }
        }

        return array_map(
            fn (float $value): float => round($value, 2),
            $totals
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $sections
     * @return array<int, float>
     */
    protected function columnTotals(array $sections): array
    {
        $totals = array_fill(0, 12, 0.0);

        foreach ($sections as $section) {
            foreach ($section['totals_by_month_raw'] as $index => $value) {
                $totals[$index] += (float) $value;
            }
        }

        return array_map(
            fn (float $value): float => round($value, 2),
            $totals
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    protected function flattenRows(array $rows): array
    {
        $items = [];

        foreach ($rows as $row) {
            $items[] = [
                ...collect($row)
                    ->except('children')
                    ->all(),
            ];

            if ($row['children'] !== []) {
                $items = [
                    ...$items,
                    ...$this->flattenRows($row['children']),
                ];
            }
        }

        return $items;
    }

    /**
     * @param  array<int, array<string, mixed>>  $sections
     * @return array<int, array<string, mixed>>
     */
    protected function summaryCards(array $sections): array
    {
        $sectionTotals = collect($sections)->mapWithKeys(
            fn (array $section): array => [$section['key'] => (float) $section['total_raw']]
        );

        $incomeTotal = round((float) ($sectionTotals[CategoryGroupTypeEnum::INCOME->value] ?? 0), 2);
        $expenseTotal = round((float) ($sectionTotals[CategoryGroupTypeEnum::EXPENSE->value] ?? 0), 2);
        $billTotal = round((float) ($sectionTotals[CategoryGroupTypeEnum::BILL->value] ?? 0), 2);
        $debtTotal = round((float) ($sectionTotals[CategoryGroupTypeEnum::DEBT->value] ?? 0), 2);
        $savingTotal = round((float) ($sectionTotals[CategoryGroupTypeEnum::SAVING->value] ?? 0), 2);
        $plannedOutflow = $sectionTotals
            ->except([
                CategoryGroupTypeEnum::INCOME->value,
                CategoryGroupTypeEnum::TRANSFER->value,
            ])
            ->sum();
        $remainingTotal = round($incomeTotal - $plannedOutflow, 2);

        return [
            $this->summaryCard('income', __('app.enums.category_groups.income'), $incomeTotal, null),
            $this->summaryCard('remaining', __('app.enums.category_groups.remaining'), $remainingTotal, $incomeTotal),
            $this->summaryCard('expense', __('app.enums.category_groups.expense'), $expenseTotal, $incomeTotal),
            $this->summaryCard('bill', __('app.enums.category_groups.bill'), $billTotal, $incomeTotal),
            $this->summaryCard('debt', __('app.enums.category_groups.debt'), $debtTotal, $incomeTotal),
            $this->summaryCard('saving', __('app.enums.category_groups.saving'), $savingTotal, $incomeTotal),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $sections
     * @return array<int, array<string, mixed>>
     */
    protected function parentBudgetConflicts(array $sections): array
    {
        return collect($sections)
            ->flatMap(fn (array $section): array => collect($section['flat_rows'])
                ->filter(fn (array $row): bool => $row['has_children'] && (float) ($row['direct_budget_total_raw'] ?? 0) > 0)
                ->map(fn (array $row): array => [
                    'uuid' => $row['uuid'],
                    'name' => $row['name'],
                    'full_path' => $row['full_path'],
                    'section_key' => $section['key'],
                    'section_label' => $section['label'],
                    'direct_budget_total_raw' => round((float) $row['direct_budget_total_raw'], 2),
                ])
                ->values()
                ->all())
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    protected function summaryCard(
        string $key,
        string $label,
        float $amount,
        ?float $incomeTotal
    ): array {
        return [
            'key' => $key,
            'label' => $label,
            'amount_raw' => round($amount, 2),
            'share_of_income' => $incomeTotal && $incomeTotal > 0
                ? round(($amount / $incomeTotal) * 100, 1)
                : null,
        ];
    }

    /**
     * @return array<int, array<string, int|string>>
     */
    protected function months(): array
    {
        $labels = [
            __('dashboard.months.1'),
            __('dashboard.months.2'),
            __('dashboard.months.3'),
            __('dashboard.months.4'),
            __('dashboard.months.5'),
            __('dashboard.months.6'),
            __('dashboard.months.7'),
            __('dashboard.months.8'),
            __('dashboard.months.9'),
            __('dashboard.months.10'),
            __('dashboard.months.11'),
            __('dashboard.months.12'),
        ];

        return collect($labels)
            ->values()
            ->map(fn (string $label, int $index): array => [
                'value' => $index + 1,
                'label' => $label,
                'short_label' => mb_substr($label, 0, 3),
            ])
            ->all();
    }

    protected function sectionKeyForCategory(Category $category): string
    {
        if ($category->group_type instanceof CategoryGroupTypeEnum) {
            return $category->group_type->value;
        }

        return match ($category->direction_type) {
            CategoryDirectionTypeEnum::INCOME => CategoryGroupTypeEnum::INCOME->value,
            CategoryDirectionTypeEnum::EXPENSE => CategoryGroupTypeEnum::EXPENSE->value,
            default => 'other',
        };
    }

    protected function sectionLabel(string $sectionKey): string
    {
        return match ($sectionKey) {
            CategoryGroupTypeEnum::INCOME->value => CategoryGroupTypeEnum::INCOME->label(),
            CategoryGroupTypeEnum::EXPENSE->value => CategoryGroupTypeEnum::EXPENSE->label(),
            CategoryGroupTypeEnum::BILL->value => CategoryGroupTypeEnum::BILL->label(),
            CategoryGroupTypeEnum::DEBT->value => CategoryGroupTypeEnum::DEBT->label(),
            CategoryGroupTypeEnum::SAVING->value => CategoryGroupTypeEnum::SAVING->label(),
            CategoryGroupTypeEnum::TAX->value => CategoryGroupTypeEnum::TAX->label(),
            CategoryGroupTypeEnum::INVESTMENT->value => CategoryGroupTypeEnum::INVESTMENT->label(),
            default => __('app.enums.category_groups.other'),
        };
    }

    protected function sectionDescription(string $sectionKey): string
    {
        return match ($sectionKey) {
            CategoryGroupTypeEnum::INCOME->value => __('planning.sections.income'),
            CategoryGroupTypeEnum::EXPENSE->value => __('planning.sections.expense'),
            CategoryGroupTypeEnum::BILL->value => __('planning.sections.bill'),
            CategoryGroupTypeEnum::DEBT->value => __('planning.sections.debt'),
            CategoryGroupTypeEnum::SAVING->value => __('planning.sections.saving'),
            CategoryGroupTypeEnum::TAX->value => __('planning.sections.tax'),
            CategoryGroupTypeEnum::INVESTMENT->value => __('planning.sections.investment'),
            default => __('planning.sections.other'),
        };
    }

    protected function budgetTypeForCategory(Category $category): BudgetTypeEnum
    {
        return match ($this->sectionKeyForCategory($category)) {
            CategoryGroupTypeEnum::INCOME->value => BudgetTypeEnum::FORECAST,
            CategoryGroupTypeEnum::EXPENSE->value,
            CategoryGroupTypeEnum::BILL->value => BudgetTypeEnum::LIMIT,
            default => BudgetTypeEnum::TARGET,
        };
    }
}
