<?php

namespace App\Services\Reports;

use App\Enums\CategoryDirectionTypeEnum;
use App\Enums\CategoryGroupTypeEnum;
use App\Enums\TransactionDirectionEnum;
use App\Enums\TransactionKindEnum;
use App\Enums\TransactionStatusEnum;
use App\Models\Category;
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

class CategoryBreakdownReportService
{
    protected const PERIOD_ANNUAL = 'annual';

    protected const PERIOD_MONTHLY = 'monthly';

    protected const PERIOD_LAST_THREE_MONTHS = 'last_3_months';

    protected const PERIOD_LAST_SIX_MONTHS = 'last_6_months';

    protected const PERIOD_YTD = 'ytd';

    protected const FOCUS_ALL = 'all';

    protected const FOCUS_INCOME = 'income';

    protected const FOCUS_EXPENSE = 'expense';

    protected const FOCUS_SAVING = 'saving';

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
     *     focus?: string|null,
     *     exclude_internal?: bool|null
     * }  $input
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
        $selectedFocus = $this->normalizeFocus($input['focus'] ?? null);
        $excludeInternal = $this->normalizeExcludeInternal($input['exclude_internal'] ?? null);

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

        $trendBuckets = $this->makeBucketMap($periodDefinition);
        $transactions = $this->operationalLedgerAnalyticsService->transactionsForPeriod(
            $user,
            $periodDefinition['start'],
            $periodDefinition['end'],
            $selectedAccountUuid,
            ! $excludeInternal,
        )->filter(function (Transaction $transaction) use ($excludeInternal): bool {
            if (! $excludeInternal) {
                return true;
            }

            return $transaction->kind !== TransactionKindEnum::CREDIT_CARD_SETTLEMENT;
        })->values();

        $tree = [];
        $resolvedTransactions = collect();
        $selectedTotalRaw = 0.0;
        $unresolvedTransactionsCount = 0;

        foreach ($transactions as $transaction) {
            $resolvedAmount = $this->operationalLedgerAnalyticsService->resolveAggregateAmountForTransaction(
                $transaction,
                $baseCurrency,
            );

            if ($resolvedAmount === null) {
                $unresolvedTransactionsCount++;

                continue;
            }

            $path = $this->categoryPath($transaction->category);

            if ($path === []) {
                continue;
            }

            if (! $this->matchesFocus($selectedFocus, $transaction, $path)) {
                continue;
            }

            $selectedTotalRaw += $resolvedAmount;
            $this->addPathAmount($tree, $path, $resolvedAmount);
            $resolvedTransactions->push([
                'transaction' => $transaction,
                'amount_raw' => $resolvedAmount,
                'path' => $path,
            ]);
        }

        $selectedTotalRaw = round($selectedTotalRaw, 2);
        $formattedTree = collect($this->formatTree($tree, $selectedTotalRaw, $baseCurrency));
        $topCategories = $this->topCategories(
            $formattedTree,
            $selectedTotalRaw,
            $baseCurrency,
        );
        $trend = $this->buildExpenseTrend(
            $resolvedTransactions,
            $trendBuckets,
            $periodDefinition['granularity'],
            $baseCurrency,
        );
        $recentTransactions = $this->recentTransactions(
            $resolvedTransactions,
            $baseCurrency,
        );
        $summaryHighlights = $this->buildSummaryHighlights($formattedTree);

        return [
            'currency' => $baseCurrency,
            'meta' => [
                'period_label' => $this->periodLabel($periodDefinition),
                'scope_label' => $this->scopeLabel($selectedAccountUuid, $accountOptions),
                'focus_label' => $this->focusLabel($selectedFocus),
                'granularity' => $periodDefinition['granularity'],
                'unresolved_transactions_count' => $unresolvedTransactionsCount,
            ],
            'filters' => [
                'year' => $selectedYear,
                'month' => $selectedPeriod === self::PERIOD_ANNUAL
                    ? null
                    : $referenceMonth,
                'period' => $selectedPeriod,
                'account_uuid' => $selectedAccountUuid,
                'focus' => $selectedFocus,
                'exclude_internal' => $excludeInternal,
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
                'focus_options' => $this->focusOptions(),
                'show_month_filter' => $selectedPeriod !== self::PERIOD_ANNUAL,
            ],
            'summary' => [
                'total_selected' => $this->formatMoney($selectedTotalRaw, $baseCurrency),
                'total_selected_raw' => $selectedTotalRaw,
                'categories_count' => $formattedTree->count(),
                'active_categories_count' => $summaryHighlights['active_categories_count'],
                'main_category_label' => $summaryHighlights['main_category_label'],
                'main_category_total' => $summaryHighlights['main_category_total'] !== null
                    ? $this->formatMoney($summaryHighlights['main_category_total'], $baseCurrency)
                    : null,
                'main_category_share_label' => $summaryHighlights['main_category_share_label'],
                'top_subcategory_label' => $summaryHighlights['top_subcategory_label'],
            ],
            'composition' => [
                'sunburst_nodes' => $formattedTree->all(),
                'treemap_nodes' => $this->treemapNodes($formattedTree),
            ],
            'top_categories' => $topCategories,
            'trend' => $trend,
            'recent_transactions' => $recentTransactions,
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
            : self::PERIOD_ANNUAL;
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

    protected function normalizeFocus(?string $requestedFocus): string
    {
        return in_array($requestedFocus, [
            self::FOCUS_ALL,
            self::FOCUS_INCOME,
            self::FOCUS_EXPENSE,
            self::FOCUS_SAVING,
        ], true)
            ? $requestedFocus
            : self::FOCUS_ALL;
    }

    protected function normalizeExcludeInternal(?bool $excludeInternal): bool
    {
        return $excludeInternal ?? true;
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
     * @param  Collection<int, array<string, mixed>>  $tree
     * @return array{
     *     active_categories_count: int,
     *     main_category_label: string|null,
     *     main_category_total: float|null,
     *     main_category_share_label: string|null,
     *     top_subcategory_label: string|null
     * }
     */
    protected function buildSummaryHighlights(Collection $tree): array
    {
        /** @var array<string, mixed>|null $mainCategory */
        $mainCategory = $tree->first();
        /** @var array<string, mixed>|null $topSubcategory */
        $topSubcategory = $tree
            ->flatMap(fn (array $node) => collect($node['children'] ?? [])->all())
            ->sortByDesc('value')
            ->first();

        return [
            'active_categories_count' => $tree
                ->filter(fn (array $node): bool => ((float) ($node['value'] ?? 0)) > 0)
                ->count(),
            'main_category_label' => is_array($mainCategory)
                ? (string) ($mainCategory['label'] ?? '')
                : null,
            'main_category_total' => is_array($mainCategory)
                ? round((float) ($mainCategory['value'] ?? 0), 2)
                : null,
            'main_category_share_label' => is_array($mainCategory)
                ? (string) ($mainCategory['share_label'] ?? '0.0%')
                : null,
            'top_subcategory_label' => is_array($topSubcategory)
                ? (string) ($topSubcategory['label'] ?? '')
                : null,
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
     * @return array<string, array{key: string, label: string, total_raw: float}>
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
                'total_raw' => 0.0,
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
        bool $excludeInternal,
    ): Collection {
        $query = Transaction::query()
            ->with([
                'category:id,uuid,parent_id,name,name_is_custom,slug,foundation_key,color,group_type,direction_type',
                'category.parent:id,uuid,parent_id,name,name_is_custom,slug,foundation_key,color,group_type,direction_type',
                'category.parent.parent:id,uuid,parent_id,name,name_is_custom,slug,foundation_key,color,group_type,direction_type',
                'account:id,uuid,name',
            ])
            ->whereIn('account_id', $accountIds !== [] ? $accountIds : [0])
            ->where('status', TransactionStatusEnum::CONFIRMED->value)
            ->where('kind', '!=', TransactionKindEnum::OPENING_BALANCE->value)
            ->whereNotNull('category_id')
            ->whereBetween('transaction_date', [
                $start->toDateString(),
                $end->toDateString(),
            ]);

        if ($excludeInternal) {
            $query
                ->where('is_transfer', false)
                ->where('kind', '!=', TransactionKindEnum::CREDIT_CARD_SETTLEMENT->value);
        }

        $this->applyTrackedItemOwnershipConstraintForOwners(
            $query,
            'tracked_item_id',
            $ownerIds,
        );

        return $query
            ->orderByDesc('transaction_date')
            ->get([
                'id',
                'uuid',
                'account_id',
                'category_id',
                'transaction_date',
                'direction',
                'kind',
                'amount',
                'currency',
                'currency_code',
                'base_currency_code',
                'converted_base_amount',
                'description',
                'is_transfer',
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
     * @return array<int, array{key: string, label: string, color: string, group_type: string|null}>
     */
    protected function categoryPath(?Category $category): array
    {
        if (! $category instanceof Category) {
            return [];
        }

        $chain = collect([
            $category->parent?->parent,
            $category->parent,
            $category,
        ])->filter(fn ($item): bool => $item instanceof Category)->values();

        return $chain
            ->map(fn (Category $node): array => [
                'key' => (string) $node->uuid,
                'label' => $node->displayName(),
                'color' => $this->categoryColor($node),
                'group_type' => $node->group_type?->value,
            ])
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $path
     */
    protected function matchesFocus(string $focus, Transaction $transaction, array $path): bool
    {
        if ($focus === self::FOCUS_ALL) {
            return true;
        }

        $rootGroupType = collect($path)
            ->pluck('group_type')
            ->filter(fn ($value): bool => is_string($value) && $value !== '')
            ->first();

        return match ($focus) {
            self::FOCUS_INCOME => $rootGroupType !== null
                ? $rootGroupType === CategoryGroupTypeEnum::INCOME->value
                : $transaction->direction === TransactionDirectionEnum::INCOME,
            self::FOCUS_EXPENSE => $rootGroupType !== null
                ? in_array($rootGroupType, [
                    CategoryGroupTypeEnum::EXPENSE->value,
                    CategoryGroupTypeEnum::BILL->value,
                    CategoryGroupTypeEnum::DEBT->value,
                    CategoryGroupTypeEnum::TAX->value,
                ], true)
                : $transaction->direction === TransactionDirectionEnum::EXPENSE,
            self::FOCUS_SAVING => in_array($rootGroupType, [
                CategoryGroupTypeEnum::SAVING->value,
                CategoryGroupTypeEnum::INVESTMENT->value,
            ], true),
            default => true,
        };
    }

    /**
     * @param  array<string, array<string, mixed>>  $tree
     * @param  array<int, array<string, mixed>>  $path
     */
    protected function addPathAmount(array &$tree, array $path, float $amount): void
    {
        $currentLevel = &$tree;

        foreach ($path as $node) {
            $key = (string) $node['key'];

            if (! isset($currentLevel[$key])) {
                $currentLevel[$key] = [
                    'key' => $key,
                    'label' => (string) $node['label'],
                    'color' => (string) $node['color'],
                    'group_type' => $node['group_type'],
                    'total_raw' => 0.0,
                    'children' => [],
                ];
            }

            $currentLevel[$key]['total_raw'] += $amount;
            $currentLevel = &$currentLevel[$key]['children'];
        }
    }

    /**
     * @param  array<string, array<string, mixed>>  $tree
     * @return array<int, array<string, mixed>>
     */
    protected function formatTree(array $tree, float $denominator, string $currency): array
    {
        return collect($tree)
            ->sortByDesc('total_raw')
            ->map(function (array $node) use ($denominator, $currency): array {
                $children = $this->formatTree(
                    $node['children'],
                    (float) $node['total_raw'],
                    $currency,
                );
                $share = $denominator > 0
                    ? round((((float) $node['total_raw']) / $denominator) * 100, 1)
                    : 0.0;

                return [
                    'key' => $node['key'],
                    'name' => $node['label'],
                    'label' => $node['label'],
                    'value' => round((float) $node['total_raw'], 2),
                    'total' => $this->formatMoney((float) $node['total_raw'], $currency),
                    'color' => $node['color'],
                    'share_percentage' => $share,
                    'share_label' => number_format($share, 1, '.', '').'%',
                    'children_count' => count($children),
                    'children' => $children,
                    'itemStyle' => [
                        'color' => $node['color'],
                    ],
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $tree
     * @return array<int, array<string, mixed>>
     */
    protected function topCategories(Collection $tree, float $selectedTotalRaw, string $currency): array
    {
        return $tree
            ->take(5)
            ->map(function (array $node) use ($selectedTotalRaw, $currency): array {
                $share = $selectedTotalRaw > 0
                    ? round((((float) $node['value']) / $selectedTotalRaw) * 100, 1)
                    : 0.0;

                return [
                    'key' => $node['key'],
                    'label' => $node['label'],
                    'total' => $this->formatMoney((float) $node['value'], $currency),
                    'total_raw' => round((float) $node['value'], 2),
                    'share_percentage' => $share,
                    'share_label' => number_format($share, 1, '.', '').'%',
                    'subcategories_count' => (int) $node['children_count'],
                    'color' => $node['color'],
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $tree
     * @return array<int, array<string, mixed>>
     */
    protected function treemapNodes(Collection $tree): array
    {
        return $tree
            ->map(function (array $node): array {
                return [
                    'name' => $node['label'],
                    'value' => $node['value'],
                    'itemStyle' => [
                        'color' => $node['color'],
                    ],
                    'children' => collect($node['children'] ?? [])
                        ->map(fn (array $child): array => [
                            'name' => $child['label'],
                            'value' => $child['value'],
                            'itemStyle' => [
                                'color' => $child['color'],
                            ],
                            'children' => collect($child['children'] ?? [])
                                ->map(fn (array $grandChild): array => [
                                    'name' => $grandChild['label'],
                                    'value' => $grandChild['value'],
                                    'itemStyle' => [
                                        'color' => $grandChild['color'],
                                    ],
                                ])
                                ->values()
                                ->all(),
                        ])
                        ->values()
                        ->all(),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, array{transaction: Transaction, amount_raw: float, path: array<int, array<string, mixed>>}>  $transactions
     * @param  array<string, array{key: string, label: string, total_raw: float}>  $bucketTemplate
     * @return array<string, mixed>
     */
    protected function buildExpenseTrend(
        Collection $transactions,
        array $bucketTemplate,
        string $granularity,
        string $currency,
    ): array {
        $expenseLikeTransactions = $transactions
            ->filter(function (array $item): bool {
                $rootGroupType = collect($item['path'])
                    ->pluck('group_type')
                    ->filter(fn ($value): bool => is_string($value) && $value !== '')
                    ->first();

                return in_array($rootGroupType, [
                    CategoryGroupTypeEnum::EXPENSE->value,
                    CategoryGroupTypeEnum::BILL->value,
                    CategoryGroupTypeEnum::DEBT->value,
                    CategoryGroupTypeEnum::TAX->value,
                ], true);
            })
            ->values();

        $topRoots = $expenseLikeTransactions
            ->groupBy(fn (array $item): string => (string) ($item['path'][0]['label'] ?? ''))
            ->map(fn (Collection $items): float => round((float) $items->sum('amount_raw'), 2))
            ->sortDesc()
            ->take(3)
            ->keys();

        $series = $topRoots
            ->map(function (string $label) use ($bucketTemplate, $expenseLikeTransactions, $granularity, $currency): array {
                $bucketMap = $bucketTemplate;

                foreach ($expenseLikeTransactions as $item) {
                    if (($item['path'][0]['label'] ?? null) !== $label) {
                        continue;
                    }

                    /** @var Transaction $transaction */
                    $transaction = $item['transaction'];
                    $bucketKey = $this->bucketKeyForDate(
                        CarbonImmutable::parse(
                            $transaction->transaction_date,
                            config('app.timezone'),
                        ),
                        $granularity,
                    );

                    if (! isset($bucketMap[$bucketKey])) {
                        continue;
                    }

                    $bucketMap[$bucketKey]['total_raw'] += $item['amount_raw'];
                }

                $rootPath = $expenseLikeTransactions
                    ->first(fn (array $item): bool => ($item['path'][0]['label'] ?? null) === $label);

                return [
                    'key' => str($label)->slug('-')->value(),
                    'name' => $label,
                    'color' => $rootPath['path'][0]['color'] ?? '#64748b',
                    'values' => collect($bucketMap)
                        ->map(fn (array $bucket): float => round((float) $bucket['total_raw'], 2))
                        ->values()
                        ->all(),
                    'total' => $this->formatMoney(
                        collect($bucketMap)->sum('total_raw'),
                        $currency,
                    ),
                ];
            })
            ->values()
            ->all();

        return [
            'labels' => collect($bucketTemplate)->pluck('label')->values()->all(),
            'granularity' => $granularity,
            'series' => $series,
        ];
    }

    /**
     * @param  Collection<int, array{transaction: Transaction, amount_raw: float, path: array<int, array<string, mixed>>}>  $transactions
     * @return array<int, array<string, mixed>>
     */
    protected function recentTransactions(Collection $transactions, string $currency): array
    {
        return $transactions
            ->sortByDesc(fn (array $item) => $item['transaction']->transaction_date)
            ->take(6)
            ->map(function (array $item) use ($currency): array {
                /** @var Transaction $transaction */
                $transaction = $item['transaction'];
                $signedAmount = $transaction->direction === TransactionDirectionEnum::EXPENSE
                    ? -1 * (float) $item['amount_raw']
                    : (float) $item['amount_raw'];

                return [
                    'uuid' => $transaction->uuid,
                    'date_label' => CarbonImmutable::parse(
                        $transaction->transaction_date,
                        config('app.timezone'),
                    )->translatedFormat('d M'),
                    'description' => $transaction->description ?: __('reports.categories.recent.fallbackDescription'),
                    'category_label' => collect($item['path'])->pluck('label')->implode(' · '),
                    'amount' => $this->formatMoney($signedAmount, $currency),
                    'amount_raw' => round($signedAmount, 2),
                    'direction' => $transaction->direction?->value,
                    'color' => $item['path'][0]['color'] ?? '#64748b',
                ];
            })
            ->values()
            ->all();
    }

    protected function categoryColor(Category $category): string
    {
        $customColor = trim((string) ($category->color ?? ''));

        if ($customColor !== '') {
            return $customColor;
        }

        return match ($category->group_type) {
            CategoryGroupTypeEnum::INCOME => '#57a773',
            CategoryGroupTypeEnum::EXPENSE => '#d95450',
            CategoryGroupTypeEnum::BILL => '#d9a441',
            CategoryGroupTypeEnum::DEBT => '#8660d8',
            CategoryGroupTypeEnum::SAVING => '#4f6fd8',
            CategoryGroupTypeEnum::INVESTMENT => '#0f766e',
            CategoryGroupTypeEnum::TAX => '#b45309',
            default => match ($category->direction_type) {
                CategoryDirectionTypeEnum::INCOME => '#57a773',
                CategoryDirectionTypeEnum::EXPENSE => '#d95450',
                default => '#64748b',
            },
        };
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

    /**
     * @return array<int, array{value: string, label: string}>
     */
    protected function focusOptions(): array
    {
        return [
            [
                'value' => self::FOCUS_ALL,
                'label' => __('reports.categories.filters.focuses.all'),
            ],
            [
                'value' => self::FOCUS_EXPENSE,
                'label' => __('reports.categories.filters.focuses.expense'),
            ],
            [
                'value' => self::FOCUS_INCOME,
                'label' => __('reports.categories.filters.focuses.income'),
            ],
            [
                'value' => self::FOCUS_SAVING,
                'label' => __('reports.categories.filters.focuses.saving'),
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

    protected function focusLabel(string $focus): string
    {
        return match ($focus) {
            self::FOCUS_INCOME => __('reports.categories.filters.focuses.income'),
            self::FOCUS_EXPENSE => __('reports.categories.filters.focuses.expense'),
            self::FOCUS_SAVING => __('reports.categories.filters.focuses.saving'),
            default => __('reports.categories.filters.focuses.all'),
        };
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
