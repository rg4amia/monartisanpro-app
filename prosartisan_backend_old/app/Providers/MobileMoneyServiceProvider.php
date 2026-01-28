<?php

namespace App\Providers;

use App\Domain\Financial\Services\MobileMoneyService;
use App\Infrastructure\Services\MobileMoney\MobileMoneyWebhookHandler;
use App\Infrastructure\Services\MobileMoney\MTNMobileMoneyGateway;
use App\Infrastructure\Services\MobileMoney\OrangeMoneyGateway;
use App\Infrastructure\Services\MobileMoney\WaveMobileMoneyGateway;
use Illuminate\Http\Client\Factory as HttpClient;
use Illuminate\Support\ServiceProvider;

/**
 * Service Provider for Mobile Money integrations
 *
 * Configures and binds mobile money gateways and services
 */
class MobileMoneyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Register individual gateways
        $this->app->singleton('mobile_money.wave', function ($app) {
            return new WaveMobileMoneyGateway(
                $app->make(HttpClient::class),
                config('services.wave.api_key') ?? '',
                config('services.wave.api_secret') ?? '',
                config('services.wave.base_url') ?? 'https://api.wave.com',
                config('services.wave.webhook_secret') ?? ''
            );
        });

        $this->app->singleton('mobile_money.orange', function ($app) {
            return new OrangeMoneyGateway(
                $app->make(HttpClient::class),
                config('services.orange_money.client_id') ?? '',
                config('services.orange_money.client_secret') ?? '',
                config('services.orange_money.base_url') ?? 'https://api.orange.com',
                config('services.orange_money.webhook_secret') ?? ''
            );
        });

        $this->app->singleton('mobile_money.mtn', function ($app) {
            return new MTNMobileMoneyGateway(
                $app->make(HttpClient::class),
                config('services.mtn.subscription_key') ?? '',
                config('services.mtn.api_user_id') ?? '',
                config('services.mtn.api_key') ?? '',
                config('services.mtn.base_url') ?? 'https://sandbox.momodeveloper.mtn.com',
                config('services.mtn.webhook_secret') ?? ''
            );
        });

        // Register the main mobile money service with all gateways
        $this->app->singleton(MobileMoneyService::class, function ($app) {
            $gateways = [
                $app->make('mobile_money.wave'),
                $app->make('mobile_money.orange'),
                $app->make('mobile_money.mtn'),
            ];

            return new MobileMoneyService(
                $gateways,
                $app->make(\App\Domain\Financial\Repositories\TransactionRepository::class),
                config('mobile_money.max_retries', 3),
                config('mobile_money.base_delay_seconds', 2)
            );
        });

        // Register webhook handler
        $this->app->singleton(MobileMoneyWebhookHandler::class, function ($app) {
            $gateways = [
                $app->make('mobile_money.wave'),
                $app->make('mobile_money.orange'),
                $app->make('mobile_money.mtn'),
            ];

            return new MobileMoneyWebhookHandler(
                $gateways,
                $app->make(\App\Domain\Financial\Repositories\TransactionRepository::class),
                $app->make(\App\Domain\Financial\Repositories\SequestreRepository::class),
                $app->make(MobileMoneyService::class)
            );
        });
    }

    public function boot(): void
    {
        // Publish configuration files
        $this->publishes([
            __DIR__.'/../../config/mobile_money.php' => config_path('mobile_money.php'),
        ], 'mobile-money-config');
    }
}
