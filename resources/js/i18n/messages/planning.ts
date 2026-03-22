export const planningMessages = {
    it: {
        title: 'Preventivazione',
        annualBadge: 'Budget Planning annuale',
        heading: 'Preventivazione / Budget Planning',
        description:
            'Pianifica l’anno per categoria, lavora sulle foglie selezionabili e lascia ai padri il ruolo di aggregazione. La vista desktop è ottimizzata per inserimento rapido, la mobile per revisione e modifica verticale.',
        filters: {
            year: 'Anno',
            macrogroup: 'Macrogruppo',
        },
        actions: {
            copyPreviousYear: 'Copia dal {year}',
            createYear: 'Crea anno di gestione',
            goToYears: 'Vai agli anni',
            hide: 'Nascondi',
        },
        activeYearNotice:
            "Stai lavorando sul {selectedYear} mentre l'anno attuale è il {currentYear}.",
        activeYearAlertTitle: 'Anno attivo non corrente',
        feedback: {
            saveFailedTitle: 'Salvataggio non riuscito',
            saveFailedFallback:
                'La modifica non è stata salvata. Il valore precedente è stato ripristinato.',
            copyFailedTitle: 'Copia non riuscita',
            copyFailedFallback:
                'Non è stato possibile copiare l’anno precedente. Controlla che esistano dati di partenza.',
            copiedTitle: 'Valori copiati',
        },
        save: {
            saving: 'Salvataggio in corso',
            checkErrors: 'Controlla le celle in errore',
            saved: 'Tutte le modifiche sono salvate',
            autosave: 'Autosave attivo',
        },
        overview: {
            monthlyTotals: 'Totali mensili',
            yearlySummary: 'Quadro annuale complessivo',
            editableCategories: '{count} categorie editabili',
            yearlyTotal: 'Totale annuo',
        },
        grid: {
            rows: '{count} righe',
            expandSection: 'Espandi blocco',
            collapseSection: 'Collassa blocco',
            category: 'Categoria',
            total: 'Totale',
            automaticSummary: 'Riepilogo automatico',
            sectionTotal: 'Totale {section}',
            groupCategories: 'Categorie del gruppo',
        },
        summaryCards: {
            annualPlan: 'Piano annuale',
            incomeShare: '{value}% del reddito',
        },
        closedYear: {
            title: 'Anno chiuso',
            fallback:
                "L'anno selezionato è chiuso e non può essere modificato.",
        },
    },
    en: {
        title: 'Planning',
        annualBadge: 'Annual budget planning',
        heading: 'Planning / Budget Planning',
        description:
            'Plan the year by category, work on selectable leaf nodes, and keep parents as aggregation rows. The desktop view is optimized for fast entry, the mobile view for vertical review and edits.',
        filters: {
            year: 'Year',
            macrogroup: 'Macrogroup',
        },
        actions: {
            copyPreviousYear: 'Copy from {year}',
            createYear: 'Create management year',
            goToYears: 'Go to years',
            hide: 'Hide',
        },
        activeYearNotice:
            'You are working on {selectedYear} while the current year is {currentYear}.',
        activeYearAlertTitle: 'Active year not current',
        feedback: {
            saveFailedTitle: 'Save failed',
            saveFailedFallback:
                'The change was not saved. The previous value has been restored.',
            copyFailedTitle: 'Copy failed',
            copyFailedFallback:
                'The previous year could not be copied. Check that source data exists.',
            copiedTitle: 'Values copied',
        },
        save: {
            saving: 'Saving in progress',
            checkErrors: 'Check the cells with errors',
            saved: 'All changes are saved',
            autosave: 'Autosave enabled',
        },
        overview: {
            monthlyTotals: 'Monthly totals',
            yearlySummary: 'Overall yearly summary',
            editableCategories: '{count} editable categories',
            yearlyTotal: 'Year total',
        },
        grid: {
            rows: '{count} rows',
            expandSection: 'Expand section',
            collapseSection: 'Collapse section',
            category: 'Category',
            total: 'Total',
            automaticSummary: 'Automatic summary',
            sectionTotal: 'Total {section}',
            groupCategories: 'Group categories',
        },
        summaryCards: {
            annualPlan: 'Annual plan',
            incomeShare: '{value}% of income',
        },
        closedYear: {
            title: 'Closed year',
            fallback: 'The selected year is closed and cannot be edited.',
        },
    },
} as const;
