<?php

return [
 /*
    |--------------------------------------------------------------------------
    | Default Notification Channels
    |--------------------------------------------------------------------------
    |
    | This option controls the default notification channels and their order
    | for retry logic. Channels will be tried in the specified order.
    |
    */

 'default_channels' => [
  'push',
  'sms',
  'whatsapp',
  'email'
 ],

 /*
    |--------------------------------------------------------------------------
    | Channel Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for each notification channel
    |
    */

 'channels' => [
  'push' => [
   'enabled' => env('PUSH_NOTIFICATIONS_ENABLED', true),
   'retry_attempts' => 3,
   'retry_delay' => 5, // seconds
  ],

  'sms' => [
   'enabled' => env('SMS_NOTIFICATIONS_ENABLED', true),
   'retry_attempts' => 2,
   'retry_delay' => 10, // seconds
  ],

  'whatsapp' => [
   'enabled' => env('WHATSAPP_NOTIFICATIONS_ENABLED', true),
   'retry_attempts' => 2,
   'retry_delay' => 15, // seconds
  ],

  'email' => [
   'enabled' => env('EMAIL_NOTIFICATIONS_ENABLED', true),
   'retry_attempts' => 1,
   'retry_delay' => 30, // seconds
  ],
 ],

 /*
    |--------------------------------------------------------------------------
    | Topic Subscriptions
    |--------------------------------------------------------------------------
    |
    | Configuration for Firebase topic subscriptions
    |
    */

 'topics' => [
  'artisan_missions' => [
   'description' => 'New missions for artisans',
   'auto_subscribe' => ['artisan'],
  ],

  'client_updates' => [
   'description' => 'Updates for clients',
   'auto_subscribe' => ['client'],
  ],

  'supplier_notifications' => [
   'description' => 'Notifications for suppliers',
   'auto_subscribe' => ['fournisseur'],
  ],
 ],

 /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Rate limiting configuration to prevent spam
    |
    */

 'rate_limiting' => [
  'enabled' => env('NOTIFICATION_RATE_LIMITING_ENABLED', true),
  'max_per_user_per_hour' => 50,
  'max_per_user_per_day' => 200,
 ],
];
