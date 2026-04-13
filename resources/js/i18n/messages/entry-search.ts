export const entrySearchMessages = {
    it: {
        triggerLabel: 'Cerca',
        placeholder: 'Cerca movimenti e ricorrenze',
        mobileTitle: 'Ricerca',
        surfaceTitle: 'Ricerca globale',
        surfaceDescription:
            'Trova rapidamente movimenti e ricorrenze, poi apri il risultato nel suo contesto reale.',
        resultsLabel: '{count} risultati',
        mobileSummaryWithFilters: '{count} filtri attivi',
        scopeOptions: {
            all: 'Tutti',
            transactions: 'Movimenti',
            recurring: 'Ricorrenti',
        },
        periodOptions: {
            currentMonth: 'Questo mese',
            allMonths: 'Tutti i mesi',
        },
        actions: {
            filters: 'Filtri',
            closeFilters: 'Chiudi filtri',
            reset: 'Reimposta',
            apply: 'Applica',
            applyFilters: 'Applica filtri',
            cancel: 'Annulla',
            close: 'Chiudi',
            adjustFilters: 'Modifica i filtri',
        },
        resultKinds: {
            transaction: 'Movimento',
            recurring: 'Ricorrente',
        },
        states: {
            idleTitle: 'Inizia da una parola chiave o da un filtro',
            idleDescription:
                'La ricerca unificata ti aiuta a trovare rapidamente movimenti e ricorrenze nel contesto giusto.',
            emptyTitle: 'Nessun risultato trovato',
            emptyDescription:
                'Prova a cambiare ambito, estendere la ricerca a tutti i mesi o alleggerire i filtri.',
            errorTitle: 'Ricerca non disponibile',
            errorDescription:
                'Non siamo riusciti a recuperare i risultati in questo momento.',
        },
        advanced: {
            title: 'Filtri avanzati',
            description:
                'Restringi la ricerca senza appesantire la barra principale.',
            account: 'Conto',
            allAccounts: 'Tutti i conti',
            category: 'Categoria',
            allCategories: 'Tutte le categorie',
            searchCategories: 'Cerca categoria',
            noCategoriesFound: 'Nessuna categoria trovata',
            categoryDescription:
                'Usa la stessa selezione categorie del resto dell’app per restringere la ricerca.',
            direction: 'Tipo movimento',
            allDirections: 'Tutte le direzioni',
            recurringStatus: 'Stato ricorrente',
            allRecurringStatuses: 'Tutti gli stati',
            amountMin: 'Importo minimo',
            amountMax: 'Importo massimo',
            withNotes: 'Solo record con note',
            withReference: 'Solo record con riferimento',
            recurringStatuses: {
                active: 'Attivo',
                paused: 'In pausa',
            },
        },
    },
    en: {
        triggerLabel: 'Search',
        placeholder: 'Search transactions and recurring entries',
        mobileTitle: 'Search',
        surfaceTitle: 'Global search',
        surfaceDescription:
            'Quickly find transactions and recurring entries, then open the result in its real context.',
        resultsLabel: '{count} results',
        mobileSummaryWithFilters: '{count} active filters',
        scopeOptions: {
            all: 'All',
            transactions: 'Transactions',
            recurring: 'Recurring',
        },
        periodOptions: {
            currentMonth: 'This month',
            allMonths: 'All months',
        },
        actions: {
            filters: 'Filters',
            closeFilters: 'Hide filters',
            reset: 'Reset',
            apply: 'Apply',
            applyFilters: 'Apply filters',
            cancel: 'Cancel',
            close: 'Close',
            adjustFilters: 'Adjust filters',
        },
        resultKinds: {
            transaction: 'Transaction',
            recurring: 'Recurring',
        },
        states: {
            idleTitle: 'Start with a keyword or a filter',
            idleDescription:
                'Unified search helps you reach the right transaction or recurring entry without leaving context.',
            emptyTitle: 'No results found',
            emptyDescription:
                'Try changing the scope, searching across all months, or removing some filters.',
            errorTitle: 'Search unavailable',
            errorDescription:
                'We could not load search results right now.',
        },
        advanced: {
            title: 'Advanced filters',
            description:
                'Narrow the search without overcrowding the main search bar.',
            account: 'Account',
            allAccounts: 'All accounts',
            category: 'Category',
            allCategories: 'All categories',
            searchCategories: 'Search category',
            noCategoriesFound: 'No categories found',
            categoryDescription:
                'Use the same category picker as the rest of the app to narrow the search.',
            direction: 'Entry type',
            allDirections: 'All directions',
            recurringStatus: 'Recurring status',
            allRecurringStatuses: 'All statuses',
            amountMin: 'Minimum amount',
            amountMax: 'Maximum amount',
            withNotes: 'Only records with notes',
            withReference: 'Only records with a reference',
            recurringStatuses: {
                active: 'Active',
                paused: 'Paused',
            },
        },
    },
} as const;
