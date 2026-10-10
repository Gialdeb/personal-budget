<?php

return [
    'retention_days' => ['automation_runs' => (int) env('TECHNICAL_ARCHIVE_AUTOMATION_DAYS', 90)],
    'batch_size' => (int) env('TECHNICAL_ARCHIVE_BATCH_SIZE', 100),
    'limit' => (int) env('TECHNICAL_ARCHIVE_LIMIT', 5000),
    'daily_at' => env('TECHNICAL_ARCHIVE_DAILY_AT', '05:15'),
];
