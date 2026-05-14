<?php

return [
    'validation' => [
        'type_required' => 'Select credit or debt.',
        'type_invalid' => 'The selected type is not valid.',
        'description_required' => 'Enter a description.',
        'amount_required' => 'Enter an amount.',
        'amount_numeric' => 'Enter a valid amount.',
        'amount_gt_zero' => 'Enter an amount greater than zero.',
        'currency_required' => 'Currency is required.',
        'currency_invalid' => 'The selected currency is not available.',
        'account_required' => 'Select an account.',
        'account_unavailable' => 'The selected account is not available.',
        'account_currency_mismatch' => 'The account currency must match the credit/debt currency.',
        'category_required' => 'Select a category.',
        'category_unavailable' => 'The selected category is not available for this account.',
        'reference_unavailable' => 'The selected reference is not available for this account and category.',
        'due_date_required' => 'Enter a due date.',
        'paid_at_required' => 'Enter the payment date.',
        'date_invalid' => 'Enter a valid date.',
        'payment_exceeds_remaining' => 'The entered amount is greater than the remaining amount.',
        'locked_with_payments' => 'You cannot change this field while linked payments exist.',
        'total_locked_with_payments' => 'You cannot change the total amount while linked payments exist. Delete the individual payments first.',
        'delete_item_with_payments' => 'This credit/debt cannot be deleted because :count linked payments exist. Delete the payments first, starting from the latest one.',
        'delete_latest_payment_required' => 'You cannot delete this payment because later payments exist. Delete the latest registered payment first.',
        'payment_transaction_mismatch' => 'The transaction linked to this payment is not valid.',
    ],
];
