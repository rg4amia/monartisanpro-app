<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Litige;
use App\Services\LitigeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class LitigeJuryController extends Controller
{
    public function __construct(
        private LitigeService $litigeService
    ) {}

    /**
     * Assigne un jury d'artisans à ce litige (Admin uniquement).
     */
    public function assign(Request $request, Litige $litige): JsonResponse
    {
        if ($request->user()->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Action non autorisée. Seul un administrateur peut assigner un jury.'
            ], 403);
        }

        if ($litige->isResolved()) {
            return response()->json([
                'success' => false,
                'message' => 'Ce litige est déjà clôturé.'
            ], 422);
        }

        $this->litigeService->assignJury($litige);

        return response()->json([
            'success' => true,
            'message' => 'Le jury a été assigné avec succès.'
        ]);
    }

    /**
     * Soumet le vote d'un juré.
     */
    public function vote(Request $request, Litige $litige): JsonResponse
    {
        $request->validate([
            'verdict' => ['required', 'string', 'in:CONFORME,NON_CONFORME'],
        ]);

        if ($litige->isResolved()) {
            return response()->json([
                'success' => false,
                'message' => 'Ce litige est déjà clôturé.'
            ], 422);
        }

        $this->litigeService->submitJuryVote($litige, $request->user(), $request->input('verdict'));

        return response()->json([
            'success' => true,
            'message' => 'Votre vote a été enregistré avec succès.'
        ]);
    }
}
