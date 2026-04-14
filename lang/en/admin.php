<?php

return [
    'automation' => [
        'flash' => [
            'dispatched' => 'Automation pipeline dispatched successfully.',
            'retried' => 'Automation pipeline retry dispatched successfully.',
        ],
        'creditCardAutopay' => [
            'partialFailure' => 'The credit card autopay check completed but reported real errors on one or more cards.',
        ],
        'recurringSummaries' => [
            'partialFailure' => 'One or more recurring summary deliveries did not complete successfully.',
        ],
    ],
    'changelog' => [
        'flash' => [
            'saved' => 'Changelog release saved successfully.',
        ],
        'validation' => [
            'versionTaken' => 'This changelog version already exists.',
        ],
    ],
    'knowledge' => [
        'validation' => [
            'duplicate_locale' => 'Each locale may only appear once.',
            'missing_locale' => 'The required :locale translation is missing.',
        ],
    ],
    'knowledge_sections' => [
        'flash' => [
            'saved' => 'Knowledge base section saved successfully.',
            'deleted' => 'Knowledge base section deleted successfully.',
        ],
    ],
    'knowledge_articles' => [
        'flash' => [
            'saved' => 'Knowledge base article saved successfully.',
            'deleted' => 'Knowledge base article deleted successfully.',
        ],
    ],
    'contextual_help' => [
        'flash' => [
            'saved' => 'Contextual help entry saved successfully.',
        ],
    ],
    'support_requests' => [
        'flash' => [
            'status_updated' => 'Support request status updated successfully.',
        ],
    ],
    'communication_templates' => [
        'flash' => [
            'override_saved' => 'Global override saved successfully.',
            'override_disabled' => 'Global override disabled successfully.',
        ],
        'channels' => [
            'mail' => 'Email',
            'database' => 'In-app',
            'sms' => 'SMS',
        ],
        'modes' => [
            'system' => 'System',
            'customizable' => 'Customizable',
            'freeform' => 'Freeform',
        ],
        'templates' => [
            'automation_failed_mail' => [
                'name' => 'Automation failed email',
                'description' => 'System email template for automation failure alerts.',
            ],
            'import_completed_mail' => [
                'name' => 'Import completed email',
                'description' => 'Customizable email template for completed imports.',
            ],
            'recurring_weekly_due_summary_mail' => [
                'name' => 'Weekly due summary email',
                'description' => 'System email template for the weekly recurring summary.',
            ],
            'recurring_monthly_due_summary_mail' => [
                'name' => 'Start-of-month due summary email',
                'description' => 'System email template for the start-of-month recurring summary.',
            ],
            'auth_verify_email_mail' => [
                'name' => 'Verify email',
                'description' => 'Mandatory system template for email verification.',
            ],
            'auth_reset_password_mail' => [
                'name' => 'Reset password',
                'description' => 'Mandatory system template for password reset.',
            ],
            'admin_freeform_mail' => [
                'name' => 'Admin freeform email',
                'description' => 'Freeform base template for future admin custom emails.',
            ],
        ],
        'validation' => [
            'is_active_required' => 'Select whether the override should be active.',
            'subject_too_long' => 'The subject template may not exceed 255 characters.',
            'title_too_long' => 'The title template may not exceed 255 characters.',
            'cta_label_too_long' => 'The CTA label template may not exceed 255 characters.',
            'cta_url_too_long' => 'The CTA URL template may not exceed 2048 characters.',
        ],
    ],
    'communication_composer' => [
        'flash' => [
            'sent' => 'Manual communication queued successfully.',
        ],
        'channels' => [
            'mail' => 'Email',
            'database' => 'Notifications',
            'sms' => 'SMS',
            'telegram' => 'Telegram',
        ],
        'locales' => [
            'recipient' => 'Recipient language',
        ],
        'content_modes' => [
            'template' => 'Category template',
            'custom' => 'Custom content',
        ],
        'categories' => [
            'auth.verify_email' => [
                'name' => 'Verify email',
                'description' => 'Email verification communication for the selected user.',
            ],
            'auth.reset_password' => [
                'name' => 'Reset password',
                'description' => 'Password reset communication for the selected user.',
            ],
            'user.welcome_after_verification' => [
                'name' => 'Welcome after verification',
                'description' => 'Welcome communication sent when the selected user account is already active and verified.',
            ],
        ],
        'validation' => [
            'category_required' => 'Select a category.',
            'category_invalid' => 'The selected category is not available for manual sending.',
            'channel_required' => 'Select a channel.',
            'channels_required' => 'Select at least one channel.',
            'channel_invalid' => 'The selected channel is not available for this category.',
            'recipient_required' => 'Select a recipient.',
            'recipients_required' => 'Select at least one recipient.',
            'recipient_invalid' => 'The selected recipient is invalid.',
            'locale_required' => 'Select a language.',
            'locale_invalid' => 'The selected language is not valid.',
            'content_mode_required' => 'Select a content mode.',
            'content_mode_invalid' => 'The selected content mode is not valid.',
            'custom_body_required' => 'Write the message body for custom content.',
        ],
    ],
    'communication_outbound' => [
        'channels' => [
            'mail' => 'Email',
            'database' => 'Notifications',
            'sms' => 'SMS',
            'telegram' => 'Telegram',
        ],
        'statuses' => [
            'queued' => 'Queued',
            'sent' => 'Sent',
            'failed' => 'Failed',
            'skipped' => 'Skipped',
        ],
        'empty' => [
            'noValue' => 'No value',
        ],
    ],
    'communication_categories' => [
        'flash' => [
            'channels_saved' => 'Category channel configuration saved successfully.',
        ],
        'validation' => [
            'category_invalid' => 'The selected category is invalid.',
            'channels_required' => 'Configure at least one channel.',
            'channel_invalid' => 'One of the selected channels is not supported.',
            'template_required' => 'Select a template when a channel is enabled.',
            'template_invalid' => 'The selected template is invalid for this channel.',
            'channel_globally_unavailable' => 'The :channel channel is not globally available.',
        ],
    ],
    'users' => [
        'filters' => [
            'roles' => [
                'all' => 'All roles',
                'admin' => 'Admin',
                'staff' => 'Staff',
                'user' => 'User',
            ],
            'statuses' => [
                'all' => 'All statuses',
            ],
            'plans' => [
                'all' => 'All plans',
                'free' => 'Free',
            ],
        ],
        'flash' => [
            'banned' => 'User banned successfully.',
            'suspended' => 'User suspended successfully.',
            'reactivated' => 'User reactivated successfully.',
            'roles_updated' => 'User roles updated successfully.',
        ],
        'support' => [
            'states' => [
                'never_donated' => 'Never donated',
                'support_recent' => 'Recent support',
                'reminder_due' => 'Reminder due',
                'support_lapsed' => 'Support lapsed',
            ],
            'labels' => [
                'lastContribution' => 'Last contribution',
                'nextReminder' => 'Next reminder',
                'noContribution' => 'No contribution yet',
            ],
        ],
        'table' => [
            'support' => 'Support',
        ],
        'actions' => [
            'support' => 'Support',
        ],
        'billing' => [
            'title' => 'Support and billing',
            'description' => 'Review support history, reminders, and manual donation operations for :user.',
            'flash' => [
                'transaction_saved' => 'Donation recorded successfully.',
                'transaction_updated' => 'Donation updated successfully.',
                'transaction_assigned' => 'Donation assigned successfully.',
                'subscription_deleted' => 'Subscription deleted successfully.',
                'support_updated' => 'Support window updated successfully.',
            ],
            'summary' => [
                'accessPlan' => 'Access plan',
                'supportState' => 'Support state',
                'lastContribution' => 'Last contribution',
                'nextReminder' => 'Next reminder',
            ],
            'sections' => [
                'history' => 'Donation history',
                'supportWindow' => 'Support window',
                'manualDonation' => 'Register donation',
                'editTransaction' => 'Edit donation',
                'assignTransaction' => 'Assign pending donation',
            ],
            'sectionDescriptions' => [
                'history' => 'Economic history stays separate from access control and remains fully auditable.',
                'supportWindow' => 'Support status is informative only and never blocks the free plan.',
                'manualDonation' => 'Register a manual, Ko-fi, or future provider donation without needing checkout logic.',
                'editTransaction' => 'Correct provider details, timing, or notes for an existing donation.',
                'assignTransaction' => 'Associate unreconciled donations to this user when needed.',
            ],
            'actions' => [
                'backToUsers' => 'Back to users',
                'saveSupport' => 'Save support state',
                'saveDonation' => 'Register donation',
                'editTransaction' => 'Edit',
                'updateTransaction' => 'Update donation',
                'assignTransaction' => 'Assign',
            ],
            'fields' => [
                'supportStatus' => 'Support status',
                'plan' => 'Billing plan',
                'supportStartedAt' => 'Support started at',
                'supportEndsAt' => 'Support review/window end',
                'nextReminderAt' => 'Next reminder at',
                'adminNotes' => 'Admin notes',
                'isSupporter' => 'Mark user as supporter for badges/reminders',
                'provider' => 'Provider',
                'amount' => 'Amount',
                'currency' => 'Currency',
                'paidAt' => 'Paid at',
                'receivedAt' => 'Received at',
                'isRecurring' => 'Recurring donation',
                'applySupportWindow' => 'Refresh support window from this donation',
            ],
            'supportStatuses' => [
                'free' => 'Free',
                'supporting' => 'Supporting',
                'inactive' => 'Inactive',
            ],
            'table' => [
                'provider' => 'Provider',
                'amount' => 'Amount',
                'status' => 'Status',
                'paidAt' => 'Paid at',
            ],
            'empty' => [
                'noValue' => 'No value',
                'history' => 'No donations recorded yet.',
                'selectTransaction' => 'Choose a transaction from the history to edit it.',
                'assignableTransactions' => 'No pending donations available for reassignment.',
            ],
        ],
        'validation' => [
            'admin_target_forbidden' => 'This action cannot be performed on an admin user.',
            'roles_required' => 'Select at least one valid role.',
        ],
    ],
];
