<?php

return [
    'alerts' => [
        'enabled' => env('AUTOMATION_ALERTS_ENABLED', false),
        'dedupe_ttl_minutes' => env('AUTOMATION_ALERTS_DEDUPE_TTL_MINUTES', 1440),

        'telegram' => [
            'enabled' => env('AUTOMATION_ALERTS_TELEGRAM_ENABLED', false),
            'bot_token' => env('AUTOMATION_ALERTS_TELEGRAM_BOT_TOKEN'),
            'chat_id' => env('AUTOMATION_ALERTS_TELEGRAM_CHAT_ID'),
        ],
    ],

    'backups' => [
        'disk' => env('AUTOMATION_BACKUPS_DISK', 'local'),
        'full' => [
            'directory' => env('AUTOMATION_FULL_BACKUP_DIRECTORY', 'backups/full'),
        ],
        'user' => [
            'directory' => env('AUTOMATION_USER_BACKUP_DIRECTORY', 'backups/users'),
        ],
        'retention' => [
            'enabled' => env('AUTOMATION_BACKUP_RETENTION_ENABLED', true),
            'full' => [
                'enabled' => env('AUTOMATION_FULL_BACKUP_RETENTION_ENABLED', true),
                'days' => env('AUTOMATION_FULL_BACKUP_RETENTION_DAYS', 90),
            ],
            'user' => [
                'enabled' => env('AUTOMATION_USER_BACKUP_RETENTION_ENABLED', true),
                'days' => env('AUTOMATION_USER_BACKUP_RETENTION_DAYS', 90),
            ],
        ],
    ],

    'health' => [
        'running_stale_after_minutes' => env('AUTOMATION_RUNNING_STALE_AFTER_MINUTES', 30),
        'missing_run_grace_multiplier' => env('AUTOMATION_MISSING_RUN_GRACE_MULTIPLIER', 1),
        'skip_missing_run_alert_in_local' => env('AUTOMATION_SKIP_MISSING_RUN_ALERT_IN_LOCAL', true),
    ],

    'pipelines' => [
        'recurring_pipeline' => [
            'enabled' => true,
            'critical' => true,
            'alert_on_failure' => true,
            'max_expected_interval_minutes' => 90,
            'supports_reference_date' => false,
        ],

        'credit_card_autopay' => [
            'enabled' => true,
            'critical' => true,
            'alert_on_failure' => true,
            'max_expected_interval_minutes' => 1440,
            'supports_reference_date' => true,
        ],

        'recurring_weekly_summary' => [
            'enabled' => true,
            'critical' => true,
            'alert_on_failure' => true,
            'max_expected_interval_minutes' => 10080,
            'supports_reference_date' => true,
        ],

        'recurring_monthly_summary' => [
            'enabled' => true,
            'critical' => true,
            'alert_on_failure' => true,
            'max_expected_interval_minutes' => 44640,
            'supports_reference_date' => true,
        ],

        'full_backup' => [
            'enabled' => env('AUTOMATION_FULL_BACKUP_ENABLED', true),
            'critical' => true,
            'alert_on_failure' => true,
            'max_expected_interval_minutes' => 1440,
            'supports_reference_date' => false,
        ],

        'user_backup' => [
            'enabled' => env('AUTOMATION_USER_BACKUP_ENABLED', true),
            'critical' => true,
            'alert_on_failure' => true,
            'max_expected_interval_minutes' => 1440,
            'supports_reference_date' => false,
        ],

        'backup_retention_cleanup' => [
            'enabled' => env('AUTOMATION_BACKUP_RETENTION_ENABLED', true),
            'critical' => false,
            'alert_on_failure' => true,
            'max_expected_interval_minutes' => 1440,
            'supports_reference_date' => false,
        ],
    ],
];
