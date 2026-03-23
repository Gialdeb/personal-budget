<?php

return [
    'title' => 'Accounts',
    'flash' => [
        'created' => 'Account created successfully.',
        'updated' => 'Account updated successfully.',
        'activated' => 'Account activated successfully.',
        'deactivated' => 'Account deactivated successfully.',
        'deleted' => 'Account deleted successfully.',
    ],
    'validation' => [
        'delete_suffix' => 'Deactivate it instead to preserve its history.',
        'opening_balance_date_required' => 'The opening date is required when setting an opening balance.',
        'opening_balance_date_after_first_transaction' => 'The first account transaction is dated :date. Please choose an opening date on or before that date.',
    ],
    'enums' => [
        'account_type' => [
            'payment_account' => 'Payment account',
            'savings_account' => 'Savings account',
            'business_account' => 'Business account',
            'credit_card' => 'Credit card',
            'investment_account' => 'Investment account',
            'pension_account' => 'Pension account',
            'cash_account' => 'Cash',
            'loan_account' => 'Loan',
        ],
    ],
];
