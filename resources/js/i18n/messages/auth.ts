export const authMessages = {
    it: {
        login: {
            title: 'Accedi al tuo account',
            description: 'Inserisci email e password per entrare nell’app.',
            headTitle: 'Accedi',
            fields: {
                email: 'Indirizzo email',
                password: 'Password',
                remember: 'Ricordami',
            },
            placeholders: {
                email: "{'email@example.com'}",
                password: 'Inserisci la password',
            },
            actions: {
                submit: 'Accedi',
                forgotPassword: 'Hai dimenticato la password?',
                register: 'Registrati',
            },
            legal: {
                prefix: 'Proseguendo accetti i',
                terms: 'Termini del servizio',
                connector: 'e',
                privacy: "l'informativa sulla privacy",
                suffix: 'di Soamco Budget.',
            },
            footer: { noAccount: 'Non hai ancora un account?' },
        },
        register: {
            title: 'Crea il tuo account',
            description: 'Inserisci i tuoi dati per iniziare.',
            headTitle: 'Registrati',
            fields: {
                name: 'Nome',
                surname: 'Cognome',
                email: 'Indirizzo email',
                password: 'Password',
                passwordConfirmation: 'Conferma password',
            },
            placeholders: {
                name: 'Nome',
                surname: 'Cognome',
                email: "{'email@example.com'}",
                password: 'Crea una password',
                passwordConfirmation: 'Ripeti la password',
            },
            actions: { submit: 'Crea account', login: 'Accedi' },
            legal: {
                prefix: 'Proseguendo accetti i',
                terms: 'Termini del servizio',
                connector: 'e',
                privacy: "l'informativa sulla privacy",
                suffix: 'di Soamco Budget.',
            },
            footer: { hasAccount: 'Hai già un account?' },
        },
        recaptcha: {
            errors: {
                unavailable:
                    'La verifica di sicurezza non è disponibile al momento. Riprova.',
                failed:
                    'La verifica di sicurezza non è andata a buon fine. Riprova.',
            },
        },
        showcase: {
            eyebrow: 'Accesso sicuro',
            panelLabel: 'Anteprima prodotto',
            title: 'Controlla movimenti, budget e scadenze in uno spazio ordinato.',
            description:
                'Un ingresso più chiaro all’app, con una gerarchia visiva pulita e una preview che richiama il lavoro quotidiano senza toccare la logica dei flussi.',
            recovery: {
                panelLabel: 'Recupero accesso',
                title: 'Reimposta la password con un flusso semplice e leggibile.',
                description:
                    'Il pannello laterale mostra un contesto più coerente con il recupero credenziali: email, link sicuro e protezione dell’account.',
                cardTitle: 'Link di reset protetto',
                cardDescription:
                    'Invio email, conferma e nuova password in pochi passaggi.',
                highlights: {
                    email: 'Usa l’indirizzo email collegato al tuo account.',
                    link: 'Ricevi un link di reset sicuro e a scadenza limitata.',
                    security:
                        'Scegli una nuova password e rientra in modo protetto.',
                },
            },
            transactionBadge: 'Ultimo aggiornamento',
            mobilePreview: {
                title: 'Movimenti recenti',
                description:
                    'Una sintesi rapida anche quando accedi da mobile.',
                badge: 'Sincronizzato',
            },
            highlights: {
                security: 'Accesso protetto e dati ordinati in un solo posto.',
                planning:
                    'Scadenze e ricorrenze visibili già dal primo ingresso.',
                control: 'Conti, budget e categorie con struttura leggibile.',
            },
            transactions: {
                salary: {
                    title: 'Stipendio marzo',
                    meta: 'Oggi · Entrata ricorrente',
                    amount: '+ €2.480',
                },
                rent: {
                    title: 'Affitto casa',
                    meta: '2 aprile · Uscita pianificata',
                    amount: '€850',
                },
                groceries: {
                    title: 'Spesa settimanale',
                    meta: 'Domani · Budget alimentari',
                    amount: '€96',
                },
            },
        },
        forgotPassword: {
            title: 'Password dimenticata',
            description:
                'Inserisci il tuo indirizzo email per ricevere il link di reset.',
            headTitle: 'Password dimenticata',
            fields: { email: 'Indirizzo email' },
            placeholders: { email: "{'email@example.com'}" },
            actions: { submit: 'Invia link di reset', login: 'login' },
            footer: { backToLogin: 'Oppure torna al' },
        },
        resetPassword: {
            title: 'Reimposta password',
            description: 'Inserisci qui sotto la tua nuova password.',
            headTitle: 'Reimposta password',
            fields: {
                email: 'Email',
                password: 'Password',
                passwordConfirmation: 'Conferma password',
            },
            placeholders: {
                password: 'Password',
                passwordConfirmation: 'Conferma password',
            },
            actions: { submit: 'Reimposta password' },
        },
        confirmPassword: {
            title: 'Conferma la password',
            description:
                'Questa area è protetta. Conferma la tua password prima di continuare.',
            headTitle: 'Conferma password',
            fields: { password: 'Password' },
            actions: { submit: 'Conferma password' },
        },
        verifyEmail: {
            title: 'Verifica il tuo indirizzo email',
            description:
                'Ti abbiamo inviato un link di conferma. Aprilo dalla tua casella di posta per attivare l’account.',
            headTitle: 'Verifica email',
            status: {
                sent: 'Abbiamo inviato un nuovo link di verifica all’indirizzo email che hai inserito in fase di registrazione.',
            },
            actions: {
                resend: 'Invia di nuovo l’email di verifica',
                logout: 'Esci',
            },
        },
        twoFactor: {
            headTitle: 'Autenticazione a due fattori',
            authenticationCode: {
                title: 'Codice di autenticazione',
                description:
                    'Inserisci il codice fornito dalla tua applicazione di autenticazione.',
                switchAction: 'accedere usando un codice di recupero',
            },
            recoveryCode: {
                title: 'Codice di recupero',
                description:
                    'Conferma l’accesso al tuo account inserendo uno dei codici di recupero di emergenza.',
                placeholder: 'Inserisci il codice di recupero',
                switchAction: 'accedere usando un codice di autenticazione',
            },
            actions: { submit: 'Continua' },
            helper: { alternative: 'oppure puoi' },
        },
        passwordInput: { show: 'Mostra password', hide: 'Nascondi password' },
        accountInvitation: {
            headTitle: 'Invito conto',
            actions: {
                goToLogin: 'Accedi per continuare',
                accept: 'Accetta invito',
                logoutAndSwitch: 'Esci e accedi con l’account corretto',
            },
            form: {
                firstName: 'Nome',
                lastName: 'Cognome',
                email: 'Indirizzo email',
                password: 'Password',
                passwordConfirmation: 'Conferma password',
                submitRegister: 'Completa registrazione e accetta invito',
            },
            summary: {
                inviter: 'Chi ti invita',
                account: 'Conto condiviso',
                role: 'Livello di accesso',
                email: 'Email invitata',
                expiresAt: 'Questo invito scade il {date}.',
            },
            fallbacks: {
                inviter: 'Persona invitante',
                account: 'Conto condiviso',
                email: 'email non disponibile',
            },
            states: {
                registration: {
                    title: 'Hai ricevuto un invito',
                    description:
                        '{inviter} ti ha invitato ad accedere al conto "{account}". Per continuare, completa la registrazione.',
                },
                login: {
                    title: 'Hai già un account',
                    description:
                        'Accedi con l’indirizzo email {email} per accettare questo invito.',
                    alertTitle: 'Accesso richiesto',
                    alertDescription:
                        'Per accettare questo invito devi entrare con l’indirizzo email associato.',
                },
                accept: {
                    title: 'Conferma invito',
                    description:
                        'Vuoi accettare l’accesso al conto "{account}"?',
                    alertTitle: 'Accesso pronto per essere confermato',
                    alertDescription:
                        'Se confermi, il conto verrà aggiunto ai conti a cui puoi accedere.',
                },
                mismatch: {
                    title: 'Questo invito è associato a un altro indirizzo email',
                    description:
                        'Per accettarlo, accedi con l’account corretto.',
                    alertTitle: 'Account non corrispondente',
                    alertDescription:
                        'Sei connesso come {currentEmail}, ma questo invito è destinato a {inviteeEmail}.',
                },
                expired: {
                    title: 'Invito scaduto',
                    description:
                        'Questo invito non è più valido. Chiedi alla persona che ti ha invitato di inviarne uno nuovo.',
                },
                processed: {
                    title: 'Invito già utilizzato',
                    description:
                        'Questo invito è già stato accettato o revocato e non può essere usato di nuovo.',
                },
                invalid: {
                    title: 'Invito non valido',
                    description:
                        'Il link che hai aperto non è valido o non è più disponibile.',
                },
            },
        },
        welcome: {
            headTitle: 'Benvenuto',
            nav: {
                home: 'Home',
                features: 'Funzionalità',
                pricing: 'Prezzi',
                changelog: 'Changelog',
                downloadApp: 'Scarica app',
                customers: 'Clienti',
                aboutMe: 'Chi sono',
                login: 'Accedi',
                registerFree: 'Registrati gratis',
                openMenu: 'Apri menu pubblico',
                tagline:
                    'Contabilità personale ordinata, leggibile, condivisibile.',
            },
            actions: {
                dashboard: 'Dashboard',
                login: 'Accedi',
                register: 'Registrati',
                changelog: 'Changelog',
                discoverFeatures: 'Scopri funzionalità',
                viewPricing: 'Vedi prezzi',
            },
            hero: {
                eyebrow: 'Controllo finanziario personale',
                title: 'Porta ordine in budget, conti e movimenti senza aggiungere complessità.',
                description:
                    'Una base chiara per seguire saldi, pianificazione mensile e ricorrenze con un’interfaccia leggibile, rigorosa sui numeri e stabile nel tempo.',
                meta: {
                    noCard: 'Attivazione veloce',
                    beta: 'Software in evoluzione continua',
                },
                stats: {
                    readiness: {
                        label: 'Avvio',
                        value: 'Subito operativo',
                        note: 'Registri, planning e movimenti ricorrenti in un flusso coerente.',
                    },
                    structure: {
                        label: 'Struttura',
                        value: 'Un solo spazio',
                        note: 'Conti, categorie e anni gestiti con ordine e gerarchia forte.',
                    },
                    visibility: {
                        label: 'Visibilità',
                        value: 'Alert utili',
                        note: 'Segnali, attività e controlli senza dashboard confuse.',
                    },
                },
            },
            preview: {
                title: 'Vista prodotto',
                description:
                    'Una shell finanziaria pensata per leggere rapidamente lo stato del mese e capire cosa richiede attenzione.',
                rows: {
                    cashflow: {
                        title: 'Flusso del mese',
                        subtitle: 'Scostamento entrate/spese sotto controllo',
                    },
                    recurring: {
                        title: 'Ricorrenze attive',
                        subtitle:
                            'Occorrenze previste già allineate al periodo',
                    },
                    alerts: {
                        title: 'Controlli aperti',
                        subtitle:
                            'Punti che meritano revisione prima della chiusura',
                    },
                },
                banner: {
                    title: 'Più chiarezza, meno attrito operativo',
                    description:
                        'Navigazione lineare, gerarchia leggibile e spazio per far evolvere branding, changelog e onboarding pubblico.',
                },
            },
            features: {
                eyebrow: 'Perché funziona',
                title: 'Una base finanziaria personale che resta chiara anche quando cresce.',
                description:
                    'Soamco Budget unisce conti, budget, movimenti e ricorrenze in un’esperienza più ordinata, leggibile e credibile fin dal primo sguardo.',
                showcase: {
                    title: 'Una vista ordinata che fa capire subito cosa conta davvero.',
                },
                cards: {
                    workspace: {
                        title: 'Struttura leggibile',
                        description:
                            'Conti, anni, categorie e stato del mese restano comprensibili senza dover ricostruire il contesto a ogni accesso.',
                    },
                    recurring: {
                        title: 'Routine mensile stabile',
                        description:
                            'Budget, ricorrenze e movimenti seguono un flusso coerente, utile sia per il controllo quotidiano sia per la revisione di fine mese.',
                    },
                    visibility: {
                        title: 'Controlli senza caos',
                        description:
                            'Segnali, priorità e punti da rivedere emergono con chiarezza, senza l’effetto di una dashboard pesante o complessa.',
                    },
                },
            },
            principles: {
                eyebrow: 'Direzione visiva',
                title: 'Pulizia, rigore e chiarezza prima di tutto.',
                description:
                    'Il prodotto punta a essere utile nel tempo: interfaccia chiara, decisioni leggibili e miglioramenti continui senza complicare l’esperienza.',
                items: {
                    clarity: {
                        title: 'Chiarezza immediata',
                        description:
                            'Il valore del prodotto è leggibile nei primi secondi, senza dipendere da spiegazioni secondarie.',
                    },
                    control: {
                        title: 'Contesto professionale',
                        description:
                            'Colori, superfici e ritmo comunicano un software finanziario sobrio, non una landing generica.',
                    },
                    rhythm: {
                        title: 'Mobile first serio',
                        description:
                            'Contenuti impilati bene, CTA accessibili e blocchi stabili anche su schermi stretti.',
                    },
                },
            },
            pricing: {
                title: 'Gratis da usare, utile da sostenere.',
                description:
                    'Puoi iniziare senza costi. Se il prodotto ti aiuta davvero, puoi anche scegliere di supportarne la crescita con una donazione facoltativa.',
                plan: {
                    label: 'Accesso completo',
                    title: 'Gratis oggi, pensato per crescere bene',
                    description:
                        'Dashboard, registri, planning e ricorrenze sono disponibili fin da subito in uno spazio ordinato, leggibile e già utile nella gestione quotidiana.',
                    price: 'Gratis',
                    period: '',
                },
                items: {
                    households:
                        'Conti, categorie e anni in un unico spazio ordinato.',
                    householdsDescription:
                        'Una struttura chiara per tenere insieme ciò che serve davvero, senza dispersione tra viste e strumenti scollegati.',
                    recurring:
                        'Planning e routine mensile già pronti a lavorare insieme.',
                    recurringDescription:
                        'Budget, movimenti e ricorrenze aiutano a seguire il mese con più continuità e meno frizione operativa.',
                    visibility:
                        'Se ti è utile, puoi aiutarlo a crescere con un supporto facoltativo.',
                },
                support: {
                    title: 'Un prodotto gratuito che può crescere grazie a chi lo trova davvero utile.',
                    description:
                        'Nessun piano artificiale e nessun obbligo. La donazione serve solo a sostenere hosting, manutenzione e miglioramenti futuri, se vuoi contribuire.',
                },
            },
            footer: {
                description:
                    'Unisciti a milioni di persone che organizzano la loro vita e il loro lavoro con una struttura più chiara.',
                language: 'Lingua',
                legal: {
                    security: 'Sicurezza',
                    privacy: 'Privacy',
                    terms: 'Termini',
                    cookies: 'Preferenze sui cookie',
                },
                groups: {
                    features: {
                        title: 'Funzionalità',
                        links: {
                            howItWorks: 'Come funziona',
                            pricing: 'Prezzi',
                        },
                    },
                    resources: {
                        title: 'Risorse',
                        links: {
                            apps: 'Scarica le app',
                            help: 'Centro Assistenza',
                            stories: 'Storie dei clienti',
                        },
                    },
                    company: {
                        title: 'Developer',
                        links: {
                            about: 'Chi sono',
                        },
                    },
                },
            },
            cta: {
                eyebrow: 'Prossimo passo',
                title: 'Inizia a mettere ordine nei tuoi conti con uno strumento chiaro e già utile.',
                description:
                    'Registrati gratis per provare dashboard, movimenti, budget e ricorrenze in un unico spazio. Se nel tempo lo trovi davvero utile, puoi anche scegliere di supportarne la crescita.',
            },
        },
    },
    en: {
        login: {
            title: 'Sign in to your account',
            description: 'Enter your email and password to access the app.',
            headTitle: 'Sign in',
            fields: {
                email: 'Email address',
                password: 'Password',
                remember: 'Remember me',
            },
            placeholders: {
                email: "{'email@example.com'}",
                password: 'Enter your password',
            },
            actions: {
                submit: 'Sign in',
                forgotPassword: 'Forgot your password?',
                register: 'Register',
            },
            legal: {
                prefix: 'By continuing you agree to the',
                terms: 'Terms of Service',
                connector: 'and',
                privacy: 'Privacy Notice',
                suffix: 'of Soamco Budget.',
            },
            footer: { noAccount: "Don't have an account yet?" },
        },
        register: {
            title: 'Create your account',
            description: 'Enter your details to get started.',
            headTitle: 'Register',
            fields: {
                name: 'First name',
                surname: 'Last name',
                email: 'Email address',
                password: 'Password',
                passwordConfirmation: 'Confirm password',
            },
            placeholders: {
                name: 'First name',
                surname: 'Last name',
                email: "{'email@example.com'}",
                password: 'Create a password',
                passwordConfirmation: 'Repeat your password',
            },
            actions: { submit: 'Create account', login: 'Sign in' },
            legal: {
                prefix: 'By continuing you agree to the',
                terms: 'Terms of Service',
                connector: 'and',
                privacy: 'Privacy Notice',
                suffix: 'of Soamco Budget.',
            },
            footer: { hasAccount: 'Already have an account?' },
        },
        recaptcha: {
            errors: {
                unavailable:
                    'The security check is currently unavailable. Please try again.',
                failed:
                    'The security check could not be completed. Please try again.',
            },
        },
        showcase: {
            eyebrow: 'Secure access',
            panelLabel: 'Product preview',
            title: 'Track transactions, budgets and due dates in one tidy workspace.',
            description:
                'A cleaner entry point to the app, with stronger hierarchy and a visual preview of daily finance work without changing any flow logic.',
            recovery: {
                panelLabel: 'Account recovery',
                title: 'Reset your password with a simpler, clearer recovery flow.',
                description:
                    'The side panel switches from transactions to a recovery-oriented visual: email verification, secure reset link and account protection.',
                cardTitle: 'Protected reset link',
                cardDescription:
                    'Email delivery, confirmation and new password in just a few steps.',
                highlights: {
                    email: 'Use the email address linked to your account.',
                    link: 'Receive a secure reset link with a limited validity window.',
                    security: 'Choose a new password and get back in safely.',
                },
            },
            transactionBadge: 'Latest update',
            mobilePreview: {
                title: 'Recent activity',
                description:
                    'A compact summary that still works well on mobile.',
                badge: 'Synced',
            },
            highlights: {
                security:
                    'Protected access with all your financial data kept in order.',
                planning:
                    'Deadlines and recurring entries visible from the first visit.',
                control:
                    'Accounts, budgets and categories with strong readability.',
            },
            transactions: {
                salary: {
                    title: 'March salary',
                    meta: 'Today · Recurring income',
                    amount: '+ €2,480',
                },
                rent: {
                    title: 'Home rent',
                    meta: 'April 2 · Planned expense',
                    amount: '€850',
                },
                groceries: {
                    title: 'Weekly groceries',
                    meta: 'Tomorrow · Food budget',
                    amount: '€96',
                },
            },
        },
        forgotPassword: {
            title: 'Forgot password',
            description:
                'Enter your email address to receive a password reset link.',
            headTitle: 'Forgot password',
            fields: { email: 'Email address' },
            placeholders: { email: "{'email@example.com'}" },
            actions: { submit: 'Send reset link', login: 'sign in' },
            footer: { backToLogin: 'Or go back to' },
        },
        resetPassword: {
            title: 'Reset password',
            description: 'Please enter your new password below.',
            headTitle: 'Reset password',
            fields: {
                email: 'Email',
                password: 'Password',
                passwordConfirmation: 'Confirm password',
            },
            placeholders: {
                password: 'Password',
                passwordConfirmation: 'Confirm password',
            },
            actions: { submit: 'Reset password' },
        },
        confirmPassword: {
            title: 'Confirm your password',
            description:
                'This area is protected. Please confirm your password before continuing.',
            headTitle: 'Confirm password',
            fields: { password: 'Password' },
            actions: { submit: 'Confirm password' },
        },
        verifyEmail: {
            title: 'Verify your email address',
            description:
                'We sent you a confirmation link. Open it from your inbox to activate your account.',
            headTitle: 'Verify email',
            status: {
                sent: 'We sent a new verification link to the email address you provided during registration.',
            },
            actions: { resend: 'Resend verification email', logout: 'Log out' },
        },
        twoFactor: {
            headTitle: 'Two-factor authentication',
            authenticationCode: {
                title: 'Authentication code',
                description:
                    'Enter the authentication code provided by your authenticator application.',
                switchAction: 'sign in using a recovery code',
            },
            recoveryCode: {
                title: 'Recovery code',
                description:
                    'Confirm access to your account by entering one of your emergency recovery codes.',
                placeholder: 'Enter recovery code',
                switchAction: 'sign in using an authentication code',
            },
            actions: { submit: 'Continue' },
            helper: { alternative: 'or you can' },
        },
        passwordInput: { show: 'Show password', hide: 'Hide password' },
        accountInvitation: {
            headTitle: 'Account invitation',
            actions: {
                goToLogin: 'Sign in to continue',
                accept: 'Accept invitation',
                logoutAndSwitch: 'Log out and switch account',
            },
            form: {
                firstName: 'First name',
                lastName: 'Last name',
                email: 'Email address',
                password: 'Password',
                passwordConfirmation: 'Confirm password',
                submitRegister: 'Complete registration and accept invitation',
            },
            summary: {
                inviter: 'Invited by',
                account: 'Shared account',
                role: 'Access level',
                email: 'Invited email',
                expiresAt: 'This invitation expires on {date}.',
            },
            fallbacks: {
                inviter: 'Inviter',
                account: 'Shared account',
                email: 'email unavailable',
            },
            states: {
                registration: {
                    title: 'You received an invitation',
                    description:
                        '{inviter} invited you to access the account "{account}". To continue, complete your registration.',
                },
                login: {
                    title: 'You already have an account',
                    description:
                        'Sign in with the email address {email} to accept this invitation.',
                    alertTitle: 'Sign in required',
                    alertDescription:
                        'To accept this invitation, sign in with the email address linked to it.',
                },
                accept: {
                    title: 'Confirm invitation',
                    description:
                        'Do you want to accept access to the account "{account}"?',
                    alertTitle: 'Ready to confirm access',
                    alertDescription:
                        'Once confirmed, this account will be added to the accounts you can access.',
                },
                mismatch: {
                    title: 'This invitation belongs to another email address',
                    description:
                        'To accept it, sign in with the correct account.',
                    alertTitle: 'Signed in with a different account',
                    alertDescription:
                        'You are signed in as {currentEmail}, but this invitation is intended for {inviteeEmail}.',
                },
                expired: {
                    title: 'Invitation expired',
                    description:
                        'This invitation is no longer valid. Ask the inviter to send a new one.',
                },
                processed: {
                    title: 'Invitation already used',
                    description:
                        'This invitation has already been accepted or revoked and can no longer be used.',
                },
                invalid: {
                    title: 'Invalid invitation',
                    description:
                        'The link you opened is invalid or no longer available.',
                },
            },
        },
        welcome: {
            headTitle: 'Welcome',
            nav: {
                home: 'Home',
                features: 'Features',
                pricing: 'Pricing',
                changelog: 'Changelog',
                downloadApp: 'Download app',
                customers: 'Customers',
                aboutMe: 'About me',
                login: 'Log in',
                registerFree: 'Sign up free',
                openMenu: 'Open public menu',
                tagline:
                    'Personal finance made orderly, readable, and shareable.',
            },
            actions: {
                dashboard: 'Dashboard',
                login: 'Log in',
                register: 'Register',
                changelog: 'Changelog',
                discoverFeatures: 'Explore features',
                viewPricing: 'View pricing',
            },
            hero: {
                eyebrow: 'Personal financial control',
                title: 'Bring order to budgets, accounts, and transactions without adding complexity.',
                description:
                    'A clear foundation for balances, monthly planning, and recurring entries with an interface built for readability, rigor, and long-term maintainability.',
                meta: {
                    noCard: 'Fast activation',
                    beta: 'Software with continuous improvement',
                },
                stats: {
                    readiness: {
                        label: 'Readiness',
                        value: 'Ready to work',
                        note: 'Registers, planning, and recurring entries in one consistent flow.',
                    },
                    structure: {
                        label: 'Structure',
                        value: 'One clear workspace',
                        note: 'Accounts, categories, and years organized with stronger hierarchy.',
                    },
                    visibility: {
                        label: 'Visibility',
                        value: 'Useful alerts',
                        note: 'Signals and checks surfaced without cluttered dashboards.',
                    },
                },
            },
            preview: {
                title: 'Product preview',
                description:
                    'A financial workspace designed to read the current month quickly and understand what needs attention.',
                rows: {
                    cashflow: {
                        title: 'Monthly cash flow',
                        subtitle: 'Income and spending variance kept visible',
                    },
                    recurring: {
                        title: 'Active recurring items',
                        subtitle:
                            'Expected occurrences aligned to the current period',
                    },
                    alerts: {
                        title: 'Open checks',
                        subtitle:
                            'Points worth reviewing before closing the month',
                    },
                },
                banner: {
                    title: 'More clarity, less operational friction',
                    description:
                        'Linear navigation, readable hierarchy, and room for branding, changelog, and future public onboarding.',
                },
            },
            features: {
                eyebrow: 'Why it works',
                title: 'A personal finance foundation that stays clear as it grows.',
                description:
                    'Soamco Budget brings accounts, budgets, transactions, and recurring flows into a clearer, more credible experience from the very first visit.',
                showcase: {
                    title: 'An orderly view that makes the important things readable right away.',
                },
                cards: {
                    workspace: {
                        title: 'Readable structure',
                        description:
                            'Accounts, years, categories, and month status stay understandable without rebuilding context every time.',
                    },
                    recurring: {
                        title: 'Stable monthly routine',
                        description:
                            'Budgets, recurring flows, and transactions follow one coherent rhythm for daily use and month-end review.',
                    },
                    visibility: {
                        title: 'Checks without chaos',
                        description:
                            'Signals, priorities, and review points stand out clearly without feeling like a heavy or overly complex dashboard.',
                    },
                },
            },
            principles: {
                eyebrow: 'Visual direction',
                title: 'Clarity, rigor, and cleanliness first.',
                description:
                    'The product is built to stay useful over time: clear interface, readable decisions, and continuous improvement without unnecessary complexity.',
                items: {
                    clarity: {
                        title: 'Immediate clarity',
                        description:
                            'The product value is understandable within seconds, without relying on secondary explanations.',
                    },
                    control: {
                        title: 'Professional tone',
                        description:
                            'Color, surfaces, and rhythm communicate a serious finance product rather than a generic landing page.',
                    },
                    rhythm: {
                        title: 'Serious mobile-first layout',
                        description:
                            'Content stacks cleanly, CTAs remain accessible, and blocks stay stable on narrow screens.',
                    },
                },
            },
            pricing: {
                title: 'Free to use, worth supporting.',
                description:
                    'You can start at no cost. If the product genuinely helps you, you can also choose to support its growth with an optional donation.',
                plan: {
                    label: 'Full access',
                    title: 'Free today, built to grow well',
                    description:
                        'Dashboard, registers, planning, and recurring flows are available from the start in a clear, practical workspace.',
                    price: 'Free',
                    period: '',
                },
                items: {
                    households:
                        'Accounts, categories, and years in one orderly workspace.',
                    householdsDescription:
                        'A clearer structure to keep everything meaningful together, without scattering the workflow across unrelated views.',
                    recurring:
                        'Planning and monthly routines already designed to work together.',
                    recurringDescription:
                        'Budgets, transactions, and recurring flows help you follow the month with more consistency and less friction.',
                    visibility:
                        'If it proves useful, you can help it keep growing with optional support.',
                },
                support: {
                    title: 'A free product that can keep improving thanks to people who genuinely find it useful.',
                    description:
                        'No artificial plan structure and no obligation. Support simply helps cover hosting, maintenance, and future improvements if you want to contribute.',
                },
            },
            footer: {
                description:
                    'Join people who organize work and personal planning with a clearer, lighter structure.',
                language: 'Language',
                legal: {
                    security: 'Security',
                    privacy: 'Privacy',
                    terms: 'Terms',
                    cookies: 'Cookie preferences',
                },
                groups: {
                    features: {
                        title: 'Features',
                        links: {
                            howItWorks: 'How it works',
                            forTeams: 'For teams',
                            pricing: 'Pricing',
                            compare: 'Compare',
                            templates: 'Templates',
                        },
                    },
                    resources: {
                        title: 'Resources',
                        links: {
                            apps: 'Download apps',
                            help: 'Help center',
                            stories: 'Customer stories',
                            productivity: 'Boost productivity',
                            integrations: 'Integrations',
                            api: 'Developer API',
                        },
                    },
                    company: {
                        title: 'Company',
                        links: {
                            about: 'About us',
                            workWithUs: 'Work with us',
                            inspiration: 'Inspiration center',
                            press: 'Press',
                        },
                    },
                },
            },
            cta: {
                eyebrow: 'Next step',
                title: 'Start bringing order to your finances with a tool that is already clear and useful.',
                description:
                    'Create a free account to try dashboard, transactions, budgets, and recurring flows in one place. If it genuinely proves useful over time, you can also choose to support its growth.',
            },
        },
    },
} as const;
