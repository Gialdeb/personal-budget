export const trackedItemsMessages = {
    it: {
        title: 'Riferimenti',
        pageTitle: 'Riferimenti',
        hero: {
            badge: 'Dettaglio personale facoltativo',
            description:
                'Usa questa sezione solo se vuoi dettagliare a cosa si riferiscono alcune spese, entrate o previsioni. Le categorie restano separate e descrivono il tipo di movimento; qui descrivi l’oggetto personale a cui il movimento si riferisce.',
        },
        actions: {
            new: 'Nuovo riferimento',
        },
        filters: {
            title: 'Filtri rapidi',
            description:
                'Cerca per nome o riferimento concreto e riduci la lista per stato, uso o struttura.',
            searchLabel: 'Ricerca',
            searchPlaceholder: 'Cerca per nome, tipo o percorso',
            activeLabel: 'Stato',
            activePlaceholder: 'Filtra per stato',
            usageLabel: 'Utilizzo',
            usagePlaceholder: 'Filtra per utilizzo',
            structureLabel: 'Struttura',
            structurePlaceholder: 'Filtra per struttura',
            all: 'Tutti',
            active: 'Attivi',
            archived: 'In archivio',
            used: 'In uso',
            unused: 'Mai usati',
            allStructure: 'Tutta la struttura',
            roots: 'Solo radice',
            leavesOnly: 'Solo foglie',
        },
        tree: {
            title: 'Riferimenti operativi',
            summary: '{visible} visibili, {roots} raggruppatori facoltativi, {used} in uso.',
            badges: {
                categoryDriven: 'Guidati dalle categorie',
                flatFirst: 'Piatti per default',
            },
            status: {
                active: 'Attivo',
                archived: 'In archivio',
                used: 'In uso ({count})',
                unused: 'Mai usato',
                childrenCount: '{count} figli',
                leaf: 'Foglia',
                rootMarker: 'R',
                nodeMarker: 'N',
                leafMarker: 'F',
            },
            labels: {
                parent: 'Padre',
                noUsage: 'Nessun utilizzo collegato',
            },
            usage: {
                transactions: '{count} transazioni',
                budgets: '{count} budget',
                recurring: '{count} ricorrenze',
                scheduled: '{count} scadenze',
            },
            actions: {
                edit: 'Modifica',
                deactivate: 'Disattiva',
                activate: 'Attiva',
                delete: 'Elimina',
            },
            emptyDefault: 'Nessun riferimento da mostrare.',
        },
        guidance: {
            title: 'Quando usarli',
            subtitle:
                'Aggiungili solo se ti serve un livello di dettaglio personale oltre la categoria.',
            points: {
                optional:
                    'Sono sempre opzionali: puoi gestire tutto anche solo con le categorie.',
                hierarchy:
                    'Tienili normalmente piatti: usa un padre solo se ti serve davvero un piccolo raggruppamento locale.',
                categories:
                    'Le categorie compatibili sono la guida principale: prima scegli il ramo categoria, poi il riferimento concreto.',
            },
        },
        usageGuide: {
            title: 'Come usarli bene',
            subtitle: 'Usali come dettaglio concreto, non come seconda tassonomia.',
            points: {
                flat: 'Il caso normale è un riferimento semplice come Decò, Eurospin, Giulietta o Dott. Rossi.',
                parent: 'Il padre è sempre facoltativo: usalo solo se ti aiuta a ritrovare meglio i riferimenti.',
                category:
                    'Associare categorie compatibili rende il riferimento selezionabile solo dove ha davvero senso operativo.',
            },
        },
        separation: {
            title: 'Separati dalle categorie',
            subtitle: 'Una dimensione in più, ma opzionale.',
            points: {
                category:
                    'Categoria: descrive la struttura del dominio, ad esempio supermercato, medico o veicoli.',
                item: 'Riferimento: descrive l’oggetto concreto del movimento, ad esempio Decò, Giulietta o Dott. Rossi.',
                payload:
                    'Il catalogo resta disponibile anche in formato flat con categorie compatibili, pronto per i futuri selettori operativi guidati dal ramo categoria.',
            },
        },
        sharedBridge: {
            badge: 'Bridge conto shared',
            title: 'Aggiungi al conto condiviso',
            description:
                'Se un tuo riferimento personale serve davvero in un conto condiviso, aggiungilo una voce alla volta al catalogo del conto senza sincronizzare tutto il personale.',
            availableCount: '{count} candidati',
            labels: {
                account: 'Conto condiviso',
                trackedItem: 'Riferimento personale compatibile',
            },
            placeholders: {
                account: 'Seleziona un conto condiviso',
                trackedItem: 'Seleziona un riferimento personale',
            },
            help: 'Vedi solo riferimenti personali attivi, operativi e non ancora presenti nel catalogo del conto.',
            empty: 'Per questo conto non hai riferimenti personali candidabili da aggiungere.',
            action: 'Aggiungi al conto condiviso',
            validation: {
                required: 'Seleziona un riferimento personale da aggiungere al conto condiviso.',
                unavailable:
                    'Questo riferimento personale non è disponibile per il conto condiviso selezionato.',
            },
            flash: {
                created: '{name} è stato aggiunto al catalogo del conto condiviso.',
                reused: '{name} era già presente nel catalogo del conto condiviso.',
            },
        },
        uiData: {
            title: 'Dati pronti per la UI',
            flatList: 'Elenco flat',
            rootNodes: 'Nodi radice',
            used: 'In uso',
        },
        summary: {
            total: 'Totali',
            active: 'Attivi',
            used: 'In uso',
            leaves: 'Foglie',
        },
        form: {
            titleCreate: 'Nuovo riferimento',
            titleEdit: 'Modifica riferimento',
            descriptionCreate:
                'Crea un riferimento concreto opzionale da proporre solo sulle categorie compatibili.',
            descriptionEdit:
                'Aggiorna il riferimento concreto selezionato senza trasformarlo in una seconda tassonomia.',
            labels: {
                name: 'Nome',
                slug: 'Slug',
                parent: 'Riferimento padre opzionale',
                type: 'Tipo opzionale',
                compatibleCategories: 'Rami categoria compatibili',
                status: 'Stato',
                active: 'Attivo',
            },
            placeholders: {
                name: 'Es. Kia, Casa 1, Cane',
                slug: 'kia-casa-1-cane',
                noParent: 'Nessun riferimento padre',
                type: 'Es. auto, moto, casa',
                categorySearch: 'Cerca ramo o categoria',
            },
            help: {
                name: 'Usa il nome concreto che vuoi associare al movimento, ad esempio Decò, Giulietta o Dott. Rossi.',
                parent: 'Facoltativo. Serve solo per piccoli raggruppamenti locali e non per ricostruire il ramo categoria.',
                type: 'Facoltativo. Può aiutarti a riconoscere più velocemente riferimenti simili, ma non sostituisce la categoria.',
                compatibleCategories:
                    'Questo è il campo principale: collega il riferimento ai rami categoria in cui deve essere davvero disponibile.',
                active: 'Se disattivato resta nello storico ma non sarà proposto come scelta normale.',
                statusBox:
                    'Le categorie restano la struttura principale; i riferimenti aggiungono solo un dettaglio concreto quando serve.',
            },
            actions: {
                remove: 'Rimuovi',
                add: 'Aggiungi',
                cancel: 'Annulla',
                save: 'Salva modifiche',
                create: 'Crea riferimento',
            },
            emptyCompatibleCategories:
                'Nessuna categoria compatibile da aggiungere.',
        },
        deleteDialog: {
            title: 'Elimina riferimento',
            confirmPrefix: 'Stai per eliminare',
            confirmSuffix: 'L’operazione è definitiva.',
            blockedMessage: 'non può essere eliminato in questo momento.',
            blockedReasonsTitle: 'Motivi del blocco',
            confirmAction: 'Elimina riferimento',
            cancelAction: 'Annulla',
        },
        feedback: {
            successTitle: 'Operazione completata',
            unavailableTitle: 'Operazione non disponibile',
            saveTitle: 'Salvataggio completato',
            statusTitle: 'Stato aggiornato',
            statusActivated: 'Il riferimento è stato attivato.',
            statusDeactivated: 'Il riferimento è stato disattivato.',
            statusError:
                'Non è stato possibile aggiornare lo stato del riferimento.',
            deletedTitle: 'Riferimento eliminato',
            deletedMessage: 'Il riferimento è stato rimosso correttamente.',
            deleteErrorTitle: 'Eliminazione non riuscita',
            deleteErrorMessage: 'Questo riferimento non può essere eliminato.',
            createSuccess: 'Riferimento creato con successo.',
            updateSuccess: 'Riferimento aggiornato con successo.',
        },
        empty: {
            initial:
                'Non hai ancora creato riferimenti. Puoi iniziare anche da un solo riferimento semplice.',
            filtered: 'Nessun riferimento corrisponde ai filtri attivi.',
        },
        deleteReasons: {
            childOne: 'Ha un riferimento figlio collegato.',
            childMany: 'Ha {count} riferimenti figli collegati.',
            transactionOne: 'È usato in 1 transazione.',
            transactionMany: 'È usato in {count} transazioni.',
            budgetOne: 'È usato in 1 budget.',
            budgetMany: 'È usato in {count} budget.',
            recurringOne: 'È usato in 1 ricorrenza.',
            recurringMany: 'È usato in {count} ricorrenze.',
            scheduledOne: 'È usato in 1 scadenza pianificata.',
            scheduledMany: 'È usato in {count} scadenze pianificate.',
        },
    },
    en: {
        title: 'References',
        pageTitle: 'References',
        hero: {
            badge: 'Optional personal detail',
            description:
                'Use this section only if you want to specify what some expenses, income, or forecasts refer to. Categories remain separate and describe the type of movement; here you describe the personal object the movement refers to.',
        },
        actions: {
            new: 'New reference',
        },
        filters: {
            title: 'Quick filters',
            description:
                'Search by name or concrete reference and narrow the list by status, usage, or structure.',
            searchLabel: 'Search',
            searchPlaceholder: 'Search by name, type, or path',
            activeLabel: 'Status',
            activePlaceholder: 'Filter by status',
            usageLabel: 'Usage',
            usagePlaceholder: 'Filter by usage',
            structureLabel: 'Structure',
            structurePlaceholder: 'Filter by structure',
            all: 'All',
            active: 'Active',
            archived: 'Archived',
            used: 'In use',
            unused: 'Never used',
            allStructure: 'Whole structure',
            roots: 'Roots only',
            leavesOnly: 'Leaves only',
        },
        tree: {
            title: 'Operational references',
            summary: '{visible} visible, {roots} optional grouping nodes, {used} in use.',
            badges: {
                categoryDriven: 'Category-driven',
                flatFirst: 'Flat by default',
            },
            status: {
                active: 'Active',
                archived: 'Archived',
                used: 'In use ({count})',
                unused: 'Never used',
                childrenCount: '{count} children',
                leaf: 'Leaf',
                rootMarker: 'R',
                nodeMarker: 'N',
                leafMarker: 'L',
            },
            labels: {
                parent: 'Parent',
                noUsage: 'No linked usage',
            },
            usage: {
                transactions: '{count} transactions',
                budgets: '{count} budgets',
                recurring: '{count} recurring entries',
                scheduled: '{count} scheduled items',
            },
            actions: {
                edit: 'Edit',
                deactivate: 'Deactivate',
                activate: 'Activate',
                delete: 'Delete',
            },
            emptyDefault: 'No references to display.',
        },
        guidance: {
            title: 'When to use them',
            subtitle:
                'Add them only if you need a personal layer of detail beyond the category.',
            points: {
                optional:
                    'They are always optional: you can manage everything with categories only.',
                hierarchy:
                    'Keep them mostly flat: use a parent only when you truly need a small local grouping.',
                categories:
                    'Compatible categories are the main guide: choose the category branch first, then the concrete reference.',
            },
        },
        usageGuide: {
            title: 'How to use them well',
            subtitle: 'Use them as concrete detail, not as a second taxonomy.',
            points: {
                flat: 'The normal case is a simple reference like Aldi, Eurospin, Giulietta, or Dr. Rossi.',
                parent: 'The parent is always optional: use it only for small local groupings, not to rebuild the category tree.',
                category:
                    'Linking compatible categories makes the reference available only where it makes operational sense.',
            },
        },
        separation: {
            title: 'Separate from categories',
            subtitle: 'An extra dimension, but optional.',
            points: {
                category:
                    'Category: describes the domain structure, such as groceries, doctor, or vehicles.',
                item: 'Reference: describes the concrete object in the movement, such as Aldi, Giulietta, or Dr. Rossi.',
                payload:
                    'The payload also stays available in flat format with compatible categories, ready for future category-driven operational selectors.',
            },
        },
        sharedBridge: {
            badge: 'Shared-account bridge',
            title: 'Add to shared account',
            description:
                'If one of your personal references is genuinely useful in a shared account, add it one item at a time to the account catalog without syncing your whole personal catalog.',
            availableCount: '{count} candidates',
            labels: {
                account: 'Shared account',
                trackedItem: 'Compatible personal reference',
            },
            placeholders: {
                account: 'Select a shared account',
                trackedItem: 'Select a personal reference',
            },
            help: 'Only active, operational personal references not already present in the account catalog are shown here.',
            empty: 'You have no personal references that can be added to this shared account.',
            action: 'Add to shared account',
            validation: {
                required: 'Select a personal reference to add to the shared account.',
                unavailable:
                    'This personal reference is not available for the selected shared account.',
            },
            flash: {
                created: '{name} was added to the shared account catalog.',
                reused: '{name} was already present in the shared account catalog.',
            },
        },
        uiData: {
            title: 'UI-ready data',
            flatList: 'Flat list',
            rootNodes: 'Root nodes',
            used: 'In use',
        },
        summary: {
            total: 'Total',
            active: 'Active',
            used: 'In use',
            leaves: 'Leaves',
        },
        form: {
            titleCreate: 'New reference',
            titleEdit: 'Edit reference',
            descriptionCreate:
                'Create an optional concrete reference that should only appear on compatible categories.',
            descriptionEdit:
                'Update the selected concrete reference without turning it into a second taxonomy.',
            labels: {
                name: 'Name',
                slug: 'Slug',
                parent: 'Optional parent reference',
                type: 'Optional type',
                compatibleCategories: 'Compatible category branches',
                status: 'Status',
                active: 'Active',
            },
            placeholders: {
                name: 'E.g. Kia, House 1, Dog',
                slug: 'kia-house-1-dog',
                noParent: 'No parent reference',
                type: 'E.g. car, motorbike, house',
                categorySearch: 'Search branch or category',
            },
            help: {
                name: 'Use the concrete name you want to attach to the movement, such as Aldi, Giulietta, or Dr. Rossi.',
                parent: 'Optional. Use it only for small local groupings, not to rebuild the category branch.',
                type: 'Optional. It can help distinguish similar references quickly, but it does not replace the category.',
                compatibleCategories:
                    'This is the main field: link the reference to the category branches where it should really be available.',
                active: 'If disabled, it remains in history but will not be suggested as a normal choice.',
                statusBox:
                    'Categories remain the main structure; references only add a concrete detail when useful.',
            },
            actions: {
                remove: 'Remove',
                add: 'Add',
                cancel: 'Cancel',
                save: 'Save changes',
                create: 'Create reference',
            },
            emptyCompatibleCategories: 'No compatible categories to add.',
        },
        deleteDialog: {
            title: 'Delete reference',
            confirmPrefix: 'You are about to delete',
            confirmSuffix: 'This action is permanent.',
            blockedMessage: 'cannot be deleted at this time.',
            blockedReasonsTitle: 'Blocking reasons',
            confirmAction: 'Delete reference',
            cancelAction: 'Cancel',
        },
        feedback: {
            successTitle: 'Operation completed',
            unavailableTitle: 'Operation unavailable',
            saveTitle: 'Save completed',
            statusTitle: 'Status updated',
            statusActivated: 'The reference has been activated.',
            statusDeactivated: 'The reference has been deactivated.',
            statusError: 'The reference status could not be updated.',
            deletedTitle: 'Reference deleted',
            deletedMessage: 'The reference was removed successfully.',
            deleteErrorTitle: 'Deletion failed',
            deleteErrorMessage: 'This reference cannot be deleted.',
            createSuccess: 'Reference created successfully.',
            updateSuccess: 'Reference updated successfully.',
        },
        empty: {
            initial:
                'You have not created references yet. You can also start with a single simple reference.',
            filtered: 'No reference matches the active filters.',
        },
        deleteReasons: {
            childOne: 'It has 1 linked child reference.',
            childMany: 'It has {count} linked child references.',
            transactionOne: 'It is used in 1 transaction.',
            transactionMany: 'It is used in {count} transactions.',
            budgetOne: 'It is used in 1 budget.',
            budgetMany: 'It is used in {count} budgets.',
            recurringOne: 'It is used in 1 recurring entry.',
            recurringMany: 'It is used in {count} recurring entries.',
            scheduledOne: 'It is used in 1 scheduled entry.',
            scheduledMany: 'It is used in {count} scheduled entries.',
        },
    },
} as const;
