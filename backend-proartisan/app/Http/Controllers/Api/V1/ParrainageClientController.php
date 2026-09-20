<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\ParrainageClientException;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreParrainageClientRequest;
use App\Models\ParrainageClient;
use App\Services\ParrainageClientService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ParrainageClientController extends Controller
{
    /**
     * Enregistre un parrainage d'un client (filleul) par un autre client (parrain).
     * Si le filleul n'a pas encore de compte, crée une invitation en attente
     * et lui envoie un SMS (voir ParrainageClientService::parrainer()).
     */
    public function store(StoreParrainageClientRequest $request, ParrainageClientService $service): JsonResponse
    {
        try {
            $parrainage = $service->parrainer(
                $request->user(),
                $request->input('filleul_phone'),
                $request->input('filleul_nom')
            );
        } catch (ParrainageClientException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $e->getStatus());
        }

        return response()->json([
            'success' => true,
            'message' => 'Parrainage enregistré avec succès.',
            'data' => $parrainage->load('filleul'),
        ], 201);
    }

    /**
     * Liste les filleuls parrainés par le client connecté.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $filleuls = ParrainageClient::where('parrain_id', $user->id)
            ->with('filleul')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $filleuls,
        ]);
    }
}
