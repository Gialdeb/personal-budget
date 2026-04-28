<?php

return [
    'imports' => [
        'enabled' => (bool) env('FEATURE_IMPORT_ENABLED', false),
    ],
    'reports' => [
        'enabled' => (bool) env('FEATURE_REPORTS_ENABLED', false),
        'sections' => [
            'kpis' => (bool) env('FEATURE_REPORTS_KPIS_ENABLED', false),
            'categories' => (bool) env('FEATURE_REPORTS_CATEGORIES_ENABLED', false),
            'category_analysis' => (bool) env('FEATURE_REPORTS_CATEGORY_ANALYSIS_ENABLED', false),
            'accounts' => (bool) env('FEATURE_REPORTS_ACCOUNTS_ENABLED', false),
        ],
    ],
    'push_notifications' => [
        'enabled' => (bool) env('FEATURE_PUSH_NOTIFICATIONS_ENABLED', false),
        'profile_enabled' => (bool) env('FEATURE_PUSH_NOTIFICATIONS_PROFILE_ENABLED', false),
    ],
];
