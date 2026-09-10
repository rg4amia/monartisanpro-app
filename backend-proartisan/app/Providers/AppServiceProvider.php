<?php

namespace App\Providers;

use App\Models\Jalon;
use App\Models\Litige;
use App\Models\Mission;
use App\Models\Order;
use App\Models\Transaction;
use App\Models\WalletTransaction;
use App\Observers\AdminDashboardCacheObserver;
use App\Services\Admin\AdminPermissionService;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

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
        foreach (AdminPermissionService::allCapabilityNames() as $capability) {
            Gate::define($capability, function ($user) use ($capability): bool {
                return $user->role === 'admin'
                    && app(AdminPermissionService::class)->userCan($user, $capability);
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
        $observer = AdminDashboardCacheObserver::class;

        foreach ([
            Transaction::class,
            WalletTransaction::class,
            Mission::class,
            Litige::class,
            Jalon::class,
            Order::class,
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
            RateLimiter::for('ai', fn () => Limit::none());

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

        // Assistant IA (chat BTP + recherche RAG). Ces appels consomment le
        // quota Gemini facturé : on plafonne agressivement, par utilisateur
        // authentifié si possible, sinon par IP pour les invités.
        RateLimiter::for('ai', function (Request $request) {
            $key = $request->user()?->id ? 'user:'.$request->user()->id : 'ip:'.$request->ip();

            return [
                Limit::perMinute(10)->by($key),
                Limit::perDay($request->user()?->id ? 150 : 40)->by($key),
            ];
        });
    }
}
