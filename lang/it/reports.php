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
    'categoryAnalysis' => [
        'category' => 'Categoria',
        'subcategory' => 'Sottocategoria',
        'allSubcategories' => 'Tutte le sottocategorie',
        'fallbackCategory' => 'Categoria selezionata',
        'budget' => [
            'label' => 'Budget',
            'seriesName' => 'Budget',
            'variance' => 'Scostamento budget',
        ],
        'scope' => [
            'summaryLabel' => 'Base di lettura',
            'summary' => ':scope. :actual :budget :comparison',
            'selectedOnly' => ':category selezionata',
            'selectedWithDescendants' => ':category e sue eventuali categorie discendenti',
            'categoryWithDescendants' => ':category con tutte le sottocategorie coerenti',
            'none' => 'Nessuna categoria selezionabile',
            'actualNone' => 'La spesa reale resta vuota finché non è disponibile una categoria di spesa.',
            'actualLedger' => 'Include solo movimenti confermati del ledger assegnati a :category o a categorie figlie incluse nel perimetro.',
            'budgetDirect' => 'Confronto sul budget mensile assegnato a :category, senza risorse o riferimenti personali.',
            'budgetAggregated' => 'Budget aggregato: somma i budget mensili configurati su :category e sulle sue sottocategorie incluse.',
            'budgetMissing' => 'Nessun budget confrontabile trovato per categoria, periodo e perimetro attivo.',
            'budgetAccountUnsupported' => 'Il budget non viene confrontato quando il filtro risorsa limita il perimetro.',
            'comparisonPreviousYear' => 'Il confronto usa lo stesso intervallo dell’anno precedente e la stessa selezione categoria.',
            'comparisonUnavailable' => 'Il confronto anno precedente resta nascosto quando non esiste una base storica coerente.',
        ],
        'comparisons' => [
            'unavailable' => 'Confronto non disponibile: non ci sono dati sufficienti nel periodo di riferimento.',
            'unavailableShort' => 'Non disponibile',
        ],
        'emptyDataset' => [
            'title' => 'Nessuna spesa nel perimetro selezionato',
            'message' => 'Nel periodo :period non ci sono movimenti reali per :scope. I grafici basati sulla spesa restano vuoti finché non esiste una base ledger confermata.',
            'budgetNote' => 'Il budget, quando presente, resta visibile come pianificazione assegnata e non come spesa effettuata.',
        ],
        'insight' => [
            'overBudgetTitle' => 'Attenzione al budget',
            'inLineTitle' => 'Categoria in linea',
            'spendingUpTitle' => 'Spesa in aumento',
            'spendingDownTitle' => 'Spesa sotto controllo',
            'noComparisonTitle' => 'Confronto limitato',
            'noSpendTitle' => 'Nessuna spesa reale',
            'noSpendMessage' => 'Non ci sono movimenti confermati nel perimetro selezionato. I confronti vengono sospesi per evitare letture fuorvianti.',
            'noSpendWithBudgetMessage' => 'Non ci sono movimenti confermati nel perimetro selezionato. Il budget assegnato è :budget e resta una soglia pianificata.',
            'overBudgetStatus' => 'Sopra budget',
            'underBudgetStatus' => 'Sotto budget',
            'budgetMessage' => ':status di :variance rispetto al budget :budget. Vs anno precedente: :year_delta (:year_percentage). :top pesa :share della categoria.',
            'previousYearMessage' => 'Variazione :delta (:percentage) rispetto all’anno precedente. :top pesa :share della categoria.',
            'noComparisonMessage' => 'Non ci sono dati sufficienti nell’anno precedente o nel budget per costruire un confronto affidabile.',
        ],
        'kpis' => [
            'totalSpent' => 'Totale speso',
            'averagePeriod' => 'Media periodo',
            'averagePerDay' => 'Media giornaliera',
            'averagePerMonth' => 'Media mensile',
            'bestMonth' => 'Miglior periodo',
            'worstMonth' => 'Peggior periodo',
            'previousPeriod' => 'vs periodo precedente',
            'previousYear' => 'vs anno precedente',
        ],
        'charts' => [
            'breakdownTitle' => 'Breakdown sottocategorie',
        ],
        'table' => [
            'title' => 'Dettaglio periodo',
            'period' => 'Periodo',
            'spent' => 'Speso',
            'previousYear' => 'Anno precedente',
            'deltaPreviousYear' => 'Delta vs anno precedente',
            'dominantSubcategory' => 'Voce dominante',
        ],
        'export' => [
            'title' => 'Analisi per categoria',
            'xlsx' => 'Excel',
            'period' => 'Periodo',
            'scope' => 'Risorsa',
            'previousYear' => 'Anno precedente',
            'share' => 'Quota',
            'generatedAt' => 'Generato il',
            'headerSubtitle' => 'Report di analisi categoria',
            'summary' => 'Riepilogo',
            'periods' => 'periodi',
            'comparisons' => 'Confronti',
            'notAvailable' => 'Non disponibile',
            'analysisScope' => 'Perimetro analisi',
            'actualScope' => 'Spesa reale',
            'budgetScope' => 'Budget',
            'comparisonScope' => 'Confronti',
        ],
    ],
    'modules' => [
        'status' => [
            'available' => 'Sezione analytics disponibile',
        ],
        'kpi' => [
            'title' => 'Panoramica del periodo',
            'description' => 'KPI affidabili del periodo e trend entrate, uscite e netto nel tempo.',
        ],
        'categories' => [
            'title' => 'Ripartizione per categoria',
            'description' => 'Composizione, categorie principali e trend di spesa.',
        ],
        'categoryAnalysis' => [
            'title' => 'Analisi per categoria',
            'description' => 'KPI, trend e confronti temporali per una categoria o sottocategoria specifica.',
        ],
        'accounts' => [
            'title' => 'Visione per conto',
            'description' => 'Lettura per conto, carte e perimetri condivisi.',
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
