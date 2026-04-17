export const appMessages = {
    it: {
        name: 'Soamco Budget',
        brand: {
            tagline: 'Pianificazione, movimenti e conti',
        },
        common: {
            close: 'Chiudi',
            cancel: 'Annulla',
            save: 'Salva',
            saved: 'Salvato.',
            edit: 'Modifica',
            delete: 'Elimina',
            remove: 'Rimuovi',
            active: 'Attiva',
            inactive: 'Disattiva',
            loading: 'Caricamento...',
            notAvailable: 'Non disponibile',
            system: 'Sistema',
            account: 'account',
            accountPlural: 'account',
        },
        language: {
            label: 'Lingua',
            options: {
                en: 'English',
                it: 'Italiano',
            },
        },
        cookieConsent: {
            badge: 'Preferenze cookie',
            title: 'Usiamo cookie essenziali e, con il tuo consenso, cookie per preferenze, analisi e marketing.',
            description:
                'I cookie essenziali mantengono sicuro e funzionante il sito. Puoi accettare tutti i cookie, rifiutare quelli non essenziali oppure scegliere categoria per categoria. Le tue preferenze verranno memorizzate su questo dispositivo.',
            alwaysActive: 'Sempre attivi',
            actions: {
                accept: 'Accetta tutti',
                reject: 'Rifiuta non essenziali',
                customize: 'Personalizza',
                essentialOnly: 'Solo essenziali',
                save: 'Salva preferenze',
            },
            preferences: {
                badge: 'Centro preferenze',
                title: 'Gestisci il consenso ai cookie',
                description:
                    'Puoi aggiornare in qualsiasi momento quali categorie di cookie autorizzare. Le modifiche saranno salvate e riutilizzate nelle visite successive.',
            },
            categories: {
                necessary: {
                    title: 'Cookie essenziali',
                    description:
                        'Necessari per sicurezza, sessione, navigazione e funzionamento tecnico del sito.',
                },
                preferences: {
                    title: 'Cookie di preferenza',
                    description:
                        'Memorizzano scelte come lingua, aspetto e altre preferenze di interfaccia.',
                },
                analytics: {
                    title: 'Cookie analitici',
                    description:
                        'Ci aiutano a capire in forma aggregata come viene usato il sito per migliorare contenuti e prestazioni.',
                },
                marketing: {
                    title: 'Cookie di marketing',
                    description:
                        'Utili per campagne, attribuzione e messaggi promozionali più coerenti, se mai abilitati.',
                },
            },
        },
        appearance: {
            light: 'Chiaro',
            dark: 'Scuro',
            system: 'Sistema',
        },
        periods: {
            all: 'Tutto',
            months: {
                short: {
                    1: 'Gen',
                    2: 'Feb',
                    3: 'Mar',
                    4: 'Apr',
                    5: 'Mag',
                    6: 'Giu',
                    7: 'Lug',
                    8: 'Ago',
                    9: 'Set',
                    10: 'Ott',
                    11: 'Nov',
                    12: 'Dic',
                },
            },
        },
        enums: {
            categoryGroups: {
                income: 'Entrate',
                expense: 'Spese',
                bill: 'Bollette',
                debt: 'Debiti',
                saving: 'Risparmi',
                tax: 'Tasse',
                investment: 'Investimenti',
                transfer: 'Giroconti',
                remaining: 'Da allocare',
                other: 'Altre categorie',
            },
            transactionTypes: {
                income: 'Entrata',
                expense: 'Spesa',
                bill: 'Bolletta',
                debt: 'Debito',
                saving: 'Risparmio',
                transfer: 'Giroconto',
            },
            balanceSources: {
                manual: 'Manuale',
                import: 'Importazione',
                system: 'Sistema',
            },
        },
        userMenu: {
            admin: 'Admin',
            settings: 'Impostazioni',
            logout: 'Esci',
            leaveImpersonation: "Esci dall'impersonation",
            languageSaving: 'Salvataggio lingua in corso...',
            version: {
                ariaLabel: 'Informazioni versione applicativo {version}',
                copy: 'Copia il numero versione {version}',
                changelog: 'Changelog',
            },
        },
        sessionWarning: {
            title: 'Sessione in scadenza',
            message:
                "Non rileviamo attività da un po'. Verrai disconnesso tra {countdown} se non continui a usare l'app.",
            expiredTitle: 'Sessione scaduta',
            expiredMessage:
                'La sessione è terminata. Accedi di nuovo per continuare.',
            checkingMessage:
                'Stiamo verificando se la sessione è ancora valida prima di confermare la scadenza.',
            keepAlive: 'Resta connesso',
            logout: 'Esci',
            reload: 'Ricarica pagina',
            signInAgain: 'Accedi di nuovo',
            home: 'Vai alla home',
            checkingLabel: 'Verifica sessione...',
            keepAliveError:
                'Non siamo riusciti a rinnovare la sessione. Prova a ricaricare la pagina.',
        },
        maintenance: {
            kicker: 'Manutenzione',
            title: 'Siamo in manutenzione',
            message:
                'Stiamo effettuando un aggiornamento o un intervento tecnico. Torna tra poco.',
            status: "L'app è temporaneamente bloccata. La schermata si aggiornerà automaticamente quando la manutenzione terminerà.",
        },
        shell: {
            quickActions: 'Azioni rapide',
            headerContext: 'Contesto operativo',
            statusBaseCurrency: 'Valuta base',
            statusFormatLocale: 'Formato',
            statusActiveYear: 'Anno attivo',
            statusCurrentPeriod: 'Periodo',
            openQuickActions: 'Apri azioni rapide',
            expandInfo: 'Espandi dettagli pagina',
            collapseInfo: 'Riduci dettagli pagina',
            compactSummary: '+{count} dettagli',
            userMenu: {
                open: 'Apri menu utente',
                account: 'Profilo',
            },
            notifications: {
                open: 'Apri notifiche',
                title: 'Notifiche',
                unread: '{count} nuove',
                subtitle:
                    'Aggiornamenti recenti del tuo account, pronti da aprire o segnare come letti.',
                now: 'Ora',
                newLabel: 'Nuova',
                openItem: 'Apri',
                viewAll: 'Vedi tutte',
                markAsRead: 'Segna come letta',
                markAllAsRead: 'Segna tutte come lette',
                empty: {
                    title: 'Nessuna notifica',
                    description:
                        'Quando arriveranno nuovi aggiornamenti li troverai qui.',
                },
            },
            notificationsPage: {
                title: 'Notifiche',
                description:
                    'Storico recente delle tue notifiche, con stato letta e azioni disponibili.',
                unreadBadge: '{count} non lette',
                standardLabel: 'Standard',
                richLabel: 'In evidenza',
                empty: {
                    title: 'Nessuna notifica disponibile',
                    description:
                        'Le nuove comunicazioni appariranno qui appena saranno disponibili.',
                },
                actions: {
                    backToDashboard: 'Torna alla dashboard',
                    markAllAsRead: 'Segna tutte come lette',
                    markAsRead: 'Segna come letta',
                },
            },
            footerTagline:
                'Shell applicativa condivisa per controllo operativo, saldi e configurazione.',
            footerVersion: 'Versione {version}',
            footerEnvironment: 'Ambiente {environment}',
            footerLinks: {
                dashboard: 'Dashboard',
                transactions: 'Registro',
                imports: 'Import',
                settings: 'Impostazioni',
            },
            pages: {
                dashboard: {
                    description:
                        'Vista operativa dei saldi, degli scostamenti e dei segnali principali del periodo.',
                },
                planning: {
                    description:
                        'Pianificazione mensile di budget e allocazioni con confronto immediato rispetto ai dati reali.',
                },
                transactions: {
                    description:
                        'Registro operativo del mese con inserimento rapido, controllo del saldo e dettaglio dei movimenti.',
                },
                recurring: {
                    description:
                        'Movimenti programmati del periodo attivo, con calendario mensile principale e lista operativa delle occorrenze previste.',
                },
                imports: {
                    description:
                        'Importa, rivedi e consolida i movimenti mantenendo coerenza con conti e categorie.',
                },
                accounts: {
                    description:
                        'Gestisci conti, carte e aperture contabili mantenendo struttura e stato operativo allineati.',
                },
                references: {
                    description:
                        'Organizza i riferimenti per arricchire movimenti, report e collegamenti operativi.',
                },
                settings: {
                    description:
                        'Configura preferenze, sicurezza e moduli trasversali dell’ambiente di lavoro.',
                },
                admin: {
                    description:
                        'Area amministrativa per supervisione utenti, ruoli e attività di piattaforma.',
                },
                generic: {
                    description:
                        'Contesto operativo condiviso con navigazione, azioni rapide e stato applicativo sempre disponibili.',
                },
            },
            actions: {
                newTransaction: 'Nuova registrazione',
                newRecurringEntry: 'Nuova ricorrenza',
                newAccount: 'Nuovo conto',
                newBank: 'Nuova banca',
                newReference: 'Nuovo riferimento',
                importTransactions: 'Importa movimenti',
            },
        },
        pwa: {
            offline: {
                title: 'Sei offline',
                description:
                    "L'interfaccia già caricata resta disponibile, ma per sincronizzare dati e novità serve di nuovo la connessione.",
            },
            update: {
                title: 'Nuova versione disponibile',
                description:
                    'Abbiamo scaricato una build più recente. Aggiorna quando hai finito l’operazione in corso per evitare di restare su contenuti superati.',
                action: 'Aggiorna ora',
                applying: 'Aggiornamento...',
            },
        },
    },
    en: {
        name: 'Soamco Budget',
        brand: {
            tagline: 'Planning, transactions and accounts',
        },
        common: {
            close: 'Close',
            cancel: 'Cancel',
            save: 'Save',
            saved: 'Saved.',
            edit: 'Edit',
            delete: 'Delete',
            remove: 'Remove',
            active: 'Active',
            inactive: 'Inactive',
            loading: 'Loading...',
            notAvailable: 'Not available',
            system: 'System',
            account: 'account',
            accountPlural: 'accounts',
        },
        language: {
            label: 'Language',
            options: {
                en: 'English',
                it: 'Italiano',
            },
        },
        cookieConsent: {
            badge: 'Cookie preferences',
            title: 'We use essential cookies and, with your consent, preference, analytics, and marketing cookies.',
            description:
                'Essential cookies keep the site secure and functional. You can accept all cookies, reject non-essential cookies, or choose category by category. Your preferences will be stored on this device.',
            alwaysActive: 'Always active',
            actions: {
                accept: 'Accept all',
                reject: 'Reject non-essential',
                customize: 'Customize',
                essentialOnly: 'Essential only',
                save: 'Save preferences',
            },
            preferences: {
                badge: 'Preference center',
                title: 'Manage cookie consent',
                description:
                    'You can update which categories of cookies you allow at any time. Your changes will be saved and reused on future visits.',
            },
            categories: {
                necessary: {
                    title: 'Essential cookies',
                    description:
                        'Required for security, session handling, navigation, and technical site operation.',
                },
                preferences: {
                    title: 'Preference cookies',
                    description:
                        'Remember choices such as language, appearance, and other interface preferences.',
                },
                analytics: {
                    title: 'Analytics cookies',
                    description:
                        'Help us understand, in aggregated form, how the site is used so we can improve content and performance.',
                },
                marketing: {
                    title: 'Marketing cookies',
                    description:
                        'Useful for campaigns, attribution, and more relevant promotional messaging if ever enabled.',
                },
            },
        },
        appearance: {
            light: 'Light',
            dark: 'Dark',
            system: 'System',
        },
        periods: {
            all: 'All',
            months: {
                short: {
                    1: 'Jan',
                    2: 'Feb',
                    3: 'Mar',
                    4: 'Apr',
                    5: 'May',
                    6: 'Jun',
                    7: 'Jul',
                    8: 'Aug',
                    9: 'Sep',
                    10: 'Oct',
                    11: 'Nov',
                    12: 'Dec',
                },
            },
        },
        enums: {
            categoryGroups: {
                income: 'Income',
                expense: 'Expenses',
                bill: 'Bills',
                debt: 'Debts',
                saving: 'Savings',
                tax: 'Taxes',
                investment: 'Investments',
                transfer: 'Transfers',
                remaining: 'To allocate',
                other: 'Other categories',
            },
            transactionTypes: {
                income: 'Income',
                expense: 'Expense',
                bill: 'Bill',
                debt: 'Debt',
                saving: 'Saving',
                transfer: 'Transfer',
            },
            balanceSources: {
                manual: 'Manual',
                import: 'Import',
                system: 'System',
            },
        },
        userMenu: {
            admin: 'Admin',
            settings: 'Settings',
            logout: 'Log out',
            leaveImpersonation: 'Leave impersonation',
            languageSaving: 'Saving language...',
            version: {
                ariaLabel: 'Application version information {version}',
                copy: 'Copy version number {version}',
                changelog: 'Changelog',
            },
        },
        sessionWarning: {
            title: 'Session expiring soon',
            message:
                'We haven’t detected activity for a while. You’ll be signed out in {countdown} unless you continue using the app.',
            expiredTitle: 'Session expired',
            expiredMessage:
                'Your session has ended. Please sign in again to continue.',
            checkingMessage:
                'We are verifying whether your session is still valid before confirming expiry.',
            keepAlive: 'Stay signed in',
            logout: 'Sign out',
            reload: 'Reload page',
            signInAgain: 'Sign in again',
            home: 'Go to home',
            checkingLabel: 'Checking session...',
            keepAliveError:
                'We could not renew the session. Please reload the page and try again.',
        },
        maintenance: {
            kicker: 'Maintenance',
            title: 'We’re under maintenance',
            message:
                'We’re performing an update or technical maintenance. Please check back soon.',
            status: 'The app is temporarily locked. This screen will update automatically when maintenance ends.',
        },
        shell: {
            quickActions: 'Quick actions',
            headerContext: 'Operational context',
            statusBaseCurrency: 'Base currency',
            statusFormatLocale: 'Format',
            statusActiveYear: 'Active year',
            statusCurrentPeriod: 'Period',
            openQuickActions: 'Open quick actions',
            expandInfo: 'Expand page details',
            collapseInfo: 'Collapse page details',
            compactSummary: '+{count} details',
            userMenu: {
                open: 'Open user menu',
                account: 'Profile',
            },
            notifications: {
                open: 'Open notifications',
                title: 'Notifications',
                unread: '{count} new',
                subtitle:
                    'Recent updates for your account, ready to open or mark as read.',
                now: 'Now',
                newLabel: 'New',
                openItem: 'Open',
                viewAll: 'View all',
                markAsRead: 'Mark as read',
                markAllAsRead: 'Mark all as read',
                empty: {
                    title: 'No notifications yet',
                    description:
                        'New updates will appear here as soon as they arrive.',
                },
            },
            notificationsPage: {
                title: 'Notifications',
                description:
                    'Recent history of your notifications, with read status and available actions.',
                unreadBadge: '{count} unread',
                standardLabel: 'Standard',
                richLabel: 'Featured',
                empty: {
                    title: 'No notifications available',
                    description:
                        'New communications will appear here as soon as they are available.',
                },
                actions: {
                    backToDashboard: 'Back to dashboard',
                    markAllAsRead: 'Mark all as read',
                    markAsRead: 'Mark as read',
                },
            },
            footerTagline:
                'Shared application shell for operations, balances, and configuration.',
            footerVersion: 'Version {version}',
            footerEnvironment: 'Environment {environment}',
            footerLinks: {
                dashboard: 'Dashboard',
                transactions: 'Register',
                imports: 'Imports',
                settings: 'Settings',
            },
            pages: {
                dashboard: {
                    description:
                        'Operational overview of balances, variances, and the main signals for the current period.',
                },
                planning: {
                    description:
                        'Monthly budget and allocation planning with immediate comparison against real data.',
                },
                transactions: {
                    description:
                        'Monthly operational register with fast entry, balance control, and movement detail.',
                },
                recurring: {
                    description:
                        'Scheduled movements for the active period, with a primary monthly calendar and an operational list of expected occurrences.',
                },
                imports: {
                    description:
                        'Import, review, and consolidate movements while keeping accounts and categories aligned.',
                },
                accounts: {
                    description:
                        'Manage accounts, cards, and opening balances while keeping structure and operating status aligned.',
                },
                references: {
                    description:
                        'Organize references to enrich movements, reports, and operational links.',
                },
                settings: {
                    description:
                        'Configure preferences, security, and cross-cutting workspace modules.',
                },
                admin: {
                    description:
                        'Administrative area for supervising users, roles, and platform activity.',
                },
                generic: {
                    description:
                        'Shared operational context with navigation, quick actions, and application status always available.',
                },
            },
            actions: {
                newTransaction: 'New entry',
                newRecurringEntry: 'New recurring entry',
                newAccount: 'New account',
                newBank: 'New bank',
                newReference: 'New reference',
                importTransactions: 'Import transactions',
            },
        },
        pwa: {
            offline: {
                title: 'You are offline',
                description:
                    'The loaded interface is still available, but reconnect to sync fresh data and new releases.',
            },
            update: {
                title: 'A new version is ready',
                description:
                    'A newer build has been downloaded. Refresh when you are done with the current action so you do not stay on stale content.',
                action: 'Refresh now',
                applying: 'Refreshing...',
            },
        },
    },
} as const;
