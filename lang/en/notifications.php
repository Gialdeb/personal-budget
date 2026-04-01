<?php

return [
    'common' => [
        'details' => 'Details',
        'footer' => 'This notification was sent by :app.',
        'brand_tagline' => 'Planning, transactions and accounts',
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
        'credit_card_autopay_completed' => [
            'topic' => 'Credit card charge completed',
            'subject' => 'Automatic charge completed for {credit_card_account_name}',
            'title' => 'Automatic charge completed',
            'message' => 'The billing cycle for {credit_card_account_name} was charged successfully for {charged_amount_formatted} on {linked_payment_account_name} on {payment_due_date_formatted}.',
            'cta' => 'Open transactions',
            'details' => [
                'credit_card_account' => 'Credit card',
                'linked_payment_account' => 'Charged account',
                'amount' => 'Amount',
                'payment_due_date' => 'Charge date',
                'cycle_end_date' => 'Cycle close date',
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
            'subject' => 'Welcome to Soamco Budget',
            'title' => 'Welcome to Soamco Budget',
            'message' => 'Welcome {user.full_name}, thank you for signing up. I hope Soamco Budget will be useful to help you keep your personal budget under control.',
            'cta' => 'Open dashboard',
            'details' => [],
        ],
        'account_invitation' => [
            'topic' => 'Account sharing invitation',
            'subject' => '{inviter_name} invited you to share an account on Soamco Budget',
            'title' => 'You received an invitation',
            'message' => "{inviter_name} invited you to access the account \"{account_name}\" on Soamco Budget.\n\nAssigned access level: {invitation_role_label}\n\nOpen the link below to accept the invitation and complete access to the account.\n\n{invitation_expiry_notice}\n\nIf you were not expecting this email, you can ignore it.",
            'cta' => 'Accept invitation',
            'expiry_notice' => 'This invitation expires on :date.',
        ],
    ],
];
