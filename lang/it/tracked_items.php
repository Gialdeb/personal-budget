<?php

return [
    'title' => 'Riferimenti',
    'flash' => [
        'created' => 'Riferimento creato correttamente.',
        'updated' => 'Riferimento aggiornato correttamente.',
        'activated' => 'Riferimento attivato correttamente.',
        'deactivated' => 'Riferimento disattivato correttamente.',
        'deleted' => 'Riferimento eliminato correttamente.',
    ],
    'validation' => [
        'activate_parent_first' => 'Attiva prima il riferimento padre per riattivare questo riferimento.',
        'delete_blocked' => 'Questo riferimento non può essere eliminato: :reasons. Disattivalo invece per conservarne lo storico.',
    ],
    'blocking_reasons' => [
        'child_one' => 'ha un riferimento figlio',
        'child_many' => 'ha :count riferimenti figli',
        'used_one' => 'è usato in 1 :label',
        'used_many' => 'è usato in :count :label',
    ],
    'blocking_labels' => [
        'transactions' => 'transazioni',
        'budgets' => 'budget',
        'recurring_entries' => 'ricorrenze',
        'scheduled_entries' => 'scadenze pianificate',
    ],
    'sharedBridge' => [
        'validation' => [
            'required' => 'Seleziona un riferimento personale da aggiungere al conto condiviso.',
            'unavailable' => 'Questo riferimento personale non è disponibile per il conto condiviso selezionato.',
        ],
        'flash' => [
            'created' => ':name è stato aggiunto al catalogo del conto condiviso.',
            'reused' => ':name era già presente nel catalogo del conto condiviso.',
        ],
    ],
];
