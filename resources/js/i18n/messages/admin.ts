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
            automation: 'Automazioni',
        },
        summaries: {
            overview: 'Panoramica degli strumenti amministrativi',
            users: 'Accesso rapido alla futura gestione utenti',
            activityLog: 'Tracciamento operativo e audit in arrivo',
            automation: 'Controllo pipeline, run e retry delle automazioni',
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
                automation: {
                    title: 'Automazioni',
                    description:
                        'Dashboard operativa per pipeline, run manuali, retry e stato di salute delle automazioni.',
                    status: 'Operativa',
                },
            },
            empty: {
                title: 'Base admin pronta',
                description:
                    'La navigazione e le pagine iniziali sono disponibili. La logica operativa verra implementata nei task successivi.',
            },
        },
        automation: {
            title: 'Automazioni',
            description:
                'Controlla lo stato delle pipeline, monitora i run recenti e intervieni con esecuzione manuale o rilancio quando supportato.',
            breadcrumbRun: 'Dettaglio run',
            flash: {
                successTitle: 'Operazione completata',
                errorTitle: 'Operazione non riuscita',
            },
            actions: {
                runNow: 'Esegui ora',
                retry: 'Rilancia',
                runInfo: 'Info run',
                backToAutomations: 'Torna alle automazioni',
                close: 'Chiudi',
                confirmRetry: 'Conferma rilancio',
            },
            overview: {
                title: 'Overview pipeline',
                description:
                    'Ogni card usa il payload backend disponibile per mostrare stato, criticita e ultimo run senza duplicare logica lato client.',
                empty: 'Nessuna pipeline disponibile.',
                critical: 'Critica',
                enabled: 'Abilitata',
                disabled: 'Disabilitata',
                latestRun: 'Ultimo run',
                latestTrigger: 'Trigger ultimo run',
                latestDuration: 'Durata ultimo run',
                alerting: 'Alert su failure',
                staleAfter: 'Attesa massima',
                neverRan: 'Mai eseguita',
                noError: 'Nessun errore recente',
                minutes: '{count} min',
            },
            filters: {
                title: 'Filtri run',
                description:
                    'Filtra la lista usando pipeline, stato e trigger type già esposti dal backend admin.',
                reset: 'Reimposta filtri',
                pipelineLabel: 'Pipeline',
                pipelinePlaceholder: 'Tutte le pipeline',
                statusLabel: 'Stato',
                statusPlaceholder: 'Tutti gli stati',
                triggerLabel: 'Trigger type',
                triggerPlaceholder: 'Tutti i trigger',
            },
            list: {
                title: 'Run recenti',
                summary: 'Run {from}-{to} di {total}',
                emptySummary: 'Nessun run disponibile',
                description:
                    'La tabella mostra metriche operative, host e azioni disponibili solo quando il backend permette il retry.',
                loading: 'Aggiornamento run in corso...',
            },
            table: {
                pipeline: 'Pipeline',
                status: 'Stato',
                triggerType: 'Trigger type',
                startedAt: 'Started at',
                finishedAt: 'Finished at',
                duration: 'Durata',
                processedCount: 'Processate',
                successCount: 'Successi',
                warningCount: 'Warning',
                errorCount: 'Errori',
                host: 'Host',
                attempt: 'Tentativo',
                actions: 'Azioni',
            },
            mobile: {
                title: 'Run',
            },
            empty: {
                title: 'Nessun run trovato',
                description:
                    'Modifica i filtri o lancia una pipeline manualmente per popolare la vista operativa.',
            },
            pagination: {
                previous: 'Precedente',
                next: 'Successiva',
                page: 'Pagina {current} di {last}',
            },
            show: {
                title: 'Dettaglio run',
                description:
                    'Vista amministrativa del run con metriche, contesto, risultato ed eventuale errore tecnico.',
                sections: {
                    summary: 'Summary',
                    metrics: 'Metriche',
                    errorDetails: 'Dettagli errore',
                    context: 'Context',
                    result: 'Result',
                },
                labels: {
                    pipeline: 'Pipeline',
                    status: 'Stato',
                    triggerType: 'Trigger type',
                    startedAt: 'Started at',
                    finishedAt: 'Finished at',
                    duration: 'Durata',
                    attempt: 'Tentativo',
                    host: 'Host',
                    jobClass: 'Job class',
                    uuid: 'UUID',
                    batchId: 'Batch ID',
                    processedCount: 'Processate',
                    successCount: 'Successi',
                    warningCount: 'Warning',
                    errorCount: 'Errori',
                    errorMessage: 'Messaggio errore',
                    exceptionClass: 'Classe eccezione',
                },
                emptyPayload: 'Nessun dato disponibile.',
                noError: 'Questo run non ha riportato errori tecnici.',
            },
            dialogs: {
                retryTitle: 'Rilancia run',
                retryDescription:
                    'Stai per rilanciare il run {run} della pipeline {pipeline}. L’azione userà il backend admin già disponibile.',
            },
            statuses: {
                healthy: 'Healthy',
                running: 'In esecuzione',
                warning: 'Warning',
                failed: 'Errore',
                stale: 'Obsoleta',
                stuck: 'Bloccata',
                never_ran: 'Mai eseguita',
                disabled: 'Disabilitata',
                timed_out: 'Timeout',
                unknown: 'Sconosciuto',
                pending: 'In attesa',
                success: 'Successo',
                skipped: 'Saltata',
            },
            triggers: {
                scheduled: 'Schedulato',
                manual: 'Manuale',
                retry: 'Retry',
                system: 'Sistema',
            },
            pipelines: {
                recurring_pipeline: 'Pipeline ricorrenze',
                notifications_pipeline: 'Pipeline notifiche',
                reports_pipeline: 'Pipeline report',
            },
            common: {
                notAvailable: 'N/D',
                emptyHost: 'Host non disponibile',
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
                subscriptionStatus: 'Stato subscription',
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
                protectedAdminUser: 'Utente admin protetto: azioni sensibili bloccate.',
                noImpersonationConsent:
                    'Impersonate non disponibile: l’utente non ha dato il consenso al supporto assistito.',
                limitedActions: 'Alcune azioni non sono disponibili per lo stato attuale di questo utente.',
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
                previous: 'Precedente',
                next: 'Successiva',
                page: 'Pagina {current} di {last}',
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
            automation: 'Automation',
        },
        summaries: {
            overview: 'Overview of administrative tools',
            users: 'Quick access to the upcoming user management area',
            activityLog: 'Operational tracking and audit trail coming soon',
            automation: 'Pipeline health, runs, and retry controls',
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
                automation: {
                    title: 'Automation',
                    description:
                        'Operational dashboard for pipelines, manual runs, retries, and automation health monitoring.',
                    status: 'Operational',
                },
            },
            empty: {
                title: 'Admin base is ready',
                description:
                    'Navigation and initial pages are available. Operational logic will be implemented in the next tasks.',
            },
        },
        automation: {
            title: 'Automation',
            description:
                'Inspect pipeline health, review recent runs, and trigger manual execution or retry when the backend allows it.',
            breadcrumbRun: 'Run details',
            flash: {
                successTitle: 'Action completed',
                errorTitle: 'Action failed',
            },
            actions: {
                runNow: 'Run now',
                retry: 'Retry',
                runInfo: 'Run info',
                backToAutomations: 'Back to automation',
                close: 'Close',
                confirmRetry: 'Confirm retry',
            },
            overview: {
                title: 'Pipeline overview',
                description:
                    'Each card uses the existing backend payload to show health, criticality, and latest run data without duplicating server logic.',
                empty: 'No pipelines available.',
                critical: 'Critical',
                enabled: 'Enabled',
                disabled: 'Disabled',
                latestRun: 'Latest run',
                latestTrigger: 'Latest trigger',
                latestDuration: 'Latest duration',
                alerting: 'Alert on failure',
                staleAfter: 'Expected interval',
                neverRan: 'Never ran',
                noError: 'No recent errors',
                minutes: '{count} min',
            },
            filters: {
                title: 'Run filters',
                description:
                    'Filter the list using the pipeline, status, and trigger type already exposed by the admin backend.',
                reset: 'Reset filters',
                pipelineLabel: 'Pipeline',
                pipelinePlaceholder: 'All pipelines',
                statusLabel: 'Status',
                statusPlaceholder: 'All statuses',
                triggerLabel: 'Trigger type',
                triggerPlaceholder: 'All triggers',
            },
            list: {
                title: 'Recent runs',
                summary: 'Runs {from}-{to} of {total}',
                emptySummary: 'No runs available',
                description:
                    'The table shows operational metrics, host, and actions only when the backend marks the run as retryable.',
                loading: 'Refreshing automation runs...',
            },
            table: {
                pipeline: 'Pipeline',
                status: 'Status',
                triggerType: 'Trigger type',
                startedAt: 'Started at',
                finishedAt: 'Finished at',
                duration: 'Duration',
                processedCount: 'Processed',
                successCount: 'Success',
                warningCount: 'Warnings',
                errorCount: 'Errors',
                host: 'Host',
                attempt: 'Attempt',
                actions: 'Actions',
            },
            mobile: {
                title: 'Runs',
            },
            empty: {
                title: 'No runs found',
                description:
                    'Adjust the filters or dispatch a pipeline manually to populate the operational view.',
            },
            pagination: {
                previous: 'Previous',
                next: 'Next',
                page: 'Page {current} of {last}',
            },
            show: {
                title: 'Run details',
                description:
                    'Administrative view of the run with metrics, context, result, and technical error details when present.',
                sections: {
                    summary: 'Summary',
                    metrics: 'Metrics',
                    errorDetails: 'Error details',
                    context: 'Context',
                    result: 'Result',
                },
                labels: {
                    pipeline: 'Pipeline',
                    status: 'Status',
                    triggerType: 'Trigger type',
                    startedAt: 'Started at',
                    finishedAt: 'Finished at',
                    duration: 'Duration',
                    attempt: 'Attempt',
                    host: 'Host',
                    jobClass: 'Job class',
                    uuid: 'UUID',
                    batchId: 'Batch ID',
                    processedCount: 'Processed',
                    successCount: 'Success',
                    warningCount: 'Warnings',
                    errorCount: 'Errors',
                    errorMessage: 'Error message',
                    exceptionClass: 'Exception class',
                },
                emptyPayload: 'No data available.',
                noError: 'This run did not report technical errors.',
            },
            dialogs: {
                retryTitle: 'Retry run',
                retryDescription:
                    'You are about to retry run {run} for pipeline {pipeline}. The action uses the existing admin backend endpoint.',
            },
            statuses: {
                healthy: 'Healthy',
                running: 'Running',
                warning: 'Warning',
                failed: 'Failed',
                stale: 'Stale',
                stuck: 'Stuck',
                never_ran: 'Never ran',
                disabled: 'Disabled',
                timed_out: 'Timed out',
                unknown: 'Unknown',
                pending: 'Pending',
                success: 'Success',
                skipped: 'Skipped',
            },
            triggers: {
                scheduled: 'Scheduled',
                manual: 'Manual',
                retry: 'Retry',
                system: 'System',
            },
            pipelines: {
                recurring_pipeline: 'Recurring pipeline',
                notifications_pipeline: 'Notifications pipeline',
                reports_pipeline: 'Reports pipeline',
            },
            common: {
                notAvailable: 'N/A',
                emptyHost: 'Host unavailable',
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
                subscriptionStatus: 'Subscription status',
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
                protectedAdminUser: 'Protected admin user: sensitive actions are blocked.',
                noImpersonationConsent:
                    'Impersonation unavailable: the user has not granted assisted-support consent.',
                limitedActions: 'Some actions are unavailable for the current state of this user.',
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
                previous: 'Previous',
                next: 'Next',
                page: 'Page {current} of {last}',
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
