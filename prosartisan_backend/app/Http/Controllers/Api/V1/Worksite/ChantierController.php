<?php

namespace App\Http\Controllers\Api\V1\Worksite;

use App\Domain\Identity\Models\ValueObjects\UserId;
use App\Domain\Marketplace\Models\ValueObjects\MissionId;
use App\Domain\Shared\ValueObjects\MoneyAmount;
use App\Domain\Worksite\Models\Chantier\Chantier;
use App\Domain\Worksite\Models\Jalon\Jalon;
use App\Domain\Worksite\Models\ValueObjects\ChantierId;
use App\Domain\Worksite\Repositories\ChantierRepository;
use App\Http\Controllers\Controller;
use App\Http\Requests\Worksite\CreateChantierRequest;
use App\Http\Resources\Worksite\ChantierResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * API Controller for Chantier (Worksite) management
 *
 * Handles chantier creation and retrieval
 * Requirements: 6.1, 6.2, 6.3
 */
class ChantierController extends Controller
{
    private ChantierRepository $chantierRepository;

    public function __construct(ChantierRepository $chantierRepository)
    {
        $this->chantierRepository = $chantierRepository;
    }

    /**
     * Create a new chantier after escrow is established
     *
     * POST /api/v1/chantiers
     * Requirement 6.1: Start chantier after escrow
     */
    public function store(CreateChantierRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();

            $missionId = MissionId::fromString($validated['mission_id']);
            $clientId = UserId::fromString($validated['client_id']);
            $artisanId = UserId::fromString($validated['artisan_id']);

            // Check if chantier already exists for this mission
            $existingChantier = $this->chantierRepository->findByMissionId($missionId);
            if ($existingChantier) {
                return response()->json([
                    'error' => 'CHANTIER_ALREADY_EXISTS',
                    'message' => 'Un chantier existe déjà pour cette mission',
                    'status_code' => 409,
                ], 409);
            }

            // Create new chantier
            $chantier = Chantier::create($missionId, $clientId, $artisanId);

            // Add milestones if provided
            if (isset($validated['milestones'])) {
                foreach ($validated['milestones'] as $milestoneData) {
                    $milestone = Jalon::create(
                        $chantier->getId(),
                        $milestoneData['description'],
                        MoneyAmount::fromCentimes($milestoneData['labor_amount_centimes']),
                        $milestoneData['sequence_number']
                    );
                    $chantier->addMilestone($milestone);
                }
            }

            $this->chantierRepository->save($chantier);

            Log::info('Chantier created', [
                'chantier_id' => $chantier->getId()->getValue(),
                'mission_id' => $missionId->getValue(),
                'artisan_id' => $artisanId->getValue(),
                'milestones_count' => count($chantier->getAllMilestones()),
            ]);

            return response()->json([
                'message' => 'Chantier créé avec succès',
                'data' => new ChantierResource($chantier),
            ], 201);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'error' => 'INVALID_DATA',
                'message' => $e->getMessage(),
                'status_code' => 400,
            ], 400);
        } catch (\Exception $e) {
            Log::error('Failed to create chantier', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => 'CHANTIER_CREATION_FAILED',
                'message' => 'Erreur lors de la création du chantier',
                'status_code' => 500,
            ], 500);
        }
    }

    /**
     * Get chantier details by ID
     *
     * GET /api/v1/chantiers/{id}
     * Requirement 6.1: View chantier details
     */
    public function show(string $id): JsonResponse
    {
        try {
            $chantierId = ChantierId::fromString($id);
            $chantier = $this->chantierRepository->findById($chantierId);

            if (! $chantier) {
                return response()->json([
                    'error' => 'CHANTIER_NOT_FOUND',
                    'message' => 'Chantier non trouvé',
                    'status_code' => 404,
                ], 404);
            }

            // Check if user has access to this chantier
            $currentUserId = Auth::id();
            if (
                $currentUserId !== $chantier->getClientId()->getValue() &&
                $currentUserId !== $chantier->getArtisanId()->getValue()
            ) {
                return response()->json([
                    'error' => 'ACCESS_DENIED',
                    'message' => 'Accès refusé à ce chantier',
                    'status_code' => 403,
                ], 403);
            }

            return response()->json([
                'data' => new ChantierResource($chantier),
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'error' => 'INVALID_CHANTIER_ID',
                'message' => 'ID de chantier invalide',
                'status_code' => 400,
            ], 400);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve chantier', [
                'chantier_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'CHANTIER_RETRIEVAL_FAILED',
                'message' => 'Erreur lors de la récupération du chantier',
                'status_code' => 500,
            ], 500);
        }
    }

    /**
     * Get chantiers for current user
     *
     * GET /api/v1/chantiers
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $currentUserId = UserId::fromString(Auth::id());
            $userType = $request->query('type', 'all'); // 'client', 'artisan', or 'all'

            $chantiers = [];

            if ($userType === 'client' || $userType === 'all') {
                $clientChantiers = $this->chantierRepository->findByClient($currentUserId);
                $chantiers = array_merge($chantiers, $clientChantiers);
            }

            if ($userType === 'artisan' || $userType === 'all') {
                $artisanChantiers = $this->chantierRepository->findByArtisan($currentUserId);
                $chantiers = array_merge($chantiers, $artisanChantiers);
            }

            // Remove duplicates and sort by started_at desc
            $uniqueChantiers = [];
            $seenIds = [];

            foreach ($chantiers as $chantier) {
                $id = $chantier->getId()->getValue();
                if (! in_array($id, $seenIds)) {
                    $uniqueChantiers[] = $chantier;
                    $seenIds[] = $id;
                }
            }

            // Sort by started date descending
            usort($uniqueChantiers, function ($a, $b) {
                return $b->getStartedAt() <=> $a->getStartedAt();
            });

            return response()->json([
                'data' => ChantierResource::collection($uniqueChantiers),
                'meta' => [
                    'total' => count($uniqueChantiers),
                    'user_type_filter' => $userType,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve chantiers', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'CHANTIERS_RETRIEVAL_FAILED',
                'message' => 'Erreur lors de la récupération des chantiers',
                'status_code' => 500,
            ], 500);
        }
    }
}
