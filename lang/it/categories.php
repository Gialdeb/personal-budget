<?php

return [
    'title' => 'Categorie di spesa',
    'flash' => [
        'created' => 'Categoria creata correttamente.',
        'updated' => 'Categoria aggiornata correttamente.',
        'activated' => 'Categoria attivata correttamente.',
        'deactivated' => 'Categoria disattivata correttamente.',
        'deleted' => 'Categoria eliminata correttamente.',
    ],
    'validation' => [
        'delete_blocked' => 'Questa categoria non può essere eliminata: :reasons.',
        'activate_parent_first' => 'Attiva prima la categoria padre per riattivare questa categoria.',
        'system_locked' => 'Le categorie foundation di sistema non possono essere eliminate.',
        'system_name_locked' => 'Il nome delle categorie foundation di sistema non può essere modificato.',
        'system_active_locked' => 'Le categorie foundation di sistema devono restare sempre attive.',
    ],
    'blocking_reasons' => [
        'child_one' => 'ha una categoria figlia',
        'child_many' => 'ha :count categorie figlie',
        'used_one' => 'è usata in 1 :label',
        'used_many' => 'è usata in :count :label',
    ],
    'blocking_labels' => [
        'transactions' => 'transazioni',
        'transaction_splits' => 'split di transazioni',
        'transaction_matchers' => 'regole di categorizzazione',
        'transaction_training_samples' => 'campioni di training',
        'recurring_entries' => 'ricorrenze',
        'scheduled_entries' => 'scadenze pianificate',
        'default_merchants' => 'merchant predefiniti',
        'old_transaction_reviews' => 'revisioni transazioni precedenti',
        'new_transaction_reviews' => 'revisioni transazioni nuove',
        'budgets' => 'budget',
    ],
];
