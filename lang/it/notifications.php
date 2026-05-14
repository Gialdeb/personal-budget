<?php

return [
    'common' => [
        'details' => 'Dettagli',
        'footer' => 'Questa notifica è stata inviata da :app.',
        'brand_tagline' => 'Pianificazione, movimenti e conti',
    ],
    'topics' => [
        'automation_failed' => [
            'topic' => 'Errore automazione',
            'subject' => 'Pipeline di automazione in errore',
            'title' => 'Pipeline di automazione in errore',
            'message' => 'Una delle pipeline di automazione richiede attenzione.',
            'cta' => 'Apri automazioni',
            'details' => [
                'pipeline' => 'Pipeline',
                'error_message' => 'Messaggio errore',
                'context' => 'Contesto',
            ],
        ],
        'credit_card_autopay_completed' => [
            'topic' => 'Addebito carta eseguito',
            'subject' => 'Addebito automatico eseguito per {credit_card_account_name}',
            'title' => 'Addebito automatico eseguito',
            'message' => 'Il ciclo della carta {credit_card_account_name} è stato addebitato con successo per {charged_amount_formatted} sul conto {linked_payment_account_name} in data {payment_due_date_formatted}.',
            'cta' => 'Apri transazioni',
            'details' => [
                'credit_card_account' => 'Carta',
                'linked_payment_account' => 'Conto addebitato',
                'amount' => 'Importo',
                'payment_due_date' => 'Data addebito',
                'cycle_end_date' => 'Chiusura ciclo',
            ],
        ],
        'auth_verify_email' => [
            'topic' => 'Verifica email',
            'subject' => 'Verifica il tuo indirizzo email',
            'title' => 'Verifica il tuo indirizzo email',
            'message' => 'Fai clic sul pulsante qui sotto per verificare il tuo indirizzo email.',
            'cta' => 'Verifica email',
            'details' => [],
        ],
        'auth_reset_password' => [
            'topic' => 'Reimposta password',
            'subject' => 'Reimposta la tua password',
            'title' => 'Reimposta la tua password',
            'message' => 'Hai ricevuto questa email perché è stata richiesta la reimpostazione della password del tuo account.',
            'cta' => 'Reimposta password',
            'expire' => 'Questo link scadrà tra :count minuti.',
            'details' => [],
        ],
        'import_completed' => [
            'topic' => 'Import completato',
            'subject' => 'Import completato',
            'title' => 'Import completato',
            'message' => 'Il tuo import è stato completato con successo.',
            'cta' => 'Apri import',
            'details' => [
                'import_uuid' => 'Import',
                'filename' => 'File',
                'imported_rows_count' => 'Righe importate',
                'rows_count' => 'Righe totali',
            ],
        ],
        'monthly_report_ready' => [
            'topic' => 'Report mensile disponibile',
            'subject' => 'Report mensile disponibile',
            'title' => 'Report mensile disponibile',
            'message' => 'Il tuo report mensile per :period è pronto.',
            'cta' => 'Apri dashboard',
            'details' => [
                'period' => 'Periodo',
            ],
        ],
        'recurring_weekly_due_summary' => [
            'topic' => 'Scadenze settimanali',
            'subject' => 'Scadenze ricorrenti della settimana',
            'title' => 'Scadenze ricorrenti della settimana',
            'message' => 'Ecco il riepilogo sintetico delle ricorrenze previste nei prossimi giorni.',
            'cta' => 'Apri dashboard',
            'details' => [],
        ],
        'recurring_monthly_due_summary' => [
            'topic' => 'Scadenze di inizio mese',
            'subject' => 'Scadenze ricorrenti di inizio mese',
            'title' => 'Scadenze ricorrenti di inizio mese',
            'message' => 'Ecco il riepilogo sintetico delle ricorrenze previste nei primi giorni del mese.',
            'cta' => 'Apri dashboard',
            'details' => [],
        ],
        'recurring_due_reminders' => [
            'topic' => 'Promemoria ricorrenze',
            'description' => 'Notifiche per ricorrenze manuali o automatiche in scadenza o scadute.',
            'title' => 'Promemoria ricorrenza',
            'message' => 'Una ricorrenza richiede attenzione.',
            'cta' => 'Apri ricorrenza',
            'details' => [],
        ],
        'credits_debts_due_reminders' => [
            'topic' => 'Promemoria crediti e debiti',
            'description' => 'Notifiche per crediti e debiti in scadenza o scaduti.',
            'title' => 'Promemoria credito/debito',
            'message' => 'Un credito o debito richiede attenzione.',
            'cta' => 'Apri credito/debito',
            'details' => [],
        ],
        'welcome_after_verification' => [
            'topic' => 'Benvenuto',
            'subject' => 'Benvenuto su Soamco Budget',
            'title' => 'Benvenuto su Soamco Budget',
            'message' => 'Benvenuto {user.full_name}, ti ringrazio per esserti iscritto. Spero che Soamco Budget possa esserti utile per controllare il tuo bilancio personale.',
            'cta' => 'Apri dashboard',
            'details' => [],
        ],
        'account_invitation' => [
            'topic' => 'Invito condivisione conto',
            'subject' => '{inviter_name} ti ha invitato a condividere un conto su Soamco Budget',
            'title' => 'Hai ricevuto un invito',
            'message' => "{inviter_name} ti ha invitato ad accedere al conto \"{account_name}\" su Soamco Budget.\n\nLivello di accesso assegnato: {invitation_role_label}\n\nApri il link qui sotto per accettare l’invito e completare l’accesso al conto.\n\n{invitation_expiry_notice}\n\nSe non ti aspettavi questa email, puoi ignorarla.",
            'cta' => 'Accetta invito',
            'expiry_notice' => 'Questo invito scade il :date.',
        ],
    ],
    'recurring_summaries' => [
        'intro' => 'Riepilogo ricorrenze per :window.',
        'item' => '- :date · :title · :amount',
        'fallback_label' => 'Ricorrenza senza titolo',
        'windows' => [
            'weekly' => 'la settimana dal :start al :end',
            'monthly' => 'i primi giorni del mese, dal :start al :end',
        ],
    ],
    'reminders' => [
        'cta' => [
            'open' => 'Apri dettaglio',
        ],
        'recurring' => [
            'fallback_description' => 'Ricorrenza senza titolo',
            'manual' => [
                'title' => 'Ricorrenza da registrare',
                'body' => '":description" scade oggi per :amount. Registrala quando il movimento è avvenuto.',
            ],
            'manual_upcoming' => [
                'title' => 'Ricorrenza da registrare in arrivo',
                'body' => '":description" scade il :date per :amount. Registrala quando il movimento è avvenuto.',
            ],
            'automatic' => [
                'title' => 'Ricorrenza automatica in arrivo',
                'body' => '":description" è prevista il :date per :amount.',
            ],
            'overdue' => [
                'title' => 'Ricorrenza scaduta',
                'body' => '":description" era prevista il :date per :amount.',
            ],
        ],
        'credits_debts' => [
            'credit' => [
                'due' => [
                    'title' => 'Credito da incassare',
                    'body' => '":description" da :reference scade il :date. Residuo: :remaining su :total.',
                    'body_without_reference' => '":description" scade il :date. Residuo: :remaining su :total.',
                ],
                'overdue' => [
                    'title' => 'Credito scaduto',
                    'body' => '":description" è scaduto il :date. Devi ancora incassare :remaining.',
                ],
            ],
            'debt' => [
                'due' => [
                    'title' => 'Debito da pagare',
                    'body' => '":description" scade il :date. Residuo: :remaining su :total.',
                ],
                'overdue' => [
                    'title' => 'Debito scaduto',
                    'body' => '":description" è scaduto il :date. Devi ancora pagare :remaining.',
                ],
            ],
        ],
    ],
];
