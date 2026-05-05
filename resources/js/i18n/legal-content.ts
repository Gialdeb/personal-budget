export type LegalLocale = 'it' | 'en';

type LegalSection = {
    title: string;
    body: string;
};

type LegalPageContent = {
    pageTitle: string;
    eyebrow: string;
    title: string;
    description: string;
    intro: string;
    sections: LegalSection[];
    sourceNote: string;
};

type LegalContentLocale = {
    terms: LegalPageContent;
    privacy: LegalPageContent;
};

export const legalContent: Record<LegalLocale, LegalContentLocale> = {
    it: {
        terms: {
            pageTitle: 'Termini del servizio',
            eyebrow: 'Policy legale',
            title: 'Termini del servizio di Soamco Budget',
            description:
                'Questi termini disciplinano l’uso di Soamco Budget come software per la gestione di budget, conti, movimenti e pianificazione personale.',
            intro: 'Usando Soamco Budget accetti questi termini, la nostra informativa sulla privacy e tutte le regole operative pubblicate all’interno del servizio.',
            sections: [
                {
                    title: '1. Panoramica del servizio',
                    body: 'Soamco Budget offre strumenti per organizzare conti, transazioni, categorie, ricorrenze, budget e funzioni collegate. Alcune funzioni possono essere gratuite, altre in beta o soggette a piani futuri.',
                },
                {
                    title: '2. Idoneità e uso corretto',
                    body: 'Puoi usare il servizio solo se hai la capacità legale per accettare questi termini e se il tuo utilizzo rispetta le leggi applicabili. Non puoi usare la piattaforma per frodi, accessi abusivi o attività illecite.',
                },
                {
                    title: '3. Account e credenziali',
                    body: 'Sei responsabile delle informazioni del tuo account, della riservatezza della password e di tutte le attività effettuate tramite il tuo accesso. Devi comunicarci rapidamente eventuali accessi non autorizzati.',
                },
                {
                    title: '4. Piani, prezzi e pagamenti',
                    body: 'Se in futuro saranno disponibili funzionalità a pagamento, i prezzi, i cicli di fatturazione, le imposte applicabili, i rinnovi e le condizioni di cancellazione saranno indicati chiaramente prima dell’acquisto.',
                },
                {
                    title: '5. Dati inseriti dall’utente',
                    body: 'I dati che inserisci, importi o organizzi nel servizio restano di tua responsabilità. Devi assicurarti che siano leciti, accurati e che tu abbia il diritto di trattarli e condividerli.',
                },
                {
                    title: '6. Condotte vietate',
                    body: 'Non puoi aggirare misure di sicurezza, tentare reverse engineering non autorizzato, sovraccaricare l’infrastruttura, usare bot impropri, violare diritti di terzi o usare il servizio per distribuire contenuti dannosi.',
                },
                {
                    title: '7. Funzionalità beta',
                    body: 'Alcune parti di Soamco Budget possono essere contrassegnate come beta. Queste funzioni possono cambiare, interrompersi o essere rimosse senza preavviso e possono non essere prive di errori.',
                },
                {
                    title: '8. Sospensione e cessazione',
                    body: 'Possiamo sospendere o chiudere account che violano questi termini, creano rischi tecnici o legali o compromettono la sicurezza della piattaforma. Puoi smettere di usare il servizio in qualsiasi momento.',
                },
                {
                    title: '9. Esclusioni di garanzia e limiti',
                    body: 'Soamco Budget viene fornito “così com’è” e “come disponibile”. Non garantiamo assenza di errori, continuità assoluta del servizio o idoneità per esigenze specifiche. Nei limiti consentiti dalla legge, la responsabilità complessiva è limitata ai corrispettivi eventualmente pagati nei dodici mesi precedenti il fatto contestato.',
                },
                {
                    title: '10. Modifiche ai termini e contatti',
                    body: 'Possiamo aggiornare questi termini per adeguamenti normativi, tecnici o di prodotto. In caso di modifiche sostanziali pubblicheremo la nuova versione sul sito con data aggiornata. L’uso continuato del servizio dopo l’aggiornamento equivale ad accettazione.',
                },
            ],
            sourceNote:
                'Questa pagina è ispirata nella struttura ai termini di servizio di un moderno software SaaS, ma il contenuto è stato riscritto e adattato per Soamco Budget.',
        },
        privacy: {
            pageTitle: 'Informativa sulla privacy',
            eyebrow: 'Policy privacy',
            title: 'Informativa sulla privacy di Soamco Budget',
            description:
                'Questa informativa spiega quali dati raccogliamo, perché li usiamo e quali scelte hai in relazione al tuo account e ai dati trattati nel servizio.',
            intro: 'Soamco Budget tratta i dati personali necessari per offrire la piattaforma, proteggerla, migliorarla e rispettare obblighi di legge.',
            sections: [
                {
                    title: '1. Dati che raccogliamo',
                    body: 'Possiamo raccogliere dati di registrazione, dati di profilo, informazioni sui dispositivi, log tecnici, preferenze di utilizzo e i dati finanziari che scegli di inserire o importare nel servizio.',
                },
                {
                    title: '2. Come usiamo i dati',
                    body: 'Usiamo i dati per creare e gestire il tuo account, mostrare budget e movimenti, sincronizzare impostazioni, fornire assistenza, prevenire abusi, migliorare il prodotto e inviare comunicazioni di servizio.',
                },
                {
                    title: '3. Cookie e tecnologie simili',
                    body: 'Possiamo usare cookie o strumenti simili per sessione, sicurezza, preferenze linguistiche, analisi aggregate e continuità dell’esperienza. Tawk.to può utilizzare cookie o tecnologie simili per consentire il funzionamento della chat, mantenere la continuità della conversazione e ricordare lo stato del widget durante la navigazione sulle pagine pubbliche del sito. Quando necessario, chiediamo il consenso in base alla normativa applicabile.',
                },
                {
                    title: '4. Chat di supporto',
                    body: 'Sulle pagine pubbliche del sito possiamo utilizzare Tawk.to, un servizio esterno di live chat, per rispondere alle richieste dei visitatori e fornire informazioni su Soamco Budget. Quando utilizzi la chat, possono essere trattati i messaggi che invii volontariamente, dati tecnici relativi al dispositivo e al browser, indirizzo IP, pagina visitata, data e ora della conversazione ed eventuali altri dati che scegli di comunicare nel messaggio. Ti invitiamo a non inserire nella chat dati sensibili, dati bancari, credenziali di accesso o informazioni non necessarie alla richiesta di supporto. Il trattamento è effettuato per fornire assistenza, rispondere alle richieste informative e migliorare la qualità del supporto, sulla base del nostro interesse legittimo a rispondere alle richieste degli utenti.',
                },
                {
                    title: '5. Con chi condividiamo i dati',
                    body: 'Possiamo condividere dati con fornitori tecnici che ci aiutano a gestire hosting, autenticazione, posta elettronica, analytics, supporto e sicurezza. Condividiamo dati solo quando necessario e con adeguate garanzie contrattuali.',
                },
                {
                    title: '6. Sicurezza e conservazione',
                    body: 'Adottiamo misure tecniche e organizzative ragionevoli per proteggere i dati. Conserviamo le informazioni per il tempo necessario alle finalità del servizio, agli obblighi legali e alla gestione di contestazioni o richieste di sicurezza.',
                },
                {
                    title: '7. Trasferimenti internazionali',
                    body: 'Se i dati vengono trattati fuori dal tuo Paese o dallo Spazio Economico Europeo, adottiamo meccanismi adeguati come clausole contrattuali standard o misure equivalenti quando richiesto.',
                },
                {
                    title: '8. I tuoi diritti',
                    body: 'In base alla legge applicabile puoi chiedere accesso, rettifica, cancellazione, limitazione, opposizione, portabilità o revoca del consenso. Valuteremo ogni richiesta nei tempi previsti dalla normativa.',
                },
                {
                    title: '9. Minori',
                    body: 'Il servizio non è destinato a minori che non possano validamente accettare questi termini in base alla legge applicabile. Se veniamo a conoscenza di dati raccolti in modo non consentito, adotteremo misure ragionevoli per rimuoverli.',
                },
                {
                    title: '10. Modifiche a questa informativa',
                    body: 'Possiamo aggiornare questa informativa per riflettere cambiamenti normativi, tecnici o di prodotto. La versione aggiornata sarà pubblicata sul sito con la data di efficacia più recente.',
                },
            ],
            sourceNote:
                'Questa pagina è ispirata nella struttura a una moderna privacy policy SaaS, ma il contenuto è stato riscritto e adattato per Soamco Budget.',
        },
    },
    en: {
        terms: {
            pageTitle: 'Terms of service',
            eyebrow: 'Legal policy',
            title: 'Soamco Budget Terms of Service',
            description:
                'These terms govern the use of Soamco Budget as software for budgeting, accounts, transactions and personal planning.',
            intro: 'By using Soamco Budget, you agree to these terms, our privacy notice and the operating rules published within the service.',
            sections: [
                {
                    title: '1. Service overview',
                    body: 'Soamco Budget provides tools to organize accounts, transactions, categories, recurring entries, budgets and related functions. Some features may be free, in beta or subject to future plans.',
                },
                {
                    title: '2. Eligibility and proper use',
                    body: 'You may use the service only if you have the legal capacity to accept these terms and if your use complies with applicable law. You may not use the platform for fraud, unauthorized access or unlawful activity.',
                },
                {
                    title: '3. Accounts and credentials',
                    body: 'You are responsible for your account information, the confidentiality of your password and all activity carried out through your access. You must promptly notify us of any unauthorized access.',
                },
                {
                    title: '4. Plans, pricing and payments',
                    body: 'If paid features become available in the future, pricing, billing cycles, applicable taxes, renewals and cancellation terms will be clearly shown before purchase.',
                },
                {
                    title: '5. User-provided data',
                    body: 'The data you enter, import or organize in the service remains your responsibility. You must ensure it is lawful, accurate and that you have the right to process and share it.',
                },
                {
                    title: '6. Prohibited conduct',
                    body: 'You may not bypass security measures, attempt unauthorized reverse engineering, overload infrastructure, misuse bots, violate third-party rights or use the service to distribute harmful content.',
                },
                {
                    title: '7. Beta features',
                    body: 'Some parts of Soamco Budget may be labeled as beta. These features may change, be interrupted or be removed without notice and may not be error free.',
                },
                {
                    title: '8. Suspension and termination',
                    body: 'We may suspend or close accounts that violate these terms, create legal or technical risks or compromise platform security. You may stop using the service at any time.',
                },
                {
                    title: '9. Disclaimers and limits',
                    body: 'Soamco Budget is provided as is and as available. We do not guarantee error-free operation, uninterrupted service or fitness for a particular purpose. To the extent permitted by law, total liability is limited to the fees, if any, paid in the twelve months before the claim arose.',
                },
                {
                    title: '10. Changes and contact',
                    body: 'We may update these terms for regulatory, technical or product reasons. If material changes are made, we will publish the new version on the site with an updated effective date. Continued use after the update means acceptance.',
                },
            ],
            sourceNote:
                'This page takes structural inspiration from a modern SaaS terms document, but the content has been rewritten and adapted for Soamco Budget.',
        },
        privacy: {
            pageTitle: 'Privacy policy',
            eyebrow: 'Privacy policy',
            title: 'Soamco Budget Privacy Notice',
            description:
                'This notice explains what data we collect, why we use it and what choices you have regarding your account and the data processed in the service.',
            intro: 'Soamco Budget processes the personal data needed to provide the platform, protect it, improve it and comply with legal obligations.',
            sections: [
                {
                    title: '1. Information we collect',
                    body: 'We may collect registration data, profile details, device information, technical logs, usage preferences and the financial data you choose to enter or import into the service.',
                },
                {
                    title: '2. How we use information',
                    body: 'We use data to create and manage your account, display budgets and transactions, sync settings, provide support, prevent abuse, improve the product and send service communications.',
                },
                {
                    title: '3. Cookies and similar technologies',
                    body: 'We may use cookies or similar tools for session continuity, security, language preferences, aggregated analytics and user experience continuity. Tawk.to may use cookies or similar technologies to enable the chat to work, maintain conversation continuity and remember the widget state while navigating the public pages of the website. Where required, we request consent under applicable law.',
                },
                {
                    title: '4. Support chat',
                    body: 'On the public pages of the website, we may use Tawk.to, an external live chat service, to respond to visitors’ requests and provide information about Soamco Budget. When you use the chat, the messages you voluntarily send, technical data related to your device and browser, IP address, visited page, date and time of the conversation, and any other data you choose to include in the message may be processed. Please do not enter sensitive data, banking information, login credentials, or information that is not necessary for your support request. This processing is carried out to provide assistance, respond to information requests, and improve the quality of support, based on our legitimate interest in responding to users’ requests.',
                },
                {
                    title: '5. Who we share data with',
                    body: 'We may share data with technical providers who help us run hosting, authentication, email delivery, analytics, support and security. We share only when necessary and under suitable contractual safeguards.',
                },
                {
                    title: '6. Security and retention',
                    body: 'We use reasonable technical and organizational safeguards to protect data. We keep information for as long as needed for service purposes, legal obligations and the handling of disputes or security requests.',
                },
                {
                    title: '7. International transfers',
                    body: 'If data is processed outside your country or the European Economic Area, we use appropriate safeguards such as standard contractual clauses or equivalent measures when required.',
                },
                {
                    title: '8. Your rights',
                    body: 'Depending on applicable law, you may request access, correction, deletion, restriction, objection, portability or withdrawal of consent. We will assess each request within the timelines required by law.',
                },
                {
                    title: '9. Children',
                    body: 'The service is not intended for minors who cannot validly accept these terms under applicable law. If we learn that data was collected in a way that is not allowed, we will take reasonable steps to remove it.',
                },
                {
                    title: '10. Changes to this notice',
                    body: 'We may update this notice to reflect legal, technical or product changes. The updated version will be published on the site with the latest effective date.',
                },
            ],
            sourceNote:
                'This page takes structural inspiration from a modern SaaS privacy notice, but the content has been rewritten and adapted for Soamco Budget.',
        },
    },
};
