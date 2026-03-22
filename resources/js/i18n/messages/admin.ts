export const adminMessages = {
    it: {
        title: 'Admin',
        description:
            'Area di amministrazione con strumenti e sezioni riservate agli utenti autorizzati.',
        badge: 'Spazio riservato',
        sections: {
            overview: 'Admin',
            users: 'Utenti',
            activityLog: 'Activity log',
        },
        summaries: {
            overview: 'Panoramica degli strumenti amministrativi',
            users: 'Accesso rapido alla futura gestione utenti',
            activityLog: 'Tracciamento operativo e audit in arrivo',
        },
        shell: {
            eyebrow: 'Controlli amministrativi',
            title: 'Presidio operativo dedicato',
            description:
                'Questa shell prepara navigazione, struttura e contenitori per le future funzionalita amministrative.',
        },
        overview: {
            title: 'Panoramica Admin',
            description:
                'Punto di ingresso dell’area Admin con accesso alle sezioni principali e stato di avanzamento.',
            cards: {
                users: {
                    title: 'Users',
                    description:
                        'Sezione pronta per accogliere gestione ruoli, sospensioni e operazioni sugli account.',
                    status: 'Struttura pronta',
                },
                activityLog: {
                    title: 'Activity log',
                    description:
                        'Sezione predisposta per audit trail, eventi di sicurezza e storico amministrativo.',
                    status: 'Placeholder attivo',
                },
            },
            empty: {
                title: 'Base admin pronta',
                description:
                    'La navigazione e le pagine iniziali sono disponibili. La logica operativa verra implementata nei task successivi.',
            },
        },
        users: {
            title: 'Admin users',
            description:
                'Monitora utenti, ruoli e stato account usando i controlli amministrativi già disponibili.',
            headerNote:
                'I profili admin restano visibili ma protetti da modifiche sensibili. Il consenso di accesso assistito è solo informativo.',
            summary: {
                total: 'Utenti visibili',
                active: 'Attivi in pagina',
                staff: 'Staff in pagina',
                impersonable: 'Consenso supporto attivo',
            },
            filters: {
                title: 'Filtri utenti',
                description:
                    'Usa i filtri backend esistenti per restringere la lista senza cambiare il contratto dati.',
                reset: 'Reimposta filtri',
                searchLabel: 'Ricerca',
                searchPlaceholder: 'Cerca per nome, cognome o email',
                roleLabel: 'Ruolo',
                rolePlaceholder: 'Seleziona un ruolo',
                statusLabel: 'Stato',
                statusPlaceholder: 'Seleziona uno stato',
                planLabel: 'Piano',
                planPlaceholder: 'Seleziona un piano',
            },
            list: {
                summary: 'Utenti {from}-{to} di {total}',
                emptySummary: 'Nessun utente disponibile',
                description:
                    'Le azioni sono mostrate solo quando il backend le consente per il profilo selezionato.',
                loading: 'Aggiornamento lista utenti in corso...',
            },
            table: {
                user: 'Utente',
                roles: 'Ruoli',
                status: 'Stato account',
                plan: 'Piano',
                emailVerification: 'Email',
                impersonationConsent: 'Consenso supporto',
                actions: 'Azioni',
            },
            roles: {
                admin: 'Admin',
                staff: 'Staff',
                user: 'User',
            },
            rolesDescriptions: {
                staff: 'Può operare come membro del team interno senza privilegi admin completi.',
                user: 'Mantiene l’accesso standard all’area personale dell’app.',
            },
            plans: {
                free: 'Free',
            },
            labels: {
                protectedUser: 'Protetto',
                readOnlyUser: 'Utente non modificabile',
                emailVerified: 'Email verificata',
                emailNotVerified: 'Email non verificata',
                impersonationAllowed: 'Supporto autorizzato',
                impersonationDenied: 'Supporto non autorizzato',
                planUnavailable: 'Piano non disponibile',
            },
            actions: {
                ban: 'Banna',
                suspend: 'Sospendi',
                reactivate: 'Riattiva',
                roles: 'Aggiorna ruoli',
                impersonate: 'Accedi per supporto',
            },
            dialogs: {
                reasonLabel: 'Motivazione interna',
                reasonPlaceholder:
                    'Aggiungi una nota facoltativa per documentare il motivo dell’azione.',
                ban: {
                    title: 'Banna utente',
                    description:
                        'Stai per bannare {user}. L’utente perderà l’accesso finché non verrà riattivato manualmente.',
                },
                suspend: {
                    title: 'Sospendi utente',
                    description:
                        'Stai per sospendere {user}. Usa questa azione per bloccare temporaneamente l’account.',
                },
                reactivate: {
                    title: 'Riattiva utente',
                    description:
                        'Stai per riattivare {user}. L’account tornerà operativo con stato attivo.',
                },
                roles: {
                    title: 'Aggiorna ruoli utente',
                    description:
                        'Seleziona i ruoli applicabili a {user}. I ruoli disponibili seguono il backend attuale.',
                    helper:
                        'L’utente admin non è modificabile da questa schermata. Per gli altri profili puoi usare solo user e staff.',
                },
            },
            feedback: {
                successTitle: 'Operazione completata',
                errorTitle: 'Operazione non disponibile',
            },
            pagination: {
                summary: 'Navigazione paginata utenti',
            },
            empty: {
                title: 'Nessun utente trovato con i filtri attuali',
                description:
                    'Modifica ricerca, ruolo, stato o piano per ripristinare la lista utenti disponibile dal backend.',
            },
        },
        activityLog: {
            title: 'Admin activity log',
            description:
                'Vista base per il futuro registro attività e gli eventi amministrativi.',
            empty: {
                title: 'Registro attività in preparazione',
                description:
                    'Qui arriveranno timeline, filtri e dettagli sugli eventi di audit quando la logica sara pronta.',
            },
        },
    },
    en: {
        title: 'Admin',
        description:
            'Administration area with tools and sections reserved for authorized users.',
        badge: 'Restricted area',
        sections: {
            overview: 'Admin',
            users: 'Users',
            activityLog: 'Activity log',
        },
        summaries: {
            overview: 'Overview of administrative tools',
            users: 'Quick access to the upcoming user management area',
            activityLog: 'Operational tracking and audit trail coming soon',
        },
        shell: {
            eyebrow: 'Administrative controls',
            title: 'Dedicated operational space',
            description:
                'This shell prepares navigation, structure, and containers for future administrative features.',
        },
        overview: {
            title: 'Admin overview',
            description:
                'Entry point for the Admin area with access to the main sections and their current status.',
            cards: {
                users: {
                    title: 'Users',
                    description:
                        'Section ready to host role management, suspensions, and account operations.',
                    status: 'Structure ready',
                },
                activityLog: {
                    title: 'Activity log',
                    description:
                        'Section prepared for audit trail, security events, and administrative history.',
                    status: 'Placeholder active',
                },
            },
            empty: {
                title: 'Admin base is ready',
                description:
                    'Navigation and initial pages are available. Operational logic will be implemented in the next tasks.',
            },
        },
        users: {
            title: 'Admin users',
            description:
                'Monitor users, roles, and account status with the administrative controls already available.',
            headerNote:
                'Admin profiles remain visible but protected from sensitive changes. Assisted access consent is read-only here.',
            summary: {
                total: 'Visible users',
                active: 'Active on page',
                staff: 'Staff on page',
                impersonable: 'Support consent enabled',
            },
            filters: {
                title: 'User filters',
                description:
                    'Use the existing backend filters to narrow the list without changing the data contract.',
                reset: 'Reset filters',
                searchLabel: 'Search',
                searchPlaceholder: 'Search by name, surname, or email',
                roleLabel: 'Role',
                rolePlaceholder: 'Select a role',
                statusLabel: 'Status',
                statusPlaceholder: 'Select a status',
                planLabel: 'Plan',
                planPlaceholder: 'Select a plan',
            },
            list: {
                summary: 'Users {from}-{to} of {total}',
                emptySummary: 'No users available',
                description:
                    'Actions are shown only when the backend allows them for the selected profile.',
                loading: 'Refreshing user list...',
            },
            table: {
                user: 'User',
                roles: 'Roles',
                status: 'Account status',
                plan: 'Plan',
                emailVerification: 'Email',
                impersonationConsent: 'Support consent',
                actions: 'Actions',
            },
            roles: {
                admin: 'Admin',
                staff: 'Staff',
                user: 'User',
            },
            rolesDescriptions: {
                staff: 'Can operate as an internal team member without full admin privileges.',
                user: 'Keeps the standard access level to the personal app area.',
            },
            plans: {
                free: 'Free',
            },
            labels: {
                protectedUser: 'Protected',
                readOnlyUser: 'User not editable',
                emailVerified: 'Verified email',
                emailNotVerified: 'Unverified email',
                impersonationAllowed: 'Support allowed',
                impersonationDenied: 'Support denied',
                planUnavailable: 'Plan unavailable',
            },
            actions: {
                ban: 'Ban',
                suspend: 'Suspend',
                reactivate: 'Reactivate',
                roles: 'Update roles',
                impersonate: 'Access for support',
            },
            dialogs: {
                reasonLabel: 'Internal reason',
                reasonPlaceholder:
                    'Add an optional note to document the reason for this action.',
                ban: {
                    title: 'Ban user',
                    description:
                        'You are about to ban {user}. The user will lose access until manually reactivated.',
                },
                suspend: {
                    title: 'Suspend user',
                    description:
                        'You are about to suspend {user}. Use this action to temporarily block the account.',
                },
                reactivate: {
                    title: 'Reactivate user',
                    description:
                        'You are about to reactivate {user}. The account will return to active status.',
                },
                roles: {
                    title: 'Update user roles',
                    description:
                        'Select the roles that apply to {user}. Available roles follow the current backend contract.',
                    helper:
                        'Admin users cannot be edited from this screen. For other profiles you can only assign user and staff.',
                },
            },
            feedback: {
                successTitle: 'Action completed',
                errorTitle: 'Action unavailable',
            },
            pagination: {
                summary: 'Paginated user navigation',
            },
            empty: {
                title: 'No users match the current filters',
                description:
                    'Adjust search, role, status, or plan to restore the user list returned by the backend.',
            },
        },
        activityLog: {
            title: 'Admin activity log',
            description:
                'Base view for the future activity registry and administrative events.',
            empty: {
                title: 'Activity registry in progress',
                description:
                    'Timelines, filters, and audit event details will appear here once the logic is ready.',
            },
        },
    },
} as const;
