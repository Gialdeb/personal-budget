<?php

return [
    'enabled' => env('RECAPTCHA_V3_ENABLED', false),
    'site_key' => env('RECAPTCHA_V3_SITE_KEY'),
    'secret_key' => env('RECAPTCHA_V3_SECRET_KEY'),
    'threshold' => (float) env('RECAPTCHA_V3_SCORE_THRESHOLD', 0.5),
    'timeout' => (int) env('RECAPTCHA_V3_TIMEOUT', 5),
    'verify_url' => env('RECAPTCHA_V3_VERIFY_URL', 'https://www.google.com/recaptcha/api/siteverify'),
    'log_results' => (bool) env('RECAPTCHA_V3_LOG_RESULTS', false),
    'expected_hostnames' => array_values(array_filter(array_map(
        static fn (string $hostname): string => trim($hostname),
        explode(',', (string) env('RECAPTCHA_V3_EXPECTED_HOSTNAMES', '')),
    ))),
    'actions' => [
        'login' => [
            'threshold' => (float) env('RECAPTCHA_V3_LOGIN_SCORE_THRESHOLD', env('RECAPTCHA_V3_SCORE_THRESHOLD', 0.5)),
        ],
        'register' => [
            'threshold' => (float) env('RECAPTCHA_V3_REGISTER_SCORE_THRESHOLD', env('RECAPTCHA_V3_SCORE_THRESHOLD', 0.5)),
        ],
    ],
];
