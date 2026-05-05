<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
        'token' => env('AWS_SESSION_TOKEN'),
        'endpoint' => env('AWS_SES_ENDPOINT'),
    ],

    'telegram' => [
        'enabled' => env('TELEGRAM_NOTIFICATIONS_ENABLED', env('AUTOMATION_ALERTS_TELEGRAM_ENABLED', false)),
        'bot_token' => env('TELEGRAM_BOT_TOKEN', env('AUTOMATION_ALERTS_TELEGRAM_BOT_TOKEN')),
        'chat_id' => env('TELEGRAM_CHAT_ID', env('AUTOMATION_ALERTS_TELEGRAM_CHAT_ID')),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'umami' => [
        'host_url' => config('analytics.umami.host_url'),
        'script_url' => config('analytics.umami.script_url'),
        'website_id' => config('analytics.umami.website_id'),
    ],

    'tawk_to' => [
        'enabled' => env('TAWK_TO_ENABLED', false),
        'property_id' => env('TAWK_TO_PROPERTY_ID'),
        'widget_id' => env('TAWK_TO_WIDGET_ID'),
    ],

    'kofi' => [
        'enabled' => env('KOFI_WIDGET_ENABLED', true),
        'script_url' => env('KOFI_WIDGET_SCRIPT_URL', 'https://storage.ko-fi.com/cdn/widget/Widget_2.js'),
        'page_id' => env('KOFI_WIDGET_PAGE_ID', 'M4M61X1IRC'),
        'button_color' => env('KOFI_WIDGET_BUTTON_COLOR', '#f59273'),
        'webhook_verification_token' => env('KOFI_WEBHOOK_VERIFICATION_TOKEN'),
    ],

];
