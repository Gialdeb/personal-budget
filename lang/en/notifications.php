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
        'auth_verify_email' => [
            'topic' => 'Verify email',
            'subject' => 'Verify your email address',
            'title' => 'Verify your email address',
            'message' => 'Please click the button below to verify your email address.',
            'cta' => 'Verify email',
            'details' => [],
        ],
        'auth_reset_password' => [
            'topic' => 'Reset password',
            'subject' => 'Reset your password',
            'title' => 'Reset your password',
            'message' => 'You are receiving this email because we received a password reset request for your account.',
            'cta' => 'Reset password',
            'expire' => 'This link will expire in :count minutes.',
            'details' => [],
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
        'welcome_after_verification' => [
            'topic' => 'Welcome',
            'subject' => 'Welcome',
            'title' => 'Welcome',
            'message' => 'Welcome {user.full_name}, your account is now active.',
            'cta' => 'Open dashboard',
            'details' => [],
        ],
    ],
];
