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
