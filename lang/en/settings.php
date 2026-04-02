<?php

return [
    'title' => 'Settings',
    'sections' => [
        'profile' => 'Profile',
        'categories' => 'Expense categories',
        'tracked_items' => 'References',
        'banks' => 'Banks',
        'accounts' => 'Accounts',
        'years' => 'Management years',
        'security' => 'Security',
        'exports' => 'Exports',
        'appearance' => 'Appearance',
    ],
    'years' => [
        'created' => 'Year :year created successfully.',
        'activated' => 'Year :year set as active.',
        'closed' => 'Year :year closed successfully.',
        'reopened' => 'Year :year reopened successfully.',
        'deleted' => 'Year :year deleted successfully.',
        'not_available' => 'Year :year is not available among your management years.',
        'closed_for_editing' => 'Year :year is closed. You can view the data, but you cannot modify it until you reopen it.',
        'validation' => [
            'delete_blocked' => 'Year :year cannot be deleted: :reasons.',
            'required' => 'Enter the management year.',
            'integer' => 'The year must be an integer.',
            'between' => 'Enter a valid year between 1900 and 2200.',
            'unique' => 'This management year already exists.',
            'future_year_not_allowed' => 'You cannot create future years. The highest allowed year is :year.',
        ],
        'delete_reasons' => [
            'keep_one' => 'at least one management year must remain available',
            'active_current' => 'it is the current active year',
            'budgets' => 'it has linked budgets',
            'transactions' => 'it has linked transactions',
            'scheduled_entries' => 'it has linked scheduled entries',
            'recurring_occurrences' => 'it has linked recurring occurrences',
            'recurring_entries' => 'it has active recurring entries in this year',
        ],
        'suggestions' => [
            'prepare_title' => 'Prepare year :year',
            'open_current_year' => 'Year :year is not open yet in the app. You can create it now without generating data automatically.',
            'open_next_year' => 'You are working on the most recent year. You can open :year now without creating anything automatically.',
        ],
    ],
    'banks' => [
        'source' => [
            'custom' => 'Custom',
            'catalog' => 'Global',
        ],
        'flash' => [
            'catalog_created' => 'Catalog bank added successfully.',
            'catalog_created_with_account' => 'Catalog bank added with a linked base account ready to use.',
            'custom_created' => 'Custom bank created successfully.',
            'custom_created_with_account' => 'Custom bank created with a linked base account ready to use.',
            'updated' => 'Custom bank updated successfully.',
            'activated' => 'Bank activated successfully.',
            'deactivated' => 'Bank deactivated successfully.',
            'deleted' => 'Bank removed successfully from your available banks.',
        ],
        'validation' => [
            'custom_only' => 'Only custom banks can be edited.',
            'delete_blocked' => 'This bank cannot be removed: :reasons. Deactivate it instead to remove it from operational selection.',
        ],
        'delete_reasons' => [
            'account_one' => 'it is linked to 1 account',
            'account_many' => 'it is linked to :count accounts',
        ],
    ],
    'enums' => [
        'user_status' => [
            'active' => 'Active',
            'suspended' => 'Suspended',
            'banned' => 'Banned',
        ],
        'subscription_status' => [
            'active' => 'Active',
            'inactive' => 'Inactive',
            'past_due' => 'Past due',
            'canceled' => 'Canceled',
            'trialing' => 'Trialing',
        ],
    ],
    'profile' => [
        'impersonation_consent_updated' => 'Assisted access preference updated successfully.',
        'currency_updated' => 'Currency updated successfully.',
        'notification_preferences_updated' => 'Notification preferences updated successfully.',
        'active_sessions' => [
            'title' => 'Active sessions',
            'description' => 'Review the devices that can access your account and sign out the ones you no longer recognize.',
            'current_badge' => 'Current session',
            'current_helper' => 'This is the session you are using right now.',
            'fields' => [
                'ip_address' => 'IP address',
                'device' => 'Device and browser',
                'last_activity' => 'Last activity',
            ],
            'actions' => [
                'revoke' => 'Sign out',
                'revoke_others' => 'Sign out all other sessions',
                'cancel' => 'Cancel',
                'confirm_single' => 'Confirm sign out',
                'confirm_others' => 'Confirm global sign out',
            ],
            'confirmations' => [
                'single_title' => 'Sign out this session?',
                'single_description' => 'The selected session will be closed immediately on that device.',
                'others_title' => 'Sign out all other sessions?',
                'others_description' => 'All other devices will be signed out. Your current session will stay active.',
            ],
            'empty' => [
                'title' => 'No other active sessions',
                'description' => 'Your account is currently open only in the current session.',
            ],
            'flash' => [
                'single_revoked' => 'Session signed out successfully.',
                'others_revoked' => ':count sessions signed out successfully.',
            ],
            'validation' => [
                'current_session' => 'The current session cannot be signed out from this action.',
            ],
        ],
        'currency_locked_after_transactions' => 'The currency can no longer be changed after the first transactions have been recorded.',
        'currency_locked_after_accounts_or_transactions' => 'The base currency cannot be changed after accounts or transactions have been created.',
        'notifications' => [
            'categories' => [
                'credit_cards' => [
                    'autopay_completed' => [
                        'label' => 'Credit card charge completed',
                        'description' => 'Notify you when a credit card billing cycle is charged automatically on the linked account.',
                    ],
                ],
                'imports' => [
                    'completed' => [
                        'label' => 'Import completed',
                        'description' => 'Notify you when an import finishes and the data is ready to review.',
                    ],
                ],
                'reports' => [
                    'weekly_ready' => [
                        'label' => 'Report available',
                        'description' => 'Notify you when a report is available in your notifications.',
                    ],
                ],
            ],
            'validation' => [
                'required' => 'Submit at least one valid notification preference set.',
                'invalid_topic' => 'One of the selected notifications is not configurable for this profile.',
                'invalid_value' => 'One of the notification preference values is invalid.',
            ],
        ],
    ],
];
