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
    'sharing' => [
        'invite_created' => 'Invito inviato correttamente.',
        'invite_accepted' => 'Invito accettato correttamente.',
        'membership_left' => 'Accesso rimosso correttamente.',
        'membership_revoked' => 'Accesso revocato correttamente.',
        'membership_restored' => 'Accesso ripristinato correttamente.',
        'membership_role_updated' => 'Livello di accesso aggiornato correttamente.',
        'unsupported_account' => 'Le carte di credito e la cassa contanti non possono essere condivise.',
    ],
    'validation' => [
        'delete_suffix' => 'Disattivalo invece per conservarne lo storico.',
        'opening_balance_date_required' => 'La data di apertura è obbligatoria quando imposti un saldo iniziale.',
        'opening_balance_date_not_future' => 'La data di apertura non può essere successiva a oggi (:date).',
        'opening_balance_date_after_first_transaction' => 'La prima transazione del conto è del :date. Imposta una data di apertura uguale o precedente.',
        'default_account_must_be_active' => 'Il conto predefinito deve restare attivo.',
        'currency_locked_after_usage' => 'La valuta del conto non può essere modificata dopo la registrazione di movimenti, ricorrenze o altri dati contabili collegati.',
        'credit_card_bank_required' => 'Per una carta di credito devi selezionare una banca.',
        'credit_card_linked_payment_account_required' => 'Per una carta di credito devi selezionare un conto di addebito.',
        'credit_card_statement_closing_day_required' => 'Per una carta di credito devi impostare il giorno di chiusura.',
        'credit_card_payment_day_required' => 'Per una carta di credito devi impostare il giorno di addebito.',
        'protected_cash_account_active_locked' => 'La Cassa contanti di sistema non può essere disattivata.',
        'protected_cash_account_delete_locked' => 'La Cassa contanti di sistema non può essere eliminata.',
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
