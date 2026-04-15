export type FeaturesLocale = 'it' | 'en';

type FeatureSection = {
    key:
        | 'dashboard'
        | 'transactions'
        | 'budget-planning'
        | 'shared-accounts'
        | 'recurring-entries'
        | 'credit-cards';
    title: string;
    description: string;
    imageAlt: string;
    highlights: string[];
};

type FeaturesContent = {
    headTitle: string;
    hero: {
        eyebrow: string;
        title: string;
        description: string;
        registerLabel: string;
        loginLabel: string;
        highlights: string[];
    };
    sections: {
        eyebrow: string;
        title: string;
        description: string;
        items: FeatureSection[];
    };
    importer: {
        eyebrow: string;
        title: string;
        description: string;
        note: string;
        bullets: string[];
        cards: Array<{
            title: string;
            description: string;
            badge: string;
        }>;
    };
    cta: {
        eyebrow: string;
        title: string;
        description: string;
        note: string;
        registerLabel: string;
        pricingLabel: string;
    };
};

export const featuresContent: Record<FeaturesLocale, FeaturesContent> = {
    it: {
        headTitle: 'Funzionalità',
        hero: {
            eyebrow: 'Panoramica prodotto',
            title: 'Le funzionalità principali di Soamco Budget, spiegate in modo concreto.',
            description:
                'Una panoramica chiara delle viste che aiutano a controllare saldi, pianificazione, movimenti e collaborazione, con screenshot localizzati in base alla lingua scelta.',
            registerLabel: 'Registrati gratis',
            loginLabel: 'Accedi',
            highlights: [
                'Dashboard sintetica per capire subito cosa richiede attenzione.',
                'Registro movimenti leggibile con controllo rapido del periodo.',
                'Ricorrenze e scadenze mantenute ordinate lungo il mese.',
            ],
        },
        sections: {
            eyebrow: 'Blocchi funzionalità',
            title: 'Una pagina semplice da leggere e facile da estendere.',
            description:
                'Ogni area mostra cosa permette di fare il prodotto, perché è utile e come si presenta davvero nell’interfaccia.',
            items: [
                {
                    key: 'dashboard',
                    title: 'Dashboard',
                    description:
                        'Una vista iniziale pensata per leggere saldi, scostamenti e segnali aperti senza perdere tempo in pannelli confusi.',
                    imageAlt: 'Screenshot della dashboard di Soamco Budget',
                    highlights: [
                        'Riepilogo immediato di entrate, uscite e saldo del periodo.',
                        'Segnali chiari sulle aree che meritano revisione.',
                        'Contesto del mese sempre leggibile a colpo d’occhio.',
                    ],
                },
                {
                    key: 'transactions',
                    title: 'Transactions',
                    description:
                        'Il registro movimenti permette di controllare il mese, filtrare velocemente e aprire il dettaglio di ogni voce senza cambiare flusso.',
                    imageAlt:
                        'Screenshot del registro movimenti di Soamco Budget',
                    highlights: [
                        'Vista operativa del mese con elenco ordinato delle registrazioni.',
                        'Filtri rapidi per trovare movimenti specifici  in modo chiaro e immediato.',
                        'Dettaglio transazione sempre disponibile quando serve approfondire.',
                    ],
                },
                {
                    key: 'budget-planning',
                    title: 'Budget planning',
                    description:
                        'La pianificazione mensile aiuta a distribuire importi, confrontare previsto e reale e mantenere il budget sotto controllo.',
                    imageAlt:
                        'Screenshot della pianificazione budget di Soamco Budget',
                    highlights: [
                        'Allocazioni chiare per categoria e gruppo di spesa.',
                        'Confronto leggibile tra obiettivo, assegnato e utilizzo reale.',
                        'Routine mensile più stabile grazie a una vista ordinata.',
                    ],
                },
                {
                    key: 'shared-accounts',
                    title: 'Shared accounts',
                    description:
                        'Gli account condivisi permettono collaborazione ordinata, inviti e visibilità coerente tra più persone sullo stesso conto.',
                    imageAlt:
                        'Screenshot della condivisione account di Soamco Budget',
                    highlights: [
                        'Inviti chiari per aggiungere nuove persone al conto condiviso.',
                        'Ruoli e permessi leggibili senza configurazioni confuse.',
                        'Stato degli accessi sempre disponibile in un unico punto.',
                    ],
                },
                {
                    key: 'recurring-entries',
                    title: 'Recurring entries',
                    description:
                        'Le ricorrenze mantengono bollette, spese periodiche ed entrate previste allineate al calendario operativo del mese.',
                    imageAlt: 'Screenshot delle ricorrenze di Soamco Budget',
                    highlights: [
                        'Programmazione chiara di spese ed entrate ricorrenti.',
                        'Occorrenze previste già pronte per il periodo attivo.',
                        'Calendario utile per vedere ritmo e distribuzione delle scadenze.',
                    ],
                },
                {
                    key: 'credit-cards',
                    title: 'Credit cards',
                    description:
                        'Le carte di credito hanno una vista dedicata per leggere limite, movimenti e riepilogo operativo con maggiore ordine.',
                    imageAlt:
                        'Screenshot della gestione carte di credito di Soamco Budget',
                    highlights: [
                        'Panoramica immediata del plafond disponibile e utilizzato.',
                        'Movimenti e riepiloghi organizzati in modo coerente.',
                        'Maggiore controllo sulle spese che passano da carta.',
                    ],
                },
            ],
        },
        importer: {
            eyebrow: 'Registrazione flessibile',
            title: 'Puoi lavorare in modo molto simile a Excel, ma senza dover riallineare tutto a mano.',
            description:
                'Se ti dimentichi qualche movimento o non hai tempo di registrare gli importi uno per uno, puoi continuare a usare Soamco Budget senza spezzare il flusso.',
            note: 'L’importatore ti aiuta a riallineare i conti e a riportare ordine quando inserire tutto manualmente non è pratico.',
            bullets: [
                'Puoi registrare a mano quando vuoi più controllo sul dettaglio.',
                'Puoi importare i movimenti quando il mese è più intenso o sei rimasto indietro.',
                'Il sistema ti aiuta a riallineare conti e saldi senza sforzo operativo inutile.',
            ],
            cards: [
                {
                    title: 'Registrazione manuale',
                    description:
                        'Controllo puntuale quando vuoi inserire tutto riga per riga.',
                    badge: 'Detail',
                },
                {
                    title: 'Importatore',
                    description:
                        'Riallineamento rapido dei movimenti quando il mese corre più veloce.',
                    badge: 'Sync',
                },
            ],
        },
        cta: {
            eyebrow: 'Chiusura',
            title: 'Vuoi vedere come queste funzionalità lavorano insieme in una gestione mensile più ordinata?',
            description:
                'Dal controllo quotidiano all’importazione dei movimenti, Soamco Budget ti aiuta a mantenere continuità senza complicare il modo in cui segui i conti.',
            note: 'Puoi iniziare gratis oppure capire meglio come il prodotto si presenta pubblicamente prima di entrare.',
            registerLabel: 'Registrati gratis',
            pricingLabel: 'Vedi prezzi',
        },
    },
    en: {
        headTitle: 'Features',
        hero: {
            eyebrow: 'Product overview',
            title: 'The main Soamco Budget features, explained in a concrete way.',
            description:
                'A clear overview of the views that help control balances, planning, transactions, and collaboration, with screenshots localized to the selected language.',
            registerLabel: 'Sign up free',
            loginLabel: 'Log in',
            highlights: [
                'A compact dashboard to see what needs attention right away.',
                'A readable transaction ledger with fast period control.',
                'Recurring entries and due dates kept orderly throughout the month.',
            ],
        },
        sections: {
            eyebrow: 'Feature blocks',
            title: 'A simple page to read and easy to extend.',
            description:
                'Each area shows what the product enables, why it matters, and how it actually looks in the interface.',
            items: [
                {
                    key: 'dashboard',
                    title: 'Dashboard',
                    description:
                        'A starting view designed to read balances, deltas, and open signals without wasting time in noisy panels.',
                    imageAlt: 'Screenshot of the Soamco Budget dashboard',
                    highlights: [
                        'Immediate summary of income, expenses, and period balance.',
                        'Clear signals for the areas that need review.',
                        'Monthly context always readable at a glance.',
                    ],
                },
                {
                    key: 'transactions',
                    title: 'Transactions',
                    description:
                        'The transaction ledger lets you review the month, filter quickly, and open each entry detail without breaking flow.',
                    imageAlt:
                        'Screenshot of the Soamco Budget transactions ledger',
                    highlights: [
                        'Operational month view with an orderly list of entries.',
                        'Quick filters to find specific entries clearly and quickly.',
                        'Transaction detail is always available when deeper review is needed.',
                    ],
                },
                {
                    key: 'budget-planning',
                    title: 'Budget planning',
                    description:
                        'Monthly planning helps distribute amounts, compare expected versus actual, and keep the budget under control.',
                    imageAlt: 'Screenshot of the Soamco Budget planning view',
                    highlights: [
                        'Clear allocations by category and spending group.',
                        'Readable comparison between target, assigned, and actual usage.',
                        'A steadier monthly routine thanks to a cleaner view.',
                    ],
                },
                {
                    key: 'shared-accounts',
                    title: 'Shared accounts',
                    description:
                        'Shared accounts support orderly collaboration, invitations, and consistent visibility for multiple people on the same account.',
                    imageAlt:
                        'Screenshot of the Soamco Budget shared accounts view',
                    highlights: [
                        'Clear invitations to add new people to a shared account.',
                        'Roles and permissions remain readable without confusing settings.',
                        'Access status is always available in one place.',
                    ],
                },
                {
                    key: 'recurring-entries',
                    title: 'Recurring entries',
                    description:
                        'Recurring entries keep bills, periodic spending, and expected income aligned with the operational calendar of the month.',
                    imageAlt:
                        'Screenshot of the Soamco Budget recurring entries view',
                    highlights: [
                        'Clear scheduling for recurring income and expenses.',
                        'Expected occurrences already lined up with the active period.',
                        'A useful calendar to see cadence and due-date distribution.',
                    ],
                },
                {
                    key: 'credit-cards',
                    title: 'Credit cards',
                    description:
                        'Credit cards have a dedicated view to read limits, activity, and operational summary with more order.',
                    imageAlt:
                        'Screenshot of the Soamco Budget credit cards view',
                    highlights: [
                        'Immediate overview of available and used card limit.',
                        'Transactions and summaries organized consistently.',
                        'More control over spending that flows through cards.',
                    ],
                },
            ],
        },
        importer: {
            eyebrow: 'Flexible logging',
            title: 'You can work in a way that feels close to Excel, without manually realigning everything afterward.',
            description:
                'If you forget a few transactions or do not have time to record every amount one by one, Soamco Budget still lets you keep the month under control.',
            note: 'The importer helps you realign accounts and restore order when entering everything manually is not realistic.',
            bullets: [
                'You can log entries by hand whenever you want more control over the detail.',
                'You can import transactions when the month gets busy or you fall behind.',
                'The system helps bring accounts and balances back in sync without unnecessary effort.',
            ],
            cards: [
                {
                    title: 'Manual entry',
                    description:
                        'Precise control when you want to insert everything line by line.',
                    badge: 'Detail',
                },
                {
                    title: 'Importer',
                    description:
                        'A faster way to realign transactions when the month moves quicker than your notes.',
                    badge: 'Sync',
                },
            ],
        },
        cta: {
            eyebrow: 'Closing',
            title: 'Want to see how these features work together in a cleaner monthly routine?',
            description:
                'From daily control to transaction imports, Soamco Budget helps you maintain continuity without making the way you manage money more complex.',
            note: 'You can start for free or understand the public positioning of the product before you sign in.',
            registerLabel: 'Sign up free',
            pricingLabel: 'View pricing',
        },
    },
};
