type CustomersContent = {
    headTitle: string;
    hero: {
        eyebrow: string;
        title: string;
        description: string;
        registerLabel: string;
        featuresLabel: string;
        highlights: string[];
    };
    audience: {
        eyebrow: string;
        title: string;
        description: string;
        items: Array<{
            title: string;
            description: string;
        }>;
    };
    scenarios: {
        eyebrow: string;
        title: string;
        description: string;
        items: Array<{
            title: string;
            description: string;
            points: string[];
        }>;
    };
    useful: {
        eyebrow: string;
        title: string;
        description: string;
        items: string[];
    };
    beta: {
        eyebrow: string;
        title: string;
        description: string;
        points: string[];
    };
    cta: {
        eyebrow: string;
        title: string;
        description: string;
        registerLabel: string;
        pricingLabel: string;
        aboutLabel: string;
    };
};

export const customersContent: Record<'it' | 'en', CustomersContent> = {
    it: {
        headTitle: 'Clienti',
        hero: {
            eyebrow: 'Per chi è utile',
            title: 'Soamco Budget è pensato per persone che vogliono più chiarezza, non più complessità inutile.',
            description:
                'Non ci sono recensioni inventate o testimonial finti. Questa pagina mostra in modo onesto per chi è stato costruito il prodotto, quali problemi risolve bene oggi e perché vale già la pena provarlo.',
            registerLabel: 'Registrati gratis',
            featuresLabel: 'Scopri le funzionalità',
            highlights: [
                'Per chi vuole ordine nelle spese quotidiane',
                'Per chi gestisce più conti, carte e ricorrenze',
                'Per chi cerca uno strumento personale in crescita',
            ],
        },
        audience: {
            eyebrow: 'Per chi è pensato',
            title: 'Profili d’uso chiari, senza forzare un target artificiale.',
            description:
                'Soamco Budget è più adatto a persone che cercano controllo concreto, semplicità leggibile e un prodotto che evolve con attenzione.',
            items: [
                {
                    title: 'Chi vuole più ordine nelle spese',
                    description:
                        'Per chi vuole vedere movimenti, categorie e saldi in uno spazio più chiaro di un foglio improvvisato.',
                },
                {
                    title: 'Chi gestisce più conti e carte',
                    description:
                        'Per chi ha bisogno di una vista più ordinata su conti, carte di credito e scadenze senza caos operativo.',
                },
                {
                    title: 'Chi condivide un conto con un’altra persona',
                    description:
                        'Per chi vuole gestire un conto condiviso mantenendo visibilità, struttura e meno attrito nella routine.',
                },
                {
                    title: 'Chi vuole budget semplici ma leggibili',
                    description:
                        'Per chi non cerca una piattaforma complessa, ma una pianificazione chiara e facile da seguire mese dopo mese.',
                },
                {
                    title: 'Chi apprezza software in evoluzione',
                    description:
                        'Per chi preferisce uno strumento personale e in crescita, costruito con cura invece di una soluzione impersonale.',
                },
            ],
        },
        scenarios: {
            eyebrow: 'Scenari d’uso',
            title: 'Esempi realistici di come il prodotto può essere utile.',
            description:
                'Al posto di recensioni finte, qui ci sono scenari concreti in cui Soamco Budget lavora bene già oggi.',
            items: [
                {
                    title: 'Gestione quotidiana di spese e categorie',
                    description:
                        'Un flusso più chiaro per registrare movimenti, categorizzarli con ordine e mantenere una lettura utile del mese.',
                    points: [
                        'Movimenti ordinati e leggibili',
                        'Categorie più facili da controllare',
                        'Meno dispersione tra strumenti diversi',
                    ],
                },
                {
                    title: 'Pianificazione del budget mensile',
                    description:
                        'Per dare struttura al mese, capire margini e tenere sotto controllo gli scostamenti prima che diventino difficili da leggere.',
                    points: [
                        'Vista più lineare del periodo',
                        'Più chiarezza su entrate e uscite',
                        'Controlli più semplici da fare con continuità',
                    ],
                },
                {
                    title: 'Condivisione di un conto',
                    description:
                        'Per coppie o persone che condividono spese e vogliono evitare confusione, duplicazioni e poca trasparenza.',
                    points: [
                        'Più visibilità sulle attività condivise',
                        'Meno caos nel seguire le spese comuni',
                        'Un flusso più ordinato per collaborare',
                    ],
                },
                {
                    title: 'Gestione delle ricorrenze',
                    description:
                        'Per tenere sotto controllo addebiti ripetitivi, entrate periodiche e routine mensili senza dimenticanze.',
                    points: [
                        'Ricorrenze già visibili nel periodo',
                        'Migliore previsione del mese',
                        'Meno sorprese operative',
                    ],
                },
                {
                    title: 'Controllo degli addebiti della carta',
                    description:
                        'Per seguire le spese su carta di credito con più ordine e leggere meglio impatto, scadenze e abitudini.',
                    points: [
                        'Più attenzione agli addebiti ricorrenti',
                        'Migliore lettura delle spese su carta',
                        'Più controllo prima della chiusura del mese',
                    ],
                },
            ],
        },
        useful: {
            eyebrow: 'Cosa rende utile il prodotto',
            title: 'Non cosa “dicono i clienti”, ma cosa ho cercato di costruire meglio.',
            description:
                'Quando non ci sono ancora testimonianze pubbliche reali, è più onesto spiegare cosa rende l’esperienza utile.',
            items: [
                'Una gerarchia visiva più chiara per leggere numeri, stati e controlli.',
                'Un flusso che unisce movimenti, budget e ricorrenze senza cambiare continuamente contesto.',
                'Un linguaggio visivo più ordinato e meno simile a una dashboard confusa.',
                'Una crescita del prodotto guidata da casi d’uso reali, non da feature decorative.',
            ],
        },
        beta: {
            eyebrow: 'Beta in crescita',
            title: 'Il prodotto è in beta, ma è già utile e continua a migliorare.',
            description:
                'La beta non è un limite comunicato per abitudine. È un modo chiaro per dire che il prodotto evolve ancora, ma lo fa con attenzione.',
            points: [
                'Aggiornamenti progressivi e miglioramenti continui.',
                'Feedback reali utili a rendere il prodotto più forte.',
                'Una base già concreta su cui costruire fiducia nel tempo.',
            ],
        },
        cta: {
            eyebrow: 'Provalo ora',
            title: 'Se questo modo di gestire il denaro ti somiglia, puoi iniziare subito.',
            description:
                'Soamco Budget è gratuito, già utile e costruito con l’idea di diventare sempre più chiaro e affidabile.',
            registerLabel: 'Registrati gratis',
            pricingLabel: 'Vedi prezzi',
            aboutLabel: 'Chi sono',
        },
    },
    en: {
        headTitle: 'Customers',
        hero: {
            eyebrow: 'Who it is useful for',
            title: 'Soamco Budget is built for people who want more clarity, not more unnecessary complexity.',
            description:
                'There are no invented testimonials or fake reviews here. This page explains honestly who the product is for, which problems it already solves well, and why it is worth trying today.',
            registerLabel: 'Sign up free',
            featuresLabel: 'Explore features',
            highlights: [
                'For people who want more order in everyday spending',
                'For people managing multiple accounts, cards, and recurring items',
                'For people who value a personal product that keeps improving',
            ],
        },
        audience: {
            eyebrow: 'Who it is for',
            title: 'Clear use profiles, without forcing an artificial audience.',
            description:
                'Soamco Budget fits people looking for practical control, readable simplicity, and a product that evolves with care.',
            items: [
                {
                    title: 'People who want more order in spending',
                    description:
                        'For anyone who wants to see transactions, categories, and balances in a clearer place than an improvised spreadsheet.',
                },
                {
                    title: 'People managing multiple accounts and cards',
                    description:
                        'For anyone who needs a more structured view of accounts, credit cards, and deadlines without operational clutter.',
                },
                {
                    title: 'People sharing an account with someone else',
                    description:
                        'For couples or shared households that want more visibility and less friction in shared finances.',
                },
                {
                    title: 'People who want simple but clear budgeting',
                    description:
                        'For anyone who does not want a bloated platform, but still wants a clean monthly planning workflow.',
                },
                {
                    title: 'People who appreciate evolving software',
                    description:
                        'For anyone who prefers a focused product that keeps improving with real care over time.',
                },
            ],
        },
        scenarios: {
            eyebrow: 'Use scenarios',
            title: 'Realistic situations where the product can already help.',
            description:
                'Instead of fake testimonials, these are concrete scenarios where Soamco Budget already provides value.',
            items: [
                {
                    title: 'Daily expense and category management',
                    description:
                        'A clearer way to record transactions, organize them, and keep a readable view of the current month.',
                    points: [
                        'Readable transaction flow',
                        'Categories easier to review',
                        'Less fragmentation across tools',
                    ],
                },
                {
                    title: 'Monthly budget planning',
                    description:
                        'A cleaner structure for the month, with better visibility on margins and deviations before they become harder to read.',
                    points: [
                        'More linear view of the period',
                        'Better visibility on income and expenses',
                        'Simpler recurring monthly checks',
                    ],
                },
                {
                    title: 'Shared account management',
                    description:
                        'For people sharing expenses and wanting less confusion, fewer duplicates, and more transparency.',
                    points: [
                        'Better visibility on shared activity',
                        'Less chaos in common expenses',
                        'A more orderly collaboration flow',
                    ],
                },
                {
                    title: 'Recurring entries tracking',
                    description:
                        'A practical way to keep repeating charges, recurring income, and monthly routines under control.',
                    points: [
                        'Recurring items visible in the period',
                        'Better month forecasting',
                        'Fewer operational surprises',
                    ],
                },
                {
                    title: 'Credit card charge control',
                    description:
                        'A more structured way to follow credit card activity and understand impact, deadlines, and spending habits.',
                    points: [
                        'More attention on recurring charges',
                        'Cleaner reading of card usage',
                        'More control before closing the month',
                    ],
                },
            ],
        },
        useful: {
            eyebrow: 'What makes it useful',
            title: 'Not what “customers say”, but what I tried to build better.',
            description:
                'When there are no public verified testimonials yet, it is more honest to explain what makes the product useful.',
            items: [
                'A clearer visual hierarchy for reading numbers, states, and checks.',
                'A workflow that keeps transactions, budgets, and recurring items connected.',
                'A calmer visual language that feels less like a cluttered dashboard.',
                'Product growth guided by real usage needs rather than decorative features.',
            ],
        },
        beta: {
            eyebrow: 'Beta and growth',
            title: 'The product is in beta, but already useful and steadily improving.',
            description:
                'The beta label is not filler. It is a clear way to say the product is still evolving, but it is doing so with care.',
            points: [
                'Progressive updates and continuous improvement.',
                'Real feedback that helps make the product stronger.',
                'A concrete foundation that can grow with trust over time.',
            ],
        },
        cta: {
            eyebrow: 'Try it now',
            title: 'If this way of managing money feels right for you, you can start today.',
            description:
                'Soamco Budget is free, already useful, and being shaped to become even clearer and more reliable over time.',
            registerLabel: 'Sign up free',
            pricingLabel: 'View pricing',
            aboutLabel: 'About me',
        },
    },
};
