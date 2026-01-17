<?php

return [
 /*
    |--------------------------------------------------------------------------
    | Mobile Money Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for mobile money providers in Côte d'Ivoire
   |
    */

 'max_retries' => env('MOBILE_MONEY_MAX_RETRIES', 3),
 'base_delay_seconds' => env('MOBILE_MONEY_BASE_DELAY', 2),

 /*
    |--------------------------------------------------------------------------
    | Wave Configuration
    |--------------------------------------------------------------------------
    */
 'wave' => [
  'api_key' => env('WAVE_API_KEY'),
  'api_secret' => env('WAVE_API_SECRET'),
  'base_url' => env('WAVE_BASE_URL', 'https://api.wave.com'),
  'webhook_secret' => env('WAVE_WEBHOOK_SECRET'),
  'environment' => env('WAVE_ENVIRONMENT', 'sandbox'), // sandbox or production
 ],

 /*
    |--------------------------------------------------------------------------
    | Orange Money Configuration
    |--------------------------------------------------------------------------
    */
 'orange_money' => [
  'client_id' => env('ORANGE_MONEY_CLIENT_ID'),
  'client_secret' => env('ORANGE_MONEY_CLIENT_SECRET'),
  'base_url' => env('ORANGE_MONEY_BASE_URL', 'https://api.orange.com'),
  'webhook_secret' => env('ORANGE_MONEY_WEBHOOK_SECRET'),
  'environment' => env('ORANGE_MONEY_ENVIRONMENT', 'sandbox'), // sandbox or production
 ],

 /*
    |--------------------------------------------------------------------------
    | MTN Mobile Money Configuration
    |--------------------------------------------------------------------------
    */
 'mtn' => [
  'subscription_key' => env('MTN_SUBSCRIPTION_KEY'),
  'api_user_id' => env('MTN_API_USER_ID'),
  'api_key' => env('MTN_API_KEY'),
  'base_url' => env('MTN_BASE_URL', 'https://sandbox.momodeveloper.mtn.com'),
  'webhook_secret' => env('MTN_WEBHOOK_SECRET'),
  'environment' => env('MTN_ENVIRONMENT', 'sandbox'), // sandbox or production
 ],

 /*
    |--------------------------------------------------------------------------
    | Service Fee Configuration
    |--------------------------------------------------------------------------
    */
 'service_fee_percentage' => env('MOBILE_MONEY_SERVICE_FEE', 5), // 5% service fee

 /*
    |--------------------------------------------------------------------------
    | Webhook Timeout Configuration
    |--------------------------------------------------------------------------
    */
 'webhook_timeout_minutes' => env('MOBILE_MONEY_WEBHOOK_TIMEOUT', 5), // 5 minutes
];
