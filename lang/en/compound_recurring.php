<?php

return [
    'name_invalid' => 'Enter a valid component name of up to 100 characters.',
    'edit_protected' => 'This component has protected payments or economic links. Its name, schedule and reference cannot be changed.',
    'archived' => 'Component removed from the plan. History is preserved.',
    'read_only' => 'This component is archived. Its history is read-only.',
    'account_unavailable' => 'Select an editable account with a configured currency.',
    'category_unavailable' => 'Select a valid category.',
    'duplicate_component' => 'This payment name is already present in the plan.',
    'protected_component' => 'This component cannot be deleted or moved to another account, currency or plan type. Manage individual occurrences from its details.',
    'date_required' => 'Enter the first due date.',
    'date_invalid' => 'Enter a valid date using your selected date format.',
    'name_required' => 'Enter a payment name.',
    'amount_required' => 'Enter the expected payment amount.',
    'amount_invalid' => 'Enter an amount greater than zero with at most two decimal places.',
    'fields' => [
        'name' => 'plan name', 'account' => 'account', 'category' => 'category',
        'direction' => 'direction', 'components' => 'payments', 'component' => 'payment',
        'component_name' => 'payment name', 'date' => 'due date', 'amount' => 'expected amount',
        'reminders' => 'advance reminders', 'reminder' => 'advance reminder',
    ],
];
