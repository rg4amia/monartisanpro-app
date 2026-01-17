<?php

namespace App\Http\Controllers\Api\V1\Worksite;

use App\Domain\Shared\ValueObjects\GPS_Coordinates;
use App\Domain\Worksite\Models\ValueObjects\JalonId;
use App\Domain\Worksite\Models\ValueObjects\ProofOfDelivery;
use App\Domain\Worksite\Repositories\JalonRepository;
use App\Http\Controllers\Controller;
use App\Http\Requests\Worksite\ContestJalonRequest;
use App\Http\Requests\Worksite\SubmitProofRequest;
use App\Http\Requests\Worksite\ValidateJalonRequest;
use App\Http\Resources\Worksite\JalonResource;
use DateTime;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * API Controller for Jalon (Milestone) management
 *
 * Handles milestone proof submission, validation, and contestation
 * Requirements: 6.2, 6.3
 */
class JalonController extends Controller
{
    private JalonRepository $jalonRepository;

    public function __construct(JalonRepository $jalonRepository)
    {
        $this->jalonRepository = $jalonRepository;
    }

    /**
     * Submit proof of delivery for a milestone
     *
     * POST /api/v1/jalons/{id}/submit-proof
     * Requirement 6.2: Submit GPS-tagged photo proof
     */
    public function submitProof(string $id, SubmitProofRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();

            $jalonId = JalonId::fromString($id);
            $jalon = $this->jalonRepository->findById($jalonId);

            if (!$jalon) {
                return response()->json([
                    'error' => 'JALON_NOT_FOUND',
                    'message' => 'Jalon non trouvé',
                    'status_code' => 404
                ], 404);
            }

            // Check if current user is the artisan for this jalon's chantier
            // This would require loading the chantier, but for now we'll assume authorization is handled by middleware

            // Handle photo upload
            $photoFile = $request->file('photo');
            $photoPath = $photoFile->store('jalons/proofs', 'public');
            $photoUrl = Storage::url($photoPath);

            // Extract GPS coordinates
            $gpsCoordinates = new GPS_Coordinates(
                (float) $validated['latitude'],
                (float) $validated['longitude'],
                (float) ($validated['accuracy'] ?? 10.0)
            );

            // Extract EXIF data if provided
            $exifData = $validated['exif_data'] ?? [];

            // Create proof of delivery
            $proof = new ProofOfDelivery(
                $photoUrl,
                $gpsCoordinates,
                new DateTime($validated['captured_at'] ?? 'now'),
                $exifData
            );

            // Submit proof to jalon
            $jalon->submitProof($proof);
            $this->jalonRepository->save($jalon);

            Log::info('Jalon proof submitted', [
                'jalon_id' => $jalonId->getValue(),
                'photo_url' => $photoUrl,
                'gps_coordinates' => [
                    'latitude' => $gpsCoordinates->getLatitude(),
                    'longitude' => $gpsCoordinates->getLongitude(),
                    'accuracy' => $gpsCoordinates->getAccuracy()
                ],
                'auto_validation_deadline' => $jalon->getAutoValidationDeadline()?->format('Y-m-d H:i:s')
            ]);

            return response()->json([
                'message' => 'Preuve de livraison soumise avec succès',
                'data' => new JalonResource($jalon)
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'error' => 'INVALID_PROOF_DATA',
                'message' => $e->getMessage(),
                'status_code' => 400
            ], 400);
        } catch (\Exception $e) {
            Log::error('Failed to submit jalon proof', [
                'jalon_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'error' => 'PROOF_SUBMISSION_FAILED',
                'message' => 'Erreur lors de la soumission de la preuve',
                'status_code' => 500
            ], 500);
        }
    }

    /**
     * Validate a milestone
     *
     * POST /api/v1/jalons/{id}/validate
     * Requirement 6.3: Client validation of milestone
     */
    public function validate(string $id, ValidateJalonRequest $request): JsonResponse
    {
        try {
            $jalonId = JalonId::fromString($id);
            $jalon = $this->jalonRepository->findById($jalonId);

            if (!$jalon) {
                return response()->json([
                    'error' => 'JALON_NOT_FOUND',
                    'message' => 'Jalon non trouvé',
                    'status_code' => 404
                ], 404);
            }

            // Check if current user is the client for this jalon's chantier
            // This would require loading the chantier, but for now we'll assume authorization is handled by middleware

            // Validate the jalon
            $jalon->validate();
            $this->jalonRepository->save($jalon);

            Log::info('Jalon validated', [
                'jalon_id' => $jalonId->getValue(),
                'validated_by' => Auth::id(),
                'validated_at' => $jalon->getValidatedAt()?->format('Y-m-d H:i:s')
            ]);

            return response()->json([
                'message' => 'Jalon validé avec succès',
                'data' => new JalonResource($jalon)
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'error' => 'VALIDATION_ERROR',
                'message' => $e->getMessage(),
                'status_code' => 400
            ], 400);
        } catch (\Exception $e) {
            Log::error('Failed to validate jalon', [
                'jalon_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'error' => 'VALIDATION_FAILED',
                'message' => 'Erreur lors de la validation du jalon',
                'status_code' => 500
            ], 500);
        }
    }

    /**
     * Contest a milestone
     *
     * POST /api/v1/jalons/{id}/contest
     * Requirement 6.3: Client can contest milestone within 48 hours
     */
    public function contest(string $id, ContestJalonRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();

            $jalonId = JalonId::fromString($id);
            $jalon = $this->jalonRepository->findById($jalonId);

            if (!$jalon) {
                return response()->json([
                    'error' => 'JALON_NOT_FOUND',
                    'message' => 'Jalon non trouvé',
                    'status_code' => 404
                ], 404);
            }

            // Check if current user is the client for this jalon's chantier
            // This would require loading the chantier, but for now we'll assume authorization is handled by middleware

            // Contest the jalon
            $jalon->contest($validated['reason']);
            $this->jalonRepository->save($jalon);

            Log::info('Jalon contested', [
                'jalon_id' => $jalonId->getValue(),
                'contested_by' => Auth::id(),
                'reason' => $validated['reason']
            ]);

            return response()->json([
                'message' => 'Jalon contesté avec succès',
                'data' => new JalonResource($jalon)
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'error' => 'CONTEST_ERROR',
                'message' => $e->getMessage(),
                'status_code' => 400
            ], 400);
        } catch (\Exception $e) {
            Log::error('Failed to contest jalon', [
                'jalon_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'error' => 'CONTEST_FAILED',
                'message' => 'Erreur lors de la contestation du jalon',
                'status_code' => 500
            ], 500);
        }
    }

    /**
     * Get jalon details by ID
     *
     * GET /api/v1/jalons/{id}
     */
    public function show(string $id): JsonResponse
    {
        try {
            $jalonId = JalonId::fromString($id);
            $jalon = $this->jalonRepository->findById($jalonId);

            if (!$jalon) {
                return response()->json([
                    'error' => 'JALON_NOT_FOUND',
                    'message' => 'Jalon non trouvé',
                    'status_code' => 404
                ], 404);
            }

            // Authorization check would be done here in a real implementation

            return response()->json([
                'data' => new JalonResource($jalon)
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'error' => 'INVALID_JALON_ID',
                'message' => 'ID de jalon invalide',
                'status_code' => 400
            ], 400);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve jalon', [
                'jalon_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'error' => 'JALON_RETRIEVAL_FAILED',
                'message' => 'Erreur lors de la récupération du jalon',
                'status_code' => 500
            ], 500);
        }
    }
}
