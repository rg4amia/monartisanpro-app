<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Litige;
use App\Models\Mission;
use App\Services\NotificationService;
use App\Services\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LitigeController extends Controller
{
    public function __construct(
        private NotificationService $notificationService,
        private WalletService $walletService
    ) {}

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

        if ($user->role !== 'admin' && $mission->client_id !== $user->id && $mission->artisan_id !== $user->id) {
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
                'adminNotes'  => $litige->admin_notes,
                'createdAt'   => $litige->created_at->toISOString(),
                'resoluAt'    => $litige->resolu_at?->toISOString(),
            ],
        ]);
    }

    /**
     * Admin arbitre le litige et prend une décision.
     * PUT /api/v1/litiges/{litige}/arbitrage
     */
    public function arbitrage(Request $request, Litige $litige): JsonResponse
    {
        $user = $request->user();

        if ($user->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Seul un admin peut arbitrer un litige.',
            ], 403);
        }

        $data = $request->validate([
            'decision' => ['required', 'in:client,artisan,gel'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $mission = $litige->mission;

        DB::transaction(function () use ($litige, $mission, $data) {
            if ($data['decision'] === 'client') {
                // Rembourser le client
                $this->walletService->refundClient($mission);

                $this->notificationService->send(
                    $mission->client,
                    'litige',
                    'Litige résolu en votre faveur',
                    "Vous avez été remboursé pour la mission #{$mission->id}.",
                    ['mission_id' => $mission->id, 'litige_id' => $litige->id]
                );
            } elseif ($data['decision'] === 'artisan') {
                // Payer l'artisan (débloquer tous les jalons)
                $this->walletService->payArtisan($mission);

                $this->notificationService->send(
                    $mission->artisan,
                    'litige',
                    'Litige résolu en votre faveur',
                    "Vous avez été payé pour la mission #{$mission->id}.",
                    ['mission_id' => $mission->id, 'litige_id' => $litige->id]
                );
            } else { // gel
                // Geler les fonds + notifier référent
                $mission->update(['funds_frozen' => true]);

                // TODO: Notifier référent de zone pour visite
            }

            $litige->update([
                'decision' => $data['decision'],
                'statut' => 'resolu',
                'resolu_at' => now(),
                'admin_notes' => $data['notes'] ?? null,
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => 'Décision d\'arbitrage enregistrée.',
            'data' => [
                'litige_id' => $litige->id,
                'decision' => $data['decision'],
            ],
        ]);
    }
}
