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
        $doc = $this->kycService->uploadDocument(
            $request->user(),
            'cni',
            $request->file('file')
        );

        return response()->json([
            'success' => true,
            'message' => 'CNI uploadée avec succès. En attente de validation.',
            'data'    => [
                'type'   => $doc->type,
                'statut' => $doc->statut,
                'url'    => $doc->file_url,
            ],
        ]);
    }

    public function uploadSelfie(UploadKycRequest $request): JsonResponse
    {
        $doc = $this->kycService->uploadDocument(
            $request->user(),
            'selfie',
            $request->file('file')
        );

        return response()->json([
            'success' => true,
            'message' => 'Selfie uploadé avec succès. En attente de validation.',
            'data'    => [
                'type'   => $doc->type,
                'statut' => $doc->statut,
                'url'    => $doc->file_url,
            ],
        ]);
    }

    public function status(Request $request): JsonResponse
    {
        $status = $this->kycService->getStatus($request->user());

        return response()->json([
            'success' => true,
            'data'    => $status,
        ]);
    }
}
