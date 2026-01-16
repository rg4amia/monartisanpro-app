<?php

namespace App\Providers;

use App\Domain\Identity\Repositories\UserRepository;
use App\Domain\Identity\Services\AuthenticationService;
use App\Domain\Identity\Services\KYCVerificationService;
use App\Domain\Identity\Services\LaravelAuthenticationService;
use App\Domain\Identity\Services\DefaultKYCVerificationService;
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

        // Register domain services
        $this->app->bind(AuthenticationService::class, LaravelAuthenticationService::class);
        $this->app->bind(KYCVerificationService::class, DefaultKYCVerificationService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
