<?php

return [
    'automation' => [
        'flash' => [
            'dispatched' => 'Pipeline di automazione avviata correttamente.',
            'retried' => 'Rilancio della pipeline di automazione avviato correttamente.',
        ],
        'creditCardAutopay' => [
            'partialFailure' => 'Il controllo addebiti carte ha completato il run ma ha rilevato errori reali su una o più carte.',
        ],
    ],
    'changelog' => [
        'flash' => [
            'saved' => 'Release changelog salvata correttamente.',
        ],
        'validation' => [
            'versionTaken' => 'Questa versione del changelog esiste già.',
        ],
    ],
    'knowledge' => [
        'validation' => [
            'duplicate_locale' => 'Ogni lingua può comparire una sola volta.',
            'missing_locale' => 'Manca la traduzione obbligatoria per :locale.',
        ],
    ],
    'knowledge_sections' => [
        'flash' => [
            'saved' => 'Sezione knowledge base salvata correttamente.',
            'deleted' => 'Sezione knowledge base eliminata correttamente.',
        ],
    ],
    'knowledge_articles' => [
        'flash' => [
            'saved' => 'Articolo knowledge base salvato correttamente.',
            'deleted' => 'Articolo knowledge base eliminato correttamente.',
        ],
    ],
    'contextual_help' => [
        'flash' => [
            'saved' => 'Guida contestuale salvata correttamente.',
        ],
    ],
    'support_requests' => [
        'flash' => [
            'status_updated' => 'Stato richiesta supporto aggiornato correttamente.',
        ],
    ],
    'communication_templates' => [
        'flash' => [
            'override_saved' => 'Override globale salvato correttamente.',
            'override_disabled' => 'Override globale disattivato correttamente.',
        ],
        'channels' => [
            'mail' => 'Email',
            'database' => 'In-app',
            'sms' => 'SMS',
        ],
        'modes' => [
            'system' => 'Sistema',
            'customizable' => 'Personalizzabile',
            'freeform' => 'Libero',
        ],
        'templates' => [
            'automation_failed_mail' => [
                'name' => 'Email errore automazione',
                'description' => 'Template email di sistema per gli errori delle pipeline di automazione.',
            ],
            'import_completed_mail' => [
                'name' => 'Email import completato',
                'description' => 'Template email personalizzabile per gli import completati.',
            ],
            'monthly_report_ready_mail' => [
                'name' => 'Email report mensile pronto',
                'description' => 'Template email personalizzabile per la disponibilità del report mensile.',
            ],
            'auth_verify_email_mail' => [
                'name' => 'Verifica email',
                'description' => 'Template di sistema obbligatorio per la verifica email.',
            ],
            'auth_reset_password_mail' => [
                'name' => 'Reimposta password',
                'description' => 'Template di sistema obbligatorio per il reset della password.',
            ],
            'admin_freeform_mail' => [
                'name' => 'Email admin libera',
                'description' => 'Template base libero per future email personalizzate admin.',
            ],
        ],
        'validation' => [
            'is_active_required' => 'Seleziona se l’override deve essere attivo.',
            'subject_too_long' => 'Il subject template non può superare 255 caratteri.',
            'title_too_long' => 'Il title template non può superare 255 caratteri.',
            'cta_label_too_long' => 'Il label template della CTA non può superare 255 caratteri.',
            'cta_url_too_long' => 'L’URL template della CTA non può superare 2048 caratteri.',
        ],
    ],
    'communication_composer' => [
        'flash' => [
            'sent' => 'Comunicazione manuale accodata correttamente.',
        ],
        'channels' => [
            'mail' => 'Email',
            'database' => 'Notifiche',
            'sms' => 'SMS',
            'telegram' => 'Telegram',
        ],
        'locales' => [
            'recipient' => 'Lingua utente',
        ],
        'content_modes' => [
            'template' => 'Template categoria',
            'custom' => 'Contenuto personalizzato',
        ],
        'categories' => [
            'auth.verify_email' => [
                'name' => 'Verifica email',
                'description' => 'Comunicazione di verifica email per l’utente selezionato.',
            ],
            'auth.reset_password' => [
                'name' => 'Reimposta password',
                'description' => 'Comunicazione di reset password per l’utente selezionato.',
            ],
            'user.welcome_after_verification' => [
                'name' => 'Benvenuto dopo verifica',
                'description' => 'Comunicazione di benvenuto inviata quando l’account dell’utente selezionato è già attivo e verificato.',
            ],
            'reports.weekly_ready' => [
                'name' => 'Report pronto',
                'description' => 'Comunicazione inviata quando il report personale è disponibile per l’utente selezionato.',
            ],
        ],
        'validation' => [
            'category_required' => 'Seleziona una categoria.',
            'category_invalid' => 'La categoria selezionata non è disponibile per l’invio manuale.',
            'channel_required' => 'Seleziona un canale.',
            'channels_required' => 'Seleziona almeno un canale.',
            'channel_invalid' => 'Il canale selezionato non è disponibile per questa categoria.',
            'recipient_required' => 'Seleziona un destinatario.',
            'recipients_required' => 'Seleziona almeno un destinatario.',
            'recipient_invalid' => 'Il destinatario selezionato non è valido.',
            'locale_required' => 'Seleziona una lingua.',
            'locale_invalid' => 'La lingua selezionata non è valida.',
            'content_mode_required' => 'Seleziona una modalità contenuto.',
            'content_mode_invalid' => 'La modalità contenuto selezionata non è valida.',
            'custom_body_required' => 'Scrivi il messaggio per il contenuto personalizzato.',
        ],
    ],
    'communication_outbound' => [
        'channels' => [
            'mail' => 'Email',
            'database' => 'Notifiche',
            'sms' => 'SMS',
            'telegram' => 'Telegram',
        ],
        'statuses' => [
            'queued' => 'Queued',
            'sent' => 'Inviato',
            'failed' => 'Fallito',
            'skipped' => 'Saltato',
        ],
        'empty' => [
            'noValue' => 'Nessun valore',
        ],
    ],
    'communication_categories' => [
        'flash' => [
            'channels_saved' => 'Configurazione canali categoria aggiornata correttamente.',
        ],
        'validation' => [
            'category_invalid' => 'La categoria selezionata non è valida.',
            'channels_required' => 'Configura almeno un canale.',
            'channel_invalid' => 'Uno dei canali selezionati non è supportato.',
            'template_required' => 'Se abiliti un canale devi scegliere un template.',
            'template_invalid' => 'Il template selezionato non è valido per questo canale.',
            'channel_globally_unavailable' => 'Il canale :channel non è disponibile globalmente.',
        ],
    ],
    'users' => [
        'filters' => [
            'roles' => [
                'all' => 'Tutti i ruoli',
                'admin' => 'Admin',
                'staff' => 'Staff',
                'user' => 'User',
            ],
            'statuses' => [
                'all' => 'Tutti gli stati',
            ],
            'plans' => [
                'all' => 'Tutti i piani',
                'free' => 'Free',
            ],
        ],
        'flash' => [
            'banned' => 'Utente bannato correttamente.',
            'suspended' => 'Utente sospeso correttamente.',
            'reactivated' => 'Utente riattivato correttamente.',
            'roles_updated' => 'Ruoli utente aggiornati correttamente.',
        ],
        'support' => [
            'states' => [
                'never_donated' => 'Mai donato',
                'support_recent' => 'Supporto recente',
                'reminder_due' => 'Reminder dovuto',
                'support_lapsed' => 'Supporto scaduto',
            ],
            'labels' => [
                'lastContribution' => 'Ultimo contributo',
                'nextReminder' => 'Prossimo reminder',
                'noContribution' => 'Nessun contributo ancora',
            ],
        ],
        'table' => [
            'support' => 'Supporto',
        ],
        'actions' => [
            'support' => 'Supporto',
        ],
        'billing' => [
            'title' => 'Supporto e billing',
            'description' => 'Controlla storico supporto, reminder e operazioni manuali per :user.',
            'flash' => [
                'transaction_saved' => 'Donazione registrata correttamente.',
                'transaction_updated' => 'Donazione aggiornata correttamente.',
                'transaction_assigned' => 'Donazione associata correttamente.',
                'subscription_deleted' => 'Sottoscrizione eliminata correttamente.',
                'support_updated' => 'Finestra supporto aggiornata correttamente.',
            ],
            'summary' => [
                'accessPlan' => 'Piano accesso',
                'supportState' => 'Stato supporto',
                'lastContribution' => 'Ultimo contributo',
                'nextReminder' => 'Prossimo reminder',
            ],
            'sections' => [
                'history' => 'Storico donazioni',
                'supportWindow' => 'Finestra supporto',
                'manualDonation' => 'Registra donazione',
                'editTransaction' => 'Modifica donazione',
                'assignTransaction' => 'Associa donazione pendente',
            ],
            'sectionDescriptions' => [
                'history' => 'Lo storico economico resta separato dal controllo accessi e completamente tracciabile.',
                'supportWindow' => 'Lo stato supporto è solo informativo e non blocca mai il piano free.',
                'manualDonation' => 'Registra una donazione manuale, Ko-fi o futura senza introdurre checkout.',
                'editTransaction' => 'Correggi dettagli provider, timing o note di una donazione esistente.',
                'assignTransaction' => 'Associa a questo utente donazioni non riconciliate quando serve.',
            ],
            'actions' => [
                'backToUsers' => 'Torna agli utenti',
                'saveSupport' => 'Salva stato supporto',
                'saveDonation' => 'Registra donazione',
                'editTransaction' => 'Modifica',
                'updateTransaction' => 'Aggiorna donazione',
                'assignTransaction' => 'Associa',
            ],
            'fields' => [
                'supportStatus' => 'Stato supporto',
                'plan' => 'Piano billing',
                'supportStartedAt' => 'Supporto dal',
                'supportEndsAt' => 'Fine finestra/review supporto',
                'nextReminderAt' => 'Prossimo reminder',
                'adminNotes' => 'Note admin',
                'isSupporter' => 'Segna l’utente come supporter per badge/reminder',
                'provider' => 'Provider',
                'amount' => 'Importo',
                'currency' => 'Valuta',
                'paidAt' => 'Pagato il',
                'receivedAt' => 'Ricevuto il',
                'isRecurring' => 'Donazione ricorrente',
                'applySupportWindow' => 'Aggiorna la finestra supporto da questa donazione',
            ],
            'supportStatuses' => [
                'free' => 'Free',
                'supporting' => 'Supporter',
                'inactive' => 'Inattivo',
            ],
            'table' => [
                'provider' => 'Provider',
                'amount' => 'Importo',
                'status' => 'Stato',
                'paidAt' => 'Pagato il',
            ],
            'empty' => [
                'noValue' => 'Nessun valore',
                'history' => 'Nessuna donazione registrata.',
                'selectTransaction' => 'Seleziona una transazione dallo storico per modificarla.',
                'assignableTransactions' => 'Nessuna donazione pendente disponibile da associare.',
            ],
        ],
        'validation' => [
            'admin_target_forbidden' => 'Non è possibile eseguire questa azione su un utente admin.',
            'roles_required' => 'Seleziona almeno un ruolo valido.',
        ],
    ],
];
