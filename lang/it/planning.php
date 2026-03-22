<?php

return [
    'title' => 'Preventivazione',
    'sections' => [
        'income' => 'Entrate previste',
        'expense' => 'Budget delle spese',
        'bill' => 'Budget delle bollette',
        'debt' => 'Obiettivo di pagamento dei debiti',
        'saving' => 'Obiettivo di risparmio',
        'tax' => 'Pianificazione fiscale',
        'investment' => 'Allocazione investimenti',
        'other' => 'Categorie disponibili',
    ],
    'save' => [
        'saving' => 'Salvataggio in corso',
        'check_errors' => 'Controlla le celle in errore',
        'saved' => 'Tutte le modifiche sono salvate',
        'autosave' => 'Autosave attivo',
    ],
    'closed_year' => [
        'title' => 'Anno chiuso',
        'fallback' => "L'anno selezionato è chiuso e non può essere modificato.",
    ],
    'validation' => [
        'leaf_only' => 'Puoi pianificare il budget solo sulle categorie foglia selezionabili.',
        'copy_source_empty' => 'Non ci sono valori pianificati nel :year da copiare.',
    ],
    'enums' => [
        'budget_goal_type' => [
            'target' => 'Target',
            'limit' => 'Limit',
            'forecast' => 'Forecast',
        ],
    ],
];
