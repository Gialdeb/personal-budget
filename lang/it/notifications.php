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
    ],
];
