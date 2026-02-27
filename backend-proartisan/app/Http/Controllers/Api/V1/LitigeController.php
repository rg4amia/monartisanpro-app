<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Litige;
use App\Models\Mission;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LitigeController extends Controller
{
    public function __construct(private NotificationService $notificationService) {}

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'mission_id'  => ['required', 'integer', 'exists:missions,id'],
            'description' => ['required', 'string', 'min:20', 'max:2000'],
        ], [
            'mission_id.required'  => 'La mission est obligatoire.',
            'description.required' => 'La description du litige est obligatoire.',
            'description.min'      => 'La description doit comporter au moins 20 caractères.',
        ]);

        $user    = $request->user();
        $mission = Mission::findOrFail($data['mission_id']);

        $isClient  = $mission->client_id === $user->id;
        $isArtisan = $mission->artisan_id === $user->id;

        if (! $isClient && ! $isArtisan) {
            return response()->json([
                'success' => false,
                'message' => 'Accès refusé.',
            ], 403);
        }

        $litige = Litige::create([
            'mission_id'     => $mission->id,
            'declencheur_id' => $user->id,
            'type'           => $isClient ? 'client' : 'artisan',
            'description'    => $data['description'],
            'statut'         => 'ouvert',
        ]);

        $mission->update(['status' => 'litige']);

        // Alerter les admins
        $this->notificationService->sendAdmin(
            'litige',
            'Nouveau litige signalé',
            "Litige ouvert sur la mission #{$mission->id} par {$user->name}.",
            ['litige_id' => $litige->id, 'mission_id' => $mission->id]
        );

        return response()->json([
            'success' => true,
            'message' => 'Litige ouvert. Notre équipe va instruire le dossier.',
            'data'    => [
                'id'        => $litige->id,
                'statut'    => $litige->statut,
                'missionId' => $litige->mission_id,
                'createdAt' => $litige->created_at->toISOString(),
            ],
        ], 201);
    }

    public function show(Litige $litige, Request $request): JsonResponse
    {
        $user = $request->user();
        $mission = $litige->mission;

        if ($mission->client_id !== $user->id && $mission->artisan_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Accès refusé.',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'id'          => $litige->id,
                'missionId'   => $litige->mission_id,
                'type'        => $litige->type,
                'description' => $litige->description,
                'statut'      => $litige->statut,
                'decision'    => $litige->decision,
                'createdAt'   => $litige->created_at->toISOString(),
                'resoluAt'    => $litige->resolu_at?->toISOString(),
            ],
        ]);
    }
}
