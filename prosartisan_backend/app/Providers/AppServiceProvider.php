<?php

namespace App\Providers;

use App\Domain\Identity\Repositories\UserRepository;
use App\Domain\Identity\Services\AuthenticationService;
use App\Domain\Identity\Services\KYCVerificationService;
use App\Domain\Identity\Services\LaravelAuthenticationService;
use App\Domain\Identity\Services\DefaultKYCVerificationService;
use App\Domain\Marketplace\Repositories\DevisRepository;
use App\Domain\Marketplace\Repositories\MissionRepository;
use App\Domain\Marketplace\Services\ArtisanSearchService;
use App\Domain\Marketplace\Services\DefaultArtisanSearchService;
use App\Domain\Marketplace\Services\LocationPrivacyService;
use App\Domain\Marketplace\Services\DefaultLocationPrivacyService;
use App\Infrastructure\Repositories\PostgresDevisRepository;
use App\Infrastructure\Repositories\PostgresMissionRepository;
use App\Infrastructure\Repositories\PostgresUserRepository;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register repositories
        $this->app->bind(UserRepository::class, PostgresUserRepository::class);
        $this->app->bind(MissionRepository::class, PostgresMissionRepository::class);
        $this->app->bind(DevisRepository::class, PostgresDevisRepository::class);

        // Register domain services
        $this->app->bind(AuthenticationService::class, LaravelAuthenticationService::class);
        $this->app->bind(KYCVerificationService::class, DefaultKYCVerificationService::class);
        $this->app->bind(ArtisanSearchService::class, DefaultArtisanSearchService::class);
        $this->app->bind(LocationPrivacyService::class, DefaultLocationPrivacyService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
