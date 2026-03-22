<?php

return [
    'title' => 'Elementi da tracciare',
    'flash' => [
        'created' => 'Elemento da tracciare creato correttamente.',
        'updated' => 'Elemento da tracciare aggiornato correttamente.',
        'activated' => 'Elemento da tracciare attivato correttamente.',
        'deactivated' => 'Elemento da tracciare disattivato correttamente.',
        'deleted' => 'Elemento da tracciare eliminato correttamente.',
    ],
    'validation' => [
        'activate_parent_first' => "Attiva prima l'elemento padre per riattivare questo elemento.",
        'delete_blocked' => 'Questo elemento non può essere eliminato: :reasons. Disattivalo invece per conservarne lo storico.',
    ],
    'blocking_reasons' => [
        'child_one' => 'ha un elemento figlio',
        'child_many' => 'ha :count elementi figli',
        'used_one' => 'è usato in 1 :label',
        'used_many' => 'è usato in :count :label',
    ],
    'blocking_labels' => [
        'transactions' => 'transazioni',
        'budgets' => 'budget',
        'recurring_entries' => 'ricorrenze',
        'scheduled_entries' => 'scadenze pianificate',
    ],
];
