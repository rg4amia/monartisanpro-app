<?php

namespace App\Application\Handlers;

use App\Domain\Dispute\Events\DisputeReported;
use App\Domain\Financial\Events\EscrowFragmented;
use App\Domain\Financial\Events\FundsBlocked;
use App\Domain\Financial\Events\JetonGenerated;
use App\Domain\Financial\Events\LaborPaymentReleased;
use App\Domain\Financial\Models\Sequestre\Sequestre;
use App\Domain\Financial\Models\ValueObjects\SequestreId;
use App\Domain\Financial\Repositories\JetonRepository;
use App\Domain\Financial\Repositories\SequestreRepository;
use App\Domain\Financial\Services\EscrowFragmentationService;
use App\Domain\Financial\Services\JetonFactory;
use App\Domain\Financial\Services\MobileMoneyGateway;
use App\Domain\Identity\Repositories\UserRepository;
use App\Domain\Marketplace\Events\QuoteAccepted;
use App\Domain\Reputation\Events\ScoreRecalculated;
use App\Domain\Reputation\Repositories\ReputationRepository;
use App\Domain\Reputation\Services\MetricsAggregationService;
use App\Domain\Reputation\Services\ScoreCalculationService;
use App\Domain\Shared\Services\DomainEventDispatcher;
use App\Domain\Worksite\Events\ChantierCompleted;
use App\Domain\Worksite\Events\MilestoneValidated;
use DateTime;
use Illuminate\Support\Facades\Log;

/**
 * Event handler for cross-context integration flows
 *
 * Handles the main business process flows:
 * 1. QuoteAccepted → block funds → fragment → generate jeton
 * 2. MilestoneValidated → release labor payment
 * 3. ChantierCompleted → recalculate score
 * 4. DisputeReported → freeze funds
 */
class IntegrationEventHandler
{
    public function __construct(
        private EscrowFragmentationService $escrowService,
        private JetonFactory $jetonFactory,
        private MobileMoneyGateway $mobileMoneyGateway,
        private SequestreRepository $sequestreRepository,
        private JetonRepository $jetonRepository,
        private ScoreCalculationService $scoreCalculationService,
        private MetricsAggregationService $metricsAggregationService,
        private ReputationRepository $reputationRepository,
        private UserRepository $userRepository,
        private DomainEventDispatcher $eventDispatcher
    ) {}

    /**
     * Handle QuoteAccepted event - initiate escrow process
     * Flow: QuoteAccepted → block funds → fragment → generate jeton
     * Requirements: 3.7, 4.1, 4.2, 4.3
     */
    public function handleQuoteAccepted(QuoteAccepted $event): void
    {
        try {
            Log::info("Starting escrow process for accepted quote {$event->devisId->getValue()}");

            // Step 1: Block funds in escrow
            $sequestreId = SequestreId::generate();
            $sequestre = new Sequestre(
                $sequestreId,
                $event->missionId,
                $event->clientId,
                $event->artisanId,
                $event->totalAmount,
                $event->materialsAmount,
                $event->laborAmount,
                new DateTime
            );

            // Block funds via mobile money
            $transactionRef = $this->mobileMoneyGateway->blockFunds(
                $event->clientId,
                $event->totalAmount
            );

            $sequestre->block($transactionRef);
            $this->sequestreRepository->save($sequestre);

            // Fire FundsBlocked event
            $this->eventDispatcher->dispatch(new FundsBlocked(
                $sequestreId,
                $event->missionId,
                $event->totalAmount,
                new DateTime
            ));

            Log::info("Funds blocked successfully for sequestre {$sequestreId->getValue()}");

            // Step 2: Fragment escrow (65% materials, 35% labor)
            $sequestre->fragment();
            $this->sequestreRepository->save($sequestre);

            // Fire EscrowFragmented event
            $this->eventDispatcher->dispatch(new EscrowFragmented(
                $sequestreId,
                $event->materialsAmount,
                $event->laborAmount,
                new DateTime
            ));

            Log::info("Escrow fragmented successfully for sequestre {$sequestreId->getValue()}");

            // Step 3: Generate jeton for materials
            $artisan = $this->userRepository->findById($event->artisanId);
            if (! $artisan) {
                throw new \Exception("Artisan not found: {$event->artisanId->getValue()}");
            }

            // Find nearby suppliers (within 5km)
            $nearbySuppliers = $this->userRepository->findSuppliersNearLocation(
                $artisan->getLocation(),
                5.0
            );

            $jeton = $this->jetonFactory->createJeton($sequestre, $artisan, $nearbySuppliers);
            $this->jetonRepository->save($jeton);

            // Fire JetonGenerated event
            $this->eventDispatcher->dispatch(new JetonGenerated(
                $jeton->getId(),
                $event->artisanId,
                $jeton->getCode(),
                $jeton->getTotalAmount(),
                $jeton->getExpiresAt(),
                new DateTime
            ));

            Log::info("Jeton generated successfully: {$jeton->getCode()}");
        } catch (\Exception $e) {
            Log::error('Failed to handle QuoteAccepted event: '.$e->getMessage());
            throw $e;
        }
    }

    /**
     * Handle MilestoneValidated event - release labor payment
     * Flow: MilestoneValidated → release labor payment
     * Requirements: 6.6
     */
    public function handleMilestoneValidated(MilestoneValidated $event): void
    {
        try {
            Log::info("Processing labor payment release for jalon {$event->jalonId->getValue()}");

            // Find the sequestre for this chantier
            $sequestre = $this->sequestreRepository->findByChantierId($event->chantierId);
            if (! $sequestre) {
                throw new \Exception("Sequestre not found for chantier {$event->chantierId->getValue()}");
            }

            // Release labor payment
            $sequestre->releaseLabor($event->laborAmountToRelease);
            $this->sequestreRepository->save($sequestre);

            // Transfer funds to artisan via mobile money
            $transactionRef = $this->mobileMoneyGateway->transferFunds(
                $sequestre->getClientId(),
                $event->artisanId,
                $event->laborAmountToRelease
            );

            // Fire LaborPaymentReleased event
            $this->eventDispatcher->dispatch(new LaborPaymentReleased(
                $sequestre->getId(),
                $event->artisanId,
                $event->laborAmountToRelease,
                new DateTime
            ));

            Log::info("Labor payment of {$event->laborAmountToRelease->format()} released to artisan {$event->artisanId->getValue()}");
        } catch (\Exception $e) {
            Log::error('Failed to handle MilestoneValidated event: '.$e->getMessage());
            throw $e;
        }
    }

    /**
     * Handle ChantierCompleted event - recalculate artisan score
     * Flow: ChantierCompleted → recalculate score
     * Requirements: 7.1
     */
    public function handleChantierCompleted(ChantierCompleted $event): void
    {
        try {
            Log::info("Recalculating score for artisan {$event->artisanId->getValue()} after chantier completion");

            // Get or create reputation profile
            $reputationProfile = $this->reputationRepository->findByArtisanId($event->artisanId);
            if (! $reputationProfile) {
                // Create new reputation profile if it doesn't exist
                $reputationProfile = new \App\Domain\Reputation\Models\ReputationProfile\ReputationProfile(
                    \App\Domain\Reputation\Models\ValueObjects\ProfileId::generate(),
                    $event->artisanId,
                    new \App\Domain\Reputation\Models\ValueObjects\NZassaScore(500), // Default starting score
                    new DateTime
                );
            }

            // Aggregate current metrics
            $metrics = $this->metricsAggregationService->aggregateMetrics($event->artisanId);

            // Calculate new score
            $oldScore = $reputationProfile->getCurrentScore();
            $reputationProfile->recalculateScore($metrics);
            $newScore = $reputationProfile->getCurrentScore();

            // Save updated profile
            $this->reputationRepository->save($reputationProfile);

            // Fire ScoreRecalculated event
            $this->eventDispatcher->dispatch(new ScoreRecalculated(
                $event->artisanId,
                $oldScore,
                $newScore,
                "Chantier completed: {$event->chantierId->getValue()}",
                new DateTime
            ));

            Log::info("Score recalculated for artisan {$event->artisanId->getValue()}: {$oldScore->getValue()} → {$newScore->getValue()}");
        } catch (\Exception $e) {
            Log::error('Failed to handle ChantierCompleted event: '.$e->getMessage());
            throw $e;
        }
    }

    /**
     * Handle DisputeReported event - freeze funds
     * Flow: DisputeReported → freeze funds
     * Requirements: 9.2
     */
    public function handleDisputeReported(DisputeReported $event): void
    {
        try {
            Log::info("Freezing funds for dispute {$event->litigeId->getValue()}");

            // Find the sequestre for this mission
            $sequestre = $this->sequestreRepository->findByMissionId($event->missionId);
            if (! $sequestre) {
                Log::warning("No sequestre found for mission {$event->missionId->getValue()}, dispute may be for completed project");

                return;
            }

            // Freeze any pending fund releases
            $sequestre->freeze("Dispute reported: {$event->litigeId->getValue()}");
            $this->sequestreRepository->save($sequestre);

            Log::info("Funds frozen successfully for dispute {$event->litigeId->getValue()}");
        } catch (\Exception $e) {
            Log::error('Failed to handle DisputeReported event: '.$e->getMessage());
            throw $e;
        }
    }
}
