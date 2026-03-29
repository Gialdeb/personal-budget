<?php

return [
    'title' => 'Dashboard',
    'greeting' => [
        'morning' => 'Good morning',
        'afternoon' => 'Good afternoon',
        'evening' => 'Good evening',
    ],
    'period' => [
        'all_year' => 'All of :year',
        'current_year' => 'Current year',
        'viewing_year' => 'You are viewing :year',
        'unknown_month' => 'Unknown month',
    ],
    'filters' => [
        'account_access_scopes' => [
            'all' => 'All accessible accounts',
            'owned' => 'Owned accounts only',
            'shared' => 'Shared accounts only',
        ],
        'account_badges' => [
            'owned' => 'Owned',
            'shared' => 'Shared',
        ],
    ],
    'budgetVsActual' => [
        'generalScope' => 'General',
    ],
    'agenda' => [
        'unspecified' => 'Unspecified',
    ],
    'months' => [
        1 => 'January',
        2 => 'February',
        3 => 'March',
        4 => 'April',
        5 => 'May',
        6 => 'June',
        7 => 'July',
        8 => 'August',
        9 => 'September',
        10 => 'October',
        11 => 'November',
        12 => 'December',
    ],
    'sections' => [
        'income' => 'Actual income for the month',
        'expense' => 'Expenses incurred',
        'bill' => 'Bills paid',
        'debt' => 'Debt payments',
        'saving' => 'Savings made',
        'tax' => 'Tax payments',
        'investment' => 'Investments made',
        'transfer' => 'Internal transfers between accounts',
        'other' => 'Other transactions',
    ],
    'enums' => [
        'AccountBalanceNature' => [
            'asset' => 'Asset',
            'liability' => 'Liability',
        ],
    ],
];
