// Every page must have a registered tour or an explicit exclusion.
export const excludedOnboardingPages: Record<string, string> = {
    Pricing:
        'Pagina pubblica di presentazione o installazione, con istruzioni proprie e senza sessione o pulsante ? del layout applicativo.',
    AboutMe:
        'Pagina pubblica di presentazione o installazione, con istruzioni proprie e senza sessione o pulsante ? del layout applicativo.',
    Dashboard:
        'Vista precedente non utilizzata dalle rotte applicative di produzione; DashboardLegacy è disponibile soltanto come confronto locale.',
    Features:
        'Pagina pubblica di presentazione o installazione, con istruzioni proprie e senza sessione o pulsante ? del layout applicativo.',
    Error: 'Pagina di errore e recupero della navigazione, senza operazioni applicative da guidare; un tour ostacolerebbe il recupero.',
    DownloadApp:
        'Pagina pubblica di presentazione o installazione, con istruzioni proprie e senza sessione o pulsante ? del layout applicativo.',
    Welcome:
        'Pagina pubblica di presentazione o installazione, con istruzioni proprie e senza sessione o pulsante ? del layout applicativo.',
    Customers:
        'Pagina pubblica di presentazione o installazione, con istruzioni proprie e senza sessione o pulsante ? del layout applicativo.',
    'help-center/Index':
        'Contenuto editoriale o legale da leggere; rimane accessibile senza sovrapporre un tour alla guida stessa.',
    'help-center/Section':
        'Contenuto editoriale o legale da leggere; rimane accessibile senza sovrapporre un tour alla guida stessa.',
    'help-center/Article':
        'Contenuto editoriale o legale da leggere; rimane accessibile senza sovrapporre un tour alla guida stessa.',
    'changelog/Index':
        'Contenuto editoriale o legale da leggere; rimane accessibile senza sovrapporre un tour alla guida stessa.',
    'changelog/Show':
        'Contenuto editoriale o legale da leggere; rimane accessibile senza sovrapporre un tour alla guida stessa.',
    'auth/Login':
        'Flusso di autenticazione o invito: usa layout dedicato e istruzioni nel modulo, prima dell’accesso al layout applicativo.',
    'auth/Register':
        'Flusso di autenticazione o invito: usa layout dedicato e istruzioni nel modulo, prima dell’accesso al layout applicativo.',
    'auth/ResetPassword':
        'Flusso di autenticazione o invito: usa layout dedicato e istruzioni nel modulo, prima dell’accesso al layout applicativo.',
    'auth/AccountInvitation':
        'Flusso di autenticazione o invito: usa layout dedicato e istruzioni nel modulo, prima dell’accesso al layout applicativo.',
    'auth/TwoFactorChallenge':
        'Flusso di autenticazione o invito: usa layout dedicato e istruzioni nel modulo, prima dell’accesso al layout applicativo.',
    'auth/ConfirmPassword':
        'Flusso di autenticazione o invito: usa layout dedicato e istruzioni nel modulo, prima dell’accesso al layout applicativo.',
    'auth/AdminInvitation':
        'Flusso di autenticazione o invito: usa layout dedicato e istruzioni nel modulo, prima dell’accesso al layout applicativo.',
    'auth/ForgotPassword':
        'Flusso di autenticazione o invito: usa layout dedicato e istruzioni nel modulo, prima dell’accesso al layout applicativo.',
    'auth/VerifyEmail':
        'Flusso di autenticazione o invito: usa layout dedicato e istruzioni nel modulo, prima dell’accesso al layout applicativo.',
    'admin/Index':
        'Area amministrativa con layout e flussi privilegiati separati, priva del pulsante ? applicativo. Esclusa per non introdurre comandi o tour editoriali duplicati.',
    'admin/PushBroadcasts':
        'Area amministrativa con layout e flussi privilegiati separati, priva del pulsante ? applicativo. Esclusa per non introdurre comandi o tour editoriali duplicati.',
    'admin/UserBilling':
        'Area amministrativa con layout e flussi privilegiati separati, priva del pulsante ? applicativo. Esclusa per non introdurre comandi o tour editoriali duplicati.',
    'admin/ActivityLog':
        'Area amministrativa con layout e flussi privilegiati separati, priva del pulsante ? applicativo. Esclusa per non introdurre comandi o tour editoriali duplicati.',
    'admin/Users':
        'Area amministrativa con layout e flussi privilegiati separati, priva del pulsante ? applicativo. Esclusa per non introdurre comandi o tour editoriali duplicati.',
    'dashboard/DashboardLegacy':
        'Vista precedente non utilizzata dalle rotte applicative di produzione; DashboardLegacy è disponibile soltanto come confronto locale.',
    'transactions/MonthlySheet':
        'Vista precedente non utilizzata dalle rotte applicative di produzione; DashboardLegacy è disponibile soltanto come confronto locale.',
    'legal/TermsOfService':
        'Contenuto editoriale o legale da leggere; rimane accessibile senza sovrapporre un tour alla guida stessa.',
    'legal/Privacy':
        'Contenuto editoriale o legale da leggere; rimane accessibile senza sovrapporre un tour alla guida stessa.',
    'reports/Section':
        'Pagina segnaposto delle analisi non ancora operative: espone stato di sviluppo e collegamenti, senza funzionalità da guidare.',
    'admin/ContextualHelp/Index':
        'Area amministrativa con layout e flussi privilegiati separati, priva del pulsante ? applicativo. Esclusa per non introdurre comandi o tour editoriali duplicati.',
    'admin/ContextualHelp/Edit':
        'Area amministrativa con layout e flussi privilegiati separati, priva del pulsante ? applicativo. Esclusa per non introdurre comandi o tour editoriali duplicati.',
    'admin/Changelog/Index':
        'Area amministrativa con layout e flussi privilegiati separati, priva del pulsante ? applicativo. Esclusa per non introdurre comandi o tour editoriali duplicati.',
    'admin/Changelog/Edit':
        'Area amministrativa con layout e flussi privilegiati separati, priva del pulsante ? applicativo. Esclusa per non introdurre comandi o tour editoriali duplicati.',
    'admin/CommunicationCategories/Index':
        'Area amministrativa con layout e flussi privilegiati separati, priva del pulsante ? applicativo. Esclusa per non introdurre comandi o tour editoriali duplicati.',
    'admin/CommunicationCategories/Show':
        'Area amministrativa con layout e flussi privilegiati separati, priva del pulsante ? applicativo. Esclusa per non introdurre comandi o tour editoriali duplicati.',
    'admin/PushBroadcasts/Index':
        'Area amministrativa con layout e flussi privilegiati separati, priva del pulsante ? applicativo. Esclusa per non introdurre comandi o tour editoriali duplicati.',
    'admin/CommunicationTemplates/Index':
        'Area amministrativa con layout e flussi privilegiati separati, priva del pulsante ? applicativo. Esclusa per non introdurre comandi o tour editoriali duplicati.',
    'admin/CommunicationTemplates/Show':
        'Area amministrativa con layout e flussi privilegiati separati, priva del pulsante ? applicativo. Esclusa per non introdurre comandi o tour editoriali duplicati.',
    'admin/CommunicationTemplates/Edit':
        'Area amministrativa con layout e flussi privilegiati separati, priva del pulsante ? applicativo. Esclusa per non introdurre comandi o tour editoriali duplicati.',
    'admin/Automation/Index':
        'Area amministrativa con layout e flussi privilegiati separati, priva del pulsante ? applicativo. Esclusa per non introdurre comandi o tour editoriali duplicati.',
    'admin/Automation/Show':
        'Area amministrativa con layout e flussi privilegiati separati, priva del pulsante ? applicativo. Esclusa per non introdurre comandi o tour editoriali duplicati.',
    'admin/SupportRequests/Index':
        'Area amministrativa con layout e flussi privilegiati separati, priva del pulsante ? applicativo. Esclusa per non introdurre comandi o tour editoriali duplicati.',
    'admin/SupportRequests/Show':
        'Area amministrativa con layout e flussi privilegiati separati, priva del pulsante ? applicativo. Esclusa per non introdurre comandi o tour editoriali duplicati.',
    'admin/Communications/Compose':
        'Area amministrativa con layout e flussi privilegiati separati, priva del pulsante ? applicativo. Esclusa per non introdurre comandi o tour editoriali duplicati.',
    'admin/Communications/Outbound/Index':
        'Area amministrativa con layout e flussi privilegiati separati, priva del pulsante ? applicativo. Esclusa per non introdurre comandi o tour editoriali duplicati.',
    'admin/Communications/Outbound/Show':
        'Area amministrativa con layout e flussi privilegiati separati, priva del pulsante ? applicativo. Esclusa per non introdurre comandi o tour editoriali duplicati.',
    'admin/KnowledgeBase/Sections/Index':
        'Area amministrativa con layout e flussi privilegiati separati, priva del pulsante ? applicativo. Esclusa per non introdurre comandi o tour editoriali duplicati.',
    'admin/KnowledgeBase/Sections/Edit':
        'Area amministrativa con layout e flussi privilegiati separati, priva del pulsante ? applicativo. Esclusa per non introdurre comandi o tour editoriali duplicati.',
    'admin/KnowledgeBase/Articles/Index':
        'Area amministrativa con layout e flussi privilegiati separati, priva del pulsante ? applicativo. Esclusa per non introdurre comandi o tour editoriali duplicati.',
    'admin/KnowledgeBase/Articles/Edit':
        'Area amministrativa con layout e flussi privilegiati separati, priva del pulsante ? applicativo. Esclusa per non introdurre comandi o tour editoriali duplicati.',
};
