<?php

return [
    'title' => 'Transactions',
    'closed_year_message' => 'Year :year is closed. You can review transactions, but you cannot modify them until it is reopened.',
    'navigation' => [
        'coverage_available' => 'Coverage available',
        'with_data' => 'With data',
        'records_in_period' => 'Records in period',
        'cumulative_records' => 'Cumulative records',
    ],
    'flash' => [
        'created' => 'Transaction created successfully.',
        'updated' => 'Transaction updated successfully.',
        'deleted' => 'Transaction deleted successfully.',
    ],
    'opening_balance' => [
        'kind_label' => 'Opening',
        'row_label' => 'Opening balance :year',
        'path_label' => 'Opening event',
        'detail' => 'Account opening balance',
        'mutation_locked' => 'Opening balances can only be edited from the related account.',
    ],
    'validation' => [
        'date_invalid' => 'The transaction date must be valid.',
        'account_unavailable' => 'The selected account is not available.',
        'category_unavailable' => 'The selected category is not available.',
    ],
    'enums' => [
        'recurring_transaction_status' => [
            'planned' => 'Planned',
            'due' => 'Due',
            'matched' => 'Matched',
            'skipped' => 'Skipped',
            'cancelled' => 'Cancelled',
            'converted' => 'Converted',
        ],
        'recurring_transaction_occurrence_status' => [
            'planned' => 'Planned',
            'due' => 'Due',
            'matched' => 'Matched',
            'converted' => 'Converted',
            'cancelled' => 'Cancelled',
        ],
        'rule_field' => [
            'bank_description_raw' => 'Original bank description',
            'bank_description_clean' => 'Clean bank description',
            'counterparty_name' => 'Counterparty',
        ],
        'rule_operator' => [
            'contains' => 'Contains',
            'equals' => 'Equals',
            'starts_with' => 'Starts with',
            'ends_with' => 'Ends with',
            'regex' => 'Regular expression',
            'similarity' => 'Similarity',
        ],
        'review_status' => [
            'confirmed' => 'Confirmed',
            'corrected' => 'Corrected',
            'ignored' => 'Ignored',
        ],
        'source_type' => [
            'import' => 'Import',
            'manual' => 'Manual',
            'generated' => 'Generated',
            'adjustment' => 'Adjustment',
        ],
        'kind' => [
            'manual' => 'Manual',
            'opening_balance' => 'Opening',
        ],
        'status' => [
            'draft' => 'Draft',
            'auto_categorized' => 'Automatically categorized',
            'review_needed' => 'Needs review',
            'confirmed' => 'Confirmed',
            'ignored' => 'Ignored',
        ],
    ],
];
