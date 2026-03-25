<?php

return [
    'automation' => [
        'flash' => [
            'dispatched' => 'Automation pipeline dispatched successfully.',
            'retried' => 'Automation pipeline retry dispatched successfully.',
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
            'monthly_report_ready_mail' => [
                'name' => 'Monthly report ready email',
                'description' => 'Customizable email template for monthly report availability.',
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
            'reports.weekly_ready' => [
                'name' => 'Report ready',
                'description' => 'Communication sent when the personal report is available for the selected user.',
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
        'validation' => [
            'admin_target_forbidden' => 'This action cannot be performed on an admin user.',
            'roles_required' => 'Select at least one valid role.',
        ],
    ],
];
