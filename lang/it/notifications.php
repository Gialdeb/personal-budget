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
    ],
];
