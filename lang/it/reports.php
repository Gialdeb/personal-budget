<?php

return [
    'filters' => [
        'allResources' => 'Tutte le risorse',
        'periods' => [
            'annual' => 'Annuale',
            'monthly' => 'Mensile',
            'lastThreeMonths' => 'Ultimi 3 mesi',
            'lastSixMonths' => 'Ultimi 6 mesi',
            'ytd' => 'Da inizio anno (YTD)',
        ],
        'periodSummaries' => [
            'annual' => 'Anno :year',
            'lastThreeMonths' => 'Ultimi 3 mesi fino a :month :year',
            'lastSixMonths' => 'Ultimi 6 mesi fino a :month :year',
            'ytd' => 'Da inizio anno fino a :month :year',
        ],
    ],
    'overview' => [
        'kpis' => [
            'averagePerDay' => 'Media per giorno',
            'averagePerMonth' => 'Media per mese',
        ],
        'meta' => [
            'coverageNote' => ':count movimenti sono esclusi dai totali monetari perche privi di conversione affidabile in valuta base.',
        ],
    ],
    'categories' => [
        'filters' => [
            'focuses' => [
                'all' => 'Tutte',
                'income' => 'Entrate',
                'expense' => 'Uscite',
                'saving' => 'Risparmi',
            ],
        ],
        'recent' => [
            'fallbackDescription' => 'Movimento senza descrizione',
        ],
    ],
    'accounts' => [
        'allAccounts' => 'Tutti i conti',
        'movement' => 'Movimento',
        'uncategorized' => 'Senza categoria',
        'types' => [
            'current' => 'Conto corrente',
            'cash' => 'Contanti',
            'credit_card' => 'Carta di credito',
        ],
    ],
];
