type PricingFaqItem = {
    question: string;
    answer: string;
};

type PricingContent = {
    headTitle: string;
    hero: {
        eyebrow: string;
        title: string;
        description: string;
        primaryLabel: string;
        secondaryLabel: string;
        highlights: string[];
    };
    free: {
        eyebrow: string;
        title: string;
        description: string;
        badge: string;
        points: string[];
        note: string;
    };
    why: {
        eyebrow: string;
        title: string;
        description: string;
        items: Array<{
            title: string;
            description: string;
        }>;
    };
    support: {
        eyebrow: string;
        title: string;
        description: string;
        points: string[];
        primaryLabel: string;
        secondaryLabel: string;
        footnote: string;
    };
    faq: {
        eyebrow: string;
        title: string;
        description: string;
        items: PricingFaqItem[];
    };
    cta: {
        eyebrow: string;
        title: string;
        description: string;
        primaryLabel: string;
        secondaryLabel: string;
    };
};

export const pricingContent: Record<'it' | 'en', PricingContent> = {
    it: {
        headTitle: 'Prezzi',
        hero: {
            eyebrow: 'Prezzi trasparenti',
            title: 'Soamco Budget è gratuito. Se ti è utile, puoi supportarlo liberamente.',
            description:
                'Nessun piano inventato, nessuna subscription forzata. Il prodotto nasce da un’esigenza reale di gestione personale del denaro e continua a evolvere nel tempo.',
            primaryLabel: 'Registrati gratis',
            secondaryLabel: 'Scopri le funzionalità',
            highlights: [
                'Uso personale gratuito',
                'Aggiornamenti continui nel tempo',
                'Supporto facoltativo tramite donazione',
            ],
        },
        free: {
            eyebrow: 'Gratis',
            title: 'Un solo piano: completo, chiaro e senza sorprese.',
            description:
                'Puoi usare Soamco Budget senza costi per organizzare conti, movimenti, budget e ricorrenze con un’interfaccia semplice e leggibile.',
            badge: 'Sempre gratuito',
            points: [
                'Dashboard, movimenti e pianificazione in un unico spazio.',
                'Conti condivisi, ricorrenze e carte di credito già inclusi.',
                'Nessun upsell aggressivo e nessun blocco artificiale delle funzioni principali.',
            ],
            note: 'L’obiettivo è mantenere il prodotto utile e accessibile, non costruire una pagina prezzi artificiale.',
        },
        why: {
            eyebrow: 'Perché gratuito',
            title: 'Un progetto nato da esigenze vere, non da un listino marketing.',
            description:
                'Soamco Budget nasce per risolvere problemi concreti di organizzazione finanziaria personale. Tenerlo gratuito aiuta a mantenerlo semplice, onesto e focalizzato su ciò che serve davvero.',
            items: [
                {
                    title: 'Nato da uso personale',
                    description:
                        'Le funzionalità seguono bisogni quotidiani reali: chiarezza sui numeri, ordine nei movimenti e meno rumore visivo.',
                },
                {
                    title: 'Migliorato con continuità',
                    description:
                        'Il prodotto viene mantenuto e rifinito nel tempo, con attenzione alla qualità e alla leggibilità dell’esperienza.',
                },
                {
                    title: 'Accessibile fin da subito',
                    description:
                        'Lasciare l’accesso gratuito abbassa la frizione e permette di capire subito se il prodotto è utile per il proprio modo di gestire il denaro.',
                },
            ],
        },
        support: {
            eyebrow: 'Supporta il progetto',
            title: 'Le donazioni sono facoltative, ma aiutano davvero.',
            description:
                'Se Soamco Budget ti è utile e vuoi supportarne la crescita, una donazione libera può contribuire a mantenere il progetto sano e sostenibile.',
            points: [
                'Server e infrastruttura',
                'Manutenzione tecnica e correzione bug',
                'Nuove funzionalità e miglioramenti continui',
            ],
            primaryLabel: 'Dona se ti piace',
            secondaryLabel: 'Scrivimi per supporto',
            footnote:
                'Supporto e contributi verranno gestiti dall’interno dell’account, in un flusso più ordinato e verificabile.',
        },
        faq: {
            eyebrow: 'FAQ',
            title: 'Domande rapide',
            description:
                'Una spiegazione breve e diretta, senza pricing ambiguo.',
            items: [
                {
                    question: 'Soamco Budget è davvero gratuito?',
                    answer: 'Sì. Oggi il prodotto è gratuito e non richiede un abbonamento per l’uso principale.',
                },
                {
                    question: 'Le donazioni sono obbligatorie?',
                    answer: 'No. Sono completamente facoltative e servono solo a supportare manutenzione e crescita del progetto.',
                },
                {
                    question: 'A cosa servirebbe il supporto economico?',
                    answer: 'A sostenere costi di hosting, manutenzione, miglioramenti continui e sviluppo di nuove parti del prodotto.',
                },
            ],
        },
        cta: {
            eyebrow: 'Inizia ora',
            title: 'Prova il prodotto senza costi e valuta tu se supportarlo nel tempo.',
            description:
                'Prima viene l’utilità reale. Il supporto arriva solo se il prodotto ti aiuta davvero.',
            primaryLabel: 'Registrati gratis',
            secondaryLabel: 'Vai alle funzionalità',
        },
    },
    en: {
        headTitle: 'Pricing',
        hero: {
            eyebrow: 'Transparent pricing',
            title: 'Soamco Budget is free. If it helps you, you can support it with an optional donation.',
            description:
                'No fake tiers and no forced subscription. The product comes from a real personal need and keeps improving over time.',
            primaryLabel: 'Create a free account',
            secondaryLabel: 'Explore features',
            highlights: [
                'Free for personal use',
                'Ongoing product improvements',
                'Optional support through donations',
            ],
        },
        free: {
            eyebrow: 'Free',
            title: 'One simple offer: complete, clear, and without surprises.',
            description:
                'You can use Soamco Budget at no cost to organize accounts, transactions, budgets, and recurring entries in a clean interface.',
            badge: 'Always free',
            points: [
                'Dashboard, transactions, and planning in one place.',
                'Shared accounts, recurring entries, and credit cards already included.',
                'No aggressive upsells and no artificial limits on the main product value.',
            ],
            note: 'The goal is to keep the product useful and accessible, not to build an artificial pricing page.',
        },
        why: {
            eyebrow: 'Why it is free',
            title: 'A project built from real needs, not from a marketing price sheet.',
            description:
                'Soamco Budget exists to solve practical personal finance problems. Keeping it free helps preserve clarity, honesty, and focus.',
            items: [
                {
                    title: 'Built from personal use',
                    description:
                        'The product follows real day-to-day needs: clear numbers, structured transactions, and less visual noise.',
                },
                {
                    title: 'Maintained over time',
                    description:
                        'The product is actively maintained and refined with attention to quality and readability.',
                },
                {
                    title: 'Easy to start using',
                    description:
                        'Free access lowers friction and lets people understand quickly whether the product fits their way of managing money.',
                },
            ],
        },
        support: {
            eyebrow: 'Support the project',
            title: 'Donations are optional, but they make a real difference.',
            description:
                'If Soamco Budget is useful to you and you want to support its growth, an optional donation can help keep the project healthy and sustainable.',
            points: [
                'Servers and infrastructure',
                'Technical maintenance and bug fixing',
                'New features and continuous product improvements',
            ],
            primaryLabel: 'Donate if you like it',
            secondaryLabel: 'Contact me for support',
            footnote:
                'Support and contributions will be handled from inside the account, in a more orderly and traceable flow.',
        },
        faq: {
            eyebrow: 'FAQ',
            title: 'Quick answers',
            description:
                'A short and direct explanation, without ambiguous pricing.',
            items: [
                {
                    question: 'Is Soamco Budget really free?',
                    answer: 'Yes. The product is currently free and does not require a subscription for its main use.',
                },
                {
                    question: 'Are donations required?',
                    answer: 'No. Donations are completely optional and only exist to support maintenance and future growth.',
                },
                {
                    question: 'What would financial support help cover?',
                    answer: 'Hosting costs, maintenance, ongoing improvements, and new product work over time.',
                },
            ],
        },
        cta: {
            eyebrow: 'Get started',
            title: 'Try the product for free and decide later whether you want to support it.',
            description:
                'Real usefulness comes first. Support only matters if the product genuinely helps you.',
            primaryLabel: 'Create a free account',
            secondaryLabel: 'See features',
        },
    },
};
