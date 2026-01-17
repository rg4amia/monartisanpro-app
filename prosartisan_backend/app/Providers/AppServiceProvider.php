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
use App\Domain\Financial\Repositories\SequestreRepository;
use App\Domain\Financial\Repositories\JetonRepository;
use App\Domain\Financial\Repositories\TransactionRepository;
use App\Domain\Financial\Services\EscrowFragmentationService;
use App\Domain\Financial\Services\DefaultEscrowFragmentationService;
use App\Domain\Financial\Services\JetonFactory;
use App\Domain\Financial\Services\DefaultJetonFactory;
use App\Domain\Financial\Services\AntiFraudService;
use App\Domain\Financial\Services\DefaultAntiFraudService;
use App\Domain\Reputation\Repositories\ReputationRepository;
use App\Domain\Reputation\Repositories\RatingRepository;
use App\Infrastructure\Repositories\PostgresDevisRepository;
use App\Infrastructure\Repositories\PostgresMissionRepository;
use App\Infrastructure\Repositories\PostgresUserRepository;
use App\Infrastructure\Repositories\PostgresSequestreRepository;
use App\Infrastructure\Repositories\PostgresJetonRepository;
use App\Infrastructure\Repositories\PostgresTransactionRepository;
use App\Infrastructure\Repositories\PostgresReputationRepository;
use App\Infrastructure\Repositories\PostgresRatingRepository;
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
        $this->app->bind(SequestreRepository::class, PostgresSequestreRepository::class);
        $this->app->bind(JetonRepository::class, PostgresJetonRepository::class);
        $this->app->bind(TransactionRepository::class, PostgresTransactionRepository::class);
        $this->app->bind(ReputationRepository::class, PostgresReputationRepository::class);
        $this->app->bind(RatingRepository::class, PostgresRatingRepository::class);

        // Register domain services
        $this->app->bind(AuthenticationService::class, LaravelAuthenticationService::class);
        $this->app->bind(KYCVerificationService::class, DefaultKYCVerificationService::class);
        $this->app->bind(ArtisanSearchService::class, DefaultArtisanSearchService::class);
        $this->app->bind(LocationPrivacyService::class, DefaultLocationPrivacyService::class);
        $this->app->bind(EscrowFragmentationService::class, DefaultEscrowFragmentationService::class);
        $this->app->bind(JetonFactory::class, DefaultJetonFactory::class);
        $this->app->bind(AntiFraudService::class, DefaultAntiFraudService::class);
        $this->app->bind(\App\Domain\Shared\Services\FraudDetectionService::class, \App\Infrastructure\Services\Security\DefaultFraudDetectionService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
