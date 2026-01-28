<?php

namespace App\Application\Handlers;

use App\Domain\Financial\Events\LaborPaymentReleased;
use App\Domain\Identity\Repositories\UserRepository;
use App\Domain\Marketplace\Events\MissionCreated;
use App\Domain\Marketplace\Events\QuoteSubmitted;
use App\Domain\Shared\Services\NotificationService;
use App\Domain\Worksite\Events\MilestoneProofSubmitted;
use Illuminate\Support\Facades\Log;

/**
 * Event handler for sending notifications based on domain events
 */
class NotificationEventHandler
{
    public function __construct(
        private NotificationService $notificationService,
        private UserRepository $userRepository
    ) {}

    /**
     * Handle MissionCreated event - notify nearby artisans
     * Requirements: 11.1
     */
    public function handleMissionCreated(MissionCreated $event): void
    {
        try {
            Log::info("Handling MissionCreated event for mission {$event->missionId->getValue()}");

            // Find nearby artisans within 5km
            $nearbyArtisans = $this->userRepository->findArtisansNearLocation(
                $event->location,
                5.0 // 5km radius
            );

            // Filter by trade category
            $relevantArtisans = array_filter($nearbyArtisans, function ($artisan) use ($event) {
                return $artisan->getTradeCategory()->equals($event->category) &&
                 $artisan->canAcceptMissions();
            });

            if (empty($relevantArtisans)) {
                Log::info("No relevant artisans found for mission {$event->missionId->getValue()}");

                return;
            }

            $title = 'Nouvelle mission disponible';
            $message = "Une nouvelle mission {$event->category->toString()} est disponible près de vous: {$event->description}";

            // Send notifications to individual artisans
            $userIds = array_map(fn ($artisan) => $artisan->getId(), $relevantArtisans);
            $results = $this->notificationService->sendToMultiple($userIds, $title, $message, [
                'type' => 'mission_created',
                'mission_id' => $event->missionId->getValue(),
                'category' => $event->category->toString(),
            ]);

            // Also send to topic for the trade category
            $topicName = 'artisan_'.strtolower($event->category->toString());
            $this->notificationService->sendToTopic($topicName, $title, $message, [
                'type' => 'mission_created',
                'mission_id' => $event->missionId->getValue(),
                'category' => $event->category->toString(),
            ]);

            $successCount = count(array_filter($results));
            Log::info("Sent mission notifications to {$successCount} out of ".count($relevantArtisans).' artisans');
        } catch (\Exception $e) {
            Log::error('Failed to handle MissionCreated event: '.$e->getMessage());
        }
    }

    /**
     * Handle QuoteSubmitted event - notify client
     * Requirements: 11.2
     */
    public function handleQuoteSubmitted(QuoteSubmitted $event): void
    {
        try {
            Log::info("Handling QuoteSubmitted event for quote {$event->devisId->getValue()}");

            $artisan = $this->userRepository->findById($event->artisanId);
            if (! $artisan) {
                Log::warning("Artisan not found for quote {$event->devisId->getValue()}");

                return;
            }

            $title = 'Nouveau devis reçu';
            $message = "Vous avez reçu un nouveau devis de {$artisan->getEmail()->getValue()} pour un montant de {$event->totalAmount->format()}";

            $success = $this->notificationService->send($event->clientId, $title, $message, [
                'type' => 'quote_submitted',
                'quote_id' => $event->devisId->getValue(),
                'mission_id' => $event->missionId->getValue(),
                'artisan_id' => $event->artisanId->getValue(),
                'amount' => $event->totalAmount->toFloat(),
            ]);

            if ($success) {
                Log::info("Quote notification sent successfully to client {$event->clientId->getValue()}");
            } else {
                Log::error("Failed to send quote notification to client {$event->clientId->getValue()}");
            }
        } catch (\Exception $e) {
            Log::error('Failed to handle QuoteSubmitted event: '.$e->getMessage());
        }
    }

    /**
     * Handle MilestoneProofSubmitted event - notify client
     * Requirements: 11.3
     */
    public function handleMilestoneProofSubmitted(MilestoneProofSubmitted $event): void
    {
        try {
            Log::info("Handling MilestoneProofSubmitted event for jalon {$event->jalonId->getValue()}");

            $artisan = $this->userRepository->findById($event->artisanId);
            if (! $artisan) {
                Log::warning("Artisan not found for jalon {$event->jalonId->getValue()}");

                return;
            }

            $title = 'Preuve de livraison soumise';
            $message = "L'artisan {$artisan->getEmail()->getValue()} a soumis une preuve de livraison pour un jalon. Vous avez 48h pour valider ou contester.";

            // Use multi-channel notification for important milestone updates
            $success = $this->notificationService->send($event->clientId, $title, $message, [
                'type' => 'milestone_proof_submitted',
                'jalon_id' => $event->jalonId->getValue(),
                'chantier_id' => $event->chantierId->getValue(),
                'artisan_id' => $event->artisanId->getValue(),
                'photo_url' => $event->photoUrl,
                'deadline_hours' => 48,
            ]);

            if ($success) {
                Log::info("Milestone proof notification sent successfully to client {$event->clientId->getValue()}");
            } else {
                Log::error("Failed to send milestone proof notification to client {$event->clientId->getValue()}");
            }
        } catch (\Exception $e) {
            Log::error('Failed to handle MilestoneProofSubmitted event: '.$e->getMessage());
        }
    }

    /**
     * Handle LaborPaymentReleased event - notify artisan
     * Requirements: 11.4
     */
    public function handleLaborPaymentReleased(LaborPaymentReleased $event): void
    {
        try {
            Log::info("Handling LaborPaymentReleased event for sequestre {$event->sequestreId->getValue()}");

            $title = 'Paiement libéré';
            $message = "Votre paiement de {$event->amount->format()} a été libéré et transféré vers votre compte mobile money.";

            $success = $this->notificationService->send($event->artisanId, $title, $message, [
                'type' => 'labor_payment_released',
                'sequestre_id' => $event->sequestreId->getValue(),
                'amount' => $event->amount->toFloat(),
                'currency' => 'XOF',
            ]);

            if ($success) {
                Log::info("Payment notification sent successfully to artisan {$event->artisanId->getValue()}");
            } else {
                Log::error("Failed to send payment notification to artisan {$event->artisanId->getValue()}");
            }
        } catch (\Exception $e) {
            Log::error('Failed to handle LaborPaymentReleased event: '.$e->getMessage());
        }
    }
}
