<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Mission\CreateMissionRequest;
use App\Http\Resources\MissionResource;
use App\Models\Mission;
use App\Services\MissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MissionController extends Controller
{
    public function __construct(private MissionService $missionService) {}

    /**
     * Liste des missions de l'utilisateur connecté.
     */
    public function index(Request $request): JsonResponse
    {
        $user     = $request->user();
        $status   = $request->query('status');

        $query = match ($user->role) {
            'client'  => Mission::where('client_id', $user->id),
            'artisan' => Mission::where('artisan_id', $user->id),
            default   => Mission::where('client_id', $user->id)->orWhere('artisan_id', $user->id),
        };

        if ($status) {
            $query->where('status', $status);
        }

        $missions = $query->with(['client', 'artisan', 'jalons', 'requestedSector', 'requestedTrade'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data'    => MissionResource::collection($missions->items()),
            'meta'    => [
                'total'        => $missions->total(),
                'current_page' => $missions->currentPage(),
                'last_page'    => $missions->lastPage(),
            ],
        ]);
    }

    /**
     * Crée une mission + estimation Gemini.
     */
    public function store(CreateMissionRequest $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->isKycActif()) {
            return response()->json([
                'success' => false,
                'message' => 'Votre KYC doit être validé pour créer une mission.',
            ], 403);
        }

        $mission = $this->missionService->create($user, $request->validated());

        return response()->json([
            'success' => true,
            'data'    => new MissionResource($mission->load('client', 'jalons', 'requestedSector', 'requestedTrade')),
        ], 201);
    }

    /**
     * Détail d'une mission avec ses jalons.
     */
    public function show(Mission $mission, Request $request): JsonResponse
    {
        $user = $request->user();

        if ($mission->client_id !== $user->id && $mission->artisan_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Accès refusé.',
            ], 403);
        }

        $mission->load([
            'client',
            'artisan.artisanProfile.trade',
            'jalons',
            'devis',
            'requestedSector',
            'requestedTrade',
        ]);

        return response()->json([
            'success' => true,
            'data'    => new MissionResource($mission),
        ]);
    }

    /**
     * Estimation Gemini (endpoint séparé pour demande de devis).
     */
    public function estimate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'description' => ['required', 'string', 'min:10'],
            'category'    => ['nullable', 'string', 'max:100'],
            'location_address' => ['nullable', 'string', 'max:255'],
        ]);

        $estimate = $this->missionService->estimate($data);

        return response()->json([
            'success' => true,
            'data'    => $estimate,
        ]);
    }

    /**
     * Met à jour le statut d'une mission.
     */
    public function updateStatus(Request $request, Mission $mission): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:en_attente,financee,en_cours,terminee,litige'],
        ]);

        $mission->update(['status' => $data['status']]);

        return response()->json([
            'success' => true,
            'data'    => new MissionResource($mission->fresh()),
        ]);
    }
}
