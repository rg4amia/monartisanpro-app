<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Devis\CreateDevisRequest;
use App\Http\Resources\DevisResource;
use App\Models\Devis;
use App\Models\Mission;
use App\Services\DevisService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DevisController extends Controller
{
    public function __construct(private DevisService $devisService) {}

    public function index(Mission $mission): JsonResponse
    {
        $devis = $mission->devis()->with('artisan')->orderBy('created_at', 'desc')->get();

        return response()->json([
            'success' => true,
            'data'    => DevisResource::collection($devis),
        ]);
    }

    public function store(CreateDevisRequest $request, Mission $mission): JsonResponse
    {
        $user = $request->user();

        if ($user->role !== 'artisan') {
            return response()->json([
                'success' => false,
                'message' => 'Seul un artisan peut créer un devis.',
            ], 403);
        }

        $devis = $this->devisService->create($mission, $user, $request->validated());

        return response()->json([
            'success' => true,
            'data'    => new DevisResource($devis->load('artisan')),
        ], 201);
    }

    public function show(Devis $devis): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => new DevisResource($devis->load('artisan')),
        ]);
    }

    public function update(CreateDevisRequest $request, Devis $devis): JsonResponse
    {
        if ($devis->statut !== 'brouillon') {
            return response()->json([
                'success' => false,
                'message' => 'Seul un devis en brouillon peut être modifié.',
            ], 422);
        }

        $data = $request->validated();

        // Normalisation : accepter lignes_json OU lignes
        $updateData = [
            'lignes_json' => $data['lignes_json'] ?? $data['lignes'] ?? $devis->lignes_json,
            'jalons_json' => $data['jalons_json'] ?? $data['jalons'] ?? $devis->jalons_json,
        ];

        $devis->update($updateData);

        return response()->json([
            'success' => true,
            'data'    => new DevisResource($devis->fresh()->load('artisan')),
        ]);
    }

    /**
     * Client accepte le devis → fragmentation séquestre + création jalons.
     */
    public function accept(Request $request, Devis $devis): JsonResponse
    {
        if ($devis->statut !== 'soumis') {
            return response()->json([
                'success' => false,
                'message' => 'Ce devis ne peut plus être accepté.',
            ], 422);
        }

        $data = $request->validate([
            'provider' => ['sometimes', 'in:wave,orange_money'],
        ]);

        $this->devisService->accept($devis, $data['provider'] ?? 'wave');

        return response()->json([
            'success' => true,
            'message' => 'Devis accepté. La mission est maintenant financée.',
            'data'    => new DevisResource($devis->fresh()->load('artisan')),
        ]);
    }

    public function refuse(Devis $devis): JsonResponse
    {
        if ($devis->statut !== 'soumis') {
            return response()->json([
                'success' => false,
                'message' => 'Ce devis ne peut plus être refusé.',
            ], 422);
        }

        $this->devisService->refuse($devis);

        return response()->json([
            'success' => true,
            'message' => 'Devis refusé.',
        ]);
    }
}
