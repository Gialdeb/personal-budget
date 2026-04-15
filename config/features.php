<?php

return [
    'imports' => [
        'enabled' => (bool) env('FEATURE_IMPORT_ENABLED', false),
    ],
    'push_notifications' => [
        'enabled' => (bool) env('FEATURE_PUSH_NOTIFICATIONS_ENABLED', false),
        'profile_enabled' => (bool) env('FEATURE_PUSH_NOTIFICATIONS_PROFILE_ENABLED', false),
    ],
];
