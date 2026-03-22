<?php

return [
    'title' => 'Impostazioni',
    'sections' => [
        'profile' => 'Profilo',
        'categories' => 'Categorie di spesa',
        'tracked_items' => 'Elementi da tracciare',
        'banks' => 'Banche',
        'accounts' => 'Conti',
        'years' => 'Anni di gestione',
        'security' => 'Sicurezza',
        'appearance' => 'Aspetto',
    ],
    'years' => [
        'created' => 'Anno :year creato correttamente.',
        'activated' => 'Anno :year impostato come attivo.',
        'closed' => 'Anno :year chiuso correttamente.',
        'reopened' => 'Anno :year riaperto correttamente.',
        'deleted' => 'Anno :year eliminato correttamente.',
        'not_available' => "L'anno :year non è disponibile tra gli anni di gestione.",
        'closed_for_editing' => "L'anno :year è chiuso. Puoi consultare i dati, ma non modificarli finché non lo riapri.",
        'validation' => [
            'delete_blocked' => "L'anno :year non può essere eliminato: :reasons.",
            'required' => "Inserisci l'anno di gestione.",
            'integer' => "L'anno deve essere un numero intero.",
            'between' => 'Inserisci un anno valido tra 1900 e 2200.',
            'unique' => 'Questo anno di gestione è già presente.',
            'future_year_not_allowed' => "Non puoi creare anni futuri. L'anno massimo consentito è :year.",
        ],
        'delete_reasons' => [
            'keep_one' => 'deve rimanere almeno un anno di gestione disponibile',
            'active_current' => "è l'anno attivo corrente",
            'budgets' => 'ha budget collegati',
            'transactions' => 'ha transazioni collegate',
            'scheduled_entries' => 'ha scadenze pianificate collegate',
            'recurring_occurrences' => 'ha occorrenze ricorrenti collegate',
            'recurring_entries' => 'ha ricorrenze attive su questo anno',
        ],
        'suggestions' => [
            'prepare_title' => "Prepara l'anno :year",
            'open_current_year' => "L'anno :year non è ancora aperto nel gestionale. Puoi crearlo ora senza generare dati in automatico.",
            'open_next_year' => "Stai lavorando sull'anno più recente. Puoi aprire ora il :year senza creare nulla in automatico.",
        ],
    ],
    'banks' => [
        'source' => [
            'custom' => 'Personalizzata',
            'catalog' => 'Globale',
        ],
        'flash' => [
            'catalog_created' => 'Banca dal catalogo aggiunta correttamente.',
            'catalog_created_with_account' => "Banca dal catalogo aggiunta con conto base associato pronto all'uso.",
            'custom_created' => 'Banca personalizzata creata correttamente.',
            'custom_created_with_account' => "Banca personalizzata creata con conto base associato pronto all'uso.",
            'updated' => 'Banca personalizzata aggiornata correttamente.',
            'activated' => 'Banca attivata correttamente.',
            'deactivated' => 'Banca disattivata correttamente.',
            'deleted' => 'Banca rimossa correttamente dalle tue banche disponibili.',
        ],
        'validation' => [
            'custom_only' => 'Solo le banche personalizzate possono essere modificate.',
            'delete_blocked' => 'Questa banca non può essere rimossa: :reasons. Disattivala invece per toglierla dalla selezione operativa.',
        ],
        'delete_reasons' => [
            'account_one' => 'è collegata a 1 account',
            'account_many' => 'è collegata a :count account',
        ],
    ],
];
