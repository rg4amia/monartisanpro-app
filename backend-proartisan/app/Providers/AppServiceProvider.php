<?php

namespace App\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\Facades\Schema;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Gate;
use Illuminate\Http\Request;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureRateLimiting();
        $this->configureAdminDashboardCache();
        $this->configureAdminGates();

        // Enregistrement des rôles et permissions
        Gate::before(function ($user, $ability) {
            // Les capacités fines du backoffice (« admin.* ») ont leur propre
            // Gate et ne doivent pas être court-circuitées ici (Chantier C6 / P2-10).
            if (str_starts_with((string) $ability, 'admin.')) {
                return null;
            }
            if ($user->role === 'admin') {
                return true;
            }
            if (method_exists($user, 'hasPermissionTo')) {
                return $user->hasPermissionTo($ability);
            }
        });
    }

    /**
     * Enregistre un Gate par capacité fine du backoffice admin (Chantier C6 / P2-10).
     *
     * Un admin sans capacité affectée — ou porteur de `admin.full-access` —
     * dispose d'un accès total ; sinon il est restreint à son périmètre.
     */
    protected function configureAdminGates(): void
    {
        foreach (\App\Services\Admin\AdminPermissionService::allCapabilityNames() as $capability) {
            Gate::define($capability, function ($user) use ($capability): bool {
                return $user->role === 'admin'
                    && app(\App\Services\Admin\AdminPermissionService::class)->userCan($user, $capability);
            });
        }
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Schema::defaultStringLength(191);
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }

    /**
     * Purge le cache des KPI du backoffice dès qu'une donnée agrégée change
     * (Chantier C4 / P1-7).
     */
    protected function configureAdminDashboardCache(): void
    {
        $observer = \App\Observers\AdminDashboardCacheObserver::class;

        foreach ([
            \App\Models\Transaction::class,
            \App\Models\WalletTransaction::class,
            \App\Models\Mission::class,
            \App\Models\Litige::class,
            \App\Models\Jalon::class,
            \App\Models\Order::class,
        ] as $model) {
            if (class_exists($model)) {
                $model::observe($observer);
            }
        }
    }

    /**
     * Configure rate limiters for the application.
     */
    protected function configureRateLimiting(): void
    {
        if (app()->environment('testing', 'local')) {
            RateLimiter::for('api', fn () => Limit::none());
            RateLimiter::for('auth', fn () => Limit::none());
            RateLimiter::for('webhook', fn () => Limit::none());
            return;
        }

        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(100)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('auth', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        RateLimiter::for('webhook', function (Request $request) {
            return Limit::perMinute(60)->by($request->ip());
        });
    }
}
