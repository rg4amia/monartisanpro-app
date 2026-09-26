<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\EvidenceVault;
use App\Services\EvidenceVaultService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EvidenceVaultController extends Controller
{
    public function __construct(
        private EvidenceVaultService $vaultService
    ) {}

    /**
     * Génère et retourne le certificat d'intégrité cryptographique SHA-256 pour une pièce scellée.
     */
    public function certificate(int $id): JsonResponse
    {
        $vault = EvidenceVault::with(['mission', 'litige', 'uploader'])->findOrFail($id);

        $certificate = $this->vaultService->generateCertificate($vault);

        return response()->json([
            'success' => true,
            'data' => $certificate,
        ]);
    }

    /**
     * Vérification d'intégrité en temps réel d'une pièce du coffre-fort.
     */
    public function verify(int $id): JsonResponse
    {
        $vault = EvidenceVault::findOrFail($id);

        $isValid = $this->vaultService->verifyIntegrity($vault);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $vault->id,
                'sha256_hash' => $vault->sha256_hash,
                'is_valid' => $isValid,
                'is_tampered' => $vault->is_tampered,
                'verified_at' => now()->toIso8601String(),
            ],
        ]);
    }
}
