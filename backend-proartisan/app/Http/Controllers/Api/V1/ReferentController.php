<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Mission;
use App\Services\WalletService;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ReferentController extends Controller
{
    public function __construct(
        private WalletService $walletService,
        private NotificationService $notificationService
    ) {}

    /**
     * Référent valide physiquement la mission sur site.
     * POST /api/v1/missions/{mission}/referent-validate
     */
    public function validateMission(Request $request, Mission $mission): JsonResponse
    {
        $user = $request->user();

        if ($user->role !== 'referent') {
            return response()->json([
                'success' => false,
                'message' => 'Seul un référent de zone peut valider une mission.',
            ], 403);
        }

        if (! $mission->referent_required) {
            return response()->json([
                'success' => false,
                'message' => 'Cette mission ne nécessite pas de validation référent.',
            ], 422);
        }

        $data = $request->validate([
            'latitude'  => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'photos'    => ['required', 'array', 'min:2'],
            'photos.*'  => ['file', 'image', 'max:5120'],
            'notes'     => ['nullable', 'string', 'max:1000'],
        ]);

        // TODO: Vérifier que le référent est dans la zone géographique de la mission (ST_Distance_Sphere)

        // Libérer tous les jalons validés en attente de paiement
        $jalonsEnAttente = $mission->jalons()
            ->where('statut', 'valide')
            ->whereNull('paye_at')
            ->get();

        foreach ($jalonsEnAttente as $jalon) {
            $this->walletService->releaseJalon($jalon);
        }

        $mission->update([
            'referent_required' => false,
            'referent_validated_at' => now(),
            'referent_validated_by' => $user->id,
        ]);

        $this->notificationService->send(
            $mission->artisan,
            'validation',
            'Mission validée par le référent',
            "La mission #{$mission->id} a été validée sur site par le référent. Les paiements en attente ont été libérés.",
            ['mission_id' => $mission->id]
        );

        return response()->json([
            'success' => true,
            'message' => 'Mission validée. Paiements libérés.',
            'data' => [
                'mission_id' => $mission->id,
                'jalons_liberes' => $jalonsEnAttente->count(),
            ],
        ]);
    }
}
