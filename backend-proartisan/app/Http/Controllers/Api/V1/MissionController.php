<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Mission\CreateMissionRequest;
use App\Http\Resources\MissionResource;
use App\Models\Mission;
use App\Models\User;
use App\Services\Admin\AdminActivityLogger;
use App\Services\MissionService;
use App\Services\NotificationService;
use App\States\Mission\CancelledState;
use App\States\Mission\CompletedState;
use App\States\Mission\DisputedState;
use App\States\Mission\DraftState;
use App\States\Mission\FundedLockedState;
use App\States\Mission\InProgressState;
use App\States\Mission\PendingApprovalState;
use App\States\Mission\PendingArtisanAcceptanceState;
use App\States\Mission\PendingFundingState;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class MissionController extends Controller
{
    public function __construct(
        private MissionService $missionService,
        private NotificationService $notificationService,
        private AdminActivityLogger $audit,
    ) {}

    /**
     * Liste des missions de l'utilisateur connecté.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $status = $request->query('status');

        $query = match ($user->role) {
            'client' => Mission::where('client_id', $user->id),
            'artisan' => Mission::where('artisan_id', $user->id),
            default => Mission::where('client_id', $user->id)->orWhere('artisan_id', $user->id),
        };

        if ($status) {
            if ($status === 'refusee') {
                $query->whereNotNull('artisan_rejected_at')->whereNull('artisan_id');
            } else {
                if ($user->role === 'artisan') {
                    $mappedStatuses = match ($status) {
                        'en_attente' => ['draft', 'pending_funding', 'pending_artisan_acceptance'],
                        'financee' => ['funded_locked'],
                        'en_cours' => ['in_progress', 'pending_approval'],
                        'terminee' => ['completed'],
                        'litige' => ['disputed'],
                        'annulee' => ['cancelled'],
                        default => [$status],
                    };
                } else {
                    $mappedStatuses = match ($status) {
                        'en_cours' => ['draft', 'pending_artisan_acceptance', 'pending_funding', 'funded_locked', 'in_progress', 'pending_approval'],
                        'terminee' => ['completed'],
                        'litige' => ['disputed'],
                        'annulee' => ['cancelled'],
                        default => [$status],
                    };
                }
                $query->whereIn('status', $mappedStatuses);
            }
        }

        $missions = $query->with(['client', 'artisan', 'jalons', 'requestedSector', 'requestedTrade', 'interventionType'])
            // Compteur agrégé en une seule requête (jamais N+1) : permet au
            // mobile d'afficher directement le nombre de messages non lus par
            // mission, sans avoir à ouvrir chaque discussion pour le savoir.
            ->withCount(['messages as unread_messages_count' => fn ($q) => $q
                ->where('sender_id', '!=', $user->id)
                ->whereNull('read_at')])
            // Agrège les drapeaux devis lus par MissionResource, qui sinon
            // déclenchait trois requêtes `exists` par mission sérialisée.
            ->withDevisFlags()
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => MissionResource::collection($missions->items()),
            'meta' => [
                'total' => $missions->total(),
                'current_page' => $missions->currentPage(),
                'last_page' => $missions->lastPage(),
            ],
        ]);
    }

    /**
     * Crée une mission + estimation Gemini.
     */
    public function store(CreateMissionRequest $request): JsonResponse
    {
        try {
            $user = $request->user();

            if (! $user->isKycActif()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Votre KYC doit être validé pour créer une mission.',
                ], 403);
            }

            try {
                if ($request->filled('payment_phone')) {
                    $user->update([
                        'payment_phone' => $request->input('payment_phone'),
                        'preferred_payment_provider' => $request->input('preferred_payment_provider', 'wave'),
                    ]);
                } elseif (! $user->payment_phone && $user->phone) {
                    $user->update([
                        'payment_phone' => $user->phone,
                        'preferred_payment_provider' => 'wave',
                    ]);
                }
            } catch (\Throwable $e) {
                Log::warning('Impossible de synchroniser le téléphone de paiement: '.$e->getMessage());
            }

            $mission = $this->missionService->create($user, $request->validated());

            return response()->json([
                'success' => true,
                'data' => new MissionResource($mission->load('client', 'jalons', 'requestedSector', 'requestedTrade')),
            ], 201);
        } catch (HttpExceptionInterface $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('Erreur lors de la création de la mission: '.$e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'payload' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création: '.$e->getMessage(),
            ], 500);
        }
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
            'interventionType',
        ]);

        return response()->json([
            'success' => true,
            'data' => new MissionResource($mission),
        ]);
    }

    /**
     * Carte du chantier (espace artisan / client) : position du client qui a
     * accepté et financé la mission + fournisseurs chez qui des matériaux ont
     * été retirés pour ce devis (liés via les J-Codes).
     *
     * GET /api/v1/missions/{mission}/site-map
     */
    public function siteMap(Mission $mission, Request $request): JsonResponse
    {
        $user = $request->user();

        if (
            $mission->client_id !== $user->id
            && $mission->artisan_id !== $user->id
            && $user->role !== 'admin'
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Accès refusé.',
            ], 403);
        }

        // La position exacte du client n'est révélée à l'artisan qu'une fois la
        // mission financée (séquestre constitué) — cohérent avec MissionResource.
        $paidStatuses = [
            'funded_locked', 'financee',
            'in_progress', 'en_cours',
            'pending_approval',
            'completed', 'terminee',
            'disputed', 'litige',
        ];
        $revealClient = $user->role === 'admin'
            || $user->id === $mission->client_id
            || ($user->id === $mission->artisan_id
                && in_array((string) $mission->status, $paidStatuses, true));

        $mission->load('client');

        $client = null;
        if (
            $revealClient
            && $mission->client_latitude !== null
            && $mission->client_longitude !== null
        ) {
            $client = [
                'name' => $mission->client?->name,
                'address' => $mission->client_address,
                'coordinates' => [
                    'lat' => (float) $mission->client_latitude,
                    'lng' => (float) $mission->client_longitude,
                ],
            ];
        }

        $suppliers = $mission->jcodes()
            ->with('fournisseur.fournisseurAgree')
            ->get()
            ->groupBy('fournisseur_id')
            ->map(function ($group) {
                $fournisseur = $group->first()->fournisseur;
                $agree = $fournisseur?->fournisseurAgree;
                $coords = $agree?->getPositionCoords();

                if (! $fournisseur || ! $coords) {
                    return null;
                }

                return [
                    'id' => $fournisseur->id,
                    'name' => $agree->nom_boutique ?: $fournisseur->name,
                    'coordinates' => $coords,
                    'jcodeCount' => $group->count(),
                    'montant' => (int) $group->sum('montant'),
                ];
            })
            ->filter()
            ->values();

        return response()->json([
            'success' => true,
            'data' => [
                'mission_id' => $mission->id,
                'client' => $client,
                'suppliers' => $suppliers,
            ],
        ]);
    }

    /**
     * Estimation Gemini (endpoint séparé pour demande de devis).
     */
    public function estimate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'description' => ['required', 'string', 'min:10'],
            'category' => ['nullable', 'string', 'max:100'],
            'location_address' => ['nullable', 'string', 'max:255'],
        ]);

        $estimate = $this->missionService->estimate($data);

        return response()->json([
            'success' => true,
            'data' => $estimate,
        ]);
    }

    /**
     * Met à jour le statut d'une mission.
     */
    public function updateStatus(Request $request, Mission $mission): JsonResponse
    {
        $user = $request->user();

        // Les transitions utilisateur passent exclusivement par les actions
        // métier dédiées (paiement, OTP, litige, etc.). Autoriser ici un client
        // ou un artisan permettrait notamment de simuler un financement.
        if ($user->role !== 'admin' || Gate::forUser($user)->denies('admin.missions.manage')) {
            return response()->json([
                'success' => false,
                'message' => 'Non autorisé.',
            ], 403);
        }

        if ($mission->hasPendingDevis()) {
            return response()->json([
                'success' => false,
                'message' => 'Cette mission a un devis en cours d\'examen et ne peut pas être modifiée.',
            ], 422);
        }

        $data = $request->validate([
            'status' => [
                'required',
                'in:en_attente,financee,en_cours,terminee,litige,annulee,draft,pending_funding,funded_locked,in_progress,pending_approval,completed,disputed,cancelled',
            ],
            'reason' => ['required', 'string', 'min:10', 'max:500'],
        ]);

        $previousStatus = (string) $mission->status;
        $status = $data['status'];
        $stateClass = match ($status) {
            'draft', 'en_attente' => DraftState::class,
            'pending_funding' => PendingFundingState::class,
            'funded_locked', 'financee' => FundedLockedState::class,
            'in_progress', 'en_cours' => InProgressState::class,
            'pending_approval' => PendingApprovalState::class,
            'completed', 'terminee' => CompletedState::class,
            'disputed', 'litige' => DisputedState::class,
            'cancelled', 'annulee' => CancelledState::class,
            default => $status,
        };

        $mission->status->transitionTo($stateClass);

        $this->audit->log(
            'mission.status.forced',
            $mission,
            [
                'before' => $previousStatus,
                'after' => (string) $mission->fresh()->status,
                'reason' => $data['reason'],
            ],
            actor: $user,
        );

        return response()->json([
            'success' => true,
            'data' => new MissionResource($mission->fresh()),
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

        if (! $mission->status instanceof PendingArtisanAcceptanceState) {
            return response()->json(['success' => false, 'message' => 'Statut invalide.'], 400);
        }

        $mission->update(['artisan_rejected_at' => null]);
        $mission->status->transitionTo(DraftState::class);

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
        $mission->load(['client', 'artisan', 'jalons', 'requestedSector', 'requestedTrade', 'interventionType']);

        return response()->json([
            'success' => true,
            'message' => 'Demande acceptée.',
            'data' => new MissionResource($mission),
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

        if (! $mission->status instanceof PendingArtisanAcceptanceState) {
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

        $mission->update([
            'artisan_id' => null,
            'artisan_rejected_at' => now(),
        ]);
        $mission->status->transitionTo(DraftState::class);

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
        $mission->load(['client', 'artisan', 'jalons', 'requestedSector', 'requestedTrade', 'interventionType']);

        return response()->json([
            'success' => true,
            'message' => 'Demande refusée, mission remise en recherche d\'artisan.',
            'data' => new MissionResource($mission),
        ]);
    }

    /**
     * Client assigne ou réassigne un artisan pour une demande de devis.
     */
    public function assignArtisan(Request $request, Mission $mission): JsonResponse
    {
        $user = $request->user();

        if ((int) $mission->client_id !== (int) $user->id) {
            return response()->json(['success' => false, 'message' => 'Accès refusé.'], 403);
        }

        if (! ($mission->status instanceof DraftState || $mission->status instanceof PendingArtisanAcceptanceState)) {
            return response()->json([
                'success' => false,
                'message' => 'Impossible de modifier l\'artisan pour une mission déjà financée ou en cours.',
            ], 400);
        }

        if ($mission->hasPendingDevis()) {
            return response()->json([
                'success' => false,
                'message' => 'Cette mission a un devis en cours d\'examen et ne peut pas être réassignée.',
            ], 422);
        }

        $validated = $request->validate([
            'artisan_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $artisan = User::findOrFail($validated['artisan_id']);

        if ($artisan->role !== 'artisan') {
            return response()->json([
                'success' => false,
                'message' => 'L\'utilisateur sélectionné n\'est pas un artisan.',
            ], 422);
        }

        if (! $artisan->isKycActif()) {
            return response()->json([
                'success' => false,
                'message' => 'Le profil de cet artisan n\'est pas encore validé.',
            ], 422);
        }

        $mission->update([
            'artisan_id' => $artisan->id,
            'artisan_rejected_at' => null,
        ]);

        if ($mission->status instanceof DraftState) {
            $mission->status->transitionTo(PendingArtisanAcceptanceState::class);
        }

        $clientName = $user->name ?? 'Client';
        $this->notificationService->send(
            $artisan,
            'mission',
            'Nouvelle demande de devis',
            "Le client {$clientName} vous a envoyé une demande de devis.",
            ['mission_id' => $mission->id]
        );

        $mission->refresh();
        $mission->load(['client', 'artisan', 'jalons', 'requestedSector', 'requestedTrade', 'interventionType']);

        return response()->json([
            'success' => true,
            'message' => 'Demande de devis transmise au nouvel artisan.',
            'data' => new MissionResource($mission),
        ]);
    }
}
