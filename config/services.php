<?php

declare(strict_types=1);

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
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | BPS WebAPI (Badan Pusat Statistik Indonesia)
    |--------------------------------------------------------------------------
    | Konfigurasi untuk integrasi dengan API resmi BPS Indonesia.
    | API Key dapat diperoleh di: https://webapi.bps.go.id/developer/
    |--------------------------------------------------------------------------
    */
    'bps' => [
        'key' => env('WEBAPI_BPS_KEY'),
        'base_url' => env('BPS_API_BASE_URL', 'https://webapi.bps.go.id/v1/api'),
        'cache_ttl' => (int) env('BPS_CACHE_TTL_HOURS', 24),
        'timeout' => (int) env('BPS_API_TIMEOUT', 20),
        'max_retries' => (int) env('BPS_API_MAX_RETRIES', 3),
    ],

];
