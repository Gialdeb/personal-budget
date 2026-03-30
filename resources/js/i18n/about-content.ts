type AboutSectionItem = {
    title: string;
    description: string;
};

type AboutContent = {
    headTitle: string;
    hero: {
        eyebrow: string;
        title: string;
        description: string;
        nameLabel: string;
        profileLabel: string;
        projectLabel: string;
        websiteLabel: string;
        linkedinLabel: string;
        githubLabel: string;
    };
    profile: {
        eyebrow: string;
        title: string;
        description: string;
        items: AboutSectionItem[];
    };
    origin: {
        eyebrow: string;
        title: string;
        description: string;
        items: AboutSectionItem[];
    };
    work: {
        eyebrow: string;
        title: string;
        description: string;
        items: string[];
    };
    links: {
        eyebrow: string;
        title: string;
        description: string;
        linkedinLabel: string;
        githubLabel: string;
    };
};

export const aboutContent: Record<'it' | 'en', AboutContent> = {
    it: {
        headTitle: 'Chi sono',
        hero: {
            eyebrow: 'About me',
            title: 'Costruisco Soamco Budget con attenzione reale a chiarezza, affidabilità e usabilità.',
            description:
                'Questa pagina racconta il lato personale e professionale del progetto: chi sono, perché è nato Soamco Budget e con quale approccio sto cercando di farlo crescere nel tempo.',
            nameLabel: 'Fondatore e sviluppatore',
            profileLabel: 'Profilo professionale',
            projectLabel: 'Perché esiste Soamco Budget',
            websiteLabel: 'Sito personale',
            linkedinLabel: 'LinkedIn',
            githubLabel: 'GitHub',
        },
        profile: {
            eyebrow: 'Profilo',
            title: 'Un approccio tecnico concreto, con cura per il prodotto.',
            description:
                'Il mio profilo unisce attenzione tecnica, sensibilità per l’esperienza d’uso e interesse reale per strumenti software chiari, stabili e utili nella vita quotidiana.',
            items: [
                {
                    title: 'Sviluppo con rigore',
                    description:
                        'Mi interessa costruire software leggibile, mantenibile e coerente, senza scorciatoie che peggiorano l’esperienza nel tempo.',
                },
                {
                    title: 'Prodotto prima del rumore',
                    description:
                        'Preferisco interfacce chiare, gerarchie forti e funzionalità con un motivo preciso, invece di aggiungere complessità inutile.',
                },
                {
                    title: 'Cura dei dettagli',
                    description:
                        'Dal copy alle micro-scelte di layout, considero ogni parte come un elemento che contribuisce alla fiducia nel prodotto.',
                },
            ],
        },
        origin: {
            eyebrow: 'Perché è nato',
            title: 'Soamco Budget nasce da esigenze personali reali.',
            description:
                'L’idea nasce dal bisogno di avere uno strumento più ordinato per seguire movimenti, budget, ricorrenze e visibilità complessiva sul denaro, senza rumore da foglio improvvisato o app generiche poco chiare.',
            items: [
                {
                    title: 'Un bisogno concreto',
                    description:
                        'Prima di essere una pagina pubblica, Soamco Budget è stato un progetto pensato per risolvere problemi veri di organizzazione personale.',
                },
                {
                    title: 'Esperienza e attenzione',
                    description:
                        'Sto costruendo il prodotto con l’idea che possa diventare utile anche per altre persone che cercano chiarezza, ordine e affidabilità.',
                },
                {
                    title: 'Una base che può crescere',
                    description:
                        'L’obiettivo non è inseguire il marketing, ma far evolvere uno strumento solido, credibile e ben progettato nel tempo.',
                },
            ],
        },
        work: {
            eyebrow: 'Come ci lavoro',
            title: 'Il progetto cresce attraverso miglioramento continuo e attenzione all’usabilità.',
            description:
                'Ogni iterazione cerca di rendere il prodotto più chiaro, più stabile e più utile, senza perdere semplicità.',
            items: [
                'Miglioramento continuo delle funzionalità reali.',
                'Attenzione costante a leggibilità, ritmo visivo e facilità d’uso.',
                'Cura dei dettagli per rendere il prodotto più affidabile e coerente.',
                'Evoluzione progressiva del software, senza strappi inutili.',
            ],
        },
        links: {
            eyebrow: 'Link professionali',
            title: 'Puoi trovarmi anche fuori dal progetto.',
            description:
                'I collegamenti sono già predisposti in modo pulito e possono essere aggiornati facilmente con i riferimenti reali.',
            linkedinLabel: 'Profilo LinkedIn',
            githubLabel: 'GitHub personale',
        },
    },
    en: {
        headTitle: 'About me',
        hero: {
            eyebrow: 'About me',
            title: 'I am building Soamco Budget with a strong focus on clarity, reliability, and usability.',
            description:
                'This page explains the personal and professional side of the project: who I am, why Soamco Budget exists, and how I am trying to grow it over time.',
            nameLabel: 'Founder and developer',
            profileLabel: 'Professional profile',
            projectLabel: 'Why Soamco Budget exists',
            websiteLabel: 'Personal website',
            linkedinLabel: 'LinkedIn',
            githubLabel: 'GitHub',
        },
        profile: {
            eyebrow: 'Profile',
            title: 'A practical technical approach shaped by product care.',
            description:
                'My profile combines technical rigor, sensitivity to user experience, and a real interest in building software that is clear, stable, and genuinely useful.',
            items: [
                {
                    title: 'Built with rigor',
                    description:
                        'I care about software that stays readable, maintainable, and coherent instead of accumulating shortcuts over time.',
                },
                {
                    title: 'Product over noise',
                    description:
                        'I prefer clear interfaces, stronger hierarchy, and features with a purpose instead of adding unnecessary complexity.',
                },
                {
                    title: 'Attention to detail',
                    description:
                        'From copy to layout choices, I treat each part as something that contributes to trust in the product.',
                },
            ],
        },
        origin: {
            eyebrow: 'Why it started',
            title: 'Soamco Budget comes from real personal needs.',
            description:
                'The project started from the need for a cleaner way to track transactions, budgets, recurring entries, and overall financial visibility, without relying on improvised spreadsheets or generic apps.',
            items: [
                {
                    title: 'A concrete need first',
                    description:
                        'Before becoming a public product page, Soamco Budget was a project built to solve real personal organization problems.',
                },
                {
                    title: 'Experience and care',
                    description:
                        'I am building it with the idea that it can also become useful for other people who want clarity, order, and reliability.',
                },
                {
                    title: 'A foundation that can grow',
                    description:
                        'The goal is not to chase marketing language, but to evolve a solid, credible, and well-designed tool over time.',
                },
            ],
        },
        work: {
            eyebrow: 'How I work on it',
            title: 'The project grows through continuous improvement and attention to usability.',
            description:
                'Each iteration aims to make the product clearer, more stable, and more useful without losing simplicity.',
            items: [
                'Continuous improvement of real product capabilities.',
                'Ongoing attention to readability, visual rhythm, and ease of use.',
                'Care for detail to make the experience more reliable and coherent.',
                'Progressive software evolution without unnecessary disruption.',
            ],
        },
        links: {
            eyebrow: 'Professional links',
            title: 'You can also find me outside the project.',
            description:
                'The links are already structured cleanly and can be updated easily with the real profiles.',
            linkedinLabel: 'LinkedIn profile',
            githubLabel: 'Personal GitHub',
        },
    },
};
