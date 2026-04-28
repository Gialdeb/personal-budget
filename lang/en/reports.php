<?php

return [
    'filters' => [
        'allResources' => 'All resources',
        'periods' => [
            'annual' => 'Annual',
            'monthly' => 'Monthly',
            'lastThreeMonths' => 'Last 3 months',
            'lastSixMonths' => 'Last 6 months',
            'ytd' => 'Year to date',
        ],
        'periodSummaries' => [
            'annual' => 'Year :year',
            'lastThreeMonths' => 'Last 3 months through :month :year',
            'lastSixMonths' => 'Last 6 months through :month :year',
            'ytd' => 'Year to date through :month :year',
        ],
    ],
    'overview' => [
        'kpis' => [
            'averagePerDay' => 'Average per day',
            'averagePerMonth' => 'Average per month',
        ],
        'meta' => [
            'coverageNote' => ':count transactions are excluded from monetary totals because they do not have a reliable base-currency conversion.',
        ],
    ],
    'categories' => [
        'filters' => [
            'focuses' => [
                'all' => 'All',
                'income' => 'Income',
                'expense' => 'Expenses',
                'saving' => 'Savings',
            ],
        ],
        'recent' => [
            'fallbackDescription' => 'Transaction without description',
        ],
    ],
    'categoryAnalysis' => [
        'category' => 'Category',
        'subcategory' => 'Subcategory',
        'allSubcategories' => 'All subcategories',
        'fallbackCategory' => 'Selected category',
        'budget' => [
            'label' => 'Budget',
            'seriesName' => 'Budget',
            'variance' => 'Budget variance',
        ],
        'scope' => [
            'summaryLabel' => 'Reading basis',
            'summary' => ':scope. :actual :budget :comparison',
            'selectedOnly' => ':category selected',
            'selectedWithDescendants' => ':category and any descendant categories',
            'categoryWithDescendants' => ':category with all coherent subcategories',
            'none' => 'No selectable category',
            'actualNone' => 'Actual spend stays empty until an expense category is available.',
            'actualLedger' => 'Includes only confirmed ledger movements assigned to :category or included child categories.',
            'budgetDirect' => 'Compares the monthly budget assigned to :category, excluding resources and tracked items.',
            'budgetAggregated' => 'Aggregated budget: sums monthly budgets configured on :category and included subcategories.',
            'budgetMissing' => 'No comparable budget was found for the active category, period, and scope.',
            'budgetAccountUnsupported' => 'Budget comparison is disabled when the resource filter limits the scope.',
            'comparisonPreviousYear' => 'The comparison uses the same interval in the previous year and the same category selection.',
            'comparisonUnavailable' => 'Previous-year comparison stays hidden when no coherent historical baseline exists.',
        ],
        'comparisons' => [
            'unavailable' => 'Comparison unavailable: there is not enough data in the reference period.',
            'unavailableShort' => 'Not available',
        ],
        'emptyDataset' => [
            'title' => 'No spend in the selected scope',
            'message' => 'In :period there are no actual movements for :scope. Spend-based charts stay empty until there is a confirmed ledger baseline.',
            'budgetNote' => 'When present, budget remains visible as assigned planning, not as actual spend.',
        ],
        'insight' => [
            'overBudgetTitle' => 'Budget attention',
            'inLineTitle' => 'Category in line',
            'spendingUpTitle' => 'Spend is increasing',
            'spendingDownTitle' => 'Spend under control',
            'noComparisonTitle' => 'Limited comparison',
            'noSpendTitle' => 'No actual spend',
            'noSpendMessage' => 'There are no confirmed movements in the selected scope. Comparisons are suspended to avoid misleading readings.',
            'noSpendWithBudgetMessage' => 'There are no confirmed movements in the selected scope. The assigned budget is :budget and remains a planned threshold.',
            'overBudgetStatus' => 'Over budget',
            'underBudgetStatus' => 'Under budget',
            'budgetMessage' => ':status by :variance against the :budget budget. Vs previous year: :year_delta (:year_percentage). :top accounts for :share of the category.',
            'previousYearMessage' => 'Change of :delta (:percentage) against the previous year. :top accounts for :share of the category.',
            'noComparisonMessage' => 'There is not enough previous-year or budget data to build a reliable comparison.',
        ],
        'kpis' => [
            'totalSpent' => 'Total spent',
            'averagePeriod' => 'Period average',
            'averagePerDay' => 'Daily average',
            'averagePerMonth' => 'Monthly average',
            'bestMonth' => 'Best period',
            'worstMonth' => 'Worst period',
            'previousPeriod' => 'vs previous period',
            'previousYear' => 'vs previous year',
        ],
        'charts' => [
            'breakdownTitle' => 'Subcategory breakdown',
        ],
        'table' => [
            'title' => 'Period detail',
            'period' => 'Period',
            'spent' => 'Spent',
            'previousYear' => 'Previous year',
            'deltaPreviousYear' => 'Delta vs previous year',
            'dominantSubcategory' => 'Dominant item',
        ],
        'export' => [
            'title' => 'Category analysis',
            'xlsx' => 'Excel',
            'period' => 'Period',
            'scope' => 'Resource',
            'previousYear' => 'Previous year',
            'share' => 'Share',
            'generatedAt' => 'Generated on',
            'headerSubtitle' => 'Category analysis report',
            'summary' => 'Executive summary',
            'periods' => 'periods',
            'comparisons' => 'Comparisons',
            'notAvailable' => 'Not available',
            'analysisScope' => 'Analysis scope',
            'actualScope' => 'Actual spend',
            'budgetScope' => 'Budget',
            'comparisonScope' => 'Comparisons',
        ],
    ],
    'modules' => [
        'status' => [
            'available' => 'Analytics section available',
        ],
        'kpi' => [
            'title' => 'Period overview',
            'description' => 'Reliable period KPIs and income, expense, and net trend over time.',
        ],
        'categories' => [
            'title' => 'Category split',
            'description' => 'Composition, main categories, and expense trend.',
        ],
        'categoryAnalysis' => [
            'title' => 'Category analysis',
            'description' => 'KPIs, trends, and time comparisons for a specific category or subcategory.',
        ],
        'accounts' => [
            'title' => 'Account view',
            'description' => 'Reading by account, cards, and shared scopes.',
        ],
    ],
    'accounts' => [
        'allAccounts' => 'All accounts',
        'movement' => 'Movement',
        'uncategorized' => 'Uncategorized',
        'types' => [
            'current' => 'Current account',
            'cash' => 'Cash',
            'credit_card' => 'Credit card',
        ],
    ],
];
