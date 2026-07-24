<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Parrainage;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ParrainageController extends Controller
{
    /**
     * Enregistre un parrainage d'un apprenti (filleul) par un Maître Artisan (parrain).
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'filleul_phone' => ['required', 'string', 'regex:/^\+225[0-9]{10}$/'],
        ]);

        $parrain = $request->user();

        // RÈGLE : Seul un artisan peut être parrain
        if (!$parrain->isArtisan()) {
            return response()->json([
                'success' => false,
                'message' => 'Action non autorisée. Seuls les artisans peuvent être parrains.'
            ], 403);
        }

        // RÈGLE : Score ProsArtisan > 800 requis
        if ($parrain->score_prosartisan <= 800) {
            return response()->json([
                'success' => false,
                'message' => 'Score ProsArtisan insuffisant pour parrainer (minimum 800).'
            ], 422);
        }

        $filleul = User::where('phone', $request->input('filleul_phone'))->first();

        if (!$filleul) {
            return response()->json([
                'success' => false,
                'message' => 'Aucun artisan trouvé avec ce numéro de téléphone.'
            ], 404);
        }

        // RÈGLE : Le filleul doit être un artisan
        if (!$filleul->isArtisan()) {
            return response()->json([
                'success' => false,
                'message' => 'Le filleul coopté doit avoir le rôle d\'artisan.'
            ], 422);
        }

        // RÈGLE : Ne pas se parrainer soi-même
        if ($filleul->id === $parrain->id) {
            return response()->json([
                'success' => false,
                'message' => 'Vous ne pouvez pas vous parrainer vous-même.'
            ], 422);
        }

        // RÈGLE : Le filleul ne doit pas déjà avoir un parrain
        $exists = Parrainage::where('filleul_id', $filleul->id)->exists();
        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => 'Cet artisan a déjà un parrain.'
            ], 422);
        }

        $parrainage = Parrainage::create([
            'parrain_id' => $parrain->id,
            'filleul_id' => $filleul->id,
            'score_caution' => 0,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Parrainage enregistré avec succès.',
            'data' => $parrainage->load('filleul')
        ], 201);
    }

    /**
     * Liste les apprentis (filleuls) parrainés par l'artisan connecté.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $filleuls = Parrainage::where('parrain_id', $user->id)
            ->with('filleul')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $filleuls
        ]);
    }
}
