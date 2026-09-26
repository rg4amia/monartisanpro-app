<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Litige;
use App\Services\LitigeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
                'message' => 'Action non autorisée. Seul un administrateur peut assigner un jury.',
            ], 403);
        }

        if ($litige->isResolved()) {
            return response()->json([
                'success' => false,
                'message' => 'Ce litige est déjà clôturé.',
            ], 422);
        }

        try {
            $this->litigeService->assignJury($litige);
        } catch (\DomainException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Le jury a été assigné avec succès.',
        ]);
    }

    /**
     * Soumet le vote d'un juré.
     */
    public function vote(Request $request, Litige $litige): JsonResponse
    {
        $request->validate([
            'verdict' => ['required', 'string', 'in:CONFORME,NON_CONFORME,RESPONSABILITE_PARTAGEE'],
            'split_artisan_percentage' => ['nullable', 'integer', 'min:0', 'max:100'],
            'technical_comment' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($litige->isResolved()) {
            return response()->json([
                'success' => false,
                'message' => 'Ce litige est déjà clôturé.',
            ], 422);
        }

        $this->litigeService->submitJuryVote(
            $litige,
            $request->user(),
            $request->input('verdict'),
            $request->input('split_artisan_percentage'),
            $request->input('technical_comment')
        );

        return response()->json([
            'success' => true,
            'message' => 'Votre avis d\'arbitrage a été enregistré avec succès. Une indemnité de 5 000 FCFA a été créditée.',
        ]);
    }
}
