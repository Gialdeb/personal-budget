<?php

namespace App\Services\Reports;

use App\Enums\CategoryDirectionTypeEnum;
use App\Enums\CategoryGroupTypeEnum;
use App\Enums\TransactionDirectionEnum;
use App\Enums\TransactionKindEnum;
use App\Models\Account;
use App\Models\Budget;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Accounts\AccessibleAccountsQuery;
use App\Services\Transactions\OperationalLedgerAnalyticsService;
use App\Services\UserYearService;
use App\Supports\CategoryHierarchy;
use App\Supports\PeriodOptions;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use NumberFormatter;

class CategoryAnalysisReportService
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
     * @param  array{
     *     year?: int|null,
     *     month?: int|null,
     *     period?: string|null,
     *     account_uuid?: string|null,
     *     category_uuid?: string|null,
     *     subcategory_uuid?: string|null
     * }  $input
     * @return array<string, mixed>
     */
    public function build(User $user, int $defaultYear, ?int $defaultMonth, array $input = []): array
    {
        $availableYears = $this->availableYears($user, $defaultYear);
        $selectedYear = $this->normalizeYear($input['year'] ?? null, $availableYears, $defaultYear);
        $selectedPeriod = $this->normalizePeriod($input['period'] ?? null);
        $referenceMonth = $this->normalizeReferenceMonth($input['month'] ?? null, $defaultMonth, $selectedYear);
        $accountOptions = $this->accessibleAccountsQuery->dashboardFilterOptions($user);
        $selectedAccountUuid = $this->normalizeAccountUuid($input['account_uuid'] ?? null, $accountOptions);
        $accountIds = $this->accessibleAccountsQuery->ids($user, 'all', $selectedAccountUuid);
        $ownerIds = $this->accessibleAccountsQuery->ownerIds($user, 'all', $selectedAccountUuid);
        $categories = $this->visibleCategories($user, $accountIds, $ownerIds);
        $categoryOptions = $this->categoryOptions($categories);
        $categoryTreeOptions = $this->categoryTreeOptions($categories);
        $selectedCategory = $this->normalizeCategory($input['category_uuid'] ?? null, $categoryOptions, $categories);
        $subcategoryOptions = $selectedCategory instanceof Category
            ? $this->subcategoryOptions($categories, $selectedCategory)
            : [];
        $subcategoryOptionsByCategory = collect($categoryOptions)
            ->mapWithKeys(function (array $option) use ($categories): array {
                $category = $categories->first(fn (Category $candidate): bool => (string) $candidate->uuid === (string) $option['value']);

                return [
                    (string) $option['value'] => $category instanceof Category
                        ? $this->subcategoryOptions($categories, $category)
                        : [],
                ];
            })
            ->all();
        $selectedSubcategory = $this->normalizeSubcategory(
            $input['subcategory_uuid'] ?? null,
            $subcategoryOptions,
            $categories,
        );
        $baseCurrency = strtoupper(trim((string) ($user->base_currency_code ?: 'EUR')));
        $periodDefinition = $this->buildPeriodDefinition($selectedYear, $selectedPeriod, $referenceMonth);
        $previousPeriodDefinition = $this->previousPeriodDefinition($periodDefinition);
        $previousYearDefinition = $this->previousYearDefinition($periodDefinition);
        $selectedCategoryIds = $this->selectedCategoryIds($categories, $selectedCategory, $selectedSubcategory);
        $current = $this->aggregate($user, $periodDefinition, $selectedAccountUuid, $selectedCategoryIds, $baseCurrency);
        $previousPeriod = $this->aggregate($user, $previousPeriodDefinition, $selectedAccountUuid, $selectedCategoryIds, $baseCurrency);
        $previousYear = $this->aggregate($user, $previousYearDefinition, $selectedAccountUuid, $selectedCategoryIds, $baseCurrency);
        $budget = $this->budget(
            $user,
            $periodDefinition,
            $selectedCategoryIds,
            $current['buckets'],
            $baseCurrency,
        );
        $bucketCount = max(1, count($current['buckets']));
        $bestBucket = collect($current['buckets'])->sortBy('total_raw')->first();
        $worstBucket = collect($current['buckets'])->sortByDesc('total_raw')->first();
        $previousPeriodComparison = $this->buildComparison($current['total_raw'], $previousPeriod['total_raw'], $baseCurrency);
        $previousYearComparison = $this->buildComparison($current['total_raw'], $previousYear['total_raw'], $baseCurrency);
        if (($budget['meta']['supported'] ?? false) === true) {
            $variance = round((float) $current['total_raw'] - (float) $budget['meta']['total_raw'], 2);
            $budget['meta']['variance_raw'] = $variance;
            $budget['meta']['variance'] = $this->formatMoney($variance, $baseCurrency);
            $budget['meta']['status'] = $variance > 0.005 ? 'over' : 'in_line';
        }

        $hasActualSpend = (float) $current['total_raw'] > 0.005;
        $periodLabel = $this->periodLabel($periodDefinition);
        $analysisScopeLabel = $this->analysisScopeLabel($selectedCategory, $selectedSubcategory, count($selectedCategoryIds));
        $actualScopeDescription = $this->actualScopeDescription($selectedCategory, $selectedSubcategory);
        $budgetScopeDescription = $this->budgetScopeDescription($budget, $selectedCategory, $selectedSubcategory);
        $comparisonScopeDescription = $this->comparisonScopeDescription($previousYearComparison);

        return [
            'currency' => $baseCurrency,
            'meta' => [
                'period_label' => $periodLabel,
                'scope_label' => $this->scopeLabel($selectedAccountUuid, $accountOptions),
                'category_label' => $selectedCategory?->name,
                'subcategory_label' => $selectedSubcategory?->name,
                'has_actual_spend' => $hasActualSpend,
                'analysis_scope_label' => $analysisScopeLabel,
                'actual_scope_description' => $actualScopeDescription,
                'budget_scope_description' => $budgetScopeDescription,
                'comparison_scope_description' => $comparisonScopeDescription,
                'scope_summary' => $this->scopeSummary($analysisScopeLabel, $actualScopeDescription, $budgetScopeDescription, $comparisonScopeDescription),
                'empty_state_title' => $hasActualSpend ? null : __('reports.categoryAnalysis.emptyDataset.title'),
                'empty_state_message' => $hasActualSpend ? null : __('reports.categoryAnalysis.emptyDataset.message', [
                    'period' => $periodLabel,
                    'scope' => $analysisScopeLabel,
                ]),
                'granularity' => $periodDefinition['granularity'],
                'previous_period_label' => $this->periodLabel($previousPeriodDefinition),
                'previous_year_label' => $this->periodLabel($previousYearDefinition),
                'unresolved_transactions_count' => $current['unresolved_count'],
                'budget' => $budget['meta'],
                'insight' => $this->insight($current, $previousYearComparison, $budget, $baseCurrency),
            ],
            'filters' => [
                'year' => $selectedYear,
                'month' => $selectedPeriod === self::PERIOD_ANNUAL ? null : $referenceMonth,
                'period' => $selectedPeriod,
                'account_uuid' => $selectedAccountUuid,
                'category_uuid' => $selectedCategory?->uuid,
                'subcategory_uuid' => $selectedSubcategory?->uuid,
                'available_years' => PeriodOptions::yearOptions($availableYears),
                'month_options' => array_values(array_filter(
                    PeriodOptions::monthOptions(false),
                    fn (array $option): bool => $this->isMonthInsidePeriodYear((int) $option['value'], $selectedYear),
                )),
                'period_options' => $this->periodOptions(),
                'account_options' => $accountOptions,
                'category_options' => $categoryOptions,
                'category_tree_options' => $categoryTreeOptions,
                'subcategory_options' => $subcategoryOptions,
                'subcategory_options_by_category' => $subcategoryOptionsByCategory,
                'show_month_filter' => $selectedPeriod !== self::PERIOD_ANNUAL,
            ],
            'summary' => [
                'total_spent' => $this->formatMoney($current['total_raw'], $baseCurrency),
                'total_spent_raw' => $current['total_raw'],
                'average_period' => $this->formatMoney(round($current['total_raw'] / $bucketCount, 2), $baseCurrency),
                'average_period_raw' => round($current['total_raw'] / $bucketCount, 2),
                'average_period_label' => $periodDefinition['granularity'] === 'day'
                    ? __('reports.categoryAnalysis.kpis.averagePerDay')
                    : __('reports.categoryAnalysis.kpis.averagePerMonth'),
                'best_period_label' => $hasActualSpend ? ($bestBucket['label'] ?? null) : null,
                'best_period_value' => $hasActualSpend && isset($bestBucket['total_raw'])
                    ? $this->formatMoney((float) $bestBucket['total_raw'], $baseCurrency)
                    : null,
                'best_period_value_raw' => $hasActualSpend ? ($bestBucket['total_raw'] ?? null) : null,
                'worst_period_label' => $hasActualSpend ? ($worstBucket['label'] ?? null) : null,
                'worst_period_value' => $hasActualSpend && isset($worstBucket['total_raw'])
                    ? $this->formatMoney((float) $worstBucket['total_raw'], $baseCurrency)
                    : null,
                'worst_period_value_raw' => $hasActualSpend ? ($worstBucket['total_raw'] ?? null) : null,
            ],
            'comparisons' => [
                'previous_period' => $previousPeriodComparison,
                'previous_year' => $previousYearComparison,
            ],
            'trend' => [
                'labels' => collect($current['buckets'])->pluck('label')->values()->all(),
                'granularity' => $periodDefinition['granularity'],
                'series' => [[
                    'key' => 'selected',
                    'name' => $selectedSubcategory?->name ?? $selectedCategory?->name ?? __('reports.categoryAnalysis.fallbackCategory'),
                    'color' => $this->categoryColor($selectedSubcategory ?? $selectedCategory),
                    'values' => collect($current['buckets'])->pluck('total_raw')->map(fn ($value): float => round((float) $value, 2))->values()->all(),
                    'total' => $this->formatMoney($current['total_raw'], $baseCurrency),
                ], ...$budget['series']],
            ],
            'subcategory_breakdown' => [
                'nodes' => $current['subcategory_nodes'],
            ],
            'account_breakdown' => [
                'nodes' => $current['account_nodes'],
            ],
            'year_comparison' => $this->yearComparisonChart($current['buckets'], $previousYear['buckets'], $selectedYear, $hasActualSpend),
            'cumulative' => $this->cumulativeChart($current['buckets'], $previousYear['buckets'], $budget['buckets'], $budget['meta'], $selectedYear),
            'subcategory_timeline' => $this->subcategoryTimeline($current['buckets'], $current['subcategory_nodes']),
            'monthly_rows' => $this->monthlyRows($current['buckets'], $previousYear['buckets'], $budget['buckets'], $baseCurrency),
        ];
    }

    /**
     * @return array{total_raw: float, unresolved_count: int, buckets: array<int, array<string, mixed>>, subcategory_nodes: array<int, array<string, mixed>>, account_nodes: array<int, array<string, mixed>>}
     */
    protected function aggregate(User $user, array $periodDefinition, ?string $accountUuid, array $categoryIds, string $currency): array
    {
        $bucketMap = $this->makeBucketMap($periodDefinition);
        $subcategoryTotals = [];
        $accountTotals = [];
        $unresolvedCount = 0;

        if ($categoryIds === []) {
            return [
                'total_raw' => 0.0,
                'unresolved_count' => 0,
                'buckets' => array_values($bucketMap),
                'subcategory_nodes' => [],
                'account_nodes' => [],
            ];
        }

        $transactions = $this->operationalLedgerAnalyticsService->transactionsForPeriod(
            $user,
            $periodDefinition['start'],
            $periodDefinition['end'],
            $accountUuid,
        );
        $categoryMap = Category::query()
            ->with('parent:id,uuid,parent_id,name,color,group_type,direction_type')
            ->whereIn('id', $transactions->pluck('category_id')->filter()->unique()->all())
            ->get()
            ->keyBy('id');
        $accountMap = Account::query()
            ->whereIn('id', $transactions->pluck('account_id')->filter()->unique()->all())
            ->get(['id', 'uuid', 'name'])
            ->keyBy('id');

        foreach ($transactions as $transaction) {
            if (! in_array((int) $transaction->category_id, $categoryIds, true)) {
                continue;
            }

            $amount = $this->operationalLedgerAnalyticsService->resolveAggregateAmountForTransaction($transaction, $currency);

            if ($amount === null) {
                $unresolvedCount++;

                continue;
            }

            $expenseImpact = $this->expenseImpact($transaction, $amount);

            if (abs($expenseImpact) < 0.005) {
                continue;
            }

            $account = $accountMap->get($transaction->account_id);
            $accountKey = $account instanceof Account ? (string) $account->uuid : 'account-'.$transaction->account_id;
            $accountTotals[$accountKey] ??= [
                'key' => $accountKey,
                'account_id' => (int) $transaction->account_id,
                'account_uuid' => $account instanceof Account ? (string) $account->uuid : null,
                'account_name' => $account instanceof Account ? $account->name : __('reports.filters.allResources'),
                'total_raw' => 0.0,
            ];
            $accountTotals[$accountKey]['total_raw'] = round((float) $accountTotals[$accountKey]['total_raw'] + $expenseImpact, 2);

            $bucketKey = $this->bucketKeyForDate(
                CarbonImmutable::parse($transaction->transaction_date, config('app.timezone')),
                $periodDefinition['granularity'],
            );

            if (isset($bucketMap[$bucketKey])) {
                $bucketMap[$bucketKey]['total_raw'] = round((float) $bucketMap[$bucketKey]['total_raw'] + $expenseImpact, 2);
            }

            $category = $categoryMap->get($transaction->category_id);
            $subcategory = $category instanceof Category && $category->parent instanceof Category
                ? $category
                : ($category instanceof Category ? $category : null);

            if ($subcategory instanceof Category) {
                $key = (string) $subcategory->uuid;
                $subcategoryTotals[$key] ??= [
                    'key' => $key,
                    'name' => $subcategory->name,
                    'label' => $subcategory->name,
                    'value' => 0.0,
                    'color' => $this->categoryColor($subcategory),
                    'children' => [],
                    'itemStyle' => ['color' => $this->categoryColor($subcategory)],
                ];
                $subcategoryTotals[$key]['value'] = round((float) $subcategoryTotals[$key]['value'] + $expenseImpact, 2);

                if (isset($bucketMap[$bucketKey])) {
                    $bucketMap[$bucketKey]['subcategory_totals'][$key] ??= [
                        'key' => $key,
                        'label' => $subcategory->name,
                        'color' => $this->categoryColor($subcategory),
                        'value' => 0.0,
                    ];
                    $bucketMap[$bucketKey]['subcategory_totals'][$key]['value'] = round(
                        (float) $bucketMap[$bucketKey]['subcategory_totals'][$key]['value'] + $expenseImpact,
                        2,
                    );
                }
            }
        }

        $totalRaw = round((float) collect($bucketMap)->sum('total_raw'), 2);

        return [
            'total_raw' => $totalRaw,
            'unresolved_count' => $unresolvedCount,
            'buckets' => collect($bucketMap)->values()->all(),
            'subcategory_nodes' => collect($subcategoryTotals)
                ->sortByDesc('value')
                ->values()
                ->map(function (array $node) use ($currency, $totalRaw): array {
                    $share = $totalRaw > 0 ? round((((float) $node['value']) / $totalRaw) * 100, 1) : 0.0;

                    return [
                        ...$node,
                        'total' => $this->formatMoney((float) $node['value'], $currency),
                        'share_percentage' => $share,
                        'share_label' => number_format($share, 1, '.', '').'%',
                        'children_count' => 0,
                    ];
                })
                ->all(),
            'account_nodes' => collect($accountTotals)
                ->sortByDesc('total_raw')
                ->values()
                ->map(function (array $node) use ($currency, $totalRaw): array {
                    $share = $totalRaw > 0 ? round((((float) $node['total_raw']) / $totalRaw) * 100, 1) : 0.0;

                    return [
                        ...$node,
                        'total_raw' => round((float) $node['total_raw'], 2),
                        'total' => $this->formatMoney((float) $node['total_raw'], $currency),
                        'share_percentage' => $share,
                        'share_label' => number_format($share, 1, '.', '').'%',
                    ];
                })
                ->all(),
        ];
    }

    protected function expenseImpact(Transaction $transaction, float $amount): float
    {
        if ($transaction->kind === TransactionKindEnum::REFUND && $transaction->direction === TransactionDirectionEnum::INCOME) {
            return round(-1 * $amount, 2);
        }

        if ($transaction->direction === TransactionDirectionEnum::EXPENSE) {
            return round($amount, 2);
        }

        return 0.0;
    }

    protected function visibleCategories(User $user, array $accountIds, array $ownerIds): Collection
    {
        return Category::query()
            ->where('is_active', true)
            ->where(function ($query) use ($user, $accountIds, $ownerIds): void {
                $query
                    ->whereIn('user_id', array_values(array_unique([...$ownerIds, $user->id])))
                    ->orWhereIn('account_id', $accountIds !== [] ? $accountIds : [0]);
            })
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    protected function categoryOptions(Collection $categories): array
    {
        return $categories
            ->filter(fn (Category $category): bool => $category->parent_id === null && $this->isExpenseCategory($category))
            ->map(fn (Category $category): array => [
                'value' => (string) $category->uuid,
                'label' => $category->name,
                'color' => $this->categoryColor($category),
                'icon' => $category->icon,
            ])
            ->values()
            ->all();
    }

    protected function subcategoryOptions(Collection $categories, Category $category): array
    {
        return $categories
            ->filter(fn (Category $candidate): bool => (int) $candidate->parent_id === (int) $category->id)
            ->map(fn (Category $candidate): array => [
                'value' => (string) $candidate->uuid,
                'label' => $candidate->name,
                'color' => $this->categoryColor($candidate),
                'icon' => $candidate->icon,
                'full_path' => $category->name.' > '.$candidate->name,
                'ancestor_uuids' => [(string) $category->uuid],
            ])
            ->values()
            ->all();
    }

    protected function categoryTreeOptions(Collection $categories): array
    {
        $expenseRootIds = $categories
            ->filter(fn (Category $category): bool => $category->parent_id === null && $this->isExpenseCategory($category))
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $childrenByParent = $categories->groupBy('parent_id');
        $includedIds = [];
        $cursor = $expenseRootIds;

        while ($cursor !== []) {
            $categoryId = (int) array_shift($cursor);
            $includedIds[] = $categoryId;

            foreach ($childrenByParent->get($categoryId, collect()) as $child) {
                $cursor[] = (int) $child->id;
            }
        }

        return collect(CategoryHierarchy::buildFlat(
            $categories->filter(fn (Category $category): bool => in_array((int) $category->id, $includedIds, true))->values()
        ))
            ->map(fn (array $category): array => [
                'value' => (string) $category['uuid'],
                'label' => (string) $category['name'],
                'full_path' => (string) ($category['full_path'] ?? $category['name']),
                'icon' => $category['icon'] ?? null,
                'color' => $category['color'] ?? null,
                'ancestor_uuids' => collect($category['ancestor_uuids'] ?? [])->filter()->values()->all(),
                'is_selectable' => true,
            ])
            ->unique(fn (array $option): string => $this->optionVisualKey($option['full_path']))
            ->values()
            ->all();
    }

    protected function optionVisualKey(string $label): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/', ' ', $label) ?? $label));
    }

    protected function normalizeCategory(?string $categoryUuid, array $categoryOptions, Collection $categories): ?Category
    {
        $fallbackOption = collect($categoryOptions)->first();
        $validUuid = $categoryUuid !== null && collect($categoryOptions)->pluck('value')->contains($categoryUuid)
            ? $categoryUuid
            : (is_array($fallbackOption) ? ($fallbackOption['value'] ?? null) : null);

        return $validUuid === null
            ? null
            : $categories->first(fn (Category $category): bool => (string) $category->uuid === $validUuid);
    }

    protected function normalizeSubcategory(?string $subcategoryUuid, array $subcategoryOptions, Collection $categories): ?Category
    {
        if ($subcategoryUuid === null || ! collect($subcategoryOptions)->pluck('value')->contains($subcategoryUuid)) {
            return null;
        }

        return $categories->first(fn (Category $category): bool => (string) $category->uuid === $subcategoryUuid);
    }

    protected function selectedCategoryIds(Collection $categories, ?Category $category, ?Category $subcategory): array
    {
        $selected = $subcategory ?? $category;

        if (! $selected instanceof Category) {
            return [];
        }

        return collect($this->equivalentCategoryRoots($categories, $selected))
            ->flatMap(fn (Category $category): array => $this->categoryAndDescendantIds($category))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<int, Category>
     */
    protected function equivalentCategoryRoots(Collection $categories, Category $selected): array
    {
        if ($selected->parent_id !== null) {
            return [$selected];
        }

        $selectedName = $this->optionVisualKey($selected->name);
        $selectedDirection = $selected->direction_type instanceof CategoryDirectionTypeEnum
            ? $selected->direction_type->value
            : (string) $selected->direction_type;
        $selectedGroup = $selected->group_type instanceof CategoryGroupTypeEnum
            ? $selected->group_type->value
            : (string) $selected->group_type;

        return $categories
            ->filter(function (Category $candidate) use ($selectedName, $selectedDirection, $selectedGroup): bool {
                $candidateDirection = $candidate->direction_type instanceof CategoryDirectionTypeEnum
                    ? $candidate->direction_type->value
                    : (string) $candidate->direction_type;
                $candidateGroup = $candidate->group_type instanceof CategoryGroupTypeEnum
                    ? $candidate->group_type->value
                    : (string) $candidate->group_type;

                return $candidate->parent_id === null
                    && $this->optionVisualKey($candidate->name) === $selectedName
                    && $candidateDirection === $selectedDirection
                    && $candidateGroup === $selectedGroup;
            })
            ->values()
            ->all();
    }

    protected function categoryAndDescendantIds(Category $category): array
    {
        $ids = [(int) $category->id];
        $cursor = [(int) $category->id];

        while ($cursor !== []) {
            $children = Category::query()
                ->whereIn('parent_id', $cursor)
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->all();
            $newIds = array_values(array_diff($children, $ids));
            $ids = array_values(array_unique([...$ids, ...$newIds]));
            $cursor = $newIds;
        }

        return $ids;
    }

    protected function buildComparison(float $current, float $previous, string $currency): array
    {
        $delta = round($current - $previous, 2);

        return [
            'available' => abs($previous) > 0.005,
            'previous_raw' => round($previous, 2),
            'previous_formatted' => $this->formatMoney($previous, $currency),
            'delta_raw' => $delta,
            'delta_formatted' => $this->formatMoney($delta, $currency),
            'delta_percentage' => abs($previous) > 0.005 ? round(($delta / $previous) * 100, 1) : null,
            'delta_percentage_label' => abs($previous) > 0.005 ? number_format(round(($delta / $previous) * 100, 1), 1, ',', '.').'%' : null,
            'direction' => $delta > 0.005 ? 'up' : ($delta < -0.005 ? 'down' : 'neutral'),
        ];
    }

    protected function monthlyRows(array $currentBuckets, array $previousYearBuckets, array $budgetBuckets, string $currency): array
    {
        $previousByPosition = collect($previousYearBuckets)->values();
        $budgetByKey = collect($budgetBuckets)->keyBy('key');

        return collect($currentBuckets)
            ->values()
            ->map(function (array $bucket, int $index) use ($previousByPosition, $budgetByKey, $currency): array {
                $previous = (float) ($previousByPosition->get($index)['total_raw'] ?? 0);
                $current = round((float) $bucket['total_raw'], 2);
                $budget = round((float) ($budgetByKey->get($bucket['key'])['total_raw'] ?? 0), 2);
                $delta = round($current - $previous, 2);
                $budgetDelta = round($current - $budget, 2);
                $dominantSubcategory = collect($bucket['subcategory_totals'] ?? [])
                    ->sortByDesc('value')
                    ->first();

                return [
                    'key' => $bucket['key'],
                    'label' => $bucket['label'],
                    'spent' => $this->formatMoney($current, $currency),
                    'spent_raw' => $current,
                    'budget_raw' => $budget,
                    'budget' => $budget > 0 ? $this->formatMoney($budget, $currency) : null,
                    'budget_delta_raw' => $budgetDelta,
                    'budget_delta' => $budget > 0 ? $this->formatMoney($budgetDelta, $currency) : null,
                    'previous_year_raw' => round($previous, 2),
                    'previous_year' => $previous > 0 ? $this->formatMoney($previous, $currency) : null,
                    'delta_previous_year' => $this->formatMoney($delta, $currency),
                    'delta_previous_year_raw' => $delta,
                    'delta_previous_year_percentage' => abs($previous) > 0.005 ? round(($delta / $previous) * 100, 1) : null,
                    'delta_previous_year_percentage_label' => abs($previous) > 0.005 ? number_format(round(($delta / $previous) * 100, 1), 1, ',', '.').'%' : null,
                    'dominant_subcategory_label' => is_array($dominantSubcategory) ? (string) $dominantSubcategory['label'] : null,
                    'dominant_subcategory_raw' => is_array($dominantSubcategory) ? round((float) $dominantSubcategory['value'], 2) : null,
                    'dominant_subcategory' => is_array($dominantSubcategory) ? $this->formatMoney((float) $dominantSubcategory['value'], $currency) : null,
                ];
            })
            ->all();
    }

    protected function yearComparisonChart(array $currentBuckets, array $previousYearBuckets, int $selectedYear, bool $hasActualSpend): array
    {
        $previousByPosition = collect($previousYearBuckets)->values();
        $previousValues = collect($currentBuckets)
            ->values()
            ->map(fn (array $bucket, int $index): float => round((float) ($previousByPosition->get($index)['total_raw'] ?? 0), 2))
            ->values()
            ->all();

        return [
            'supported' => $hasActualSpend && collect($previousValues)->contains(fn (float $value): bool => abs($value) > 0.005),
            'labels' => collect($currentBuckets)->pluck('label')->values()->all(),
            'series' => [
                [
                    'key' => 'current',
                    'name' => (string) $selectedYear,
                    'color' => '#ef4444',
                    'type' => 'bar',
                    'values' => collect($currentBuckets)->pluck('total_raw')->map(fn ($value): float => round((float) $value, 2))->values()->all(),
                ],
                [
                    'key' => 'previous',
                    'name' => (string) ($selectedYear - 1),
                    'color' => '#94a3b8',
                    'type' => 'bar',
                    'values' => $previousValues,
                ],
            ],
        ];
    }

    protected function cumulativeChart(array $currentBuckets, array $previousYearBuckets, array $budgetBuckets, array $budgetMeta, int $selectedYear): array
    {
        $budgetSupported = ($budgetMeta['supported'] ?? false) === true;

        if (! $budgetSupported) {
            return [
                'supported' => false,
                'labels' => [],
                'series' => [],
            ];
        }

        $previousByPosition = collect($previousYearBuckets)->values();
        $budgetByKey = collect($budgetBuckets)->keyBy('key');
        $currentRunning = 0.0;
        $previousRunning = 0.0;
        $budgetRunning = 0.0;
        $currentValues = [];
        $previousValues = [];
        $budgetValues = [];

        foreach (array_values($currentBuckets) as $index => $bucket) {
            $currentRunning = round($currentRunning + (float) $bucket['total_raw'], 2);
            $previousRunning = round($previousRunning + (float) ($previousByPosition->get($index)['total_raw'] ?? 0), 2);
            $budgetRunning = round($budgetRunning + (float) ($budgetByKey->get($bucket['key'])['total_raw'] ?? 0), 2);

            $currentValues[] = $currentRunning;
            $previousValues[] = $previousRunning;
            $budgetValues[] = $budgetRunning;
        }

        return [
            'supported' => true,
            'labels' => collect($currentBuckets)->pluck('label')->values()->all(),
            'series' => [
                [
                    'key' => 'current',
                    'name' => (string) $selectedYear,
                    'color' => '#ef4444',
                    'type' => 'line',
                    'values' => $currentValues,
                ],
                [
                    'key' => 'budget',
                    'name' => __('reports.categoryAnalysis.budget.seriesName'),
                    'color' => '#2563eb',
                    'style' => 'dashed',
                    'type' => 'line',
                    'values' => $budgetValues,
                ],
                [
                    'key' => 'previous',
                    'name' => (string) ($selectedYear - 1),
                    'color' => '#94a3b8',
                    'style' => 'dashed',
                    'type' => 'line',
                    'values' => $previousValues,
                ],
            ],
        ];
    }

    protected function subcategoryTimeline(array $currentBuckets, array $subcategoryNodes): array
    {
        $topNodes = collect($subcategoryNodes)->take(5)->values();

        if ($topNodes->isEmpty()) {
            return [
                'supported' => false,
                'labels' => [],
                'series' => [],
            ];
        }

        return [
            'supported' => true,
            'labels' => collect($currentBuckets)->pluck('label')->values()->all(),
            'series' => $topNodes
                ->map(fn (array $node): array => [
                    'key' => (string) $node['key'],
                    'name' => (string) $node['label'],
                    'color' => (string) $node['color'],
                    'type' => 'bar',
                    'stack' => 'subcategories',
                    'values' => collect($currentBuckets)
                        ->map(fn (array $bucket): float => round((float) (($bucket['subcategory_totals'][$node['key']]['value'] ?? 0)), 2))
                        ->values()
                        ->all(),
                ])
                ->all(),
        ];
    }

    /**
     * @return array{meta: array<string, mixed>, buckets: array<int, array<string, mixed>>, series: array<int, array<string, mixed>>}
     */
    protected function budget(User $user, array $periodDefinition, array $categoryIds, array $currentBuckets, string $currency): array
    {
        $budgetCategoryIds = $this->personalBudgetCategoryIds($user, $categoryIds);
        $empty = [
            'meta' => [
                'supported' => false,
                'reason' => 'missing_budget',
                'aggregated' => false,
                'total_raw' => 0.0,
                'total' => $this->formatMoney(0, $currency),
                'variance_raw' => null,
                'variance' => null,
                'status' => 'unavailable',
            ],
            'buckets' => [],
            'series' => [],
        ];

        if ($budgetCategoryIds === []) {
            return $empty;
        }

        $budgets = Budget::query()
            ->where('user_id', $user->id)
            ->whereIn('category_id', $budgetCategoryIds)
            ->whereNull('scope_id')
            ->whereNull('tracked_item_id')
            ->where('year', '>=', $periodDefinition['start']->year)
            ->where('year', '<=', $periodDefinition['end']->year)
            ->get();

        $bucketMap = collect($currentBuckets)
            ->mapWithKeys(fn (array $bucket): array => [$bucket['key'] => [...$bucket, 'total_raw' => 0.0]])
            ->all();

        foreach ($budgets as $budget) {
            $budgetDate = CarbonImmutable::create((int) $budget->year, (int) $budget->month, 1, 0, 0, 0, config('app.timezone'));

            if ($budgetDate->lt($periodDefinition['start']->startOfMonth()) || $budgetDate->gt($periodDefinition['end']->endOfMonth())) {
                continue;
            }

            if ($periodDefinition['granularity'] === 'day') {
                $daysInMonth = $budgetDate->daysInMonth;
                $dailyAmount = round(((float) $budget->amount) / max(1, $daysInMonth), 2);
                $cursor = $budgetDate->startOfMonth();

                while ($cursor->lte($budgetDate->endOfMonth())) {
                    if ($cursor->betweenIncluded($periodDefinition['start'], $periodDefinition['end'])) {
                        $key = $this->bucketKeyForDate($cursor, 'day');
                        if (isset($bucketMap[$key])) {
                            $bucketMap[$key]['total_raw'] = round((float) $bucketMap[$key]['total_raw'] + $dailyAmount, 2);
                        }
                    }
                    $cursor = $cursor->addDay();
                }

                continue;
            }

            $key = $this->bucketKeyForDate($budgetDate, 'month');
            if (isset($bucketMap[$key])) {
                $bucketMap[$key]['total_raw'] = round((float) $bucketMap[$key]['total_raw'] + (float) $budget->amount, 2);
            }
        }

        $buckets = array_values($bucketMap);
        $total = round((float) collect($buckets)->sum('total_raw'), 2);

        if ($total <= 0) {
            return $empty;
        }

        return [
            'meta' => [
                'supported' => true,
                'reason' => null,
                'aggregated' => count($budgetCategoryIds) > 1,
                'total_raw' => $total,
                'total' => $this->formatMoney($total, $currency),
                'variance_raw' => null,
                'variance' => null,
                'status' => 'available',
            ],
            'buckets' => $buckets,
            'series' => [[
                'key' => 'budget',
                'name' => __('reports.categoryAnalysis.budget.seriesName'),
                'color' => '#2563eb',
                'values' => collect($buckets)->pluck('total_raw')->map(fn ($value): float => round((float) $value, 2))->values()->all(),
                'total' => $this->formatMoney($total, $currency),
                'style' => 'dashed',
            ]],
        ];
    }

    /**
     * @param  array<int, int>  $selectedCategoryIds
     * @return array<int, int>
     */
    protected function personalBudgetCategoryIds(User $user, array $selectedCategoryIds): array
    {
        if ($selectedCategoryIds === []) {
            return [];
        }

        $selectedPaths = Category::query()
            ->whereIn('id', $selectedCategoryIds)
            ->get()
            ->map(fn (Category $category): string => $this->categoryPathKey($category))
            ->unique()
            ->values();

        return Category::query()
            ->where('user_id', $user->id)
            ->whereNull('account_id')
            ->get()
            ->filter(fn (Category $category): bool => $selectedPaths->contains($this->categoryPathKey($category)))
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    protected function categoryPathKey(Category $category): string
    {
        $segments = [];
        $current = $category;
        $visited = [];

        while ($current instanceof Category && ! in_array((int) $current->id, $visited, true)) {
            $visited[] = (int) $current->id;
            array_unshift($segments, $this->optionVisualKey($current->name));
            $current = $current->parent_id !== null
                ? Category::query()->find((int) $current->parent_id)
                : null;
        }

        return implode('>', $segments);
    }

    protected function insight(array $current, array $previousYearComparison, array $budget, string $currency): array
    {
        $total = (float) $current['total_raw'];
        $budgetTotal = (float) ($budget['meta']['total_raw'] ?? 0);
        $topSubcategory = collect($current['subcategory_nodes'])->sortByDesc('value')->first();

        if ($total <= 0.005) {
            return [
                'tone' => 'info',
                'title' => __('reports.categoryAnalysis.insight.noSpendTitle'),
                'message' => ($budget['meta']['supported'] ?? false) === true
                    ? __('reports.categoryAnalysis.insight.noSpendWithBudgetMessage', [
                        'budget' => $this->formatMoney($budgetTotal, $currency),
                    ])
                    : __('reports.categoryAnalysis.insight.noSpendMessage'),
            ];
        }

        if (($budget['meta']['supported'] ?? false) && $budgetTotal > 0) {
            $variance = round($total - $budgetTotal, 2);
            $status = $variance > 0.005 ? 'warning' : 'stable';

            return [
                'tone' => $status,
                'title' => $status === 'warning'
                    ? __('reports.categoryAnalysis.insight.overBudgetTitle')
                    : __('reports.categoryAnalysis.insight.inLineTitle'),
                'message' => __('reports.categoryAnalysis.insight.budgetMessage', [
                    'status' => $variance > 0.005
                        ? __('reports.categoryAnalysis.insight.overBudgetStatus')
                        : __('reports.categoryAnalysis.insight.underBudgetStatus'),
                    'variance' => $this->formatMoney(abs($variance), $currency),
                    'budget' => $this->formatMoney($budgetTotal, $currency),
                    'year_delta' => ($previousYearComparison['available'] ?? false) === true
                        ? (string) $previousYearComparison['delta_formatted']
                        : __('reports.categoryAnalysis.comparisons.unavailable'),
                    'year_percentage' => ($previousYearComparison['available'] ?? false) === true
                        ? (string) ($previousYearComparison['delta_percentage_label'] ?? '-')
                        : '-',
                    'top' => is_array($topSubcategory) ? (string) $topSubcategory['label'] : __('reports.categoryAnalysis.fallbackCategory'),
                    'share' => is_array($topSubcategory) ? (string) $topSubcategory['share_label'] : '0%',
                ]),
            ];
        }

        if ($previousYearComparison['available'] ?? false) {
            return [
                'tone' => $previousYearComparison['direction'] === 'up' ? 'warning' : 'stable',
                'title' => $previousYearComparison['direction'] === 'up'
                    ? __('reports.categoryAnalysis.insight.spendingUpTitle')
                    : __('reports.categoryAnalysis.insight.spendingDownTitle'),
                'message' => __('reports.categoryAnalysis.insight.previousYearMessage', [
                    'delta' => $previousYearComparison['delta_formatted'],
                    'percentage' => $previousYearComparison['delta_percentage_label'] ?? '-',
                    'top' => is_array($topSubcategory) ? (string) $topSubcategory['label'] : __('reports.categoryAnalysis.fallbackCategory'),
                    'share' => is_array($topSubcategory) ? (string) $topSubcategory['share_label'] : '0%',
                ]),
            ];
        }

        return [
            'tone' => 'info',
            'title' => __('reports.categoryAnalysis.insight.noComparisonTitle'),
            'message' => __('reports.categoryAnalysis.insight.noComparisonMessage'),
        ];
    }

    protected function isExpenseCategory(Category $category): bool
    {
        return in_array($category->group_type?->value, [
            CategoryGroupTypeEnum::EXPENSE->value,
            CategoryGroupTypeEnum::BILL->value,
            CategoryGroupTypeEnum::DEBT->value,
            CategoryGroupTypeEnum::TAX->value,
        ], true) || $category->direction_type === CategoryDirectionTypeEnum::EXPENSE;
    }

    protected function availableYears(User $user, int $defaultYear): array
    {
        $years = $this->userYearService->availableYears($user);

        return $years === [] ? [$defaultYear] : $years;
    }

    protected function normalizeYear(?int $requestedYear, array $availableYears, int $fallbackYear): int
    {
        if ($requestedYear !== null && in_array($requestedYear, $availableYears, true)) {
            return $requestedYear;
        }

        return in_array($fallbackYear, $availableYears, true) ? $fallbackYear : max($availableYears);
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

    protected function normalizeAccountUuid(?string $accountUuid, array $accountOptions): ?string
    {
        return $accountUuid !== null && collect($accountOptions)->pluck('value')->contains($accountUuid)
            ? $accountUuid
            : null;
    }

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

    protected function previousYearDefinition(array $periodDefinition): array
    {
        return [
            ...$periodDefinition,
            'start' => $periodDefinition['start']->subYear(),
            'end' => $periodDefinition['end']->subYear(),
            'reference_month' => $periodDefinition['reference_month'],
        ];
    }

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
                'total_raw' => 0.0,
                'subcategory_totals' => [],
            ];
            $cursor = $periodDefinition['granularity'] === 'day' ? $cursor->addDay() : $cursor->addMonth();
        }

        return $buckets;
    }

    protected function bucketKeyForDate(CarbonImmutable $date, string $granularity): string
    {
        return $granularity === 'day' ? $date->format('Y-m-d') : $date->format('Y-m');
    }

    protected function bucketLabelForDate(CarbonImmutable $date, string $granularity): string
    {
        return $granularity === 'day' ? $date->translatedFormat('d M') : $date->translatedFormat('M y');
    }

    protected function periodLabel(array $periodDefinition): string
    {
        if ($periodDefinition['period'] === self::PERIOD_ANNUAL) {
            return (string) $periodDefinition['start']->year;
        }

        if ($periodDefinition['period'] === self::PERIOD_MONTHLY) {
            return $periodDefinition['start']->translatedFormat('F Y');
        }

        return __('reports.filters.periodSummaries.'.match ($periodDefinition['period']) {
            self::PERIOD_LAST_THREE_MONTHS => 'lastThreeMonths',
            self::PERIOD_LAST_SIX_MONTHS => 'lastSixMonths',
            self::PERIOD_YTD => 'ytd',
            default => 'annual',
        }, [
            'year' => $periodDefinition['end']->year,
            'month' => $periodDefinition['end']->translatedFormat('F'),
        ]);
    }

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

    protected function isMonthInsidePeriodYear(int $month, int $selectedYear): bool
    {
        $now = CarbonImmutable::now(config('app.timezone'));

        return $selectedYear < $now->year || $month <= $now->month || $selectedYear > $now->year;
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

    protected function analysisScopeLabel(?Category $category, ?Category $subcategory, int $selectedCategoryCount): string
    {
        if ($subcategory instanceof Category) {
            return __('reports.categoryAnalysis.scope.selectedWithDescendants', [
                'category' => $subcategory->name,
            ]);
        }

        if ($category instanceof Category && $selectedCategoryCount > 1) {
            return __('reports.categoryAnalysis.scope.categoryWithDescendants', [
                'category' => $category->name,
            ]);
        }

        if ($category instanceof Category) {
            return __('reports.categoryAnalysis.scope.selectedOnly', [
                'category' => $category->name,
            ]);
        }

        return __('reports.categoryAnalysis.scope.none');
    }

    protected function actualScopeDescription(?Category $category, ?Category $subcategory): string
    {
        $focus = $subcategory ?? $category;

        if (! $focus instanceof Category) {
            return __('reports.categoryAnalysis.scope.actualNone');
        }

        return __('reports.categoryAnalysis.scope.actualLedger', [
            'category' => $focus->name,
        ]);
    }

    protected function budgetScopeDescription(array $budget, ?Category $category, ?Category $subcategory): string
    {
        $meta = $budget['meta'] ?? [];

        if (($meta['supported'] ?? false) !== true) {
            return match ($meta['reason'] ?? null) {
                'account_scope_not_supported' => __('reports.categoryAnalysis.scope.budgetAccountUnsupported'),
                default => __('reports.categoryAnalysis.scope.budgetMissing'),
            };
        }

        $focus = $subcategory ?? $category;
        $label = $focus instanceof Category ? $focus->name : __('reports.categoryAnalysis.fallbackCategory');

        if (($meta['aggregated'] ?? false) === true) {
            return __('reports.categoryAnalysis.scope.budgetAggregated', [
                'category' => $label,
            ]);
        }

        return __('reports.categoryAnalysis.scope.budgetDirect', [
            'category' => $label,
        ]);
    }

    protected function comparisonScopeDescription(array $previousYearComparison): string
    {
        if (($previousYearComparison['available'] ?? false) === true) {
            return __('reports.categoryAnalysis.scope.comparisonPreviousYear');
        }

        return __('reports.categoryAnalysis.scope.comparisonUnavailable');
    }

    protected function scopeSummary(string $analysisScopeLabel, string $actualScopeDescription, string $budgetScopeDescription, string $comparisonScopeDescription): string
    {
        return __('reports.categoryAnalysis.scope.summary', [
            'scope' => $analysisScopeLabel,
            'actual' => $actualScopeDescription,
            'budget' => $budgetScopeDescription,
            'comparison' => $comparisonScopeDescription,
        ]);
    }

    protected function categoryColor(?Category $category): string
    {
        if (! $category instanceof Category) {
            return '#ef4444';
        }

        $customColor = trim((string) ($category->color ?? ''));

        if ($customColor !== '') {
            return $customColor;
        }

        return match ($category->group_type) {
            CategoryGroupTypeEnum::EXPENSE => '#ef4444',
            CategoryGroupTypeEnum::BILL => '#f97316',
            CategoryGroupTypeEnum::DEBT => '#8b5cf6',
            CategoryGroupTypeEnum::TAX => '#b45309',
            default => '#ef4444',
        };
    }

    protected function formatMoney(float $value, string $currency): string
    {
        $formatter = new NumberFormatter(app()->getLocale() === 'it' ? 'it_IT' : 'en_US', NumberFormatter::CURRENCY);
        $formatted = $formatter->formatCurrency($value, $currency);

        return $formatted !== false ? $formatted : number_format($value, 2, ',', '.')." {$currency}";
    }
}
