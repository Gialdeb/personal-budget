export const exportMessages = {
    it: {
        title: 'Export',
        description:
            'Esporta i tuoi dati in modo chiaro, selettivo e pronto per fogli di calcolo, backup o analisi esterne.',
        heroEyebrow: 'Dati pronti all’uso',
        steps: {
            dataset: '1. Scegli cosa esportare',
            period: '2. Scegli il periodo',
            format: '3. Scegli il formato',
            summary: '4. Controlla e scarica',
        },
        datasets: {
            transactions: {
                label: 'Transazioni',
                description: 'Esporta movimenti e dettagli associati.',
            },
            accounts: {
                label: 'Conti',
                description:
                    'Esporta i tuoi conti e le informazioni principali.',
            },
            categories: {
                label: 'Categorie',
                description: 'Esporta categorie, gerarchia, icona e colore.',
            },
            trackedItems: {
                label: 'Riferimenti',
                description:
                    'Esporta i riferimenti operativi e la loro struttura.',
            },
            recurringEntries: {
                label: 'Ricorrenze',
                description:
                    'Esporta le ricorrenze con importi, stato e collegamenti.',
            },
            budgets: {
                label: 'Preventivazione',
                description:
                    'Esporta i budget mensili con categoria, riferimento e ambito.',
            },
            fullExport: {
                label: 'Full export',
                description:
                    'Esporta tutti i tuoi dati in un JSON strutturato per backup o migrazione futura.',
            },
        },
        period: {
            title: 'Periodo',
            description:
                'Usa filtri rapidi o un intervallo personalizzato solo dove il dataset è temporale.',
            notApplicableTitle: 'Periodo non necessario',
            notApplicableDescription:
                'Per questo dataset il filtro periodo non si applica, quindi l’export include tutti i dati disponibili.',
            startDate: 'Data inizio',
            endDate: 'Data fine',
            customHint:
                'Imposta una data inizio e una data fine per restringere l’export.',
            presets: {
                allTime: 'Tutto',
                thisMonth: 'Questo mese',
                lastMonth: 'Mese scorso',
                thisYear: 'Quest’anno',
                customRange: 'Intervallo personalizzato',
            },
            labels: {
                allTime: 'Tutto',
                customRange: '{start} → {end}',
            },
        },
        formats: {
            title: 'Formato',
            description:
                'Scegli un formato stabile e leggibile per fogli di calcolo, backup o analisi.',
            csv: {
                label: 'CSV',
                description: 'Compatibile con Excel e Google Sheets.',
            },
            json: {
                label: 'JSON',
                description:
                    'Strutturato, leggibile e pronto per backup o integrazioni.',
            },
        },
        summary: {
            title: 'Riepilogo export',
            description:
                'Controlla cosa stai per scaricare prima di avviare il download.',
            dataset: 'Dataset',
            period: 'Periodo',
            format: 'Formato',
            machineFriendly:
                'Date e importi nei file restano in formato stabile e machine-friendly.',
        },
        actions: {
            exportCsv: 'Esporta CSV',
            exportJson: 'Esporta JSON',
        },
        validation: {
            customRange: 'Seleziona data inizio e data fine.',
        },
    },
    en: {
        title: 'Export',
        description:
            'Export your data in a clear, selective format that works well for spreadsheets, backups, and external analysis.',
        heroEyebrow: 'Data ready to use',
        steps: {
            dataset: '1. Choose what to export',
            period: '2. Choose the period',
            format: '3. Choose the format',
            summary: '4. Review and download',
        },
        datasets: {
            transactions: {
                label: 'Transactions',
                description: 'Export movements and their linked details.',
            },
            accounts: {
                label: 'Accounts',
                description: 'Export your accounts and the main account data.',
            },
            categories: {
                label: 'Categories',
                description: 'Export categories, hierarchy, icon, and color.',
            },
            trackedItems: {
                label: 'References',
                description:
                    'Export tracked references and their operational structure.',
            },
            recurringEntries: {
                label: 'Recurring entries',
                description:
                    'Export recurring entries with amounts, status, and links.',
            },
            budgets: {
                label: 'Budget planning',
                description:
                    'Export monthly budgets with category, reference, and scope.',
            },
            fullExport: {
                label: 'Full export',
                description:
                    'Export all your data in a structured JSON file for backup or future migration.',
            },
        },
        period: {
            title: 'Period',
            description:
                'Use quick presets or a custom range only for time-based datasets.',
            notApplicableTitle: 'Period not required',
            notApplicableDescription:
                'This dataset does not use a date filter, so the export includes all available records.',
            startDate: 'Start date',
            endDate: 'End date',
            customHint:
                'Set both a start date and an end date to narrow down the export.',
            presets: {
                allTime: 'All time',
                thisMonth: 'This month',
                lastMonth: 'Last month',
                thisYear: 'This year',
                customRange: 'Custom range',
            },
            labels: {
                allTime: 'All time',
                customRange: '{start} → {end}',
            },
        },
        formats: {
            title: 'Format',
            description:
                'Choose a stable and readable format for spreadsheets, backups, or analysis.',
            csv: {
                label: 'CSV',
                description: 'Compatible with Excel and Google Sheets.',
            },
            json: {
                label: 'JSON',
                description:
                    'Structured, readable, and ready for backups or integrations.',
            },
        },
        summary: {
            title: 'Export summary',
            description:
                'Review what you are about to download before starting the export.',
            dataset: 'Dataset',
            period: 'Period',
            format: 'Format',
            machineFriendly:
                'Dates and amounts in the files stay stable and machine-friendly.',
        },
        actions: {
            exportCsv: 'Export CSV',
            exportJson: 'Export JSON',
        },
        validation: {
            customRange: 'Choose both a start date and an end date.',
        },
    },
} as const;
