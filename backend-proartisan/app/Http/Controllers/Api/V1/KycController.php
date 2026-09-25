                <?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Kyc\UploadKycRequest;
use App\Services\KycService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class KycController extends Controller
{
    public function __construct(private KycService $kycService) {}

    public function uploadCni(UploadKycRequest $request): JsonResponse
    {
        $user = $request->user();
        $doc = $this->kycService->uploadDocument(
            $user,
            'cni',
            $request->file('file')
        );

        $freshUser = $user->fresh();
        $isAutoVerified = (bool) $doc->auto_verified;

        return response()->json([
            'success' => true,
            'message' => $isAutoVerified
                ? 'Pièce d\'identité et selfie vérifiés : votre compte est actif.'
                : 'Pièce d\'identité enregistrée. En attente de validation.',
            'data' => [
                'type' => $doc->type,
                'statut' => $doc->statut,
                'url' => $doc->file_url,
                'auto_verified' => $isAutoVerified,
                'ai_confidence_score' => $doc->ai_confidence_score,
                'ocr_data' => (object) ($doc->ocr_data ?? []),
                'kyc_status' => $freshUser->kyc_status,
            ],
        ]);
    }

    public function uploadSelfie(UploadKycRequest $request): JsonResponse
    {
        $user = $request->user();
        $doc = $this->kycService->uploadDocument(
            $user,
            'selfie',
            $request->file('file')
        );

        $freshUser = $user->fresh();
        $isAutoVerified = (bool) $doc->auto_verified;

        return response()->json([
            'success' => true,
            'message' => $isAutoVerified
                ? 'Selfie vérifié : votre compte est actif.'
                : 'Selfie enregistré. En attente de validation.',
            'data' => [
                'type' => $doc->type,
                'statut' => $doc->statut,
                'url' => $doc->file_url,
                'auto_verified' => $isAutoVerified,
                'face_matched' => (bool) $doc->face_matched,
                'ai_confidence_score' => $doc->ai_confidence_score,
                'kyc_status' => $freshUser->kyc_status,
            ],
        ]);
    }

    public function verifyWithAi(Request $request): JsonResponse
    {
        $result = $this->kycService->processAiVerification($request->user());
        $freshUser = $request->user()->fresh();

        return response()->json([
            'success' => $result['success'],
            'message' => $result['message'],
            'data' => array_merge($result, [
                'kyc_status' => $freshUser->kyc_status,
            ]),
        ], $result['success'] ? 200 : 422);
    }

    public function status(Request $request): JsonResponse
    {
        $status = $this->kycService->getStatus($request->user());

        return response()->json([
            'success' => true,
            'data' => $status,
        ]);
    }
}
