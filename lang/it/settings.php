<?php

return [
    'title' => 'Impostazioni',
    'sections' => [
        'profile' => 'Profilo',
        'categories' => 'Categorie di spesa',
        'tracked_items' => 'Riferimenti',
        'banks' => 'Banche',
        'accounts' => 'Conti',
        'years' => 'Anni di gestione',
        'security' => 'Sicurezza',
        'exports' => 'Esportazione',
        'appearance' => 'Aspetto',
    ],
    'years' => [
        'created' => 'Anno :year creato correttamente.',
        'activated' => 'Anno :year impostato come attivo.',
        'closed' => 'Anno :year chiuso correttamente.',
        'reopened' => 'Anno :year riaperto correttamente.',
        'deleted' => 'Anno :year eliminato correttamente.',
        'not_available' => "L'anno :year non è disponibile tra gli anni di gestione.",
        'closed_for_editing' => "L'anno :year è chiuso. Puoi consultare i dati, ma non modificarli finché non lo riapri.",
        'validation' => [
            'delete_blocked' => "L'anno :year non può essere eliminato: :reasons.",
            'required' => "Inserisci l'anno di gestione.",
            'integer' => "L'anno deve essere un numero intero.",
            'between' => 'Inserisci un anno valido tra 1900 e 2200.',
            'unique' => 'Questo anno di gestione è già presente.',
            'future_year_not_allowed' => "Non puoi creare anni futuri. L'anno massimo consentito è :year.",
        ],
        'delete_reasons' => [
            'keep_one' => 'deve rimanere almeno un anno di gestione disponibile',
            'active_current' => "è l'anno attivo corrente",
            'budgets' => 'ha budget collegati',
            'transactions' => 'ha transazioni collegate',
            'scheduled_entries' => 'ha scadenze pianificate collegate',
            'recurring_occurrences' => 'ha occorrenze ricorrenti collegate',
            'recurring_entries' => 'ha ricorrenze attive su questo anno',
        ],
        'suggestions' => [
            'prepare_title' => "Prepara l'anno :year",
            'open_current_year' => "L'anno :year non è ancora aperto nel gestionale. Puoi crearlo ora senza generare dati in automatico.",
            'open_next_year' => "Stai lavorando sull'anno più recente. Puoi aprire ora il :year senza creare nulla in automatico.",
        ],
    ],
    'banks' => [
        'source' => [
            'custom' => 'Personalizzata',
            'catalog' => 'Globale',
        ],
        'flash' => [
            'catalog_created' => 'Banca dal catalogo aggiunta correttamente.',
            'catalog_created_with_account' => "Banca dal catalogo aggiunta con conto base associato pronto all'uso.",
            'custom_created' => 'Banca personalizzata creata correttamente.',
            'custom_created_with_account' => "Banca personalizzata creata con conto base associato pronto all'uso.",
            'updated' => 'Banca personalizzata aggiornata correttamente.',
            'activated' => 'Banca attivata correttamente.',
            'deactivated' => 'Banca disattivata correttamente.',
            'deleted' => 'Banca rimossa correttamente dalle tue banche disponibili.',
        ],
        'validation' => [
            'custom_only' => 'Solo le banche personalizzate possono essere modificate.',
            'delete_blocked' => 'Questa banca non può essere rimossa: :reasons. Disattivala invece per toglierla dalla selezione operativa.',
        ],
        'delete_reasons' => [
            'account_one' => 'è collegata a 1 account',
            'account_many' => 'è collegata a :count account',
        ],
    ],
    'enums' => [
        'user_status' => [
            'active' => 'Attivo',
            'suspended' => 'Sospeso',
            'banned' => 'Bannato',
        ],
        'subscription_status' => [
            'active' => 'Attiva',
            'inactive' => 'Inattiva',
            'past_due' => 'Scaduta',
            'canceled' => 'Annullata',
            'trialing' => 'In prova',
        ],
    ],
    'profile' => [
        'impersonation_consent_updated' => 'Preferenza di accesso assistito aggiornata correttamente.',
        'currency_updated' => 'Valuta aggiornata correttamente.',
        'notification_preferences_updated' => 'Preferenze notifiche aggiornate correttamente.',
        'push_web' => [
            'flash' => [
                'enabled' => 'Push web attivate e token browser registrato correttamente.',
                'disabled' => 'Push web disattivate e token browser attivi disabilitati correttamente.',
            ],
            'validation' => [
                'token_required' => 'Serve un token push browser valido.',
                'token_invalid' => 'Il token push browser non è valido.',
                'platform_invalid' => 'La piattaforma push browser non è valida.',
                'locale_invalid' => 'La lingua del browser non è valida.',
            ],
        ],
        'active_sessions' => [
            'title' => 'Sessioni attive',
            'description' => 'Controlla i dispositivi che hanno accesso al tuo account e chiudi quelli che non riconosci più.',
            'current_badge' => 'Sessione corrente',
            'current_helper' => 'Questa è la sessione che stai usando ora.',
            'fields' => [
                'ip_address' => 'Indirizzo IP',
                'device' => 'Dispositivo e browser',
                'last_activity' => 'Ultima attività',
            ],
            'actions' => [
                'revoke' => 'Disconnetti',
                'revoke_others' => 'Disconnetti tutte le altre sessioni',
                'cancel' => 'Annulla',
                'confirm_single' => 'Conferma disconnessione',
                'confirm_others' => 'Conferma disconnessione globale',
            ],
            'confirmations' => [
                'single_title' => 'Disconnettere questa sessione?',
                'single_description' => 'La sessione selezionata verrà chiusa immediatamente su quel dispositivo.',
                'others_title' => 'Disconnettere tutte le altre sessioni?',
                'others_description' => 'Tutti gli altri dispositivi verranno disconnessi. La sessione corrente resterà attiva.',
            ],
            'empty' => [
                'title' => 'Nessun’altra sessione attiva',
                'description' => 'Al momento il tuo account risulta aperto solo nella sessione corrente.',
            ],
            'flash' => [
                'single_revoked' => 'Sessione disconnessa correttamente.',
                'others_revoked' => ':count sessioni disconnesse correttamente.',
            ],
            'validation' => [
                'current_session' => 'La sessione corrente non può essere disconnessa da questa azione.',
            ],
        ],
        'currency_locked_after_transactions' => 'La valuta non può più essere modificata dopo l’inserimento delle prime transazioni.',
        'currency_locked_after_accounts_or_transactions' => 'La valuta base non può essere modificata dopo la creazione di conti o transazioni.',
        'notifications' => [
            'categories' => [
                'credit_cards' => [
                    'autopay_completed' => [
                        'label' => 'Addebito carta eseguito',
                        'description' => 'Avvisa quando il ciclo di una carta di credito viene addebitato automaticamente sul conto collegato.',
                    ],
                ],
                'imports' => [
                    'completed' => [
                        'label' => 'Import completato',
                        'description' => 'Avvisa quando un import termina e i dati sono pronti da controllare.',
                    ],
                ],
                'reports' => [
                    'weekly_ready' => [
                        'label' => 'Report disponibile',
                        'description' => 'Avvisa quando un report è disponibile tra le tue notifiche.',
                    ],
                ],
            ],
            'validation' => [
                'required' => 'Invia almeno un set valido di preferenze notifiche.',
                'invalid_topic' => 'Una delle notifiche selezionate non è configurabile da questo profilo.',
                'invalid_value' => 'Uno dei valori delle preferenze notifiche non è valido.',
            ],
        ],
    ],
];
