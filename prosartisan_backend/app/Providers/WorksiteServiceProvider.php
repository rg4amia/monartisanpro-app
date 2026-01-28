<?php

namespace App\Providers;

use App\Domain\Worksite\Repositories\ChantierRepository;
use App\Domain\Worksite\Repositories\JalonRepository;
use App\Domain\Worksite\Services\AutoValidationService;
use App\Domain\Worksite\Services\DefaultAutoValidationService;
use App\Infrastructure\Repositories\PostgresChantierRepository;
use App\Infrastructure\Repositories\PostgresJalonRepository;
use Illuminate\Support\ServiceProvider;

/**
 * Service provider for Worksite context
 *
 * Binds worksite domain interfaces to their implementations
 */
class WorksiteServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Bind repositories
        $this->app->bind(ChantierRepository::class, PostgresChantierRepository::class);
        $this->app->bind(JalonRepository::class, PostgresJalonRepository::class);

        // Bind services
        $this->app->bind(AutoValidationService::class, DefaultAutoValidationService::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
