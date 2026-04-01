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
            changelog: 'Changelog',
            communicationCategories: 'Categorie comunicazioni',
            communicationComposer: 'Composer comunicazioni',
            communicationOutbound: 'Storico invii',
            communicationTemplates: 'Template comunicazioni',
        },
        summaries: {
            overview: 'Panoramica degli strumenti amministrativi',
            users: 'Accesso rapido alla futura gestione utenti',
            activityLog: 'Tracciamento operativo e audit in arrivo',
            automation: 'Controllo pipeline, run e retry delle automazioni',
            changelog:
                'Gestione release, versioning assistito e feed pubblico del changelog',
            communicationCategories:
                'Gestione centrale dei canali disponibili e dei template default per categoria',
            communicationComposer:
                'Invio manuale admin con preview reale delle comunicazioni',
            communicationOutbound:
                'Monitoraggio funzionale degli outbound message e dei relativi esiti',
            communicationTemplates:
                'Gestione template e override globali delle comunicazioni',
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
                changelog: {
                    title: 'Changelog',
                    description:
                        'CRUD admin per release multilingua, sezioni, item e payload pubblico pronto per il frontend.',
                    status: 'Operativo',
                },
                communicationCategories: {
                    title: 'Categorie comunicazioni',
                    description:
                        'Fonte centrale dei mapping categoria, canale e template usati sia dal composer sia dagli invii automatici.',
                    status: 'Operativa',
                },
                communicationComposer: {
                    title: 'Composer comunicazioni',
                    description:
                        'Composizione e invio manuale admin appoggiati al motore reale di categorie, template e composer backend.',
                    status: 'Operativo',
                },
                communicationOutbound: {
                    title: 'Storico invii',
                    description:
                        'Monitoraggio admin degli outbound message con stato, canale, destinatario ed eventuali errori applicativi.',
                    status: 'Operativo',
                },
                communicationTemplates: {
                    title: 'Template comunicazioni',
                    description:
                        'Gestione admin dei template notification e dei relativi override globali.',
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
                configureRun: 'Configura esecuzione',
                retry: 'Rilancia',
                runInfo: 'Info run',
                backToAutomations: 'Torna alle automazioni',
                close: 'Chiudi',
                confirmRetry: 'Conferma rilancio',
                confirmRun: 'Avvia controllo',
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
                    businessSummary: 'Esito operativo',
                    accountResults: 'Dettaglio per carta',
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
                    examinedCount: 'Carte esaminate',
                    dueCount: 'Carte dovute',
                    chargedCount: 'Addebiti creati',
                    skippedCount: 'No-op leciti',
                    notifiedCount: 'Utenti avvisati',
                    errorMessage: 'Messaggio errore',
                    exceptionClass: 'Classe eccezione',
                    account: 'Carta',
                    outcome: 'Esito',
                    detail: 'Dettaglio',
                    cycleEndDate: 'Chiusura ciclo',
                    paymentDueDate: 'Data addebito',
                    chargedAmount: 'Importo addebitato',
                },
                emptyPayload: 'Nessun dato disponibile.',
                noError: 'Questo run non ha riportato errori tecnici.',
                noAccountResults:
                    'Nessun dettaglio carta disponibile per questo run.',
                accountOutcomes: {
                    charged: 'Addebito creato',
                    already_processed: 'Ciclo già processato',
                    zero_amount: 'Nessun importo da addebitare',
                    not_due: 'Non dovuta alla data',
                    autopay_disabled: 'Autopay disattivato',
                    configuration_error: 'Errore configurazione',
                    execution_error: 'Errore reale',
                    due: 'Dovuta',
                },
            },
            dialogs: {
                runTitle: 'Esegui pipeline',
                runDescription:
                    'Puoi lanciare la pipeline {pipeline} subito. Per i controlli carta puoi anche impostare una data di riferimento facoltativa per testare un giorno specifico.',
                referenceDateLabel: 'Data di riferimento',
                referenceDateHelper:
                    'Lascia vuoto per usare la data corrente del server. Valido solo per le pipeline che supportano il test su data.',
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
                credit_card_autopay: 'Controllo addebiti carte',
                notifications_pipeline: 'Pipeline notifiche',
                reports_pipeline: 'Pipeline report',
            },
            common: {
                notAvailable: 'N/D',
                emptyHost: 'Host non disponibile',
            },
        },
        communicationCategories: {
            title: 'Categorie comunicazioni',
            description:
                'Configura a monte quali canali sono davvero disponibili per ogni categoria e quale template default usa ciascun canale.',
            filters: {
                title: 'Ricerca categorie',
                description:
                    'Trova rapidamente le categorie da amministrare senza spostare la logica nel composer runtime.',
                searchLabel: 'Ricerca',
                searchPlaceholder: 'Cerca per nome, chiave o descrizione',
                reset: 'Reimposta',
            },
            list: {
                summary: 'Categorie {from}-{to} di {total}',
                emptySummary: 'Nessuna categoria disponibile',
            },
            sections: {
                general: 'Informazioni generali',
                channelRules: 'Capability condivise',
                channels: 'Canali categoria',
                channelsDescription:
                    'Ogni card riflette insieme disponibilità globale del canale e mapping default category -> channel -> template.',
            },
            labels: {
                key: 'Chiave',
                deliveryMode: 'Delivery mode',
                preferenceMode: 'Preference mode',
                contextType: 'Context type',
                fixedChannel: 'Canale fisso',
                currentTemplate: 'Template corrente',
                globalAvailability: 'Disponibilità globale',
            },
            actions: {
                manageChannels: 'Gestisci canali',
                saveChannels: 'Salva configurazione canali',
                backToCategories: 'Torna alle categorie',
            },
            status: {
                noActiveChannels: 'Nessun canale attivo',
                activeChannels: '{count} canali attivi',
            },
            empty: {
                title: 'Nessuna categoria trovata',
                description:
                    'Modifica la ricerca oppure aggiungi nuove categorie lato backend per popolare questa sezione.',
                noDescription: 'Nessuna descrizione disponibile.',
                noTemplate: 'Nessun template associato',
                noFixedChannel: 'Nessun canale fisso',
            },
            pagination: {
                previous: 'Precedente',
                next: 'Successiva',
                page: 'Pagina {current} di {last}',
            },
            deliveryModes: {
                transactional: 'Transazionale',
                campaign: 'Campagna',
                manual: 'Manuale',
                system: 'Sistema',
            },
            preferenceModes: {
                mandatory: 'Obbligatoria',
                user_configurable: 'Configurabile utente',
                admin_configurable: 'Configurabile admin',
                system: 'Sistema',
            },
            channels: {
                mail: 'Email',
                database: 'Notifiche',
                sms: 'SMS',
                telegram: 'Telegram',
            },
            channelState: {
                enabled: 'Attivo',
                disabled: 'Disattivato',
                fixed: 'Fisso',
                globallyUnavailable: 'Non disponibile globalmente',
            },
            channelHints: {
                globallyAvailable:
                    'Il canale è pronto globalmente e può essere abilitato per questa categoria se ha un template attivo compatibile.',
                globallyUnavailable:
                    'Il canale non è configurato globalmente o il trasporto non è ancora pronto, quindi resta disabilitato.',
            },
            form: {
                enableChannel: 'Abilita canale',
                template: 'Template default',
                templatePlaceholder: 'Seleziona template',
            },
            flags: {
                manualSend: 'Disponibile nel composer admin',
                automaticDispatch: 'Disponibile per dispatch automatico',
                enabled: 'Abilitato',
                disabled: 'Disabilitato',
            },
            feedback: {
                successTitle: 'Configurazione aggiornata',
                errorTitle: 'Aggiornamento non riuscito',
            },
        },
        communicationComposer: {
            eyebrow: 'Invio manuale',
            title: 'Composer comunicazioni',
            description:
                'Composer admin operativo per selezionare categoria, canali, lingua, destinatari e contenuto, con preview reale prima dell’invio.',
            actions: {
                backToAdmin: 'Torna all’admin',
                send: 'Invia comunicazione',
                sending: 'Invio in corso...',
            },
            sections: {
                category: 'Step A — Categoria, canali e lingua',
                recipient: 'Step B — Destinatari',
                content: 'Step C — Contenuto',
                preview: 'Step D — Preview reale',
                send: 'Step E — Invio',
            },
            sectionDescriptions: {
                category:
                    'Mostra categorie compatibili con invio admin, i canali davvero supportati oggi e la lingua da usare.',
                recipient:
                    'La ricerca usa il backend reale e consente selezione singola o multipla di utenti.',
                content:
                    'Puoi usare il template della categoria oppure sovrascrivere il contenuto con un testo custom admin.',
                preview:
                    'La preview arriva dal backend per ogni canale selezionato e usa destinatario campione, lingua e contenuto reali.',
                send: 'L’invio reale crea gli outbound message per tutti i destinatari e i canali selezionati.',
            },
            fields: {
                category: 'Categoria',
                channels: 'Canali',
                locale: 'Lingua invio',
                searchRecipient: 'Cerca destinatario',
                subject: 'Oggetto email',
                title: 'Titolo',
                body: 'Messaggio',
                ctaLabel: 'Testo pulsante',
                ctaUrl: 'Link pulsante',
            },
            placeholders: {
                category: 'Scegli una categoria',
                searchCategory: 'Cerca categoria',
                locale: 'Scegli una lingua',
                searchLocale: 'Cerca lingua',
                searchRecipient: 'Cerca per nome o email',
                subject: 'Usa il subject base se lasci vuoto',
                title: 'Usa il titolo base se lasci vuoto',
                body: 'Scrivi il contenuto amministrativo da inviare',
                ctaLabel: 'Usa la CTA base se lasci vuoto',
                ctaUrl: 'https://... oppure /percorso',
            },
            labels: {
                selected: 'Selezionato',
                fixed: 'Fisso',
                unavailable: 'Non disponibile',
                selectedRecipients: '{count} destinatari selezionati',
                locale: 'Lingua',
                sampleRecipient: 'Destinatario campione',
                recipientCount: 'Destinatari',
            },
            channels: {
                mail: 'Email',
                database: 'Notifiche',
                sms: 'SMS',
                telegram: 'Telegram',
            },
            locales: {
                recipient: 'Lingua utente',
            },
            contentModes: {
                template: 'Template categoria',
                custom: 'Contenuto personalizzato',
            },
            preview: {
                emailSubject: 'Oggetto email',
            },
            result: {
                summary:
                    '{count} outbound creati per {recipients} destinatari su {channels} canali.',
            },
            feedback: {
                successTitle: 'Invio completato',
                errorTitle: 'Invio non riuscito',
                sent: 'Comunicazione accodata correttamente.',
                sendFailed:
                    'Non è stato possibile inviare la comunicazione manuale.',
            },
            help: {
                channels:
                    'Seleziona uno o più canali realmente disponibili per la categoria scelta.',
                modeSelected: 'Modalità attiva',
                mode_template:
                    'Usa il contenuto standard della categoria per ciascun canale.',
                mode_custom:
                    'Sovrascrivi il contenuto amministrativo mantenendo il motore backend per preview e invio.',
            },
            loading: {
                recipients: 'Caricamento destinatari...',
            },
            empty: {
                categories: 'Nessuna categoria disponibile',
                recipients: 'Nessun destinatario trovato',
                preview:
                    'La preview comparira appena categoria, canali e destinatari saranno completi.',
                noDescription: 'Nessuna descrizione disponibile.',
                noValue: 'Nessun valore',
                locales: 'Nessuna lingua disponibile',
            },
            categories: {
                'auth.verify_email': {
                    name: 'Verifica email',
                    description:
                        'Invio manuale della comunicazione di verifica email per l’utente selezionato.',
                },
                'auth.reset_password': {
                    name: 'Reset password',
                    description:
                        'Invio manuale della comunicazione di reset password per l’utente selezionato.',
                },
                'user.welcome_after_verification': {
                    name: 'Benvenuto dopo verifica',
                    description:
                        'Comunicazione di benvenuto inviata quando l’account dell’utente selezionato è già attivo e verificato.',
                },
                'reports.weekly_ready': {
                    name: 'Report pronto',
                    description:
                        'Comunicazione inviata quando il report personale e disponibile per l’utente selezionato.',
                },
            },
            validation: {
                category: 'Seleziona una categoria valida per l’invio manuale.',
                recipient: 'Seleziona almeno un destinatario valido.',
                channel: 'Seleziona almeno un canale disponibile.',
            },
        },
        communicationOutbound: {
            title: 'Storico invii',
            description:
                'Monitora gli outbound message reali del sistema, con filtri funzionali su stato, canale, categoria e destinatario.',
            breadcrumbDetail: 'Dettaglio outbound',
            feedback: {
                errorTitle: 'Caricamento non riuscito',
            },
            actions: {
                open: 'Apri dettaglio',
                backToOutbound: 'Torna allo storico',
            },
            filters: {
                title: 'Filtri outbound',
                description:
                    'Ricerca testuale e filtri server-side per restringere rapidamente gli invii rilevanti.',
                reset: 'Reimposta filtri',
                searchLabel: 'Ricerca',
                searchPlaceholder: 'Cerca per categoria, contenuto o errore',
                statusLabel: 'Stato',
                statusPlaceholder: 'Tutti gli stati',
                channelLabel: 'Canale',
                channelPlaceholder: 'Tutti i canali',
                categoryLabel: 'Categoria',
                categoryPlaceholder: 'Tutte le categorie',
                recipientLabel: 'Destinatario',
                recipientPlaceholder: 'Nome o email destinatario',
                dateFromLabel: 'Da',
                dateToLabel: 'A',
            },
            list: {
                title: 'Outbound messages',
                description:
                    'Vista operativa degli invii creati dal communication layer, distinta da Horizon e centrata sul risultato business.',
                emptySummary: 'Nessun outbound disponibile',
                summary: 'Messaggi {from}-{to} di {total}',
            },
            table: {
                createdAt: 'Creato',
                category: 'Categoria',
                recipient: 'Destinatario',
                channel: 'Canale',
                status: 'Stato',
                template: 'Template',
                context: 'Contesto',
                error: 'Errore',
                actions: 'Azioni',
            },
            detail: {
                title: 'Dettaglio outbound',
                description:
                    'Dettaglio del singolo invio con contenuto risolto, payload base e timestamp di delivery.',
                sections: {
                    summary: 'Summary',
                    content: 'Contenuto inviato',
                    payload: 'Payload snapshot',
                },
                labels: {
                    uuid: 'UUID',
                    createdAt: 'Creato',
                    queuedAt: 'Queued at',
                    sentAt: 'Sent at',
                    failedAt: 'Failed at',
                    channel: 'Canale',
                    status: 'Stato',
                    category: 'Categoria',
                    template: 'Template',
                    recipient: 'Destinatario',
                    context: 'Contesto',
                    creator: 'Creato da',
                    error: 'Errore',
                    subject: 'Oggetto email',
                    title: 'Titolo',
                    body: 'Messaggio',
                    ctaLabel: 'Testo pulsante',
                    ctaUrl: 'Link pulsante',
                },
            },
            statuses: {
                queued: 'Queued',
                sent: 'Inviato',
                failed: 'Fallito',
                skipped: 'Saltato',
            },
            channels: {
                mail: 'Email',
                database: 'Notifiche',
                sms: 'SMS',
            },
            empty: {
                title: 'Nessun outbound trovato',
                description:
                    'Prova a modificare i filtri oppure genera nuovi invii dal composer admin o dai flussi automatici.',
                noValue: 'Nessun valore disponibile',
            },
            pagination: {
                previous: 'Precedente',
                next: 'Successiva',
                page: 'Pagina {current} di {last}',
            },
        },
        communicationTemplates: {
            title: 'Template comunicazioni',
            description:
                'Gestisci i template del communication layer e gli override globali appoggiandoti al backend già disponibile.',
            breadcrumbDetail: 'Dettaglio template',
            breadcrumbEdit: 'Modifica override',
            feedback: {
                successTitle: 'Operazione completata',
                errorTitle: 'Operazione non riuscita',
            },
            actions: {
                open: 'Apri',
                editOverride: 'Modifica override',
                disableOverride: 'Disattiva override',
                saveOverride: 'Salva override',
                createOverride: 'Crea override',
                close: 'Chiudi',
                cancel: 'Annulla',
                backToTemplates: 'Torna ai template',
                backToDetail: 'Torna al dettaglio',
                confirmDisable: 'Conferma disattivazione',
            },
            badges: {
                locked: 'Bloccato',
                editable: 'Modificabile',
                active: 'Attivo',
                inactive: 'Inattivo',
                overrideActive: 'Override attivo',
                overrideInactive: 'Override inattivo',
                overrideMissing: 'Nessun override',
            },
            channels: {
                mail: 'Email',
                database: 'In-app',
                sms: 'SMS',
            },
            modes: {
                system: 'Sistema',
                customizable: 'Personalizzabile',
                freeform: 'Libero',
            },
            index: {
                title: 'Template disponibili',
                description:
                    'Vista compatta dei template, del topic associato e dello stato dell’override globale.',
                summary: '{count} template disponibili',
            },
            filters: {
                title: 'Filtri template',
                description:
                    'Ricerca e restringi l’elenco con i filtri lato server già supportati dal backend admin.',
                reset: 'Reimposta filtri',
                searchLabel: 'Ricerca',
                searchPlaceholder: 'Cerca per nome, chiave o topic',
                channelLabel: 'Canale',
                channelPlaceholder: 'Tutti i canali',
                templateModeLabel: 'Tipo template',
                templateModePlaceholder: 'Tutti i tipi',
                overrideStateLabel: 'Override',
                overrideStatePlaceholder: 'Tutti gli stati override',
                lockStateLabel: 'Blocco',
                lockStatePlaceholder: 'Tutti gli stati blocco',
                overrideStates: {
                    withOverride: 'Con override',
                    withoutOverride: 'Senza override',
                },
                lockStates: {
                    locked: 'Bloccati',
                    editable: 'Modificabili',
                },
            },
            table: {
                name: 'Nome',
                key: 'Chiave',
                channel: 'Canale',
                templateMode: 'Tipo template',
                topic: 'Topic associato',
                override: 'Override',
                status: 'Stato',
                actions: 'Azioni',
            },
            mobile: {
                title: 'Template',
            },
            list: {
                summary: 'Template {from}-{to} di {total}',
                emptySummary: 'Nessun template disponibile',
                description:
                    'Apri il dettaglio o passa alla pagina dedicata di modifica override quando il backend lo consente.',
                loading: 'Aggiornamento elenco template in corso...',
            },
            detail: {
                title: 'Dettaglio template',
                description:
                    'Confronta contenuto base, override globale e anteprima finale senza toccare la logica di rendering backend.',
                sections: {
                    general: 'Informazioni generali',
                    base: 'Template base',
                    override: 'Override globale',
                    resolved: 'Contenuto risolto',
                    preview: 'Anteprima',
                },
                labels: {
                    name: 'Nome',
                    key: 'Chiave',
                    channel: 'Canale',
                    templateMode: 'Tipo template',
                    topic: 'Topic associato',
                    lockState: 'Stato modifica',
                    scope: 'Scope',
                    subject: 'Subject',
                    title: 'Title',
                    body: 'Body',
                    ctaLabel: 'CTA label',
                    ctaUrl: 'CTA URL',
                    overrideState: 'Stato override',
                },
            },
            form: {
                title: 'Override globale',
                description:
                    'Aggiorna i campi override senza modificare il template base di sistema.',
                fields: {
                    subject: 'Oggetto email',
                    title: 'Titolo',
                    body: 'Messaggio',
                    ctaLabel: 'Testo pulsante',
                    ctaUrl: 'Link pulsante',
                    isActive: 'Override attivo',
                },
                hints: {
                    subject: 'Lascia vuoto per usare l’oggetto base.',
                    title: 'Lascia vuoto per usare il titolo base.',
                    body: 'Lascia vuoto per usare il messaggio base. Puoi usare le variabili supportate quando disponibili.',
                    ctaLabel:
                        'Il pulsante è opzionale. Lascia vuoto per usare il testo base.',
                    ctaUrl: 'Il link è opzionale e deve restare coerente con l’URL finale atteso.',
                    isActive:
                        'Quando disattivato, l’override resta salvato ma non viene applicato.',
                },
                helper: 'Lascia un campo vuoto per ereditare il valore base. I template bloccati restano in sola lettura.',
                disabled:
                    'Questo template è bloccato dal sistema e non può ricevere override globali.',
            },
            edit: {
                title: 'Modifica override template',
                description:
                    'Aggiorna l’override globale in una vista ampia con valori base, risultato finale e preview live.',
                sections: {
                    override: 'Modifica override',
                    base: 'Valori base',
                    resolved: 'Risultato finale',
                    variables: 'Variabili disponibili',
                    preview: 'Anteprima email',
                },
                preview: {
                    subject: 'Oggetto finale',
                    footer: 'Messaggio generato dal prodotto. Questa anteprima amministrativa mostra il contenuto finale che il sistema userà.',
                },
                variablesEmpty:
                    'Nessuna variabile rilevata nel template corrente.',
            },
            dialogs: {
                disableTitle: 'Disattiva override globale',
                disableDescription:
                    'Stai per disattivare l’override globale del template {template}. I valori salvati resteranno storicizzati ma non più attivi.',
            },
            pagination: {
                previous: 'Precedente',
                next: 'Successiva',
                page: 'Pagina {current} di {last}',
            },
            empty: {
                title: 'Nessun template trovato',
                description:
                    'Prova a cambiare ricerca o filtri per vedere di nuovo template, topic associati e override globali.',
                noTopic: 'Nessun topic associato',
                noValue: 'Nessun valore',
                noOverride: 'Nessun override globale attivo o salvato.',
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
                support: 'Sottoscrizione',
                plan: 'Piano',
                emailVerification: 'Email',
                impersonationConsent: 'Consenso supporto',
                actions: 'Azioni',
            },
            support: {
                states: {
                    never_donated: 'Mai donato',
                    support_recent: 'Supporto recente',
                    reminder_due: 'Reminder dovuto',
                    support_lapsed: 'Supporto scaduto',
                },
                labels: {
                    lastContribution: 'Ultimo contributo',
                    nextReminder: 'Prossimo reminder',
                    noContribution: 'Nessun contributo ancora',
                },
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
                protectedAdminUser:
                    'Utente admin protetto: azioni sensibili bloccate.',
                noImpersonationConsent:
                    'Impersonate non disponibile: l’utente non ha dato il consenso al supporto assistito.',
                limitedActions:
                    'Alcune azioni non sono disponibili per lo stato attuale di questo utente.',
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
                support: 'Sottoscrizione',
            },
            billing: {
                title: 'Supporto e billing',
                description: 'Gestisci supporto, promemoria e storico donazioni per {user}.',
                flash: {
                    transaction_saved: 'Donazione registrata correttamente.',
                    transaction_updated: 'Donazione aggiornata correttamente.',
                    transaction_assigned: 'Donazione associata correttamente.',
                    support_updated: 'Finestra supporto aggiornata correttamente.',
                    subscription_deleted: 'Sottoscrizione eliminata correttamente.',
                },
                summary: {
                    managedUser: 'Utente gestito',
                    accessPlan: 'Piano accesso',
                    supportState: 'Stato supporto',
                    lastContribution: 'Ultimo contributo',
                    nextReminder: 'Prossimo reminder',
                },
                sections: {
                    history: 'Storico donazioni',
                    supportWindow: 'Finestra supporto',
                    manualDonation: 'Registra donazione',
                    editTransaction: 'Modifica donazione',
                    assignTransaction: 'Associa donazione pendente',
                },
                sectionDescriptions: {
                    history: 'Storico economico reale delle donazioni associate all’utente.',
                    supportWindow: 'Stato supporto non bloccante, utile per badge, review e reminder futuri.',
                    manualDonation: 'Registra manualmente una donazione o un contributo importato.',
                    editTransaction: 'Correggi i dati di una donazione già registrata.',
                    assignTransaction: 'Associa a questo utente donazioni non riconciliate quando serve.',
                },
                actions: {
                    backToUsers: 'Torna agli utenti',
                    saveSupport: 'Salva finestra supporto',
                    clearSubscription: 'Azzera sottoscrizione',
                    deleteSubscription: 'Elimina sottoscrizione',
                    saveDonation: 'Registra donazione',
                    editTransaction: 'Modifica',
                    updateTransaction: 'Aggiorna donazione',
                    assignTransaction: 'Associa',
                },
                confirmations: {
                    deleteSubscription:
                        'Vuoi eliminare questa sottoscrizione? Lo storico donazioni restera invariato.',
                },
                fields: {
                    supportStatus: 'Stato supporto',
                    plan: 'Piano billing',
                    supportStartedAt: 'Supporto iniziato il',
                    supportEndsAt: 'Supporto fino al',
                    nextReminderAt: 'Prossimo reminder',
                    adminNotes: 'Note admin',
                    isSupporter: 'Mostra utente come supporter attivo',
                    provider: 'Provider',
                    amount: 'Importo',
                    currency: 'Valuta',
                    paidAt: 'Pagato il',
                    receivedAt: 'Ricevuto il',
                    isRecurring: 'Donazione ricorrente',
                    applySupportWindow: 'Aggiorna anche la finestra supporto',
                },
                supportStatuses: {
                    free: 'Free',
                    supporting: 'Supporto attivo',
                    inactive: 'Supporto inattivo',
                },
                table: {
                    provider: 'Provider',
                    amount: 'Importo',
                    status: 'Stato',
                    paidAt: 'Pagato il',
                },
                empty: {
                    noValue: 'Nessun valore',
                    history: 'Nessuna donazione registrata per questo utente.',
                    selectTransaction: 'Seleziona una donazione dallo storico per modificarla.',
                    assignableTransactions: 'Nessuna donazione pendente disponibile da associare.',
                },
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
                    helper: 'L’utente admin non è modificabile da questa schermata. Per gli altri profili puoi usare solo user e staff.',
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
        changelog: {
            flash: {
                saved: 'Release changelog salvata correttamente.',
            },
            validation: {
                versionTaken:
                    'Esiste già una release con questa versione finale.',
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
            changelog: 'Changelog',
            communicationCategories: 'Communication categories',
            communicationComposer: 'Communication composer',
            communicationOutbound: 'Outbound history',
            communicationTemplates: 'Communication templates',
        },
        summaries: {
            overview: 'Overview of administrative tools',
            users: 'Quick access to the upcoming user management area',
            activityLog: 'Operational tracking and audit trail coming soon',
            automation: 'Pipeline health, runs, and retry controls',
            changelog:
                'Release management, assisted versioning, and public changelog feed',
            communicationCategories:
                'Central management of channel availability and default templates by category',
            communicationComposer:
                'Manual admin sending with real communication previews',
            communicationOutbound:
                'Functional monitoring of outbound messages and delivery outcomes',
            communicationTemplates:
                'Manage communication templates and global overrides',
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
                changelog: {
                    title: 'Changelog',
                    description:
                        'Admin CRUD for multilingual releases, sections, items, and a public-ready changelog payload.',
                    status: 'Operational',
                },
                communicationCategories: {
                    title: 'Communication categories',
                    description:
                        'Central source of category, channel, and template mappings shared by composer and automatic deliveries.',
                    status: 'Operational',
                },
                communicationComposer: {
                    title: 'Communication composer',
                    description:
                        'Compose and send manual admin communications using the real category, template, and composer backend.',
                    status: 'Operational',
                },
                communicationOutbound: {
                    title: 'Outbound history',
                    description:
                        'Admin monitoring of outbound messages with status, channel, recipient, and delivery errors.',
                    status: 'Operational',
                },
                communicationTemplates: {
                    title: 'Communication templates',
                    description:
                        'Admin area for notification templates and their global overrides.',
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
                configureRun: 'Configure run',
                retry: 'Retry',
                runInfo: 'Run info',
                backToAutomations: 'Back to automation',
                close: 'Close',
                confirmRetry: 'Confirm retry',
                confirmRun: 'Start check',
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
                    businessSummary: 'Business outcome',
                    accountResults: 'Per-card details',
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
                    examinedCount: 'Cards checked',
                    dueCount: 'Cards due',
                    chargedCount: 'Charges created',
                    skippedCount: 'Valid no-ops',
                    notifiedCount: 'Users notified',
                    errorMessage: 'Error message',
                    exceptionClass: 'Exception class',
                    account: 'Card',
                    outcome: 'Outcome',
                    detail: 'Detail',
                    cycleEndDate: 'Cycle end date',
                    paymentDueDate: 'Payment due date',
                    chargedAmount: 'Charged amount',
                },
                emptyPayload: 'No data available.',
                noError: 'This run did not report technical errors.',
                noAccountResults: 'No card details are available for this run.',
                accountOutcomes: {
                    charged: 'Charge created',
                    already_processed: 'Cycle already processed',
                    zero_amount: 'No amount to charge',
                    not_due: 'Not due on the reference date',
                    autopay_disabled: 'Autopay disabled',
                    configuration_error: 'Configuration error',
                    execution_error: 'Real error',
                    due: 'Due',
                },
            },
            dialogs: {
                runTitle: 'Run pipeline',
                runDescription:
                    'You can trigger the {pipeline} pipeline immediately. Credit card checks also support an optional reference date for testing a specific day.',
                referenceDateLabel: 'Reference date',
                referenceDateHelper:
                    'Leave empty to use the current server date. This is only used by pipelines that support date-based testing.',
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
                credit_card_autopay: 'Credit card autopay check',
                notifications_pipeline: 'Notifications pipeline',
                reports_pipeline: 'Reports pipeline',
            },
            common: {
                notAvailable: 'N/A',
                emptyHost: 'Host unavailable',
            },
        },
        communicationCategories: {
            title: 'Communication categories',
            description:
                'Configure upstream which channels are actually available for each category and which default template each channel uses.',
            filters: {
                title: 'Search categories',
                description:
                    'Quickly find the categories to administer without moving configuration into the runtime composer.',
                searchLabel: 'Search',
                searchPlaceholder: 'Search by name, key, or description',
                reset: 'Reset',
            },
            list: {
                summary: 'Categories {from}-{to} of {total}',
                emptySummary: 'No categories available',
            },
            sections: {
                general: 'General information',
                channelRules: 'Shared capabilities',
                channels: 'Category channels',
                channelsDescription:
                    'Each card reflects both global channel availability and the default category -> channel -> template mapping.',
            },
            labels: {
                key: 'Key',
                deliveryMode: 'Delivery mode',
                preferenceMode: 'Preference mode',
                contextType: 'Context type',
                fixedChannel: 'Fixed channel',
                currentTemplate: 'Current template',
                globalAvailability: 'Global availability',
            },
            actions: {
                manageChannels: 'Manage channels',
                saveChannels: 'Save channel configuration',
                backToCategories: 'Back to categories',
            },
            status: {
                noActiveChannels: 'No active channels',
                activeChannels: '{count} active channels',
            },
            empty: {
                title: 'No categories found',
                description:
                    'Adjust the search or add new backend categories to populate this section.',
                noDescription: 'No description available.',
                noTemplate: 'No template assigned',
                noFixedChannel: 'No fixed channel',
            },
            pagination: {
                previous: 'Previous',
                next: 'Next',
                page: 'Page {current} of {last}',
            },
            deliveryModes: {
                transactional: 'Transactional',
                campaign: 'Campaign',
                manual: 'Manual',
                system: 'System',
            },
            preferenceModes: {
                mandatory: 'Mandatory',
                user_configurable: 'User configurable',
                admin_configurable: 'Admin configurable',
                system: 'System',
            },
            channels: {
                mail: 'Email',
                database: 'Notifications',
                sms: 'SMS',
                telegram: 'Telegram',
            },
            channelState: {
                enabled: 'Enabled',
                disabled: 'Disabled',
                fixed: 'Fixed',
                globallyUnavailable: 'Globally unavailable',
            },
            channelHints: {
                globallyAvailable:
                    'The channel is globally ready and can be enabled for this category when it has a compatible active template.',
                globallyUnavailable:
                    'The channel is not configured globally or its transport is not ready yet, so it stays disabled.',
            },
            form: {
                enableChannel: 'Enable channel',
                template: 'Default template',
                templatePlaceholder: 'Select template',
            },
            flags: {
                manualSend: 'Available in admin composer',
                automaticDispatch: 'Available for automatic dispatch',
                enabled: 'Enabled',
                disabled: 'Disabled',
            },
            feedback: {
                successTitle: 'Configuration updated',
                errorTitle: 'Update failed',
            },
        },
        communicationComposer: {
            eyebrow: 'Manual sending',
            title: 'Communication composer',
            description:
                'Admin-first composer to choose category, channels, language, recipients, and content with a real backend preview before dispatching.',
            actions: {
                backToAdmin: 'Back to admin',
                send: 'Send communication',
                sending: 'Sending...',
            },
            sections: {
                category: 'Step A — Category, channels, and language',
                recipient: 'Step B — Recipients',
                content: 'Step C — Content',
                preview: 'Step D — Real preview',
                send: 'Step E — Send',
            },
            sectionDescriptions: {
                category:
                    'Shows categories compatible with admin sending, the channels truly supported today, and the language used for the delivery.',
                recipient:
                    'The lookup uses the real backend and supports single or multiple user recipients.',
                content:
                    'Use the category template or override the content with custom admin text.',
                preview:
                    'The preview comes from the backend for each selected channel using the sample recipient, chosen language, and actual content.',
                send: 'The real send creates outbound messages for every selected recipient and channel.',
            },
            fields: {
                category: 'Category',
                channels: 'Channels',
                locale: 'Send language',
                searchRecipient: 'Search recipient',
                subject: 'Email subject',
                title: 'Title',
                body: 'Message',
                ctaLabel: 'Button text',
                ctaUrl: 'Button link',
            },
            placeholders: {
                category: 'Choose a category',
                searchCategory: 'Search category',
                locale: 'Choose a language',
                searchLocale: 'Search language',
                searchRecipient: 'Search by name or email',
                subject: 'Leave empty to keep the base subject',
                title: 'Leave empty to keep the base title',
                body: 'Write the admin content to send',
                ctaLabel: 'Leave empty to keep the base CTA',
                ctaUrl: 'https://... or /path',
            },
            labels: {
                selected: 'Selected',
                fixed: 'Fixed',
                unavailable: 'Unavailable',
                selectedRecipients: '{count} selected recipients',
                locale: 'Language',
                sampleRecipient: 'Sample recipient',
                recipientCount: 'Recipients',
            },
            channels: {
                mail: 'Email',
                database: 'Notifications',
                sms: 'SMS',
                telegram: 'Telegram',
            },
            locales: {
                recipient: 'Recipient language',
            },
            contentModes: {
                template: 'Category template',
                custom: 'Custom content',
            },
            preview: {
                emailSubject: 'Email subject',
            },
            result: {
                summary:
                    '{count} outbound messages created for {recipients} recipients across {channels} channels.',
            },
            feedback: {
                successTitle: 'Send completed',
                errorTitle: 'Send failed',
                sent: 'Communication queued successfully.',
                sendFailed: 'The manual communication could not be sent.',
            },
            help: {
                channels:
                    'Select one or more channels genuinely available for the chosen category.',
                modeSelected: 'Active mode',
                mode_template:
                    'Use the standard content defined by the selected category.',
                mode_custom:
                    'Override the admin content while still relying on the backend for preview and send.',
            },
            loading: {
                recipients: 'Loading recipients...',
            },
            empty: {
                categories: 'No categories available',
                recipients: 'No recipients found',
                preview:
                    'The preview will appear as soon as category, channels, and recipients are complete.',
                noDescription: 'No description available.',
                noValue: 'No value',
                locales: 'No language available',
            },
            categories: {
                'auth.verify_email': {
                    name: 'Verify email',
                    description:
                        'Manually send the email verification communication to the selected user.',
                },
                'auth.reset_password': {
                    name: 'Reset password',
                    description:
                        'Manually send the password reset communication to the selected user.',
                },
                'user.welcome_after_verification': {
                    name: 'Welcome after verification',
                    description:
                        'Welcome communication sent when the selected user account is already active and verified.',
                },
                'reports.weekly_ready': {
                    name: 'Report ready',
                    description:
                        'Communication sent when the personal report is available for the selected user.',
                },
            },
            validation: {
                category: 'Select a valid category for manual sending.',
                recipient: 'Select at least one valid recipient.',
                channel: 'Select at least one available channel.',
            },
        },
        communicationOutbound: {
            title: 'Outbound history',
            description:
                'Monitor real outbound messages with functional filters for status, channel, category, and recipient.',
            breadcrumbDetail: 'Outbound details',
            feedback: {
                errorTitle: 'Loading failed',
            },
            actions: {
                open: 'Open details',
                backToOutbound: 'Back to outbound history',
            },
            filters: {
                title: 'Outbound filters',
                description:
                    'Text search and server-side filters to narrow the messages that matter.',
                reset: 'Reset filters',
                searchLabel: 'Search',
                searchPlaceholder: 'Search by category, content, or error',
                statusLabel: 'Status',
                statusPlaceholder: 'All statuses',
                channelLabel: 'Channel',
                channelPlaceholder: 'All channels',
                categoryLabel: 'Category',
                categoryPlaceholder: 'All categories',
                recipientLabel: 'Recipient',
                recipientPlaceholder: 'Recipient name or email',
                dateFromLabel: 'From',
                dateToLabel: 'To',
            },
            list: {
                title: 'Outbound messages',
                description:
                    'Operational view of messages created by the communication layer, distinct from Horizon and focused on business delivery.',
                emptySummary: 'No outbound messages available',
                summary: 'Messages {from}-{to} of {total}',
            },
            table: {
                createdAt: 'Created',
                category: 'Category',
                recipient: 'Recipient',
                channel: 'Channel',
                status: 'Status',
                template: 'Template',
                context: 'Context',
                error: 'Error',
                actions: 'Actions',
            },
            detail: {
                title: 'Outbound details',
                description:
                    'Single outbound view with resolved content, base payload, and delivery timestamps.',
                sections: {
                    summary: 'Summary',
                    content: 'Delivered content',
                    payload: 'Payload snapshot',
                },
                labels: {
                    uuid: 'UUID',
                    createdAt: 'Created',
                    queuedAt: 'Queued at',
                    sentAt: 'Sent at',
                    failedAt: 'Failed at',
                    channel: 'Channel',
                    status: 'Status',
                    category: 'Category',
                    template: 'Template',
                    recipient: 'Recipient',
                    context: 'Context',
                    creator: 'Created by',
                    error: 'Error',
                    subject: 'Email subject',
                    title: 'Title',
                    body: 'Message',
                    ctaLabel: 'Button text',
                    ctaUrl: 'Button link',
                },
            },
            statuses: {
                queued: 'Queued',
                sent: 'Sent',
                failed: 'Failed',
                skipped: 'Skipped',
            },
            channels: {
                mail: 'Email',
                database: 'Notifications',
                sms: 'SMS',
            },
            empty: {
                title: 'No outbound messages found',
                description:
                    'Try changing the filters or generate new messages through the admin composer or automatic flows.',
                noValue: 'No value available',
            },
            pagination: {
                previous: 'Previous',
                next: 'Next',
                page: 'Page {current} of {last}',
            },
        },
        communicationTemplates: {
            title: 'Communication templates',
            description:
                'Manage communication layer templates and their global overrides using the existing backend rules.',
            breadcrumbDetail: 'Template details',
            breadcrumbEdit: 'Edit override',
            feedback: {
                successTitle: 'Action completed',
                errorTitle: 'Action failed',
            },
            actions: {
                open: 'Open',
                editOverride: 'Edit override',
                disableOverride: 'Disable override',
                saveOverride: 'Save override',
                createOverride: 'Create override',
                close: 'Close',
                cancel: 'Cancel',
                backToTemplates: 'Back to templates',
                backToDetail: 'Back to details',
                confirmDisable: 'Confirm disable',
            },
            badges: {
                locked: 'Locked',
                editable: 'Editable',
                active: 'Active',
                inactive: 'Inactive',
                overrideActive: 'Override active',
                overrideInactive: 'Override inactive',
                overrideMissing: 'No override',
            },
            channels: {
                mail: 'Email',
                database: 'In-app',
                sms: 'SMS',
            },
            modes: {
                system: 'System',
                customizable: 'Customizable',
                freeform: 'Freeform',
            },
            index: {
                title: 'Available templates',
                description:
                    'Compact view of templates, linked topic, and current global override status.',
                summary: '{count} templates available',
            },
            filters: {
                title: 'Template filters',
                description:
                    'Search and narrow the list using the server-side filters already supported by the admin backend.',
                reset: 'Reset filters',
                searchLabel: 'Search',
                searchPlaceholder: 'Search by name, key, or topic',
                channelLabel: 'Channel',
                channelPlaceholder: 'All channels',
                templateModeLabel: 'Template mode',
                templateModePlaceholder: 'All modes',
                overrideStateLabel: 'Override',
                overrideStatePlaceholder: 'All override states',
                lockStateLabel: 'Lock state',
                lockStatePlaceholder: 'All lock states',
                overrideStates: {
                    withOverride: 'With override',
                    withoutOverride: 'Without override',
                },
                lockStates: {
                    locked: 'Locked',
                    editable: 'Editable',
                },
            },
            table: {
                name: 'Name',
                key: 'Key',
                channel: 'Channel',
                templateMode: 'Template mode',
                topic: 'Linked topic',
                override: 'Override',
                status: 'Status',
                actions: 'Actions',
            },
            mobile: {
                title: 'Templates',
            },
            list: {
                summary: 'Templates {from}-{to} of {total}',
                emptySummary: 'No templates available',
                description:
                    'Open details or move to the dedicated override edit page when the backend allows it.',
                loading: 'Refreshing template list...',
            },
            detail: {
                title: 'Template details',
                description:
                    'Compare base content, global override, and final preview without changing the backend rendering logic.',
                sections: {
                    general: 'General information',
                    base: 'Base template',
                    override: 'Global override',
                    resolved: 'Resolved content',
                    preview: 'Preview',
                },
                labels: {
                    name: 'Name',
                    key: 'Key',
                    channel: 'Channel',
                    templateMode: 'Template mode',
                    topic: 'Linked topic',
                    lockState: 'Edit state',
                    scope: 'Scope',
                    subject: 'Subject',
                    title: 'Title',
                    body: 'Body',
                    ctaLabel: 'CTA label',
                    ctaUrl: 'CTA URL',
                    overrideState: 'Override state',
                },
            },
            form: {
                title: 'Global override',
                description:
                    'Update override fields without changing the base system template.',
                fields: {
                    subject: 'Email subject',
                    title: 'Title',
                    body: 'Message',
                    ctaLabel: 'Button text',
                    ctaUrl: 'Button link',
                    isActive: 'Override active',
                },
                hints: {
                    subject: 'Leave empty to keep the base subject.',
                    title: 'Leave empty to keep the base title.',
                    body: 'Leave empty to keep the base message. Use supported variables when available.',
                    ctaLabel:
                        'The button is optional. Leave empty to keep the base label.',
                    ctaUrl: 'The link is optional and should stay consistent with the final expected URL.',
                    isActive:
                        'When disabled, the override stays saved but is no longer applied.',
                },
                helper: 'Leave a field empty to inherit the base value. Locked templates remain read-only.',
                disabled:
                    'This template is system locked and cannot receive global overrides.',
            },
            edit: {
                title: 'Edit template override',
                description:
                    'Update the global override in a wider view with base values, resolved result, and live preview.',
                sections: {
                    override: 'Edit override',
                    base: 'Base values',
                    resolved: 'Resolved result',
                    variables: 'Available variables',
                    preview: 'Email preview',
                },
                preview: {
                    subject: 'Final subject',
                    footer: 'Generated product message. This admin preview shows the final content the system will use.',
                },
                variablesEmpty:
                    'No variables detected for the current template.',
            },
            dialogs: {
                disableTitle: 'Disable global override',
                disableDescription:
                    'You are about to disable the global override for template {template}. Stored values remain available as history but will no longer be active.',
            },
            pagination: {
                previous: 'Previous',
                next: 'Next',
                page: 'Page {current} of {last}',
            },
            empty: {
                title: 'No templates found',
                description:
                    'Try changing the search or filters to bring templates, linked topics, and global overrides back into view.',
                noTopic: 'No linked topic',
                noValue: 'No value',
                noOverride: 'No global override saved or active.',
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
                support: 'Subscription',
                plan: 'Plan',
                emailVerification: 'Email',
                impersonationConsent: 'Support consent',
                actions: 'Actions',
            },
            support: {
                states: {
                    never_donated: 'Never donated',
                    support_recent: 'Recent support',
                    reminder_due: 'Reminder due',
                    support_lapsed: 'Support lapsed',
                },
                labels: {
                    lastContribution: 'Last contribution',
                    nextReminder: 'Next reminder',
                    noContribution: 'No contribution yet',
                },
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
                protectedAdminUser:
                    'Protected admin user: sensitive actions are blocked.',
                noImpersonationConsent:
                    'Impersonation unavailable: the user has not granted assisted-support consent.',
                limitedActions:
                    'Some actions are unavailable for the current state of this user.',
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
                support: 'Subscription',
            },
            billing: {
                title: 'Support and billing',
                description: 'Manage support state, reminders, and donation history for {user}.',
                flash: {
                    transaction_saved: 'Donation recorded successfully.',
                    transaction_updated: 'Donation updated successfully.',
                    transaction_assigned: 'Donation assigned successfully.',
                    support_updated: 'Support window updated successfully.',
                    subscription_deleted: 'Subscription deleted successfully.',
                },
                summary: {
                    managedUser: 'Managed user',
                    accessPlan: 'Access plan',
                    supportState: 'Support state',
                    lastContribution: 'Last contribution',
                    nextReminder: 'Next reminder',
                },
                sections: {
                    history: 'Donation history',
                    supportWindow: 'Support window',
                    manualDonation: 'Record donation',
                    editTransaction: 'Edit donation',
                    assignTransaction: 'Assign pending donation',
                },
                sectionDescriptions: {
                    history: 'Real economic history of donations associated with this user.',
                    supportWindow: 'Non-blocking support state used for badges, review, and future reminders.',
                    manualDonation: 'Record a manual donation or an imported contribution.',
                    editTransaction: 'Correct data for a donation already recorded.',
                    assignTransaction: 'Associate unreconciled donations to this user when needed.',
                },
                actions: {
                    backToUsers: 'Back to users',
                    saveSupport: 'Save support window',
                    clearSubscription: 'Clear subscription',
                    deleteSubscription: 'Delete subscription',
                    saveDonation: 'Record donation',
                    editTransaction: 'Edit',
                    updateTransaction: 'Update donation',
                    assignTransaction: 'Assign',
                },
                confirmations: {
                    deleteSubscription:
                        'Do you want to delete this subscription? Donation history will remain unchanged.',
                },
                fields: {
                    supportStatus: 'Support status',
                    plan: 'Billing plan',
                    supportStartedAt: 'Support started at',
                    supportEndsAt: 'Support ends at',
                    nextReminderAt: 'Next reminder',
                    adminNotes: 'Admin notes',
                    isSupporter: 'Show user as active supporter',
                    provider: 'Provider',
                    amount: 'Amount',
                    currency: 'Currency',
                    paidAt: 'Paid at',
                    receivedAt: 'Received at',
                    isRecurring: 'Recurring donation',
                    applySupportWindow: 'Update support window too',
                },
                supportStatuses: {
                    free: 'Free',
                    supporting: 'Active support',
                    inactive: 'Inactive support',
                },
                table: {
                    provider: 'Provider',
                    amount: 'Amount',
                    status: 'Status',
                    paidAt: 'Paid at',
                },
                empty: {
                    noValue: 'No value',
                    history: 'No donations recorded for this user.',
                    selectTransaction: 'Select a donation from history to edit it.',
                    assignableTransactions: 'No pending donations available to assign.',
                },
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
                    helper: 'Admin users cannot be edited from this screen. For other profiles you can only assign user and staff.',
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
        changelog: {
            flash: {
                saved: 'Changelog release saved successfully.',
            },
            validation: {
                versionTaken:
                    'A release with this final version already exists.',
            },
        },
    },
} as const;
