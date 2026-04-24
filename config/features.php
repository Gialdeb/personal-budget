<?php

return [
    'imports' => [
        'enabled' => (bool) env('FEATURE_IMPORT_ENABLED', false),
    ],
    'reports' => [
        'enabled' => (bool) env('FEATURE_REPORTS_ENABLED', true),
        'sections' => [
            'kpis' => (bool) env('FEATURE_REPORTS_KPIS_ENABLED', true),
            'categories' => (bool) env('FEATURE_REPORTS_CATEGORIES_ENABLED', true),
            'accounts' => (bool) env('FEATURE_REPORTS_ACCOUNTS_ENABLED', true),
        ],
    ],
    'push_notifications' => [
        'enabled' => (bool) env('FEATURE_PUSH_NOTIFICATIONS_ENABLED', false),
        'profile_enabled' => (bool) env('FEATURE_PUSH_NOTIFICATIONS_PROFILE_ENABLED', false),
    ],
];
