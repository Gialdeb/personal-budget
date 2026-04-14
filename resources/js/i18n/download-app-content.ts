type DownloadStep = {
    title: string;
    description: string;
};

type DownloadContent = {
    headTitle: string;
    hero: {
        eyebrow: string;
        title: string;
        description: string;
        androidLabel: string;
        iosLabel: string;
    };
    benefits: {
        eyebrow: string;
        title: string;
        description: string;
        items: string[];
    };
    android: {
        eyebrow: string;
        title: string;
        description: string;
        imageAlt: string;
        steps: DownloadStep[];
    };
    ios: {
        eyebrow: string;
        title: string;
        description: string;
        imageAlt: string;
        steps: DownloadStep[];
    };
    faq: {
        eyebrow: string;
        title: string;
        description: string;
        items: Array<{
            question: string;
            answer: string;
        }>;
    };
    cta: {
        eyebrow: string;
        title: string;
        description: string;
        installLabel: string;
        installingLabel: string;
        installedLabel: string;
        iosLabel: string;
        unavailableLabel: string;
        dismissedLabel: string;
        iosHint: string;
        unavailableHint: string;
        dismissedHint: string;
        registerLabel: string;
        featuresLabel: string;
        pricingLabel: string;
    };
};

export const downloadAppContent: Record<'it' | 'en', DownloadContent> = {
    it: {
        headTitle: 'Scarica app',
        hero: {
            eyebrow: 'Installa la PWA',
            title: 'Installa Soamco Budget sul tuo telefono in pochi passaggi.',
            description:
                'Puoi aggiungere Soamco Budget alla schermata home del dispositivo senza passare per forza da uno store. La procedura è semplice sia su Android sia su iPhone o iPad.',
            androidLabel: 'Vai ad Android',
            iosLabel: 'Vai a iPhone / iPad',
        },
        benefits: {
            eyebrow: 'Perché installarla',
            title: 'Un accesso più rapido e un’esperienza più comoda da mobile.',
            description:
                'L’installazione come app rende l’uso più immediato, soprattutto quando vuoi controllare il budget o registrare un movimento velocemente.',
            items: [
                'Accesso diretto dalla schermata home.',
                'Esperienza più simile a un’app dedicata.',
                'Uso più rapido da telefono o tablet.',
                'Nessun passaggio obbligatorio da Play Store o App Store.',
            ],
        },
        android: {
            eyebrow: 'Android',
            title: 'Come installarla su Android',
            description:
                'Su Android il modo più semplice è usare Chrome o un browser compatibile e aggiungere il sito alla schermata home.',
            imageAlt: 'Guida Android per installare Soamco Budget come app',
            steps: [
                {
                    title: 'Apri Soamco Budget nel browser',
                    description:
                        'Visita il sito dal tuo telefono Android usando Chrome o un browser compatibile.',
                },
                {
                    title: 'Apri il menu del browser',
                    description:
                        'Tocca il menu in alto a destra e cerca l’opzione per installare o aggiungere alla schermata home.',
                },
                {
                    title: 'Conferma l’installazione',
                    description:
                        'Accetta il nome proposto e conferma. L’icona apparirà nella schermata home come una normale app.',
                },
            ],
        },
        ios: {
            eyebrow: 'iPhone / iPad',
            title: 'Come installarla su iPhone o iPad',
            description:
                'Su iPhone e iPad il flusso più affidabile passa da Safari e dall’opzione “Aggiungi a Home”.',
            imageAlt:
                'Guida iPhone e iPad per installare Soamco Budget come app',
            steps: [
                {
                    title: 'Apri Soamco Budget in Safari',
                    description:
                        'Usa Safari su iPhone o iPad per visitare il sito, perché è il browser che gestisce correttamente l’aggiunta alla Home.',
                },
                {
                    title: 'Tocca il pulsante Condividi',
                    description:
                        'Apri il menu di condivisione di Safari e scorri fino all’opzione “Aggiungi a Home”.',
                },
                {
                    title: 'Aggiungi l’app alla schermata home',
                    description:
                        'Conferma il nome e salva. Da quel momento Soamco Budget sarà disponibile come icona sulla Home del dispositivo.',
                },
            ],
        },
        faq: {
            eyebrow: 'FAQ',
            title: 'Domande rapide prima di installarla',
            description:
                'Una guida semplice, pensata anche per chi non vuole leggere documentazione tecnica.',
            items: [
                {
                    question: 'Serve il Play Store o l’App Store?',
                    answer: 'No. L’installazione può avvenire direttamente dal browser, senza passare obbligatoriamente dagli store.',
                },
                {
                    question: 'Quale browser devo usare?',
                    answer: 'Su Android è consigliato Chrome. Su iPhone e iPad è meglio usare Safari.',
                },
                {
                    question: 'Posso installarla anche più tardi?',
                    answer: 'Sì. Puoi usare il sito normalmente e decidere di aggiungerlo alla Home quando preferisci.',
                },
                {
                    question: 'Cosa cambia dopo l’installazione?',
                    answer: 'Avrai un accesso più rapido dalla schermata home e un’esperienza più vicina a quella di un’app.',
                },
            ],
        },
        cta: {
            eyebrow: 'Prossimo passo',
            title: 'Installa l’app e tieni Soamco Budget a portata di tocco.',
            description:
                'Puoi iniziare dal browser, aggiungerla alla Home e continuare a usarla nel modo più comodo per te.',
            installLabel: 'Installa app',
            installingLabel: 'Apro il prompt...',
            installedLabel: 'App già installata',
            iosLabel: 'Apri istruzioni iPhone / iPad',
            unavailableLabel: 'Controlla come installarla',
            dismissedLabel: 'Prompt chiuso, vedi istruzioni',
            iosHint:
                'Su iPhone e iPad il pulsante di installazione del browser non esiste: usa Safari e scegli Condividi -> Aggiungi a Home.',
            unavailableHint:
                'Se il browser non mostra il prompt, usa il menu del browser per installare o aggiungere il sito alla schermata Home.',
            dismissedHint:
                'Il prompt del browser e stato chiuso o non e piu disponibile. Puoi riprovare dal menu del browser oppure seguire i passaggi qui sotto.',
            registerLabel: 'Registrati gratis',
            featuresLabel: 'Scopri le funzionalità',
            pricingLabel: 'Vedi prezzi',
        },
    },
    en: {
        headTitle: 'Download app',
        hero: {
            eyebrow: 'Install the PWA',
            title: 'Install Soamco Budget on your phone in just a few steps.',
            description:
                'You can add Soamco Budget to your device home screen without necessarily going through an app store. The process is simple on both Android and iPhone or iPad.',
            androidLabel: 'Go to Android',
            iosLabel: 'Go to iPhone / iPad',
        },
        benefits: {
            eyebrow: 'Why install it',
            title: 'Faster access and a more comfortable mobile experience.',
            description:
                'Installing it as an app makes it easier to open quickly, especially when you want to check a budget or log a transaction on the go.',
            items: [
                'Direct access from the home screen.',
                'An experience closer to a dedicated app.',
                'More convenient use on phone or tablet.',
                'No mandatory Play Store or App Store step.',
            ],
        },
        android: {
            eyebrow: 'Android',
            title: 'How to install it on Android',
            description:
                'On Android the easiest path is through Chrome or a compatible browser, then adding the site to your home screen.',
            imageAlt: 'Android guide to install Soamco Budget as an app',
            steps: [
                {
                    title: 'Open Soamco Budget in the browser',
                    description:
                        'Visit the site from your Android phone using Chrome or another compatible browser.',
                },
                {
                    title: 'Open the browser menu',
                    description:
                        'Tap the top-right browser menu and look for the option to install or add the site to your home screen.',
                },
                {
                    title: 'Confirm the installation',
                    description:
                        'Accept the suggested name and confirm. The icon will appear on your home screen like a regular app.',
                },
            ],
        },
        ios: {
            eyebrow: 'iPhone / iPad',
            title: 'How to install it on iPhone or iPad',
            description:
                'On iPhone and iPad the most reliable flow uses Safari and the “Add to Home Screen” action.',
            imageAlt:
                'iPhone and iPad guide to install Soamco Budget as an app',
            steps: [
                {
                    title: 'Open Soamco Budget in Safari',
                    description:
                        'Use Safari on your iPhone or iPad because it handles home screen installation properly.',
                },
                {
                    title: 'Tap the Share button',
                    description:
                        'Open Safari’s share menu and scroll until you find “Add to Home Screen”.',
                },
                {
                    title: 'Add the app to your home screen',
                    description:
                        'Confirm the suggested name and save it. From there, Soamco Budget will be available as an icon on the device home screen.',
                },
            ],
        },
        faq: {
            eyebrow: 'FAQ',
            title: 'Quick questions before installing it',
            description:
                'A simple guide, written to stay clear even for non-technical users.',
            items: [
                {
                    question: 'Do I need the Play Store or App Store?',
                    answer: 'No. Installation can happen directly from the browser without necessarily going through the stores.',
                },
                {
                    question: 'Which browser should I use?',
                    answer: 'Chrome is recommended on Android. Safari is the best option on iPhone and iPad.',
                },
                {
                    question: 'Can I install it later?',
                    answer: 'Yes. You can use the site normally first and add it to your home screen whenever you want.',
                },
                {
                    question: 'What changes after installation?',
                    answer: 'You get faster access from the home screen and a more app-like experience on mobile.',
                },
            ],
        },
        cta: {
            eyebrow: 'Next step',
            title: 'Install the app and keep Soamco Budget one tap away.',
            description:
                'You can start from the browser, add it to your home screen, and keep using it in the most convenient way for you.',
            installLabel: 'Install app',
            installingLabel: 'Opening prompt...',
            installedLabel: 'App already installed',
            iosLabel: 'Open iPhone / iPad instructions',
            unavailableLabel: 'See installation steps',
            dismissedLabel: 'Prompt closed, see instructions',
            iosHint:
                'On iPhone and iPad there is no browser install prompt: use Safari and choose Share -> Add to Home Screen.',
            unavailableHint:
                'If the browser does not expose the prompt, use the browser menu to install the site or add it to your home screen.',
            dismissedHint:
                'The browser prompt was dismissed or is no longer available. You can retry from the browser menu or follow the steps below.',
            registerLabel: 'Sign up free',
            featuresLabel: 'Explore features',
            pricingLabel: 'View pricing',
        },
    },
};
