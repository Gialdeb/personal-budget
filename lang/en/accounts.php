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
