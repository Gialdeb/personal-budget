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
];
