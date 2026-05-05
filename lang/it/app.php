<?php

return [
    'name' => 'Personal Balance',
    'common' => [
        'none_recorded' => 'Nessuna registrazione',
        'not_available' => 'Non disponibile',
        'all_groups' => 'Tutti i gruppi',
        'uncategorized' => 'Senza categoria',
        'unknown_account' => 'Conto sconosciuto',
        'category' => 'Categoria',
        'current_year' => 'Anno :year',
    ],
    'language' => [
        'label' => 'Lingua',
        'options' => [
            'it' => 'Italiano',
            'en' => 'English',
        ],
    ],
    'user_menu' => [
        'settings' => 'Impostazioni',
        'logout' => 'Esci',
        'language_saving' => 'Salvataggio lingua in corso...',
    ],
    'appearance' => [
        'light' => 'Chiaro',
        'dark' => 'Scuro',
        'system' => 'Sistema',
    ],
    'periods' => [
        'all' => 'Tutto',
        'months' => [
            'short' => [
                1 => 'Gen',
                2 => 'Feb',
                3 => 'Mar',
                4 => 'Apr',
                5 => 'Mag',
                6 => 'Giu',
                7 => 'Lug',
                8 => 'Ago',
                9 => 'Set',
                10 => 'Ott',
                11 => 'Nov',
                12 => 'Dic',
            ],
        ],
    ],
    'enums' => [
        'category_groups' => [
            'income' => 'Entrate',
            'expense' => 'Spese',
            'bill' => 'Bollette',
            'debt' => 'Debiti',
            'saving' => 'Risparmi',
            'tax' => 'Tasse',
            'investment' => 'Investimenti',
            'transfer' => 'Giroconti',
            'remaining' => 'Da allocare',
            'other' => 'Altre categorie',
        ],
        'category_directions' => [
            'income' => 'Entrata',
            'expense' => 'Spesa',
            'transfer' => 'Trasferimento',
            'mixed' => 'Misto',
        ],
        'transaction_directions' => [
            'income' => 'Entrata',
            'expense' => 'Spesa',
            'transfer' => 'Trasferimento',
        ],
        'balance_sources' => [
            'manual' => 'Manuale',
            'import' => 'Importazione',
            'system' => 'Sistema',
        ],
    ],
];
