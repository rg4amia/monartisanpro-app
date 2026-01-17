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
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Mobile Money Services
    |--------------------------------------------------------------------------
    */

    'wave' => [
        'api_key' => env('WAVE_API_KEY'),
        'api_secret' => env('WAVE_API_SECRET'),
        'base_url' => env('WAVE_BASE_URL', 'https://api.wave.com'),
        'webhook_secret' => env('WAVE_WEBHOOK_SECRET'),
        'environment' => env('WAVE_ENVIRONMENT', 'sandbox'),
    ],

    'orange_money' => [
        'client_id' => env('ORANGE_MONEY_CLIENT_ID'),
        'client_secret' => env('ORANGE_MONEY_CLIENT_SECRET'),
        'base_url' => env('ORANGE_MONEY_BASE_URL', 'https://api.orange.com'),
        'webhook_secret' => env('ORANGE_MONEY_WEBHOOK_SECRET'),
        'environment' => env('ORANGE_MONEY_ENVIRONMENT', 'sandbox'),
    ],

    'mtn' => [
        'subscription_key' => env('MTN_SUBSCRIPTION_KEY'),
        'api_user_id' => env('MTN_API_USER_ID'),
        'api_key' => env('MTN_API_KEY'),
        'base_url' => env('MTN_BASE_URL', 'https://sandbox.momodeveloper.mtn.com'),
        'webhook_secret' => env('MTN_WEBHOOK_SECRET'),
        'environment' => env('MTN_ENVIRONMENT', 'sandbox'),
    ],

];
