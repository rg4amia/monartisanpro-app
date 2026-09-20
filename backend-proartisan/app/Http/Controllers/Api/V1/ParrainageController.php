<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\ParrainageException;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreParrainageRequest;
use App\Models\Parrainage;
use App\Services\ParrainageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ParrainageController extends Controller
{
    /**
     * Enregistre un parrainage d'un apprenti (filleul) par un Maître Artisan (parrain).
     * Si le filleul n'a pas encore de compte, crée une invitation en attente
     * et lui envoie un SMS (voir ParrainageService::parrainer()).
     */
    public function store(StoreParrainageRequest $request, ParrainageService $service): JsonResponse
    {
        try {
            $parrainage = $service->parrainer(
                $request->user(),
                $request->input('filleul_phone'),
                $request->input('filleul_nom')
            );
        } catch (ParrainageException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $e->getStatus());
        }

        return response()->json([
            'success' => true,
            'message' => 'Parrainage enregistré avec succès.',
            'data' => $parrainage,
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
            'data' => $filleuls,
        ]);
    }
}
