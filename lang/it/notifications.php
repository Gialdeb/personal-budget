<?php

return [
    'common' => [
        'details' => 'Dettagli',
        'footer' => 'Questa notifica è stata inviata da :app.',
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
        'welcome_after_verification' => [
            'topic' => 'Benvenuto',
            'subject' => 'Benvenuto',
            'title' => 'Benvenuto',
            'message' => 'Benvenuto {user.full_name}, il tuo account è ora attivo.',
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
];
