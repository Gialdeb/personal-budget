<?php

return [
    'enabled' => env('REMINDERS_ENABLED', true),
    'due_soon_days' => max(0, (int) env('REMINDERS_DUE_SOON_DAYS', 3)),
    'overdue_repeat_daily' => env('REMINDERS_OVERDUE_REPEAT_DAILY', true),
    'daily_run_time' => env('REMINDERS_DAILY_RUN_TIME', '08:00'),
    'recurring' => [
        'max_custom_alerts' => 3,
        'max_days_before' => 15,
    ],
];
