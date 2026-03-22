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
    'validation' => [
        'delete_blocked' => 'This category cannot be deleted: :reasons.',
        'activate_parent_first' => 'Activate the parent category before reactivating this category.',
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
