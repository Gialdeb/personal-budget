<?php

return [
    'title' => 'Tracked items',
    'flash' => [
        'created' => 'Tracked item created successfully.',
        'updated' => 'Tracked item updated successfully.',
        'activated' => 'Tracked item activated successfully.',
        'deactivated' => 'Tracked item deactivated successfully.',
        'deleted' => 'Tracked item deleted successfully.',
    ],
    'validation' => [
        'activate_parent_first' => 'Activate the parent item before reactivating this item.',
        'delete_blocked' => 'This item cannot be deleted: :reasons. Deactivate it instead to preserve its history.',
    ],
    'blocking_reasons' => [
        'child_one' => 'it has 1 child item',
        'child_many' => 'it has :count child items',
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
