<?php

return [
    'title' => 'Transazioni',
    'closed_year_message' => "L'anno :year è chiuso. Puoi consultare le transazioni, ma non modificarle finché non viene riaperto.",
    'navigation' => [
        'coverage_available' => 'Copertura presente',
        'with_data' => 'Con dati',
        'records_in_period' => 'Registrazioni nel periodo',
        'cumulative_records' => 'Registrazioni cumulative',
    ],
    'flash' => [
        'created' => 'Transazione creata correttamente.',
        'updated' => 'Transazione aggiornata correttamente.',
        'deleted' => 'Transazione eliminata correttamente.',
    ],
    'opening_balance' => [
        'kind_label' => 'Apertura',
        'row_label' => 'Apertura contabile :year',
        'path_label' => 'Evento di apertura',
        'detail' => 'Saldo iniziale del conto',
        'mutation_locked' => "L'apertura contabile può essere modificata solo dal conto associato.",
    ],
    'validation' => [
        'date_invalid' => 'La data movimento deve essere valida.',
        'account_unavailable' => 'Il conto selezionato non è disponibile.',
        'category_unavailable' => 'La categoria selezionata non è disponibile.',
    ],
    'enums' => [
        'recurring_transaction_status' => [
            'planned' => 'Pianificata',
            'due' => 'In scadenza',
            'matched' => 'Abbinata',
            'skipped' => 'Saltata',
            'cancelled' => 'Annullata',
            'converted' => 'Convertita',
        ],
        'recurring_transaction_occurrence_status' => [
            'planned' => 'Pianificata',
            'due' => 'In scadenza',
            'matched' => 'Abbinata',
            'converted' => 'Convertita',
            'cancelled' => 'Annullata',
        ],
        'rule_field' => [
            'bank_description_raw' => 'Descrizione banca originale',
            'bank_description_clean' => 'Descrizione banca pulita',
            'counterparty_name' => 'Controparte',
        ],
        'rule_operator' => [
            'contains' => 'Contiene',
            'equals' => 'Uguale',
            'starts_with' => 'Inizia con',
            'ends_with' => 'Finisce con',
            'regex' => 'Espressione regolare',
            'similarity' => 'Somiglianza',
        ],
        'review_status' => [
            'confirmed' => 'Confermata',
            'corrected' => 'Corretta',
            'ignored' => 'Ignorata',
        ],
        'source_type' => [
            'import' => 'Importazione',
            'manual' => 'Manuale',
            'generated' => 'Generata',
            'adjustment' => 'Rettifica',
        ],
        'kind' => [
            'manual' => 'Manuale',
            'opening_balance' => 'Apertura',
        ],
        'status' => [
            'draft' => 'Bozza',
            'auto_categorized' => 'Categorizzata automaticamente',
            'review_needed' => 'Da revisionare',
            'confirmed' => 'Confermata',
            'ignored' => 'Ignorata',
        ],
    ],
];
