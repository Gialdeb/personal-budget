<?php

return [
    'channels' => [
        'mail' => [
            'label' => 'Email',
            'is_enabled' => true,
            'is_transport_ready' => true,
        ],
        'database' => [
            'label' => 'Notifiche',
            'is_enabled' => true,
            'is_transport_ready' => true,
        ],
        'sms' => [
            'label' => 'SMS',
            'is_enabled' => false,
            'is_transport_ready' => false,
        ],
        'telegram' => [
            'label' => 'Telegram',
            'is_enabled' => false,
            'is_transport_ready' => false,
        ],
    ],
];
