<?php

return [
    'title' => 'Conti',
    'flash' => [
        'created' => 'Conto creato correttamente.',
        'updated' => 'Conto aggiornato correttamente.',
        'activated' => 'Conto attivato correttamente.',
        'deactivated' => 'Conto disattivato correttamente.',
        'deleted' => 'Conto eliminato correttamente.',
    ],
    'validation' => [
        'delete_suffix' => 'Disattivalo invece per conservarne lo storico.',
    ],
    'enums' => [
        'account_type' => [
            'payment_account' => 'Conto di pagamento',
            'savings_account' => 'Conto di risparmio',
            'business_account' => 'Conto commerciale',
            'credit_card' => 'Carta di credito',
            'investment_account' => 'Investimento',
            'pension_account' => 'Previdenza',
            'cash_account' => 'Contanti',
            'loan_account' => 'Prestito',
        ],
    ],
];
