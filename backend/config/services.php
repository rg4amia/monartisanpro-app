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
    | ProsArtisan Third-Party Services
    |--------------------------------------------------------------------------
    */

    'cinetpay' => [
        'api_key' => env('CINETPAY_API_KEY'),
        'site_id' => env('CINETPAY_SITE_ID'),
        'secret_key' => env('CINETPAY_SECRET_KEY'),
        'mode' => env('CINETPAY_MODE', 'sandbox'), // sandbox or production
        'return_url' => env('CINETPAY_RETURN_URL', env('APP_URL') . '/payment/callback'),
        'notify_url' => env('CINETPAY_NOTIFY_URL', env('APP_URL') . '/api/v1/payments/webhook'),
    ],

    'africastalking' => [
        'username' => env('AFRICASTALKING_USERNAME'),
        'api_key' => env('AFRICASTALKING_API_KEY'),
        'shortcode' => env('AFRICASTALKING_SHORTCODE', '40520'),
    ],

    'firebase' => [
        'credentials' => env('FIREBASE_CREDENTIALS', base_path('firebase-credentials.json')),
        'database_url' => env('FIREBASE_DATABASE_URL'),
    ],

];
