<?php

namespace App\Http\Controllers\Api\V1\Reputation;

use App\Domain\Identity\Models\ValueObjects\UserId;
use App\Domain\Marketplace\Models\ValueObjects\MissionId;
use App\Domain\Reputation\Models\Rating\Rating;
use App\Domain\Reputation\Models\ValueObjects\RatingValue;
use App\Domain\Reputation\Repositories\RatingRepository;
use App\Domain\Reputation\Repositories\ReputationRepository;
use App\Http\Controllers\Controller;
use App\Http\Requests\Reputation\SubmitRatingRequest;
use App\Http\Resources\Reputation\RatingResource;
use App\Http\Resources\Reputation\ReputationResource;
use App\Http\Resources\Reputation\ScoreHistoryResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ReputationController extends Controller
{
    public function __construct(
        private ReputationRepository $reputationRepository,
        private RatingRepository $ratingRepository
    ) {}

    /**
     * Get artisan reputation profile
     * GET /api/v1/artisans/{id}/reputation
     */
    public function getArtisanReputation(string $artisanId): JsonResponse
    {
        try {
            $userId = UserId::fromString($artisanId);
            $reputation = $this->reputationRepository->findByArtisanId($userId);

            if (!$reputation) {
                return response()->json([
                    'error' => 'REPUTATION_NOT_FOUND',
                    'message' => 'Profil de réputation non trouvé pour cet artisan.',
                    'status_code' => 404
                ], 404);
            }

            return response()->json([
                'data' => new ReputationResource($reputation),
                'message' => 'Profil de réputation récupéré avec succès.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'REPUTATION_FETCH_ERROR',
                'message' => 'Erreur lors de la récupération du profil de réputation.',
                'status_code' => 500
            ], 500);
        }
    }

    /**
     * Get artisan score history
     * GET /api/v1/artisans/{id}/score-history
     */
    public function getScoreHistory(string $artisanId): JsonResponse
    {
        try {
            $userId = UserId::fromString($artisanId);
            $reputation = $this->reputationRepository->findByArtisanId($userId);

            if (!$reputation) {
                return response()->json([
                    'error' => 'REPUTATION_NOT_FOUND',
                    'message' => 'Profil de réputation non trouvé pour cet artisan.',
                    'status_code' => 404
                ], 404);
            }

            $scoreHistory = $reputation->getScoreHistory();

            return response()->json([
                'data' => ScoreHistoryResource::collection($scoreHistory),
                'message' => 'Historique des scores récupéré avec succès.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'SCORE_HISTORY_FETCH_ERROR',
                'message' => 'Erreur lors de la récupération de l\'historique des scores.',
                'status_code' => 500
            ], 500);
        }
    }

    /**
     * Submit rating for a mission
     * POST /api/v1/missions/{id}/rate
     */
    public function submitRating(string $missionId, SubmitRatingRequest $request): JsonResponse
    {
        try {
            $missionIdVO = MissionId::fromString($missionId);
            $clientId = UserId::fromString(auth()->id());

            // Check if rating already exists for this mission
            $existingRating = $this->ratingRepository->findByMissionId($missionIdVO);
            if ($existingRating) {
                return response()->json([
                    'error' => 'RATING_ALREADY_EXISTS',
                    'message' => 'Une note a déjà été soumise pour cette mission.',
                    'status_code' => 409
                ], 409);
            }

            // TODO: Verify that the mission is completed and the client is authorized
            // This would require access to Mission repository to check mission status and client ownership

            // For now, we'll assume the artisan_id is provided in the request
            // In a real implementation, this would be fetched from the mission
            $artisanId = UserId::fromString($request->input('artisan_id'));

            $rating = Rating::create(
                $missionIdVO,
                $clientId,
                $artisanId,
                RatingValue::fromInt($request->validated('rating')),
                $request->validated('comment')
            );

            $this->ratingRepository->save($rating);

            return response()->json([
                'data' => new RatingResource($rating),
                'message' => 'Note soumise avec succès.'
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'RATING_SUBMISSION_ERROR',
                'message' => 'Erreur lors de la soumission de la note.',
                'status_code' => 500
            ], 500);
        }
    }

    /**
     * Get all ratings for an artisan
     * GET /api/v1/artisans/{id}/ratings
     */
    public function getArtisanRatings(string $artisanId, Request $request): AnonymousResourceCollection
    {
        try {
            $userId = UserId::fromString($artisanId);
            $ratings = $this->ratingRepository->findByArtisanId($userId);

            // Apply pagination if requested
            $page = $request->get('page', 1);
            $perPage = $request->get('per_page', 20);
            $offset = ($page - 1) * $perPage;

            $paginatedRatings = array_slice($ratings, $offset, $perPage);

            return RatingResource::collection($paginatedRatings);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'RATINGS_FETCH_ERROR',
                'message' => 'Erreur lors de la récupération des notes.',
                'status_code' => 500
            ], 500);
        }
    }
}
