export const changelogContent = {
    it: {
        headTitle: 'Changelog',
        hero: {
            eyebrow: 'Aggiornamenti prodotto',
            title: 'Novità di Soamco Budget',
            description:
                'Tutte le release pubblicate in ordine di versione, con un riepilogo chiaro e il dettaglio completo di ciò che cambia nel prodotto.',
            primaryLabel: 'Registrati gratis',
            secondaryLabel: 'Scopri le funzionalità',
        },
        list: {
            title: 'Release pubblicate',
            description:
                'Ogni release arriva dal backend admin, con contenuti localizzati e ordine semver coerente.',
            ctaLabel: 'Apri release',
            emptyTitle: 'Nessuna release pubblicata',
            emptyDescription:
                'Il changelog sarà disponibile qui appena verranno pubblicati i primi aggiornamenti del prodotto.',
        },
        detail: {
            backLabel: 'Torna al changelog',
            relatedLabel: 'Altre release',
            notFoundTitle: 'Release non disponibile',
            notFoundDescription:
                'La release richiesta non esiste, non è pubblicata oppure non è disponibile nella lingua corrente.',
            notFoundAction: 'Torna all’indice',
        },
        badges: {
            beta: 'Beta',
            stable: 'Stable',
            pinned: 'In evidenza',
        },
    },
    en: {
        headTitle: 'Changelog',
        hero: {
            eyebrow: 'Product updates',
            title: 'What is new in Soamco Budget',
            description:
                'Published releases ordered by version, with a clear summary and a complete breakdown of what changed in the product.',
            primaryLabel: 'Create your free account',
            secondaryLabel: 'Explore features',
        },
        list: {
            title: 'Published releases',
            description:
                'Every release comes from the admin backend, with localized content and consistent semver ordering.',
            ctaLabel: 'Open release',
            emptyTitle: 'No published releases yet',
            emptyDescription:
                'The changelog will appear here as soon as the first product updates are published.',
        },
        detail: {
            backLabel: 'Back to changelog',
            relatedLabel: 'Other releases',
            notFoundTitle: 'Release not available',
            notFoundDescription:
                'The requested release does not exist, is not published, or is not available in the current language.',
            notFoundAction: 'Back to index',
        },
        badges: {
            beta: 'Beta',
            stable: 'Stable',
            pinned: 'Pinned',
        },
    },
} as const;
