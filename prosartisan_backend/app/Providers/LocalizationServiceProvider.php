<?php

namespace App\Providers;

use App\Infrastructure\Services\Localization\LocalizationService;
use Illuminate\Support\ServiceProvider;

class LocalizationServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(LocalizationService::class, function ($app) {
            $locale = config('app.locale', 'fr');

            return new LocalizationService($locale === 'fr' ? 'fr_CI' : 'en_US');
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Set default locale to French
        app()->setLocale('fr');

        // Set timezone to Abidjan (Côte d'Ivoire)
        date_default_timezone_set('Africa/Abidjan');
    }
}
