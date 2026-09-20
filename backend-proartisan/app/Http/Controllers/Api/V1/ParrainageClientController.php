<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\ParrainageClientException;
use App\Http\Controllers\Controller;
use App\Models\ParrainageClient;
use App\Services\ParrainageClientService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ParrainageClientController extends Controller
{
    /**
     * Enregistre un parrainage d'un client (filleul) par un autre client (parrain).
     */
    public function store(Request $request, ParrainageClientService $service): JsonResponse
    {
        $request->validate([
            'filleul_phone' => ['required', 'string', 'regex:/^\+225[0-9]{10}$/'],
        ]);

        try {
            $parrainage = $service->parrainer($request->user(), $request->input('filleul_phone'));
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
