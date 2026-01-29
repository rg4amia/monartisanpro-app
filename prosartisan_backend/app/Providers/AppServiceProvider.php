<?php

namespace App\Providers;

use App\Domain\Financial\Repositories\JetonRepository;
use App\Domain\Financial\Repositories\SequestreRepository;
use App\Domain\Financial\Repositories\TransactionRepository;
use App\Domain\Financial\Services\AntiFraudService;
use App\Domain\Financial\Services\DefaultAntiFraudService;
use App\Domain\Financial\Services\DefaultEscrowFragmentationService;
use App\Domain\Financial\Services\DefaultJetonFactory;
use App\Domain\Financial\Services\EscrowFragmentationService;
use App\Domain\Financial\Services\JetonFactory;
use App\Domain\Identity\Repositories\UserRepository;
use App\Domain\Identity\Services\AuthenticationService;
use App\Domain\Identity\Services\DefaultKYCVerificationService;
use App\Domain\Identity\Services\KYCVerificationService;
use App\Domain\Identity\Services\LaravelAuthenticationService;
use App\Domain\Marketplace\Repositories\DevisRepository;
use App\Domain\Marketplace\Repositories\MissionRepository;
use App\Domain\Marketplace\Services\ArtisanSearchService;
use App\Domain\Marketplace\Services\DefaultArtisanSearchService;
use App\Domain\Marketplace\Services\DefaultLocationPrivacyService;
use App\Domain\Marketplace\Services\LocationPrivacyService;
use App\Domain\Reputation\Repositories\RatingRepository;
use App\Domain\Reputation\Repositories\ReputationRepository;
use App\Infrastructure\Repositories\CachedUserRepository;
use App\Infrastructure\Repositories\PostgresDevisRepository;
use App\Infrastructure\Repositories\PostgresJetonRepository;
use App\Infrastructure\Repositories\PostgresMissionRepository;
use App\Infrastructure\Repositories\PostgresRatingRepository;
use App\Infrastructure\Repositories\PostgresReputationRepository;
use App\Infrastructure\Repositories\PostgresSequestreRepository;
use App\Infrastructure\Repositories\PostgresTransactionRepository;
use App\Infrastructure\Repositories\PostgresUserRepository;
use App\Infrastructure\Services\Cache\CacheService;
use App\Infrastructure\Services\Cache\StaticDataCacheService;
use Illuminate\Support\ServiceProvider;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register cache services
        $this->app->singleton(CacheService::class);
        $this->app->singleton(StaticDataCacheService::class);

        // Register repositories with caching decorators
        $this->app->bind('postgres.user.repository', PostgresUserRepository::class);
        $this->app->bind(UserRepository::class, function ($app) {
            return new CachedUserRepository(
                $app->make('postgres.user.repository'),
                $app->make(CacheService::class)
            );
        });

        $this->app->bind(MissionRepository::class, PostgresMissionRepository::class);
        $this->app->bind(DevisRepository::class, PostgresDevisRepository::class);
        $this->app->bind(SequestreRepository::class, PostgresSequestreRepository::class);
        $this->app->bind(JetonRepository::class, PostgresJetonRepository::class);
        $this->app->bind(TransactionRepository::class, PostgresTransactionRepository::class);
        $this->app->bind(ReputationRepository::class, PostgresReputationRepository::class);
        $this->app->bind(RatingRepository::class, PostgresRatingRepository::class);

        // Register System Parameter services
        $this->app->bind(
            \App\Domain\Shared\Repositories\SystemParameterRepository::class,
            \App\Infrastructure\Repositories\PostgresSystemParameterRepository::class
        );
        $this->app->singleton(\App\Domain\Shared\Services\SystemParameterService::class);

        // Register domain services
        $this->app->bind(AuthenticationService::class, LaravelAuthenticationService::class);
        $this->app->bind(KYCVerificationService::class, DefaultKYCVerificationService::class);
        $this->app->bind(ArtisanSearchService::class, DefaultArtisanSearchService::class);
        $this->app->bind(LocationPrivacyService::class, DefaultLocationPrivacyService::class);
        $this->app->bind(EscrowFragmentationService::class, DefaultEscrowFragmentationService::class);
        $this->app->bind(JetonFactory::class, DefaultJetonFactory::class);
        $this->app->bind(AntiFraudService::class, DefaultAntiFraudService::class);
        $this->app->bind(\App\Domain\Shared\Services\FraudDetectionService::class, \App\Infrastructure\Services\Security\DefaultFraudDetectionService::class);
        $this->app->bind(\App\Domain\Shared\Services\EncryptionService::class, \App\Infrastructure\Services\Security\LaravelEncryptionService::class);
        $this->app->bind(\App\Domain\Shared\Services\SecureFileStorageService::class, \App\Infrastructure\Services\Storage\LocalSecureFileStorageService::class);
        $this->app->bind(\App\Domain\Shared\Services\GPSUtilityService::class, \App\Infrastructure\Services\GPS\GPSUtilityService::class);

        // Bind SMS notification service (use LocalSMSService for development)
        $this->app->bind(\App\Domain\Shared\Services\SMSNotificationService::class, \App\Infrastructure\Services\SMS\LocalSMSService::class);

        $this->app->bind(\App\Domain\Identity\Services\SMSService::class, function ($app) {
            return new \App\Infrastructure\Services\SMS\SMSServiceAdapter(
                $app->make(\App\Domain\Shared\Services\SMSNotificationService::class)
            );
        });
    }
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
    }

    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(
            fn(): ?Password => app()->isProduction()
                ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
                : null
        );
    }
}
