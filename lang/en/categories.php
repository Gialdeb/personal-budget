<?php

return [
    'title' => 'Expense categories',
    'flash' => [
        'created' => 'Category created successfully.',
        'updated' => 'Category updated successfully.',
        'activated' => 'Category activated successfully.',
        'deactivated' => 'Category deactivated successfully.',
        'deleted' => 'Category deleted successfully.',
    ],
    'sharedPage' => [
        'materialize' => [
            'flash' => [
                'created' => ':name was added to the shared account catalog.',
                'reused' => ':name was already available in the shared account catalog.',
            ],
            'validation' => [
                'required' => 'Select a personal category to add.',
                'unavailable' => 'The selected personal category is not available for this shared account.',
            ],
        ],
    ],
    'validation' => [
        'delete_blocked' => 'This category cannot be deleted: :reasons.',
        'activate_parent_first' => 'Activate the parent category before reactivating this category.',
        'system_locked' => 'System foundation categories cannot be deleted.',
        'system_name_locked' => 'System foundation category names cannot be changed.',
        'technical_system_locked' => 'System technical categories used for transfers and settlements cannot be changed from the standard UI.',
        'system_active_locked' => 'System foundation categories must always stay active.',
        'system_classification_locked' => 'System foundation category direction and group cannot be changed.',
        'system_parent_locked' => 'System foundation categories cannot be moved.',
        'max_depth' => 'Personal category hierarchies can contain at most three levels.',
    ],
    'blocking_reasons' => [
        'child_one' => 'it has 1 child category',
        'child_many' => 'it has :count child categories',
        'used_one' => 'it is used in 1 :label',
        'used_many' => 'it is used in :count :label',
    ],
    'blocking_labels' => [
        'transactions' => 'transactions',
        'transaction_splits' => 'transaction splits',
        'transaction_matchers' => 'categorization rules',
        'transaction_training_samples' => 'training samples',
        'recurring_entries' => 'recurrences',
        'scheduled_entries' => 'scheduled items',
        'default_merchants' => 'default merchants',
        'old_transaction_reviews' => 'previous transaction reviews',
        'new_transaction_reviews' => 'new transaction reviews',
        'budgets' => 'budgets',
    ],
];
