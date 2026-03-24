<?php

return [
    'automation' => [
        'flash' => [
            'dispatched' => 'Pipeline di automazione avviata correttamente.',
            'retried' => 'Rilancio della pipeline di automazione avviato correttamente.',
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
        'validation' => [
            'admin_target_forbidden' => 'Non è possibile eseguire questa azione su un utente admin.',
            'roles_required' => 'Seleziona almeno un ruolo valido.',
        ],
    ],
];
