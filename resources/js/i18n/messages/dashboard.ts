export const dashboardMessages = {
    it: {
        title: 'Dashboard',
        greeting: {
            morning: 'Buongiorno',
            afternoon: 'Buon pomeriggio',
            evening: 'Buonasera',
        },
        period: {
            allYear: 'Tutto il {year}',
            currentYear: 'Anno corrente',
            viewingYear: 'Stai consultando il {year}',
            active: 'Periodo attivo',
            all: 'Tutto',
        },
        metrics: {
            currentBalance: 'Saldo attuale',
            previousBalance: 'Saldo precedente',
            periodDelta: 'Delta periodo',
            income: 'Entrate',
            activeAccounts: 'Conti attivi',
            expenses: 'Uscite',
            budget: 'Budget',
            remainingBudget: 'Budget residuo',
            pendingActions: 'Da gestire',
            open: 'Aperte',
            noPendingActions:
                'Nessuna azione operativa da gestire per questo periodo.',
            actionStatuses: {
                upcoming: 'In arrivo',
                today: 'Oggi',
                overdue: 'Scaduta',
                to_record: 'Da registrare',
            },
            transactions: '{count} movimenti',
            savingsRate: 'Tasso spesa e risparmio',
            savingsRateHint: 'Basato sul periodo selezionato',
            baseCurrencyHint:
                'I totali globali sono espressi nella tua valuta base {currency}.',
            savings: 'Risparmio',
            savingsPlural: 'Risparmi',
            expensesPlural: 'Spese',
        },
        actions: {
            createYear: 'Crea anno di gestione',
        },
        quickStart: {
            eyebrow: 'Inizia da qui',
            title: 'Configura il primo conto per iniziare davvero',
            description:
                'Per usare Soamco Budget serve almeno un conto operativo. Bastano pochi passaggi per partire senza dubbi.',
            dismiss: 'Nascondi',
            cta: 'Apri impostazioni banche',
            steps: {
                one: 'Aggiungi una banca o un conto.',
                two: 'Imposta il saldo iniziale.',
                three: 'Registra il primo movimento.',
            },
        },
        supportPrompt: {
            eyebrow: 'Supporta Soamco Budget',
            note: 'Per associare correttamente la donazione al tuo profilo, usa su Ko-fi la stessa email del tuo account.',
            variants: {
                first_support: {
                    title: 'Ti sta aiutando davvero?',
                    description:
                        'Se Soamco Budget ti e utile, la tua prima donazione ci aiuta a mantenere il progetto sostenibile senza cambiare il piano free.',
                    button: 'Offrimi un Ko-fi',
                },
                renew_support: {
                    title: 'Il tuo supporto sta per scadere',
                    description:
                        'Hai gia sostenuto il progetto. Se vuoi, puoi rinnovare con una nuova donazione e continuare a supportarne l’evoluzione.',
                    button: 'Rinnova il supporto',
                },
                support_again: {
                    title: 'Vuoi tornare a supportare il progetto?',
                    description:
                        'E passato un po’ dall’ultimo contributo. Se vuoi, puoi fare una nuova donazione e riattivare il tuo supporto.',
                    button: 'Dona di nuovo',
                },
            },
        },
        filters: {
            yearPlaceholder: 'Anno',
            accountScopePlaceholder: 'Ambito conti',
            accountPlaceholder: 'Conto specifico',
            accountAll: 'Tutti i conti nel filtro',
            paymentAccountsGroup: 'Conti di pagamento',
            creditCardsGroup: 'Carte di credito',
            ownedBadge: 'Personale',
            sharedBadge: 'Condiviso',
        },
        alerts: {
            review: 'Da revisionare',
            overdueRecurring: 'Ricorrenze scadute',
            urgentScheduled: 'Scadenze urgenti',
        },
        trend: {
            title: 'Andamento del periodo',
            description:
                'Linea pulita per leggere entrate e uscite senza cambiare schermata.',
        },
        expenseBreakdown: {
            title: 'Ripartizione spese',
            description:
                'Le categorie con il peso maggiore nel periodo selezionato.',
            topFive: 'Top 5',
            totalExpenses: 'Totale spese',
            empty: 'Nessuna spesa categorizzata disponibile per questo periodo.',
        },
        budgetVsActual: {
            title: 'Budget vs effettivo',
            description:
                'Dove stai spendendo di piu rispetto ai limiti che hai impostato.',
            of: 'su',
            used: '{value} usato',
            remaining: 'Residuo {value}',
            exceeded: 'Sforato {value}',
            empty: 'Nessun budget disponibile per il filtro selezionato.',
            generalScope: 'Generale',
        },
        chart: {
            empty: 'Nessun movimento disponibile per il periodo selezionato.',
        },
        sections: {
            transfer: 'Giroconti interni tra conti',
        },
        categoryTargets: {
            title: 'Obiettivi per categoria',
            description:
                'Tutte le categorie padre con budget aggregato, spesa effettiva e scostamento nel periodo selezionato.',
            groups: '{count} gruppi',
            headers: {
                category: 'Categoria',
                target: 'Obiettivi',
                actual: 'Effettivo',
                difference: 'Differenza',
                budgetPercent: '% budget',
            },
            mobile: {
                marginAvailable: 'Margine ancora disponibile',
                watchCategory: 'Categoria da tenere sotto controllo',
                inControl: 'Sotto controllo',
                needsAttention: 'Da attenzionare',
                differencePositive: 'Differenza +{value}',
                differenceNegative: 'Differenza -{value}',
            },
            trend: {
                label: 'Andamento sul budget',
                within: 'In margine',
                over: 'Oltre il previsto',
            },
            empty: 'Nessuna categoria padre con figli disponibile per il periodo selezionato.',
        },
        agenda: {
            title: 'Agenda finanziaria',
            description:
                'Scadenze vicine, ricorrenze rilevanti e beneficiari principali del periodo.',
            dueSoon: 'In scadenza',
            recurring: 'Ricorrenze',
            review: 'Da revisionare',
            upcomingPlanned: 'Prossime uscite pianificate',
            upcomingEmpty: 'Nessuna scadenza imminente nel periodo.',
            topPayees: 'Beneficiari principali',
            transactions: '{count} movimenti',
            transactionOne: '{count} movimento',
            transactionMany: '{count} movimenti',
            payeesEmpty:
                'Nessun beneficiario rilevante da mostrare per il filtro corrente.',
            unspecified: 'Non specificato',
            entryKinds: {
                recurring: 'Ricorrente',
                scheduled: 'Pianificata',
            },
        },
    },
    en: {
        title: 'Dashboard',
        greeting: {
            morning: 'Good morning',
            afternoon: 'Good afternoon',
            evening: 'Good evening',
        },
        period: {
            allYear: 'All of {year}',
            currentYear: 'Current year',
            viewingYear: 'You are viewing {year}',
            active: 'Active period',
            all: 'All',
        },
        metrics: {
            currentBalance: 'Current balance',
            previousBalance: 'Previous balance',
            periodDelta: 'Period delta',
            income: 'Income',
            activeAccounts: 'Active accounts',
            expenses: 'Expenses',
            budget: 'Budget',
            remainingBudget: 'Remaining budget',
            pendingActions: 'Pending actions',
            open: 'Open',
            noPendingActions:
                'No operational actions to handle for this period.',
            actionStatuses: {
                upcoming: 'Upcoming',
                today: 'Today',
                overdue: 'Overdue',
                to_record: 'To record',
            },
            transactions: '{count} transactions',
            savingsRate: 'Spending and savings rate',
            savingsRateHint: 'Based on the selected period',
            baseCurrencyHint:
                'Global totals are shown in your base currency {currency}.',
            savings: 'Savings',
            savingsPlural: 'Savings',
            expensesPlural: 'Expenses',
        },
        actions: {
            createYear: 'Create management year',
        },
        quickStart: {
            eyebrow: 'Start here',
            title: 'Set up your first account to really get started',
            description:
                'To use Soamco Budget you need at least one operational account. It only takes a few clear steps to begin.',
            dismiss: 'Dismiss',
            cta: 'Open bank settings',
            steps: {
                one: 'Add a bank or an account.',
                two: 'Set the opening balance.',
                three: 'Record your first transaction.',
            },
        },
        supportPrompt: {
            eyebrow: 'Support Soamco Budget',
            note: 'To link your donation to your profile, please use the same email on Ko-fi as your account email.',
            variants: {
                first_support: {
                    title: 'Is it genuinely helping you?',
                    description:
                        'If Soamco Budget is useful to you, your first donation helps keep the project sustainable without changing the free plan.',
                    button: 'Support me on Ko-fi',
                },
                renew_support: {
                    title: 'Your support is about to expire',
                    description:
                        'You already supported the project once. If you want, you can renew with a new donation and keep supporting its evolution.',
                    button: 'Renew support',
                },
                support_again: {
                    title: 'Want to support the project again?',
                    description:
                        'It has been a while since your last contribution. If you want, you can donate again and reactivate your support.',
                    button: 'Donate again',
                },
            },
        },
        filters: {
            yearPlaceholder: 'Year',
            accountScopePlaceholder: 'Account scope',
            accountPlaceholder: 'Specific account',
            accountAll: 'All accounts in scope',
            paymentAccountsGroup: 'Payment accounts',
            creditCardsGroup: 'Credit cards',
            ownedBadge: 'Personal',
            sharedBadge: 'Shared',
        },
        alerts: {
            review: 'To review',
            overdueRecurring: 'Overdue recurring',
            urgentScheduled: 'Urgent due items',
        },
        trend: {
            title: 'Period trend',
            description:
                'A clean line to read income and expenses without changing screen.',
        },
        expenseBreakdown: {
            title: 'Expense breakdown',
            description:
                'Categories with the biggest weight in the selected period.',
            topFive: 'Top 5',
            totalExpenses: 'Total expenses',
            empty: 'No categorized expenses available for this period.',
        },
        budgetVsActual: {
            title: 'Budget vs actual',
            description: 'Where you are spending more than the limits you set.',
            of: 'of',
            used: '{value} used',
            remaining: 'Remaining {value}',
            exceeded: 'Exceeded {value}',
            empty: 'No budget available for the selected filter.',
            generalScope: 'General',
        },
        chart: {
            empty: 'No transactions available for the selected period.',
        },
        sections: {
            transfer: 'Internal transfers between accounts',
        },
        categoryTargets: {
            title: 'Category targets',
            description:
                'All parent categories with aggregated budget, actual spend, and variance in the selected period.',
            groups: '{count} groups',
            headers: {
                category: 'Category',
                target: 'Targets',
                actual: 'Actual',
                difference: 'Difference',
                budgetPercent: '% budget',
            },
            mobile: {
                marginAvailable: 'Margin still available',
                watchCategory: 'Category to keep under control',
                inControl: 'Under control',
                needsAttention: 'Needs attention',
                differencePositive: 'Difference +{value}',
                differenceNegative: 'Difference -{value}',
            },
            trend: {
                label: 'Budget trend',
                within: 'Within budget',
                over: 'Over expected',
            },
            empty: 'No parent category with children available for this period.',
        },
        agenda: {
            title: 'Financial agenda',
            description:
                'Upcoming due items, relevant recurring entries, and top payees for the period.',
            dueSoon: 'Due soon',
            recurring: 'Recurring',
            review: 'To review',
            upcomingPlanned: 'Upcoming planned expenses',
            upcomingEmpty: 'No upcoming due items in this period.',
            topPayees: 'Top payees',
            transactions: '{count} transactions',
            transactionOne: '{count} transaction',
            transactionMany: '{count} transactions',
            payeesEmpty: 'No relevant payees to show for the current filter.',
            unspecified: 'Unspecified',
            entryKinds: {
                recurring: 'Recurring',
                scheduled: 'Scheduled',
            },
        },
    },
} as const;
