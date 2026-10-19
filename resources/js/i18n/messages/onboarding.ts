import { additionalOnboardingMessages } from './additional-onboarding';

const it = {
    progress: 'Passaggio {current} di {total}',
    next: 'Avanti',
    back: 'Indietro',
    done: 'Completa',
    skip: 'Salta il tour',
    disable: 'Non mostrare più automaticamente',
    replay: 'Riavvia il tour',
    guide: 'Consulta la guida',
    help: 'Guida e tour di questa pagina',
    saveError:
        'Non è stato possibile salvare la preferenza. Riprova quando sei online.',
    unavailable:
        'Il tour non è disponibile in questo momento. Puoi consultare la guida e riprovare più tardi.',
    steps: {
        ...additionalOnboardingMessages.it,
        dashboardPeriod: {
            title: 'Periodo e conti',
            body: 'Scegli mese, anno e conti da analizzare. Le sezioni sotto seguono questo perimetro.',
        },
        capacity: {
            title: 'Quanto puoi ancora spendere',
            body: 'Leggi il budget residuo del mese. Le simulazioni includono spese previste, crediti o debiti senza registrare movimenti.',
        },
        pending: {
            title: 'Cosa richiede attenzione',
            body: 'Qui trovi scadenze e pagamenti da verificare. Apri la voce per controllare il dettaglio prima di confermare.',
        },
        budget: {
            title: 'Confronta spese e budget',
            body: 'Individua le categorie vicine al limite o già oltre il budget del periodo.',
        },
        agenda: {
            title: 'Tieni d’occhio le scadenze',
            body: 'L’agenda riunisce prossime uscite, ricorrenze e beneficiari frequenti. Usala per preparare le prossime operazioni.',
        },
        transactionPeriod: {
            title: 'Parti dal periodo giusto',
            body: 'Scegli anno e mese del registro. Il conto e gli altri filtri restringono i movimenti da consultare.',
        },
        transactionCreate: {
            title: 'Registra entrate, uscite e trasferimenti',
            body: 'Apri Nuovo o il + su mobile. Scegli il tipo di movimento; per un trasferimento indica il conto di partenza e quello di arrivo.',
        },
        transactionSummary: {
            title: 'Leggi il riepilogo',
            body: 'Confronta entrate, uscite e saldo del periodo. I trasferimenti tra conti non sono nuove entrate o spese.',
        },
        transactionReferences: {
            title: 'Collega un riferimento',
            body: 'Il riferimento identifica la persona, il servizio o il soggetto del movimento. Puoi sceglierlo quando crei o modifichi una registrazione.',
        },
        transactionRegister: {
            title: 'Controlla e gestisci i movimenti',
            body: 'Usa i pulsanti di visualizzazione per includere entrate, uscite e altre voci. Nelle righe o schede trovi i dettagli e le azioni disponibili.',
        },
        quickActions: {
            title: 'Ripeti le operazioni frequenti',
            body: 'Le azioni rapide aprono un movimento precompilato. Controlla data, conto e importo prima di salvarlo; dal menu puoi anche gestire i modelli.',
        },
        recurringPeriod: {
            title: 'Scegli mese e conto',
            body: 'Consulta le scadenze del periodo e limita la vista al conto che ti interessa.',
        },
        recurringCreate: {
            title: 'Crea un abbonamento o un piano a rate',
            body: 'Usa Nuovo o il + su mobile. Definisci frequenza, importo e conto; per le rate imposta anche la durata del piano.',
        },
        recurringSummary: {
            title: 'Distingui previsioni e pagamenti',
            body: 'Il riepilogo mostra piani, scadenze e importi previsti. Le rate pagate sono indicate separatamente: una scadenza prevista non è ancora una transazione.',
        },
        recurringCalendar: {
            title: 'Trova le scadenze nel calendario',
            body: 'Espandi il calendario se è chiuso. Seleziona un giorno per vedere le relative scadenze nell’elenco.',
        },
        recurringFilters: {
            title: 'Trova il piano che ti serve',
            body: 'Espandi i filtri per distinguere ricorrenze e rate, stato del piano e scadenze registrate o ancora da convertire.',
        },
        recurringPayments: {
            title: 'Verifica prima di registrare',
            body: 'Ogni riga o scheda mostra la scadenza e lo stato del pagamento. Converti in transazione quando va registrata; apri i pagamenti collegati per controllarli.',
        },
        sharedBalances: {
            title: 'Quanto devi ricevere o pagare',
            body: 'Questi saldi riguardano solo le spese condivise e non cambiano con il periodo del registro. Non sono il saldo dei tuoi conti.',
        },
        sharedPeople: {
            title: 'Controlla il rapporto con ogni persona',
            body: 'Le schede distinguono il saldo delle spese condivise dalla situazione completa della persona. Apri il dettaglio per ricostruire le voci.',
        },
        sharedFilters: {
            title: 'Ritrova le spese del periodo',
            body: 'Filtra per persona, anno e mese oppure cerca titolo, categoria e riferimento. Il filtro del registro non modifica i saldi attuali in alto.',
        },
        sharedStatus: {
            title: 'Distingui aperte e regolate',
            body: 'Cambia vista per concentrarti sui movimenti ancora da regolare o consultare quelli già chiusi.',
        },
        sharedRegister: {
            title: 'Ricostruisci gli anticipi',
            body: 'Il registro mostra spese condivise e relativi rimborsi. Hai pagato tu indica un tuo anticipo; se ha pagato l’altra persona, controlla la tua quota.',
        },
        sharedShares: {
            title: 'Leggi le quote e il residuo',
            body: 'Distingui la tua quota da quella dell’altra persona. Gli importi rimborsati o compensati spiegano quanto resta da regolare.',
        },
        sharedRepayments: {
            title: 'Registra il rimborso effettivo',
            body: 'Dalla scheda della persona apri Registra rimborso spese condivise. Indica conto, data e importo solo quando il denaro è stato ricevuto o pagato.',
        },

        setup: {
            title: 'Inizia con i tuoi conti',
            body: 'Aggiungi una banca e un conto con il saldo iniziale, poi registra i primi movimenti. La dashboard si aggiornerà con i tuoi dati.',
        },
        navigation: {
            title: 'Le sezioni di Soamco',
            body: 'Il menu ti porta a movimenti, ricorrenze e pianificazione. Su mobile trovi le destinazioni principali nella barra in basso e le altre nel menu.',
        },
        search: {
            title: 'Cerca un movimento',
            body: 'La ricerca nell’intestazione ti aiuta a ritrovare rapidamente le registrazioni.',
        },
        help: {
            title: 'La guida è sempre qui',
            body: 'Usa questo pulsante per ripetere il tour o consultare la guida completa della pagina.',
        },
        period: {
            title: 'Scegli il periodo',
            body: 'Cambia mese e anno per leggere la situazione del periodo che ti interessa.',
        },
        accounts: {
            title: 'Scegli i conti',
            body: 'Analizza tutti i conti oppure limita la dashboard al conto selezionato.',
        },
        timeline: {
            title: 'Segui l’andamento',
            body: 'La linea temporale mostra come cambia la situazione. Puoi selezionare un mese e confrontare uscite, entrate e disponibilità.',
        },
        metrics: {
            title: 'Leggi i valori del periodo',
            body: 'Questi indicatori riassumono la situazione del periodo e dei conti selezionati. Le sezioni successive offrono i dettagli.',
        },
    },
};
const en: typeof it = {
    progress: 'Step {current} of {total}',
    next: 'Next',
    back: 'Back',
    done: 'Complete',
    skip: 'Skip tour',
    disable: 'Do not show automatically again',
    replay: 'Restart tour',
    guide: 'Read the guide',
    help: 'Page guide and tour',
    saveError:
        'Your preference could not be saved. Try again when you are online.',
    unavailable:
        'The tour is not available right now. You can read the guide and try again later.',
    steps: {
        ...additionalOnboardingMessages.en,
        dashboardPeriod: {
            title: 'Period and accounts',
            body: 'Choose the month, year and accounts to analyse. The sections below follow this scope.',
        },
        capacity: {
            title: 'What you can still spend',
            body: 'Read the month’s remaining budget. Simulations include forecast expenses, credits or debts without recording transactions.',
        },
        pending: {
            title: 'What needs attention',
            body: 'Find upcoming events and payments to verify. Open an item and check its details before confirming.',
        },
        budget: {
            title: 'Compare spending with budget',
            body: 'Find categories approaching or already exceeding the period’s budget.',
        },
        agenda: {
            title: 'Keep track of due dates',
            body: 'The agenda brings together upcoming expenses, recurring entries and frequent payees. Use it to prepare your next actions.',
        },
        transactionPeriod: {
            title: 'Start with the right period',
            body: 'Choose the register’s year and month. Account and other filters narrow the transactions to review.',
        },
        transactionCreate: {
            title: 'Record income, expenses and transfers',
            body: 'Open New or the + on mobile. Choose a transaction type; for a transfer, select both the source and destination accounts.',
        },
        transactionSummary: {
            title: 'Read the summary',
            body: 'Compare income, expenses and the period’s balance. Transfers between accounts are not new income or spending.',
        },
        transactionReferences: {
            title: 'Link a reference',
            body: 'The reference identifies the person, service or subject of a transaction. Select it when creating or editing a record.',
        },
        transactionRegister: {
            title: 'Review and manage transactions',
            body: 'Use the display buttons to include income, expenses and other entries. Rows or cards show details and available actions.',
        },
        quickActions: {
            title: 'Repeat frequent operations',
            body: 'Quick actions open a prefilled transaction. Check the date, account and amount before saving; the menu also lets you manage templates.',
        },
        recurringPeriod: {
            title: 'Choose a month and account',
            body: 'Review the period’s due dates and narrow the view to the account you need.',
        },
        recurringCreate: {
            title: 'Create a subscription or instalment plan',
            body: 'Use New or the + on mobile. Set frequency, amount and account; for instalments, also set the plan’s duration.',
        },
        recurringSummary: {
            title: 'Separate forecasts from payments',
            body: 'The summary shows plans, occurrences and planned amounts. Paid instalments are listed separately: a planned occurrence is not yet a transaction.',
        },
        recurringCalendar: {
            title: 'Find due dates in the calendar',
            body: 'Expand the calendar if it is closed. Select a day to see its occurrences in the list.',
        },
        recurringFilters: {
            title: 'Find the plan you need',
            body: 'Expand filters to distinguish recurring and instalment plans, plan status and occurrences already recorded or still awaiting conversion.',
        },
        recurringPayments: {
            title: 'Check before recording',
            body: 'Each row or card shows an occurrence and payment status. Convert it to a transaction when it should be recorded; open linked payments to review them.',
        },
        sharedBalances: {
            title: 'What to receive or pay',
            body: 'These balances cover shared expenses only and do not follow the register’s period. They are not your account balances.',
        },
        sharedPeople: {
            title: 'Review each person’s balance',
            body: 'Cards distinguish shared expense balances from the person’s complete position. Open the details to review the entries.',
        },
        sharedFilters: {
            title: 'Find the period’s expenses',
            body: 'Filter by person, year and month, or search title, category and reference. Register filters do not change current balances above.',
        },
        sharedStatus: {
            title: 'Separate open and settled entries',
            body: 'Switch views to focus on entries still to settle or review those already closed.',
        },
        sharedRegister: {
            title: 'Trace who paid in advance',
            body: 'The register shows shared expenses and related repayments. You paid identifies your advance; if the other person paid, review your share.',
        },
        sharedShares: {
            title: 'Read shares and remaining amounts',
            body: 'Distinguish your share from the other person’s. Repaid or offset amounts explain what remains to settle.',
        },
        sharedRepayments: {
            title: 'Record an actual repayment',
            body: 'Open Record shared expense repayment on the person’s card. Enter account, date and amount only when money was received or paid.',
        },

        setup: {
            title: 'Start with your accounts',
            body: 'Add a bank and an account with its opening balance, then record your first transactions. The dashboard will update with your data.',
        },
        navigation: {
            title: 'Soamco sections',
            body: 'Use the menu to access transactions, recurring entries and planning. On mobile, the bottom bar holds the main destinations and the menu holds the others.',
        },
        search: {
            title: 'Find a transaction',
            body: 'Use the search in the header to quickly find your records.',
        },
        help: {
            title: 'Help is always here',
            body: 'Use this button to repeat the tour or read the full guide for this page.',
        },
        period: {
            title: 'Choose a period',
            body: 'Change the month and year to view the period you need.',
        },
        accounts: {
            title: 'Choose accounts',
            body: 'Analyse all accounts or limit the dashboard to the selected account.',
        },
        timeline: {
            title: 'Follow the trend',
            body: 'The timeline shows how your situation changes. Select a month and compare expenses, income and availability.',
        },
        metrics: {
            title: 'Read the period’s figures',
            body: 'These indicators summarise the selected period and accounts. The following sections provide the details.',
        },
    },
};
export const onboardingMessages = { it, en };
