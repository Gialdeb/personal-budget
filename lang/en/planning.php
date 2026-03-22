<?php

return [
    'title' => 'Planning',
    'sections' => [
        'income' => 'Planned income',
        'expense' => 'Expense budget',
        'bill' => 'Bills budget',
        'debt' => 'Debt payoff target',
        'saving' => 'Savings target',
        'tax' => 'Tax planning',
        'investment' => 'Investment allocation',
        'other' => 'Available categories',
    ],
    'save' => [
        'saving' => 'Saving in progress',
        'check_errors' => 'Check the cells with errors',
        'saved' => 'All changes are saved',
        'autosave' => 'Autosave enabled',
    ],
    'closed_year' => [
        'title' => 'Closed year',
        'fallback' => 'The selected year is closed and cannot be edited.',
    ],
    'validation' => [
        'leaf_only' => 'You can plan the budget only on selectable leaf categories.',
        'copy_source_empty' => 'There are no planned values in :year to copy.',
    ],
    'enums' => [
        'budget_goal_type' => [
            'target' => 'Target',
            'limit' => 'Limit',
            'forecast' => 'Forecast',
        ],
    ],
];
