export const trackedItemsMessages = {
    it: {
        title: 'Elementi da tracciare',
        pageTitle: 'Elementi da tracciare',
        hero: {
            badge: 'Dettaglio personale facoltativo',
            description:
                'Usa questa sezione solo se vuoi dettagliare a cosa si riferiscono alcune spese, entrate o previsioni. Le categorie restano separate e descrivono il tipo di movimento; qui descrivi l’oggetto personale a cui il movimento si riferisce.',
        },
        actions: {
            new: 'Nuovo elemento',
        },
        filters: {
            title: 'Filtri rapidi',
            description:
                'Cerca per nome o percorso e riduci la lista per stato, uso o struttura.',
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
            title: 'Struttura gerarchica',
            summary: '{visible} visibili, {roots} radici, {used} in uso.',
            badges: {
                hierarchical: 'Lista ad albero',
                fullPath: 'Percorso completo',
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
                createChild: 'Figlio',
                edit: 'Modifica',
                deactivate: 'Disattiva',
                activate: 'Attiva',
                delete: 'Elimina',
            },
            emptyDefault: 'Nessun elemento da mostrare.',
        },
        guidance: {
            title: 'Quando usarli',
            subtitle:
                'Aggiungili solo se ti serve un livello di dettaglio personale oltre la categoria.',
            points: {
                optional:
                    'Sono sempre opzionali: puoi gestire tutto anche solo con le categorie.',
                hierarchy:
                    'Puoi creare una piccola gerarchia per raggruppare oggetti simili.',
                categories:
                    'Associare categorie compatibili aiuta a suggerire più velocemente gli elementi durante l’inserimento.',
            },
        },
        usageGuide: {
            title: 'Come usarli bene',
            subtitle: 'Mantieni la struttura semplice e utile.',
            points: {
                hierarchy:
                    'Puoi creare un solo elemento come Kia oppure una struttura come Veicoli > Auto > Kia.',
                parent:
                    'Il padre è sempre facoltativo: usalo solo se ti aiuta a ritrovare meglio gli elementi.',
                deactivate:
                    'Se un elemento è già in uso, la soluzione normale è disattivarlo per non perdere lo storico.',
            },
        },
        separation: {
            title: 'Separati dalle categorie',
            subtitle: 'Una dimensione in più, ma opzionale.',
            points: {
                category:
                    'Categoria: descrive la natura del movimento, ad esempio carburante o regali.',
                item:
                    'Elemento da tracciare: descrive l’oggetto personale, ad esempio Auto, Smart o Cane.',
                payload:
                    'Payload disponibile anche in formato flat con percorso completo per futuri selettori nei moduli operativi.',
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
            titleCreate: 'Nuovo elemento da tracciare',
            titleEdit: 'Modifica elemento da tracciare',
            descriptionCreate:
                'Crea un elemento personale opzionale per dettagliare meglio spese, entrate e previsioni.',
            descriptionEdit:
                'Aggiorna nome, eventuale padre e stato dell’elemento selezionato.',
            labels: {
                name: 'Nome',
                parent: 'Elemento padre opzionale',
                type: 'Tipo opzionale',
                compatibleCategories: 'Rami categoria compatibili',
                status: 'Stato',
                active: 'Attivo',
            },
            placeholders: {
                name: 'Es. Kia, Casa 1, Cane',
                noParent: 'Nessun elemento padre',
                type: 'Es. auto, moto, casa',
                categorySearch: 'Cerca ramo o categoria',
            },
            help: {
                name:
                    'Dai un nome chiaro all’oggetto personale che vuoi tracciare.',
                parent:
                    'Facoltativo. Serve solo se vuoi organizzare gli elementi in una piccola gerarchia.',
                type:
                    'Facoltativo. Può aiutarti a distinguere rapidamente gruppi simili.',
                compatibleCategories:
                    'Associa questo elemento a uno o più rami o foglie categoria. Sarà poi suggerito anche sulle categorie figlie del ramo scelto.',
                active:
                    'Se disattivato resta nello storico ma non sarà proposto come scelta normale.',
                statusBox:
                    'Gli elementi da tracciare sono sempre facoltativi e non sostituiscono le categorie.',
            },
            actions: {
                remove: 'Rimuovi',
                add: 'Aggiungi',
                cancel: 'Annulla',
                save: 'Salva modifiche',
                create: 'Crea elemento',
            },
            emptyCompatibleCategories:
                'Nessuna categoria compatibile da aggiungere.',
        },
        deleteDialog: {
            title: 'Elimina elemento',
            confirmPrefix: 'Stai per eliminare',
            confirmSuffix: 'L’operazione è definitiva.',
            blockedMessage:
                'non può essere eliminato in questo momento.',
            blockedReasonsTitle: 'Motivi del blocco',
            confirmAction: 'Elimina elemento',
            cancelAction: 'Annulla',
        },
        feedback: {
            successTitle: 'Operazione completata',
            unavailableTitle: 'Operazione non disponibile',
            saveTitle: 'Salvataggio completato',
            statusTitle: 'Stato aggiornato',
            statusActivated: "L'elemento è stato attivato.",
            statusDeactivated: "L'elemento è stato disattivato.",
            statusError:
                "Non è stato possibile aggiornare lo stato dell'elemento.",
            deletedTitle: 'Elemento eliminato',
            deletedMessage: "L'elemento è stato rimosso correttamente.",
            deleteErrorTitle: 'Eliminazione non riuscita',
            deleteErrorMessage:
                'Questo elemento da tracciare non può essere eliminato.',
            createSuccess: 'Elemento da tracciare creato con successo.',
            updateSuccess: 'Elemento da tracciare aggiornato con successo.',
        },
        empty: {
            initial:
                'Non hai ancora creato elementi da tracciare. Puoi iniziare anche da un solo elemento semplice.',
            filtered: 'Nessun elemento corrisponde ai filtri attivi.',
        },
        deleteReasons: {
            childOne: 'Ha un elemento figlio collegato.',
            childMany: 'Ha {count} elementi figli collegati.',
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
        title: 'Tracked items',
        pageTitle: 'Tracked items',
        hero: {
            badge: 'Optional personal detail',
            description:
                'Use this section only if you want to specify what some expenses, income, or forecasts refer to. Categories remain separate and describe the type of movement; here you describe the personal object the movement refers to.',
        },
        actions: {
            new: 'New item',
        },
        filters: {
            title: 'Quick filters',
            description:
                'Search by name or path and narrow the list by status, usage, or structure.',
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
            title: 'Hierarchical structure',
            summary: '{visible} visible, {roots} roots, {used} in use.',
            badges: {
                hierarchical: 'Tree list',
                fullPath: 'Full path',
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
                createChild: 'Child',
                edit: 'Edit',
                deactivate: 'Deactivate',
                activate: 'Activate',
                delete: 'Delete',
            },
            emptyDefault: 'No items to display.',
        },
        guidance: {
            title: 'When to use them',
            subtitle:
                'Add them only if you need a personal layer of detail beyond the category.',
            points: {
                optional:
                    'They are always optional: you can manage everything with categories only.',
                hierarchy:
                    'You can create a small hierarchy to group similar objects.',
                categories:
                    'Associating compatible categories helps suggest items faster during entry.',
            },
        },
        usageGuide: {
            title: 'How to use them well',
            subtitle: 'Keep the structure simple and useful.',
            points: {
                hierarchy:
                    'You can create a single item like Kia or a structure such as Vehicles > Cars > Kia.',
                parent:
                    'The parent is always optional: use it only if it helps you find items more easily.',
                deactivate:
                    'If an item is already in use, the normal solution is to deactivate it so you do not lose history.',
            },
        },
        separation: {
            title: 'Separate from categories',
            subtitle: 'An extra dimension, but optional.',
            points: {
                category:
                    'Category: describes the nature of the movement, for example fuel or gifts.',
                item:
                    'Tracked item: describes the personal object, for example Car, Smart, or Dog.',
                payload:
                    'Payload is also available in flat format with full path for future selectors in operational modules.',
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
            titleCreate: 'New tracked item',
            titleEdit: 'Edit tracked item',
            descriptionCreate:
                'Create an optional personal item to better detail expenses, income, and forecasts.',
            descriptionEdit:
                'Update the name, optional parent, and status of the selected item.',
            labels: {
                name: 'Name',
                parent: 'Optional parent item',
                type: 'Optional type',
                compatibleCategories: 'Compatible category branches',
                status: 'Status',
                active: 'Active',
            },
            placeholders: {
                name: 'E.g. Kia, House 1, Dog',
                noParent: 'No parent item',
                type: 'E.g. car, motorbike, house',
                categorySearch: 'Search branch or category',
            },
            help: {
                name:
                    'Give a clear name to the personal object you want to track.',
                parent:
                    'Optional. Useful only if you want to organize items into a small hierarchy.',
                type:
                    'Optional. It can help you quickly distinguish similar groups.',
                compatibleCategories:
                    'Associate this item with one or more category branches or leaves. It will then also be suggested on child categories of the selected branch.',
                active:
                    'If disabled, it remains in history but will not be suggested as a normal choice.',
                statusBox:
                    'Tracked items are always optional and do not replace categories.',
            },
            actions: {
                remove: 'Remove',
                add: 'Add',
                cancel: 'Cancel',
                save: 'Save changes',
                create: 'Create item',
            },
            emptyCompatibleCategories:
                'No compatible categories to add.',
        },
        deleteDialog: {
            title: 'Delete item',
            confirmPrefix: 'You are about to delete',
            confirmSuffix: 'This action is permanent.',
            blockedMessage:
                'cannot be deleted at this time.',
            blockedReasonsTitle: 'Blocking reasons',
            confirmAction: 'Delete item',
            cancelAction: 'Cancel',
        },
        feedback: {
            successTitle: 'Operation completed',
            unavailableTitle: 'Operation unavailable',
            saveTitle: 'Save completed',
            statusTitle: 'Status updated',
            statusActivated: 'The item has been activated.',
            statusDeactivated: 'The item has been deactivated.',
            statusError: 'The item status could not be updated.',
            deletedTitle: 'Item deleted',
            deletedMessage: 'The item was removed successfully.',
            deleteErrorTitle: 'Deletion failed',
            deleteErrorMessage: 'This tracked item cannot be deleted.',
            createSuccess: 'Tracked item created successfully.',
            updateSuccess: 'Tracked item updated successfully.',
        },
        empty: {
            initial:
                'You have not created tracked items yet. You can also start with a single simple item.',
            filtered: 'No item matches the active filters.',
        },
        deleteReasons: {
            childOne: 'It has 1 linked child item.',
            childMany: 'It has {count} linked child items.',
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
