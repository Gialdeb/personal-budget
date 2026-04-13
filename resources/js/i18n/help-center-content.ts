export type HelpCenterContent = {
    index: {
        hero: {
            eyebrow: string;
            title: string;
            description: string;
            changelogLabel: string;
            sectionAnchorLabel: string;
        };
        sections: {
            eyebrow: string;
            title: string;
            description: string;
        };
        featured: {
            eyebrow: string;
            title: string;
            description: string;
        };
    };
    section: {
        eyebrow: string;
        articlesTitle: string;
        articlesDescription: string;
        emptyTitle: string;
        emptyDescription: string;
    };
    article: {
        eyebrow: string;
        relatedTitle: string;
        relatedDescription: string;
    };
    common: {
        rootLabel: string;
        browseLabel: string;
        backToHelpCenter: string;
        availableIn: string;
        sectionOpenLabel: string;
        articleReadLabel: string;
        articleCountLabel: string;
    };
    support: {
        eyebrow: string;
        title: string;
        description: string;
        guestPrimary: string;
        guestSecondary: string;
        authPrimary: string;
        authSecondary: string;
    };
};

export const helpCenterContent: Record<'it' | 'en', HelpCenterContent> = {
    it: {
        index: {
            hero: {
                eyebrow: 'Help Center pubblico',
                title: 'Trova rapidamente la guida giusta per capire come funziona Soamco Budget.',
                description:
                    'Qui trovi sezioni e articoli pubblici per orientarti nel prodotto, chiarire i flussi principali e capire dove cercare prima di contattare il supporto.',
                changelogLabel: 'Vedi aggiornamenti di prodotto nel changelog',
                sectionAnchorLabel: 'Esplora le sezioni della guida',
            },
            sections: {
                eyebrow: 'Sezioni guida',
                title: 'Scegli l’area che ti serve e vai subito agli articoli utili.',
                description:
                    'Le sezioni sono organizzate per aiutarti a orientarti più in fretta: basi del prodotto, flussi di lavoro e contesto di accesso/supporto.',
            },
            featured: {
                eyebrow: 'Guide consigliate',
                title: 'Se vuoi partire subito, inizia da questi articoli.',
                description:
                    'Una selezione rapida di contenuti già pubblicati per capire struttura, flussi principali e uso corretto della guida.',
            },
        },
        section: {
            eyebrow: 'Sezione Help Center',
            articlesTitle: 'Articoli della sezione',
            articlesDescription:
                'Contenuti pubblici ordinati per offrire una base chiara prima di passare a supporto autenticato.',
            emptyTitle: 'Nessun articolo pubblicato',
            emptyDescription:
                'Questa sezione è già pronta, ma i contenuti pubblici arriveranno nei prossimi aggiornamenti.',
        },
        article: {
            eyebrow: 'Articolo Help Center',
            relatedTitle: 'Articoli correlati',
            relatedDescription:
                'Se vuoi approfondire lo stesso contesto, qui trovi altri passaggi utili della stessa sezione.',
        },
        common: {
            rootLabel: 'Help Center',
            browseLabel: 'Consulta la guida',
            backToHelpCenter: 'Torna all’Help Center',
            availableIn: 'Disponibile in',
            sectionOpenLabel: 'Apri sezione',
            articleReadLabel: 'Leggi articolo',
            articleCountLabel: 'articoli',
        },
        support: {
            eyebrow: 'Serve ancora aiuto?',
            title: 'Se la guida pubblica non basta, il supporto resta dentro l’account.',
            description:
                'Prima orientati con la knowledge base. Se poi ti serve assistenza più diretta, accedi e usa il canale dedicato dentro il prodotto.',
            guestPrimary: 'Accedi per supporto',
            guestSecondary: 'Registrati gratis',
            authPrimary: 'Apri area supporto',
            authSecondary: 'Vai alla dashboard',
        },
    },
    en: {
        index: {
            hero: {
                eyebrow: 'Public Help Center',
                title: 'Find the right guide quickly and understand how Soamco Budget works.',
                description:
                    'This page collects public sections and articles to help you understand the product, clarify core workflows, and know where to look before contacting support.',
                changelogLabel: 'See product updates in the changelog',
                sectionAnchorLabel: 'Browse Help Center sections',
            },
            sections: {
                eyebrow: 'Guide sections',
                title: 'Choose the area you need and jump straight to useful articles.',
                description:
                    'The sections are grouped to make orientation easier: product basics, working flows, and access or support context.',
            },
            featured: {
                eyebrow: 'Suggested guides',
                title: 'If you want a quick start, begin with these articles.',
                description:
                    'A short editorial selection of already published articles to understand structure, workflows, and the best use of the guide.',
            },
        },
        section: {
            eyebrow: 'Help Center section',
            articlesTitle: 'Section articles',
            articlesDescription:
                'Public content organized to provide a clear starting point before authenticated support becomes necessary.',
            emptyTitle: 'No published articles yet',
            emptyDescription:
                'This section structure is ready, but its public content will arrive in upcoming updates.',
        },
        article: {
            eyebrow: 'Help Center article',
            relatedTitle: 'Related articles',
            relatedDescription:
                'If you want to stay in the same context, these are the next useful reads from the same section.',
        },
        common: {
            rootLabel: 'Help Center',
            browseLabel: 'Browse the guide',
            backToHelpCenter: 'Back to Help Center',
            availableIn: 'Available in',
            sectionOpenLabel: 'Open section',
            articleReadLabel: 'Read article',
            articleCountLabel: 'articles',
        },
        support: {
            eyebrow: 'Need more help?',
            title: 'If the public guide is not enough, support stays inside the account area.',
            description:
                'Start with the knowledge base. If you still need more direct help, sign in and use the dedicated support space inside the product.',
            guestPrimary: 'Log in for support',
            guestSecondary: 'Sign up free',
            authPrimary: 'Open support area',
            authSecondary: 'Go to dashboard',
        },
    },
};
