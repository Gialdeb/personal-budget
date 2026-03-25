export const settingsMessages = {
    it: {
        title: 'Impostazioni',
        description:
            'Personalizza il tuo account, la sicurezza e le sezioni di configurazione.',
        accountArea: 'Area account',
        accountAreaTitle: 'Gestisci il tuo spazio personale',
        accountAreaDescription:
            'Accesso rapido alle preferenze più importanti con una navigazione più chiara.',
        sections: {
            profile: 'Profilo',
            categories: 'Categorie di spesa',
            trackedItems: 'Riferimenti',
            banks: 'Banche',
            accounts: 'Conti',
            years: 'Anni di gestione',
            security: 'Sicurezza',
            appearance: 'Aspetto',
        },
        summaries: {
            profile: 'Dati personali e contatti',
            categories: 'Struttura delle categorie',
            trackedItems: 'Riferimenti opzionali',
            banks: 'Rubrica banche disponibili',
            accounts: 'Conti, carte e saldi',
            years: 'Anni aperti e anno attivo',
            security: 'Password e autenticazione',
            appearance: 'Tema e preferenze visive',
        },
        appearancePage: {
            title: 'Aspetto',
            description:
                'Scegli il tema dell’app e rendi l’esperienza più coerente con le tue preferenze.',
        },
        profile: {
            title: 'Informazioni profilo',
            description:
                'Aggiorna nome e indirizzo email mantenendo invariato il flusso esistente.',
            regional: {
                title: 'Preferenze regionali',
                description:
                    'Gestisci separatamente lingua dell’interfaccia, formato regionale e valuta base del profilo.',
                locale: {
                    label: 'Lingua interfaccia',
                    helper: 'Controlla la lingua dei testi dell’app senza toccare formati regionali o valuta.',
                    placeholder: 'Seleziona una lingua',
                    save: 'Salva lingua',
                },
                formatLocale: {
                    label: 'Formato regionale',
                    helper: 'Definisce come mostrare numeri, importi e date, senza cambiare la lingua della UI.',
                    placeholder: 'Seleziona un formato regionale',
                    save: 'Salva formato',
                },
                baseCurrency: {
                    label: 'Valuta base',
                    helper: 'Usata come valuta del profilo e degli account in questa fase del progetto.',
                    placeholder: 'Seleziona una valuta',
                    save: 'Salva valuta',
                },
            },
            feedback: {
                successTitle: 'Profilo aggiornato',
                errorTitle: 'Aggiornamento non riuscito',
            },
            fields: {
                name: 'Nome',
                surname: 'Cognome',
                email: 'Indirizzo email',
            },
            placeholders: {
                name: 'Nome',
                surname: 'Cognome',
                email: "{'nome@esempio.it'}",
            },
            verify: {
                notice: 'Il tuo indirizzo email non è ancora verificato.',
                resend: 'Invia di nuovo l’email di verifica.',
                sent: 'Abbiamo inviato un nuovo link di verifica al tuo indirizzo email.',
            },
            impersonation: {
                title: 'Consenso supporto amministrativo',
                description:
                    'Decidi se autorizzare il supporto amministrativo ad accedere temporaneamente al tuo account per assistenza tecnica o risoluzione problemi.',
                label: 'Autorizzo il supporto amministrativo ad accedere temporaneamente al mio account per assistenza tecnica o risoluzione problemi',
                helper: 'Questa preferenza viene letta dagli admin solo per capire se possono intervenire sul tuo account in caso di ticket o anomalie. Non abilita modifiche automatiche e puoi cambiarla in qualsiasi momento.',
                enabledState:
                    'Consenso attivo. Il team di supporto potrà intervenire solo se necessario.',
                disabledState:
                    'Consenso disattivato. Nessun accesso assistito sarà consentito al supporto admin.',
            },
            notifications: {
                title: 'Preferenze notifiche',
                description:
                    'Scegli quali aggiornamenti opzionali vuoi ricevere via email o vedere nella dashboard.',
                save: 'Salva preferenze notifiche',
                channels: {
                    email: 'Email',
                    dashboard: 'Notifiche',
                    sms: 'SMS',
                },
                channelDescriptions: {
                    email: 'Ricevi questo aggiornamento via email.',
                    dashboard:
                        'Mostra questo aggiornamento tra le tue notifiche.',
                },
                empty: {
                    title: 'Nessuna notifica configurabile disponibile',
                    description:
                        'Al momento non ci sono notifiche opzionali che puoi gestire dal tuo profilo.',
                },
            },
            save: 'Salva modifiche',
        },
        security: {
            password: {
                title: 'Aggiorna password',
                description:
                    'Mantieni l’accesso al tuo account protetto con una password robusta e aggiornata.',
                current: 'Password attuale',
                currentPlaceholder: 'Inserisci la password attuale',
                next: 'Nuova password',
                nextPlaceholder: 'Inserisci la nuova password',
                confirmation: 'Conferma password',
                confirmationPlaceholder: 'Ripeti la nuova password',
                save: 'Salva password',
            },
            twoFactor: {
                title: 'Autenticazione a due fattori',
                description:
                    'Aggiungi un livello di protezione extra al login del tuo account.',
                enableDescription:
                    'Quando attivi l’autenticazione a due fattori, durante il login ti verrà richiesto un codice sicuro generato da un’app compatibile TOTP sul tuo telefono.',
                enabledDescription:
                    'Durante il login ti verrà richiesto un codice sicuro generato dall’app TOTP collegata al tuo account.',
                continue: 'Continua configurazione',
                enable: 'Attiva 2FA',
                disable: 'Disattiva 2FA',
                recoveryTitle: 'Codici di recupero 2FA',
                recoveryDescription:
                    'Ti permettono di recuperare l’accesso se perdi il dispositivo 2FA. Conservali in un password manager sicuro.',
                showRecovery: 'Mostra codici di recupero',
                hideRecovery: 'Nascondi codici di recupero',
                regenerateRecovery: 'Rigenera codici',
                recoveryHelper:
                    'Ogni codice può essere usato una sola volta e viene rimosso dopo l’utilizzo. Se te ne servono altri, usa Rigenera codici.',
                setup: {
                    enabledTitle: 'Autenticazione a due fattori attivata',
                    enabledDescription:
                        'La protezione extra è attiva. Scansiona il QR code o inserisci la chiave di configurazione nella tua app di autenticazione.',
                    verifyTitle: 'Verifica il codice',
                    verifyDescription:
                        'Inserisci il codice a 6 cifre della tua app di autenticazione.',
                    enableTitle: 'Attiva l’autenticazione a due fattori',
                    enableDescription:
                        'Per completare l’attivazione, scansiona il QR code oppure inserisci manualmente la chiave nella tua app di autenticazione.',
                    continue: 'Continua',
                    close: 'Chiudi',
                    manualKey: 'oppure inserisci la chiave manualmente',
                    codeLabel: 'Codice',
                    codePlaceholder: '123456',
                    confirm: 'Conferma',
                },
            },
            deleteUser: {
                title: 'Elimina account',
                description:
                    'Rimuovi definitivamente account e dati associati.',
                warningTitle: 'Attenzione',
                warningDescription:
                    'Questa azione è definitiva e non può essere annullata.',
                confirmTitle: 'Confermi l’eliminazione del tuo account?',
                confirmDescription:
                    'Una volta eliminato l’account, tutti i dati e le relative risorse verranno rimossi in modo permanente. Inserisci la password per confermare.',
                password: 'Password',
                delete: 'Elimina account',
            },
        },
        banks: {
            title: 'Banche',
            badge: 'Banche disponibili',
            description:
                'Gestisci l’elenco delle banche selezionabili per i tuoi account: catalogo condiviso quando basta, banche personalizzate quando serve.',
            create: 'Nuova banca personalizzata',
            summary: {
                total: 'Totali',
                active: 'Attive',
                custom: 'Personalizzate',
                used: 'Usate da account',
            },
            feedback: {
                successTitle: 'Operazione completata',
                unavailableTitle: 'Operazione non disponibile',
                saveTitle: 'Salvataggio completato',
                catalogTitle: 'Catalogo aggiornato',
                catalogMessage:
                    'La banca è stata aggiunta alle tue banche disponibili.',
                statusTitle: 'Stato aggiornato',
                activated: 'La banca è stata attivata.',
                deactivated: 'La banca è stata disattivata.',
                deletedTitle: 'Banca rimossa',
                deletedMessage:
                    'La banca è stata rimossa dalle tue banche disponibili.',
            },
            catalog: {
                title: 'Aggiungi dal catalogo globale',
                description:
                    'Rendi disponibili solo le banche che vuoi usare davvero.',
                selectLabel: 'Banca dal catalogo',
                selectPlaceholder: 'Seleziona una banca',
                noOptions: 'Nessuna banca aggiuntiva disponibile',
                createBaseAccount: 'Crea anche un conto base',
                createBaseAccountHelper:
                    'Attivo di default per evitare un secondo passaggio manuale.',
                add: 'Aggiungi dal catalogo',
            },
            catalogList: {
                title: 'Banche dal catalogo',
                description: 'Voci globali rese disponibili al tuo profilo.',
                empty: 'Non hai ancora aggiunto banche dal catalogo condiviso. Usa il selettore qui sopra per rendere disponibili solo quelle che ti servono.',
            },
            customList: {
                title: 'Banche personalizzate',
                description: 'Voci create solo per il tuo profilo utente.',
                empty: 'Nessuna banca personalizzata. Creane una solo se non trovi la banca nel catalogo oppure vuoi una voce tutta tua.',
                emptyCompact:
                    'Nessuna banca personalizzata. Creane una solo se non trovi ciò che ti serve nel catalogo.',
            },
            labels: {
                countryUnavailable: 'Codice paese non disponibile',
                slug: 'Slug',
                active: 'Attiva',
                inactive: 'Disattiva',
                remove: 'Rimuovi',
                delete: 'Elimina',
            },
            deleteDialog: {
                title: 'Rimuovi banca disponibile',
                removable:
                    'Stai per rimuovere {name} dalla tua rubrica banche.',
                blocked: '{name} non può essere rimossa in questo momento.',
                blockedTitle: 'Motivi del blocco',
                confirm: 'Rimuovi banca',
            },
            deleteReasons: {
                accountOne: 'È già collegata a 1 account.',
                accountMany: 'È già collegata a {count} account.',
            },
        },
        yearsPage: {
            title: 'Anni di gestione',
            badge: 'Settings / Years',
            description:
                "Gli anni disponibili sono definiti manualmente nel gestionale. Non vengono creati dai movimenti: servono per aprire un nuovo ciclo operativo, scegliere l'anno attivo e chiuderlo quando non deve più essere modificabile.",
            summary: {
                total: 'Totali',
                open: 'Aperti',
                closed: 'Chiusi',
                used: 'Con utilizzi',
            },
            feedback: {
                successTitle: 'Operazione completata',
                unavailableTitle: 'Operazione non disponibile',
            },
            create: {
                title: 'Nuovo anno di gestione',
                description:
                    "Per la v1 basta inserire l'anno numerico. Se è il primo anno disponibile viene impostato automaticamente come attivo.",
                placeholder: '2027',
                submit: 'Nuovo anno',
                quickCreate: 'Crea {year}',
            },
            empty: {
                title: 'Nessun anno di gestione configurato',
                description:
                    'Crea il primo anno per iniziare a lavorare nel gestionale con uno spazio operativo esplicito.',
            },
            table: {
                year: 'Anno',
                status: 'Stato',
                usage: 'Utilizzo',
                actions: 'Azioni',
            },
            status: {
                active: 'Attivo',
                closed: 'Chiuso',
                openDescription: 'Anno operativo aperto alle modifiche.',
                closedDescription:
                    'Solo consultazione: le modifiche operative sono bloccate.',
                mobileOpenDescription: 'Anno aperto e modificabile.',
                mobileClosedDescription: 'Solo lettura fino a riapertura.',
            },
            metrics: {
                budgets: 'Budget',
                transactions: 'Transazioni',
                scheduled: 'Pianificate',
                recurring: 'Ricorrenze',
                usageTitle: 'Utilizzi',
                usedOne: '1 collegamento operativo',
                usedMany: '{count} collegamenti operativi',
                noUsage: 'Nessun utilizzo',
                deletableHint: 'Puoi eliminarlo se non ti serve più.',
                lockedHint:
                    'Se è già usato o attivo, può essere solo aperto o chiuso.',
            },
            actions: {
                setActive: 'Imposta attivo',
                open: 'Apri',
                close: 'Chiudi',
                delete: 'Elimina',
                deleteYear: 'Elimina anno',
            },
            deleteDialog: {
                title: 'Elimina anno {year}',
                description:
                    "L'eliminazione è consentita solo per anni non attivi e senza dati collegati.",
                blockedTitle: 'Eliminazione bloccata',
                confirm:
                    'Confermando, rimuoverai questo anno di gestione in modo definitivo',
                confirmSuffix: "dall'elenco disponibile.",
                cancel: 'Annulla',
                confirmAction: 'Elimina anno',
            },
        },
    },
    en: {
        title: 'Settings',
        description:
            'Customize your account, security, and configuration areas.',
        accountArea: 'Account area',
        accountAreaTitle: 'Manage your personal space',
        accountAreaDescription:
            'Quick access to your most important preferences with clearer navigation.',
        sections: {
            profile: 'Profile',
            categories: 'Expense categories',
            trackedItems: 'References',
            banks: 'Banks',
            accounts: 'Accounts',
            years: 'Management years',
            security: 'Security',
            appearance: 'Appearance',
        },
        summaries: {
            profile: 'Personal details and contacts',
            categories: 'Category structure',
            trackedItems: 'Optional personal items',
            banks: 'Available banks directory',
            accounts: 'Accounts, cards, and balances',
            years: 'Open years and active year',
            security: 'Password and authentication',
            appearance: 'Theme and visual preferences',
        },
        appearancePage: {
            title: 'Appearance',
            description:
                'Choose the app theme and make the experience more consistent with your preferences.',
        },
        profile: {
            title: 'Profile information',
            description:
                'Update your name and email address while keeping the existing flow unchanged.',
            regional: {
                title: 'Regional preferences',
                description:
                    'Manage interface language, regional format, and base currency separately.',
                locale: {
                    label: 'Interface language',
                    helper: 'Controls the application language without changing regional formatting or base currency.',
                    placeholder: 'Select a language',
                    save: 'Save language',
                },
                formatLocale: {
                    label: 'Regional format',
                    helper: 'Defines how numbers, amounts, and dates are displayed without changing the UI language.',
                    placeholder: 'Select a regional format',
                    save: 'Save format',
                },
                baseCurrency: {
                    label: 'Base currency',
                    helper: 'Used as the profile and account currency in this phase of the project.',
                    placeholder: 'Select a currency',
                    save: 'Save currency',
                },
            },
            feedback: {
                successTitle: 'Profile updated',
                errorTitle: 'Update failed',
            },
            fields: {
                name: 'Name',
                surname: 'Surname',
                email: 'Email address',
            },
            placeholders: {
                name: 'Name',
                surname: 'Surname',
                email: "{'name@example.com'}",
            },
            verify: {
                notice: 'Your email address has not been verified yet.',
                resend: 'Send the verification email again.',
                sent: 'We have sent a new verification link to your email address.',
            },
            impersonation: {
                title: 'Administrative support consent',
                description:
                    'Choose whether to allow administrative support to temporarily access your account for technical assistance or troubleshooting.',
                label: 'I authorize administrative support to temporarily access my account for technical assistance or troubleshooting',
                helper: 'Admins can only read this preference to understand whether they may assist on your account when a ticket or issue occurs. It does not grant automatic changes, and you can update it at any time.',
                enabledState:
                    'Consent enabled. Support staff may assist only when needed.',
                disabledState:
                    'Consent disabled. No assisted access will be allowed for admin support.',
            },
            notifications: {
                title: 'Notification preferences',
                description:
                    'Choose which optional updates you want to receive by email or see in your dashboard.',
                save: 'Save notification preferences',
                channels: {
                    email: 'Email',
                    dashboard: 'Notifications',
                    sms: 'SMS',
                },
                channelDescriptions: {
                    email: 'Receive this update by email.',
                    dashboard: 'Show this update in your notifications.',
                },
                empty: {
                    title: 'No configurable notifications available',
                    description:
                        'There are no optional notifications you can manage from your profile right now.',
                },
            },
            save: 'Save changes',
        },
        security: {
            password: {
                title: 'Update password',
                description:
                    'Keep access to your account protected with a strong and up-to-date password.',
                current: 'Current password',
                currentPlaceholder: 'Enter your current password',
                next: 'New password',
                nextPlaceholder: 'Enter the new password',
                confirmation: 'Confirm password',
                confirmationPlaceholder: 'Repeat the new password',
                save: 'Save password',
            },
            twoFactor: {
                title: 'Two-factor authentication',
                description:
                    'Add an extra layer of protection to your account sign-in.',
                enableDescription:
                    'When you enable two-factor authentication, you will be prompted for a secure code generated by a TOTP-compatible app on your phone during sign-in.',
                enabledDescription:
                    'During sign-in, you will be prompted for a secure code generated by the TOTP app linked to your account.',
                continue: 'Continue setup',
                enable: 'Enable 2FA',
                disable: 'Disable 2FA',
                recoveryTitle: '2FA recovery codes',
                recoveryDescription:
                    'They let you recover access if you lose your 2FA device. Store them in a secure password manager.',
                showRecovery: 'Show recovery codes',
                hideRecovery: 'Hide recovery codes',
                regenerateRecovery: 'Regenerate codes',
                recoveryHelper:
                    'Each code can be used only once and is removed after use. If you need more, use Regenerate codes.',
                setup: {
                    enabledTitle: 'Two-factor authentication enabled',
                    enabledDescription:
                        'Extra protection is active. Scan the QR code or enter the setup key in your authentication app.',
                    verifyTitle: 'Verify the code',
                    verifyDescription:
                        'Enter the 6-digit code from your authentication app.',
                    enableTitle: 'Enable two-factor authentication',
                    enableDescription:
                        'To complete activation, scan the QR code or manually enter the key in your authentication app.',
                    continue: 'Continue',
                    close: 'Close',
                    manualKey: 'or enter the key manually',
                    codeLabel: 'Code',
                    codePlaceholder: '123456',
                    confirm: 'Confirm',
                },
            },
            deleteUser: {
                title: 'Delete account',
                description:
                    'Permanently remove your account and related data.',
                warningTitle: 'Warning',
                warningDescription:
                    'This action is permanent and cannot be undone.',
                confirmTitle: 'Do you confirm deleting your account?',
                confirmDescription:
                    'Once your account is deleted, all data and related resources will be permanently removed. Enter your password to confirm.',
                password: 'Password',
                delete: 'Delete account',
            },
        },
        banks: {
            title: 'Banks',
            badge: 'Available banks',
            description:
                'Manage the list of banks selectable for your accounts: shared catalog when enough, custom banks when needed.',
            create: 'New custom bank',
            summary: {
                total: 'Total',
                active: 'Active',
                custom: 'Custom',
                used: 'Used by accounts',
            },
            feedback: {
                successTitle: 'Operation completed',
                unavailableTitle: 'Operation unavailable',
                saveTitle: 'Save completed',
                catalogTitle: 'Catalog updated',
                catalogMessage:
                    'The bank has been added to your available banks.',
                statusTitle: 'Status updated',
                activated: 'The bank has been activated.',
                deactivated: 'The bank has been deactivated.',
                deletedTitle: 'Bank removed',
                deletedMessage:
                    'The bank has been removed from your available banks.',
            },
            catalog: {
                title: 'Add from the global catalog',
                description:
                    'Make available only the banks you really want to use.',
                selectLabel: 'Catalog bank',
                selectPlaceholder: 'Select a bank',
                noOptions: 'No additional bank available',
                createBaseAccount: 'Create a base account as well',
                createBaseAccountHelper:
                    'Enabled by default to avoid a second manual step.',
                add: 'Add from catalog',
            },
            catalogList: {
                title: 'Catalog banks',
                description: 'Global entries made available to your profile.',
                empty: 'You have not added any banks from the shared catalog yet. Use the selector above to enable only the ones you need.',
            },
            customList: {
                title: 'Custom banks',
                description: 'Entries created only for your user profile.',
                empty: 'No custom banks. Create one only if you cannot find the bank in the catalog or if you want your own entry.',
                emptyCompact:
                    'No custom banks. Create one only if you cannot find what you need in the catalog.',
            },
            labels: {
                countryUnavailable: 'Country code unavailable',
                slug: 'Slug',
                active: 'Active',
                inactive: 'Inactive',
                remove: 'Remove',
                delete: 'Delete',
            },
            deleteDialog: {
                title: 'Remove available bank',
                removable:
                    'You are about to remove {name} from your bank directory.',
                blocked: '{name} cannot be removed right now.',
                blockedTitle: 'Block reasons',
                confirm: 'Remove bank',
            },
            deleteReasons: {
                accountOne: 'It is already linked to 1 account.',
                accountMany: 'It is already linked to {count} accounts.',
            },
        },
        yearsPage: {
            title: 'Management years',
            badge: 'Settings / Years',
            description:
                'Available years are defined manually in the app. They are not created from transactions: they are used to open a new operating cycle, choose the active year, and close it when it should no longer be editable.',
            summary: {
                total: 'Total',
                open: 'Open',
                closed: 'Closed',
                used: 'With usage',
            },
            feedback: {
                successTitle: 'Operation completed',
                unavailableTitle: 'Operation unavailable',
            },
            create: {
                title: 'New management year',
                description:
                    'For v1, entering the numeric year is enough. If it is the first available year, it is automatically set as active.',
                placeholder: '2027',
                submit: 'New year',
                quickCreate: 'Create {year}',
            },
            empty: {
                title: 'No management year configured',
                description:
                    'Create the first year to start working in the app with an explicit operational space.',
            },
            table: {
                year: 'Year',
                status: 'Status',
                usage: 'Usage',
                actions: 'Actions',
            },
            status: {
                active: 'Active',
                closed: 'Closed',
                openDescription: 'Operational year open to changes.',
                closedDescription:
                    'Read-only: operational changes are blocked.',
                mobileOpenDescription: 'Year open and editable.',
                mobileClosedDescription: 'Read-only until reopened.',
            },
            metrics: {
                budgets: 'Budgets',
                transactions: 'Transactions',
                scheduled: 'Scheduled',
                recurring: 'Recurring',
                usageTitle: 'Usage',
                usedOne: '1 operational link',
                usedMany: '{count} operational links',
                noUsage: 'No usage',
                deletableHint: 'You can delete it if you no longer need it.',
                lockedHint:
                    'If it is already used or active, it can only be opened or closed.',
            },
            actions: {
                setActive: 'Set active',
                open: 'Open',
                close: 'Close',
                delete: 'Delete',
                deleteYear: 'Delete year',
            },
            deleteDialog: {
                title: 'Delete year {year}',
                description:
                    'Deletion is allowed only for non-active years without linked data.',
                blockedTitle: 'Deletion blocked',
                confirm:
                    'By confirming, you will permanently remove this management year',
                confirmSuffix: 'from the available list.',
                cancel: 'Cancel',
                confirmAction: 'Delete year',
            },
        },
    },
} as const;
