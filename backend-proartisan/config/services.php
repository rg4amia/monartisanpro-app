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
    | Payment Providers (Mobile Money - Côte d'Ivoire)
    |--------------------------------------------------------------------------
    */

    'wave' => [
        'api_url' => env('WAVE_API_URL', 'https://api.wave.com/v1'),
        'api_key' => env('WAVE_API_KEY'),
        'secret_key' => env('WAVE_SECRET_KEY'),
        'webhook_secret' => env('WAVE_WEBHOOK_SECRET'),
        'currency' => env('WAVE_CURRENCY', 'XOF'), // FCFA
        'success_url' => env('WAVE_SUCCESS_URL', env('APP_URL') . '/payment/success'),
        'error_url' => env('WAVE_ERROR_URL', env('APP_URL') . '/payment/error'),
    ],

    'orange_money' => [
        'api_url' => env('ORANGE_MONEY_API_URL', 'https://api.orange.com/orange-money-webpay/ci/v1'),
        'client_id' => env('ORANGE_MONEY_CLIENT_ID'),
        'client_secret' => env('ORANGE_MONEY_CLIENT_SECRET'),
        'merchant_key' => env('ORANGE_MONEY_MERCHANT_KEY'),
        'merchant_id' => env('ORANGE_MONEY_MERCHANT_ID'),
        'auth_header' => env('ORANGE_MONEY_AUTH_HEADER'),
        'currency' => env('ORANGE_MONEY_CURRENCY', 'XOF'), // FCFA
        'return_url' => env('ORANGE_MONEY_RETURN_URL', env('APP_URL') . '/payment/return'),
        'cancel_url' => env('ORANGE_MONEY_CANCEL_URL', env('APP_URL') . '/payment/cancel'),
        'notif_url' => env('ORANGE_MONEY_NOTIF_URL', env('APP_URL') . '/api/webhooks/orange-money'),
    ],

    'infobip' => [
        'api_url' => env('INFOBIP_API_URL', 'https://api.infobip.com'),
        'api_key' => env('INFOBIP_API_KEY'),
        'sender' => env('INFOBIP_SENDER', 'ProsArtisan'),
    ],

    /*
    |--------------------------------------------------------------------------
    | SMS Pro Africa
    |--------------------------------------------------------------------------
    */

    'sms' => [
        'provider' => env('SMS_PROVIDER', 'smspro'), // 'log' or 'smspro'
        'api_token' => env('SMS_API_TOKEN', '1227|Gjd4N2x6qRYdnwWybpkJfoA87LbCFAFnvpNK2NPwa4861d63'),
        'base_url' => env('SMS_BASE_URL', 'https://app.smspro.africa/api/v3'),
        'sender_id' => env('SMS_SENDER_ID', 'ProsArtisan'),
    ],

    'whatsapp' => [
        'provider' => env('WHATSAPP_PROVIDER', 'api'), // 'log' or 'api'
        'api_token' => env('WHATSAPP_API_TOKEN'),
        'base_url' => env('WHATSAPP_BASE_URL', 'https://api.whatsapp.com/v1'),
    ],

    'gemini' => [
        'api_key' => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_MODEL', 'gemini-3.5-flash'),
    ],

    'qdrant' => [
        'url' => env('QDRANT_URL'),
        'api_key' => env('QDRANT_API_KEY'),
        'collection' => env('QDRANT_COLLECTION', 'btp_rules'),
    ],

];
