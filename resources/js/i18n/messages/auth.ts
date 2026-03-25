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
            footer: { hasAccount: 'Hai già un account?' },
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
        welcome: {
            headTitle: 'Benvenuto',
            actions: {
                dashboard: 'Dashboard',
                login: 'Accedi',
                register: 'Registrati',
                docs: 'Documentazione',
                deploy: 'Pubblica ora',
            },
            hero: {
                title: 'Iniziamo',
                description:
                    'Laravel offre un ecosistema molto ricco. Ti suggeriamo di partire da qui.',
            },
            resources: {
                docsPrefix: 'Leggi la',
                tutorialsPrefix: 'Guarda i video su',
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
            footer: { hasAccount: 'Already have an account?' },
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
        welcome: {
            headTitle: 'Welcome',
            actions: {
                dashboard: 'Dashboard',
                login: 'Log in',
                register: 'Register',
                docs: 'Documentation',
                deploy: 'Deploy now',
            },
            hero: {
                title: "Let's get started",
                description:
                    'Laravel has an incredibly rich ecosystem. We suggest starting with the following.',
            },
            resources: {
                docsPrefix: 'Read the',
                tutorialsPrefix: 'Watch video tutorials at',
            },
        },
    },
} as const;
