<?php

namespace App\Http\Controllers\Api\V1\Marketplace;

use App\Domain\Identity\Models\ValueObjects\TradeCategory;
use App\Domain\Identity\Models\ValueObjects\UserId;
use App\Domain\Marketplace\Models\Mission\Mission;
use App\Domain\Marketplace\Models\ValueObjects\MissionId;
use App\Domain\Marketplace\Repositories\MissionRepository;
use App\Domain\Marketplace\Services\ArtisanSearchService;
use App\Domain\Marketplace\Services\LocationPrivacyService;
use App\Domain\Shared\ValueObjects\GPS_Coordinates;
use App\Domain\Shared\ValueObjects\MoneyAmount;
use App\Http\Controllers\Controller;
use App\Http\Requests\Marketplace\CreateMissionRequest;
use App\Http\Resources\Marketplace\MissionResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * API Controller for Mission management
 *
 * Handles mission creation, listing, and retrieval
 * Requirements: 3.1, 3.2
 */
class MissionController extends Controller
{
    public function __construct(
        private MissionRepository $missionRepository,
        private ArtisanSearchService $artisanSearchService,
        private LocationPrivacyService $locationPrivacyService
    ) {}

    /**
     * Create a new mission
     *
     * POST /api/v1/missions
     * Requirement 3.1: Mission creation with description, category, location, budget range
     */
    public function store(CreateMissionRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $mission = Mission::create(
            UserId::fromString($request->user()->id),
            $validated['description'],
            TradeCategory::fromString($validated['category']),
            new GPS_Coordinates($validated['latitude'], $validated['longitude']),
            MoneyAmount::fromCentimes($validated['budget_min_centimes']),
            MoneyAmount::fromCentimes($validated['budget_max_centimes'])
        );

        $this->missionRepository->save($mission);

        // TODO: Trigger MissionCreated domain event for notifications (Task 19.4)

        return response()->json([
            'message' => 'Mission créée avec succès',
            'data' => new MissionResource($mission)
        ], Response::HTTP_CREATED);
    }

    /**
     * List missions with pagination
     *
     * GET /api/v1/missions
     * Requirement 17.2: Paginate results with 20 items per page
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        // Clients see their own missions, Artisans see nearby open missions
        if ($user->user_type === 'CLIENT') {
            $missions = $this->missionRepository->findByClientId(UserId::fromString($user->id));
        } else {
            // For artisans, find nearby open missions
            // TODO: Get artisan location from profile
            $location = new GPS_Coordinates(5.3600, -4.0083); // Default to Abidjan for now
            $missions = $this->missionRepository->findOpenMissionsNearLocation($location, 10.0);

            // Blur GPS coordinates for privacy (Requirement 2.4)
            foreach ($missions as $mission) {
                $blurredLocation = $this->locationPrivacyService->blurCoordinates($mission->getLocation());
                // TODO: Apply blurred location to mission (requires mission update method)
            }
        }

        // Simple pagination (first 20 items)
        $paginatedMissions = array_slice($missions, 0, 20);

        return response()->json([
            'data' => array_map(fn($mission) => new MissionResource($mission), $paginatedMissions),
            'meta' => [
                'total' => count($missions),
                'per_page' => 20,
                'current_page' => 1,
                'last_page' => ceil(count($missions) / 20)
            ]
        ]);
    }

    /**
     * Get a specific mission
     *
     * GET /api/v1/missions/{id}
     */
    public function show(string $id, Request $request): JsonResponse
    {
        $mission = $this->missionRepository->findById(MissionId::fromString($id));

        if (!$mission) {
            return response()->json([
                'error' => 'MISSION_NOT_FOUND',
                'message' => 'Mission non trouvée'
            ], Response::HTTP_NOT_FOUND);
        }

        $user = $request->user();

        // Check authorization
        if ($user->user_type === 'CLIENT' && $mission->getClientId()->getValue() !== $user->id) {
            return response()->json([
                'error' => 'UNAUTHORIZED',
                'message' => 'Accès non autorisé à cette mission'
            ], Response::HTTP_FORBIDDEN);
        }

        // For artisans, blur location unless they have accepted quote
        if ($user->user_type === 'ARTISAN') {
            try {
                // Try to reveal exact location (will throw exception if not authorized)
                $exactLocation = $this->locationPrivacyService->revealExactLocation(
                    $mission->getId(),
                    UserId::fromString($user->id)
                );
                // If successful, artisan can see exact location
            } catch (\InvalidArgumentException $e) {
                // Artisan not authorized, blur the location
                $blurredLocation = $this->locationPrivacyService->blurCoordinates($mission->getLocation());
                // TODO: Apply blurred location to mission response
            }
        }

        return response()->json([
            'data' => new MissionResource($mission)
        ]);
    }
}
