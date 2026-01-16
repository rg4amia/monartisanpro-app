<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Domain\Identity\Models\ValueObjects\KYCDocuments;
use App\Domain\Identity\Models\ValueObjects\UserId;
use App\Domain\Identity\Repositories\UserRepository;
use App\Domain\Identity\Services\KYCVerificationService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\UploadKYCRequest;
use App\Http\Resources\User\ArtisanResource;
use App\Http\Resources\User\FournisseurResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * KYC Controller
 *
 * Handles KYC document upload and verification
 *
 * Requirements: 1.2
 */
class KYCController extends Controller
{
 public function __construct(
  private UserRepository $userRepository,
  private KYCVerificationService $kycService
 ) {}

 /**
  * Upload KYC documents
  *
  * POST /api/v1/users/{id}/kyc
  *
  * Uploads and verifies KYC documents for Artisan or Fournisseur
  * Requires authentication
  *
  * @param string $id User ID
  * @param UploadKYCRequest $request
  * @return JsonResponse
  */
 public function uploadKYC(string $id, UploadKYCRequest $request): JsonResponse
 {
  try {
   $validated = $request->validated();

   // Find user
   $userId = UserId::fromString($id);
   $user = $this->userRepository->findById($userId);

   if ($user === null) {
    return response()->json([
     'error' => 'USER_NOT_FOUND',
     'message' => 'Utilisateur non trouvé',
     'status_code' => 404,
    ], 404);
   }

   // Check if user is Artisan or Fournisseur
   $userType = $user->getType()->getValue();
   if (!in_array($userType, ['ARTISAN', 'FOURNISSEUR'])) {
    return response()->json([
     'error' => 'INVALID_USER_TYPE',
     'message' => 'La vérification KYC est requise uniquement pour les artisans et fournisseurs',
     'status_code' => 400,
    ], 400);
   }

   // Upload files to storage
   $idDocumentPath = $this->uploadFile($request->file('id_document'), 'kyc/documents');
   $selfiePath = $this->uploadFile($request->file('selfie'), 'kyc/selfies');

   // Create KYC documents value object
   $kycDocuments = new KYCDocuments(
    idType: $validated['id_type'],
    idNumber: $validated['id_number'],
    idDocumentUrl: Storage::url($idDocumentPath),
    selfieUrl: Storage::url($selfiePath),
    submittedAt: new \DateTime()
   );

   // Verify KYC documents
   $verificationResult = $this->kycService->verifyDocuments($kycDocuments);

   // Update user with KYC documents
   $user->verifyKYC($kycDocuments);

   // Save user
   $this->userRepository->save($user);

   // Return appropriate resource based on user type
   $resource = match ($userType) {
    'ARTISAN' => new ArtisanResource($user),
    'FOURNISSEUR' => new FournisseurResource($user),
   };

   return response()->json([
    'message' => 'Documents KYC soumis avec succès',
    'data' => $resource,
    'verification_status' => $verificationResult->isApproved() ? 'APPROVED' : 'PENDING',
   ], 200);
  } catch (\InvalidArgumentException $e) {
   return response()->json([
    'error' => 'VALIDATION_ERROR',
    'message' => $e->getMessage(),
    'status_code' => 400,
   ], 400);
  } catch (\Exception $e) {
   Log::error('KYC upload error', [
    'user_id' => $id,
    'error' => $e->getMessage(),
    'trace' => $e->getTraceAsString(),
   ]);

   return response()->json([
    'error' => 'KYC_UPLOAD_FAILED',
    'message' => 'Une erreur est survenue lors du téléchargement des documents KYC',
    'status_code' => 500,
   ], 500);
  }
 }

 /**
  * Upload a file to storage
  *
  * @param \Illuminate\Http\UploadedFile $file
  * @param string $directory
  * @return string File path
  */
 private function uploadFile($file, string $directory): string
 {
  // Generate unique filename
  $filename = uniqid() . '_' . time() . '.' . $file->getClientOriginalExtension();

  // Store file
  $path = $file->storeAs($directory, $filename, 'public');

  return $path;
 }
}
