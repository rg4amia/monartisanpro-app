<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Mission\CreateMissionRequest;
use App\Http\Resources\MissionResource;
use App\Models\Mission;
use App\Services\MissionService;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MissionController extends Controller
{
    public function __construct(
        private MissionService $missionService,
        private NotificationService $notificationService
    ) {}

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
            if ($user->role === 'artisan') {
                $mappedStatuses = match ($status) {
                    'en_attente' => ['draft', 'pending_funding', 'pending_artisan_acceptance'],
                    'financee'   => ['funded_locked'],
                    'en_cours'   => ['in_progress', 'pending_approval'],
                    'terminee'   => ['completed'],
                    'litige'     => ['disputed'],
                    'annulee'    => ['cancelled'],
                    default      => [$status],
                };
            } else {
                $mappedStatuses = match ($status) {
                    'en_cours'   => ['draft', 'pending_artisan_acceptance', 'pending_funding', 'funded_locked', 'in_progress', 'pending_approval'],
                    'terminee'   => ['completed'],
                    'litige'     => ['disputed'],
                    'annulee'    => ['cancelled'],
                    default      => [$status],
                };
            }
            $query->whereIn('status', $mappedStatuses);
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

        if ($request->has('payment_phone')) {
            $user->update([
                'payment_phone' => $request->input('payment_phone'),
                'preferred_payment_provider' => $request->input('preferred_payment_provider'),
            ]);
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
        if ($mission->hasPendingDevis()) {
            return response()->json([
                'success' => false,
                'message' => 'Cette mission a un devis en cours d\'examen et ne peut pas être modifiée.',
            ], 422);
        }

        $data = $request->validate([
            'status' => [
                'required',
                'in:en_attente,financee,en_cours,terminee,litige,annulee,draft,pending_funding,funded_locked,in_progress,pending_approval,completed,disputed,cancelled'
            ],
        ]);

        $status = $data['status'];
        $stateClass = match ($status) {
            'draft', 'en_attente' => \App\States\Mission\DraftState::class,
            'pending_funding' => \App\States\Mission\PendingFundingState::class,
            'funded_locked', 'financee' => \App\States\Mission\FundedLockedState::class,
            'in_progress', 'en_cours' => \App\States\Mission\InProgressState::class,
            'pending_approval' => \App\States\Mission\PendingApprovalState::class,
            'completed', 'terminee' => \App\States\Mission\CompletedState::class,
            'disputed', 'litige' => \App\States\Mission\DisputedState::class,
            'cancelled', 'annulee' => \App\States\Mission\CancelledState::class,
            default => $status,
        };

        $mission->status->transitionTo($stateClass);

        return response()->json([
            'success' => true,
            'data'    => new MissionResource($mission->fresh()),
        ]);
    }

    /**
     * Artisan accepte la demande de devis.
     */
    public function acceptRequest(Request $request, Mission $mission): JsonResponse
    {
        if ($mission->hasPendingDevis()) {
            return response()->json([
                'success' => false,
                'message' => 'Cette mission a un devis en cours d\'examen et ne peut pas être traitée.',
            ], 422);
        }

        $user = $request->user();

        if (! $user->isKycActif()) {
            return response()->json([
                'success' => false,
                'message' => 'Votre KYC doit être validé pour accepter cette mission.',
            ], 403);
        }

        if ((int) $mission->artisan_id !== (int) $user->id) {
            return response()->json(['success' => false, 'message' => 'Accès refusé.'], 403);
        }

        if (!$mission->status instanceof \App\States\Mission\PendingArtisanAcceptanceState) {
            return response()->json(['success' => false, 'message' => 'Statut invalide.'], 400);
        }

        $mission->status->transitionTo(\App\States\Mission\DraftState::class);

        if ($mission->client) {
            $this->notificationService->send(
                $mission->client,
                'mission',
                'Demande de devis acceptée',
                "L'artisan a accepté votre demande et prépare le devis.",
                ['mission_id' => $mission->id]
            );
        }

        $mission->refresh();
        $mission->load(['client', 'artisan', 'jalons', 'requestedSector', 'requestedTrade']);

        return response()->json([
            'success' => true,
            'message' => 'Demande acceptée.',
            'data'    => new MissionResource($mission),
        ]);
    }

    /**
     * Artisan refuse la demande de devis.
     */
    public function rejectRequest(Request $request, Mission $mission): JsonResponse
    {
        if ($mission->hasPendingDevis()) {
            return response()->json([
                'success' => false,
                'message' => 'Cette mission a un devis en cours d\'examen et ne peut pas être traitée.',
            ], 422);
        }

        $user = $request->user();

        if ((int) $mission->artisan_id !== (int) $user->id) {
            return response()->json(['success' => false, 'message' => 'Accès refusé.'], 403);
        }

        if (!$mission->status instanceof \App\States\Mission\PendingArtisanAcceptanceState) {
            return response()->json(['success' => false, 'message' => 'Statut invalide.'], 400);
        }

        // Éviter les conflits/litiges si un paiement est en cours ou déjà effectué
        $hasActiveTransaction = $mission->transactions()
            ->whereIn('statut', ['en_attente', 'confirme'])
            ->exists();

        if ($hasActiveTransaction) {
            return response()->json([
                'success' => false,
                'message' => 'Impossible de refuser la demande : un paiement est en cours d\'initiation ou a déjà été validé.',
            ], 400);
        }

        $client = $mission->client;

        $mission->update(['artisan_id' => null]);
        $mission->status->transitionTo(\App\States\Mission\DraftState::class);

        if ($client) {
            $this->notificationService->send(
                $client,
                'mission',
                'Demande de devis refusée',
                "L'artisan a refusé votre demande de devis. Vous pouvez sélectionner un autre artisan.",
                ['mission_id' => $mission->id]
            );
        }

        $mission->refresh();
        $mission->load(['client', 'artisan', 'jalons', 'requestedSector', 'requestedTrade']);

        return response()->json([
            'success' => true,
            'message' => 'Demande refusée, mission remise en recherche d\'artisan.',
            'data'    => new MissionResource($mission),
        ]);
    }
}
