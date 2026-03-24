<?php

return [
    'common' => [
        'details' => 'Details',
        'footer' => 'This notification was sent by :app.',
    ],
    'topics' => [
        'automation_failed' => [
            'topic' => 'Automation failure',
            'subject' => 'Automation pipeline failed',
            'title' => 'Automation pipeline failed',
            'message' => 'One of the automation pipelines requires attention.',
            'cta' => 'Open automations',
            'details' => [
                'pipeline' => 'Pipeline',
                'error_message' => 'Error message',
                'context' => 'Context',
            ],
        ],
        'import_completed' => [
            'topic' => 'Import completed',
            'subject' => 'Import completed',
            'title' => 'Import completed',
            'message' => 'Your import completed successfully.',
            'cta' => 'Open import',
            'details' => [
                'import_uuid' => 'Import',
                'filename' => 'File',
                'imported_rows_count' => 'Imported rows',
                'rows_count' => 'Total rows',
            ],
        ],
        'monthly_report_ready' => [
            'topic' => 'Monthly report ready',
            'subject' => 'Monthly report ready',
            'title' => 'Monthly report ready',
            'message' => 'Your monthly report for :period is ready.',
            'cta' => 'Open dashboard',
            'details' => [
                'period' => 'Period',
            ],
        ],
    ],
];
