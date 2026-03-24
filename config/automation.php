<?php

return [
    'alerts' => [
        'enabled' => env('AUTOMATION_ALERTS_ENABLED', false),

        'telegram' => [
            'enabled' => env('AUTOMATION_ALERTS_TELEGRAM_ENABLED', false),
            'bot_token' => env('AUTOMATION_ALERTS_TELEGRAM_BOT_TOKEN'),
            'chat_id' => env('AUTOMATION_ALERTS_TELEGRAM_CHAT_ID'),
        ],
    ],

    'health' => [
        'running_stale_after_minutes' => env('AUTOMATION_RUNNING_STALE_AFTER_MINUTES', 30),
    ],

    'pipelines' => [
        'recurring_pipeline' => [
            'enabled' => true,
            'critical' => true,
            'alert_on_failure' => true,
            'max_expected_interval_minutes' => 90,
        ],

        'notifications_pipeline' => [
            'enabled' => true,
            'critical' => true,
            'alert_on_failure' => true,
            'max_expected_interval_minutes' => 30,
        ],

        'reports_pipeline' => [
            'enabled' => true,
            'critical' => false,
            'alert_on_failure' => true,
            'max_expected_interval_minutes' => 1440,
        ],
    ],
];
