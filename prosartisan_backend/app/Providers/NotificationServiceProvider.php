<?php

namespace App\Providers;

use App\Domain\Identity\Repositories\UserRepository;
use App\Domain\Shared\Services\EmailNotificationService;
use App\Domain\Shared\Services\NotificationService;
use App\Domain\Shared\Services\PushNotificationService;
use App\Domain\Shared\Services\SMSNotificationService;
use App\Domain\Shared\Services\WhatsAppNotificationService;
use App\Infrastructure\Services\Email\LaravelEmailService;
use App\Infrastructure\Services\Firebase\FirebasePushNotificationService;
use App\Infrastructure\Services\SMS\LocalSMSService;
use App\Infrastructure\Services\SMS\TwilioSMSService;
use App\Infrastructure\Services\WhatsApp\WhatsAppBusinessService;
use Illuminate\Support\ServiceProvider;

class NotificationServiceProvider extends ServiceProvider
{
 /**
  * Register services.
  */
 public function register(): void
 {
  // Register Push Notification Service
  $this->app->bind(PushNotificationService::class, function ($app) {
   return new FirebasePushNotificationService(
    $app->make(UserRepository::class)
   );
  });

  // Register SMS Notification Service
  $this->app->bind(SMSNotificationService::class, function ($app) {
   // Use local SMS service by default, fallback to Twilio
   if (config('services.local_sms.api_url')) {
    return new LocalSMSService($app->make(UserRepository::class));
   }

   return new TwilioSMSService($app->make(UserRepository::class));
  });

  // Register WhatsApp Notification Service
  $this->app->bind(WhatsAppNotificationService::class, function ($app) {
   return new WhatsAppBusinessService(
    $app->make(UserRepository::class)
   );
  });

  // Register Email Notification Service
  $this->app->bind(EmailNotificationService::class, function ($app) {
   return new LaravelEmailService(
    $app->make(UserRepository::class)
   );
  });

  // Register main Notification Service
  $this->app->singleton(NotificationService::class, function ($app) {
   return new NotificationService(
    $app->make(UserRepository::class),
    $app->make(PushNotificationService::class),
    $app->make(SMSNotificationService::class),
    $app->make(WhatsAppNotificationService::class),
    $app->make(EmailNotificationService::class)
   );
  });
 }

 /**
  * Bootstrap services.
  */
 public function boot(): void
 {
  //
 }
}
