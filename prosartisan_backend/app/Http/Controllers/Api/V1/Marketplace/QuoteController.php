<?php

namespace App\Http\Controllers\Api\V1\Marketplace;

use App\Domain\Identity\Models\ValueObjects\UserId;
use App\Domain\Marketplace\Exceptions\MaximumQuotesExceededException;
use App\Domain\Marketplace\Models\Devis\Devis;
use App\Domain\Marketplace\Models\Devis\DevisLine;
use App\Domain\Marketplace\Models\ValueObjects\DevisId;
use App\Domain\Marketplace\Models\ValueObjects\DevisLineType;
use App\Domain\Marketplace\Models\ValueObjects\MissionId;
use App\Domain\Marketplace\Repositories\DevisRepository;
use App\Domain\Marketplace\Repositories\MissionRepository;
use App\Domain\Shared\ValueObjects\MoneyAmount;
use App\Http\Controllers\Controller;
use App\Http\Requests\Marketplace\CreateQuoteRequest;
use App\Http\Requests\Marketplace\AcceptQuoteRequest;
use App\Http\Resources\Marketplace\DevisResource;
use DateTime;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use InvalidArgumentException;

/**
 * API Controller for Quote (Devis) management
 *
 * Handles quote submission and acceptance
 * Requirements: 3.3, 3.4, 3.5, 3.6, 3.7
 */
class QuoteController extends Controller
{
    public function __construct(
        private DevisRepository $devisRepository,
        private MissionRepository $missionRepository
    ) {}

    /**
     * Submit a quote for a mission
     *
     * POST /api/v1/missions/{missionId}/quotes
     * Requirements: 3.3, 3.4
     */
    public function store(string $missionId, CreateQuoteRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $user = $request->user();

        // Verify user is an artisan
        if ($user->user_type !== 'ARTISAN') {
            return response()->json([
                'error' => 'UNAUTHORIZED',
                'message' => 'Seuls les artisans peuvent soumettre des devis'
            ], Response::HTTP_FORBIDDEN);
        }

        // Find the mission
        $mission = $this->missionRepository->findById(MissionId::fromString($missionId));
        if (!$mission) {
            return response()->json([
                'error' => 'MISSION_NOT_FOUND',
                'message' => 'Mission non trouvée'
            ], Response::HTTP_NOT_FOUND);
        }

        // Check if mission can receive more quotes
        if (!$mission->canReceiveMoreQuotes()) {
            return response()->json([
                'error' => 'MAXIMUM_QUOTES_EXCEEDED',
                'message' => 'Cette mission a déjà reçu le nombre maximum de devis (3)'
            ], Response::HTTP_CONFLICT);
        }

        try {
            // Create line items
            $lineItems = [];
            foreach ($validated['line_items'] as $lineData) {
                $lineItems[] = new DevisLine(
                    $lineData['description'],
                    $lineData['quantity'],
                    MoneyAmount::fromCentimes($lineData['unit_price_centimes']),
                    DevisLineType::fromString($lineData['type'])
                );
            }

            // Create devis with 7-day expiration
            $expiresAt = new DateTime('+7 days');
            $devis = Devis::create(
                MissionId::fromString($missionId),
                UserId::fromString($user->id),
                $lineItems,
                $expiresAt
            );

            // Add quote to mission (enforces maximum quotes rule)
            $mission->addQuote($devis);

            // Save both mission and devis
            $this->devisRepository->save($devis);
            $this->missionRepository->save($mission);

            // TODO: Trigger QuoteSubmitted domain event for notifications (Task 19.4)

            return response()->json([
                'message' => 'Devis soumis avec succès',
                'data' => new DevisResource($devis)
            ], Response::HTTP_CREATED);
        } catch (MaximumQuotesExceededException $e) {
            return response()->json([
                'error' => 'MAXIMUM_QUOTES_EXCEEDED',
                'message' => $e->getMessage()
            ], Response::HTTP_CONFLICT);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'error' => 'VALIDATION_ERROR',
                'message' => $e->getMessage()
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Accept a quote
     *
     * POST /api/v1/quotes/{id}/accept
     * Requirements: 3.5, 3.6, 3.7
     */
    public function accept(string $id, AcceptQuoteRequest $request): JsonResponse
    {
        $user = $request->user();

        // Verify user is a client
        if ($user->user_type !== 'CLIENT') {
            return response()->json([
                'error' => 'UNAUTHORIZED',
                'message' => 'Seuls les clients peuvent accepter des devis'
            ], Response::HTTP_FORBIDDEN);
        }

        // Find the devis
        $devis = $this->devisRepository->findById(DevisId::fromString($id));
        if (!$devis) {
            return response()->json([
                'error' => 'QUOTE_NOT_FOUND',
                'message' => 'Devis non trouvé'
            ], Response::HTTP_NOT_FOUND);
        }

        // Find the mission
        $mission = $this->missionRepository->findById($devis->getMissionId());
        if (!$mission) {
            return response()->json([
                'error' => 'MISSION_NOT_FOUND',
                'message' => 'Mission associée non trouvée'
            ], Response::HTTP_NOT_FOUND);
        }

        // Verify client owns the mission
        if ($mission->getClientId()->getValue() !== $user->id) {
            return response()->json([
                'error' => 'UNAUTHORIZED',
                'message' => 'Vous ne pouvez accepter que vos propres devis'
            ], Response::HTTP_FORBIDDEN);
        }

        try {
            // Accept the quote (this will reject all other pending quotes)
            $mission->acceptQuote($devis->getId());

            // Save the updated mission
            $this->missionRepository->save($mission);

            // Reload all quotes to get updated statuses
            $allQuotes = $this->devisRepository->findByMissionId($mission->getId());
            foreach ($allQuotes as $quote) {
                $this->devisRepository->save($quote);
            }

            // TODO: Trigger QuoteAccepted domain event for escrow initiation (Task 8.1)
            // TODO: Reveal exact GPS coordinates to artisan (Requirement 3.5)

            return response()->json([
                'message' => 'Devis accepté avec succès',
                'data' => new DevisResource($devis)
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'error' => 'VALIDATION_ERROR',
                'message' => $e->getMessage()
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Get quotes for a mission
     *
     * GET /api/v1/missions/{missionId}/quotes
     */
    public function index(string $missionId): JsonResponse
    {
        $mission = $this->missionRepository->findById(MissionId::fromString($missionId));
        if (!$mission) {
            return response()->json([
                'error' => 'MISSION_NOT_FOUND',
                'message' => 'Mission non trouvée'
            ], Response::HTTP_NOT_FOUND);
        }

        $quotes = $this->devisRepository->findByMissionId(MissionId::fromString($missionId));

        return response()->json([
            'data' => array_map(fn($quote) => new DevisResource($quote), $quotes)
        ]);
    }
}
