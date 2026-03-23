<?php

return [
    'default' => 'EUR',

    'supported' => [
        'EUR' => [
            'code' => 'EUR',
            'symbol' => '€',
            'name' => 'Euro',
            'locale' => 'it-IT',
        ],
        'USD' => [
            'code' => 'USD',
            'symbol' => '$',
            'name' => 'US Dollar',
            'locale' => 'en-US',
        ],
        'GBP' => [
            'code' => 'GBP',
            'symbol' => '£',
            'name' => 'British Pound',
            'locale' => 'en-GB',
        ],
    ],
    'format_locales' => [
        'it-IT' => 'Italia (1.234,56)',
        'en-GB' => 'United Kingdom (1,234.56)',
        'en-US' => 'United States (1,234.56)',
    ],
];
