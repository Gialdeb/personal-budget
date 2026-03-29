<?php

return [
    'title' => 'References',
    'flash' => [
        'created' => 'Reference created successfully.',
        'updated' => 'Reference updated successfully.',
        'activated' => 'Reference activated successfully.',
        'deactivated' => 'Reference deactivated successfully.',
        'deleted' => 'Reference deleted successfully.',
    ],
    'validation' => [
        'activate_parent_first' => 'Activate the parent reference before reactivating this reference.',
        'delete_blocked' => 'This reference cannot be deleted: :reasons. Deactivate it instead to preserve its history.',
    ],
    'blocking_reasons' => [
        'child_one' => 'it has 1 child reference',
        'child_many' => 'it has :count child references',
        'used_one' => 'it is used in 1 :label',
        'used_many' => 'it is used in :count :label',
    ],
    'blocking_labels' => [
        'transactions' => 'transactions',
        'budgets' => 'budgets',
        'recurring_entries' => 'recurring entries',
        'scheduled_entries' => 'scheduled items',
    ],
    'sharedBridge' => [
        'validation' => [
            'required' => 'Select a personal reference to add to the shared account.',
            'unavailable' => 'This personal reference is not available for the selected shared account.',
        ],
        'flash' => [
            'created' => ':name was added to the shared account catalog.',
            'reused' => ':name was already present in the shared account catalog.',
        ],
    ],
];
