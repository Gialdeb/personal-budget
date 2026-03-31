<?php

return [
    'umami' => [
        'enabled' => env('UMAMI_ENABLED', false),
        'host_url' => env('UMAMI_HOST_URL'),
        'script_url' => env(
            'UMAMI_SCRIPT_URL',
            ($host = env('UMAMI_HOST_URL'))
                ? rtrim($host, '/').'/script.js'
                : null,
        ),
        'website_id' => env('UMAMI_WEBSITE_ID'),
        'domains' => array_values(array_filter(array_map(
            static fn (string $domain): string => trim($domain),
            explode(',', (string) env('UMAMI_DOMAINS', '')),
        ))),
        'environment_tag' => env('UMAMI_ENVIRONMENT_TAG'),
        'respect_dnt' => env('UMAMI_RESPECT_DNT', true),
        'public_route_names' => [
            'home',
            'features',
            'pricing',
            'about-me',
            'customers',
            'download-app',
            'changelog.index',
        ],
    ],
];
