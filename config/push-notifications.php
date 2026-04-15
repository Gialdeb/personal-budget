<?php

return [
    'queue' => env('PUSH_NOTIFICATIONS_QUEUE', 'default'),
    'firebase_web' => [
        'api_key' => env('VITE_FIREBASE_API_KEY', ''),
        'auth_domain' => env('VITE_FIREBASE_AUTH_DOMAIN', ''),
        'project_id' => env('VITE_FIREBASE_PROJECT_ID', ''),
        'storage_bucket' => env('VITE_FIREBASE_STORAGE_BUCKET', ''),
        'messaging_sender_id' => env('VITE_FIREBASE_MESSAGING_SENDER_ID', ''),
        'app_id' => env('VITE_FIREBASE_APP_ID', ''),
    ],
    'webpush' => [
        'headers' => [
            'Urgency' => env('PUSH_NOTIFICATIONS_URGENCY', 'high'),
            'TTL' => env('PUSH_NOTIFICATIONS_TTL'),
        ],
        'notification' => [
            'icon' => env('PUSH_NOTIFICATIONS_ICON'),
            'badge' => env('PUSH_NOTIFICATIONS_BADGE'),
            'require_interaction' => (bool) env(
                'PUSH_NOTIFICATIONS_REQUIRE_INTERACTION',
                true,
            ),
        ],
    ],
];
