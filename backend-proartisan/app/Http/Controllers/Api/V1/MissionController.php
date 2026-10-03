<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\MissionActionException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Mission\CreateMissionRequest;
use App\Http\Resources\MissionResource;
use App\Models\Mission;
use App\Services\AiMonitoringService;
use App\Services\GeminiService;
use App\Services\MissionCancellationService;
use App\Services\MissionHistoryService;
use App\Services\MissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class MissionController extends Controller
{
    private const RELATIONS = ['client', 'artisan', 'jalons', 'requestedSector', 'requestedTrade', 'interventionType'];

    public function __construct(
        private MissionService $missionService,
        private MissionCancellationService $cancellations,
        private GeminiService $geminiService,
    ) {}

    /**
     * Exécute une action métier et traduit son refus en réponse JSON.
     *
     * @param  \Closure(): Mission  $action
     */
    private function respond(\Closure $action, string $message): JsonResponse
    {
        try {
            $mission = $action();
        } catch (MissionActionException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], $e->status);
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => new MissionResource($mission->load(self::RELATIONS)),
        ]);
    }

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
            default => Mission::where(fn ($q) => $q->where('client_id', $user->id)->orWhere('artisan_id', $user->id)),
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

            // Coordonnées de paiement : celles que le client fournit, sinon son
            // numéro de compte (comportement couvert par MobileMoneyValidationTest).
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
            // Ni le contenu de la demande (adresse, numéro de paiement) dans le
            // journal, ni le message interne dans la réponse.
            Log::error('Erreur lors de la création de la mission: '.$e->getMessage(), [
                'user_id' => $request->user()?->id,
                'exception' => $e::class,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'La demande n\'a pas pu être enregistrée. Veuillez réessayer dans quelques instants.',
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
        $revealClient = $user->role === 'admin'
            || $user->id === $mission->client_id
            || ($user->id === $mission->artisan_id && $mission->isFunded());

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
     * Pré-diagnostic vidéo & photo multimodal (Gemini 3.6 Flash).
     * POST /api/v1/missions/pre-diagnostic
     */
    public function preDiagnostic(Request $request): JsonResponse
    {
        $user = $request->user();

        // 1. Contrôle KYC obligatoire
        if (! $user->isKycActif()) {
            return response()->json([
                'success' => false,
                'message' => 'Votre KYC doit être validé pour effectuer un pré-diagnostic IA.',
            ], 403);
        }

        // 2. Contrôle de quota IA journalier
        if (! AiMonitoringService::checkUserLimit($user->id)) {
            return response()->json([
                'success' => false,
                'message' => 'Quota journalier IA atteint. Veuillez réessayer plus tard.',
                'quota' => AiMonitoringService::remainingFor($user->id),
            ], 429);
        }

        // 3. Validation des entrées
        $validated = $request->validate([
            'description' => ['nullable', 'string', 'max:2000'],
            'category' => ['nullable', 'string', 'max:100'],
            'photos' => ['nullable', 'array', 'max:5'],
            'photos.*' => ['file', 'mimes:jpeg,jpg,png,webp', 'max:15360'],
            'video' => ['nullable', 'file', 'mimes:mp4,mov,quicktime,webm', 'max:26214400'],
        ]);

        $hasPhotos = $request->hasFile('photos');
        $hasVideo = $request->hasFile('video');
        $hasDescription = ! empty(trim((string) ($validated['description'] ?? '')));

        if (! $hasPhotos && ! $hasVideo && ! $hasDescription) {
            return response()->json([
                'success' => false,
                'message' => 'Veuillez fournir au moins une photo, une vidéo ou une description pour le pré-diagnostic.',
            ], 422);
        }

        $mediaFiles = [];
        if ($hasPhotos) {
            foreach ($request->file('photos') as $photo) {
                $mediaFiles[] = $photo;
            }
        }
        if ($hasVideo) {
            $mediaFiles[] = $request->file('video');
        }

        $result = $this->geminiService->analyzePreDiagnostic(
            $mediaFiles,
            $validated['description'] ?? '',
            $validated['category'] ?? null,
            $user->id
        );

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }

    /**
     * Force l'état d'une mission (administrateur uniquement).
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

        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(MissionService::FORCEABLE_STATES))],
            'reason' => ['required', 'string', 'min:10', 'max:500'],
        ]);

        // Une transition refusée par la machine à états répond 422 (bootstrap/app.php).
        return $this->respond(
            fn () => $this->missionService->forceStatus($mission, $data['status'], $user, $data['reason']),
            'Statut de la mission mis à jour.',
        );
    }

    /**
     * Artisan accepte la demande de devis.
     */
    public function acceptRequest(Request $request, Mission $mission): JsonResponse
    {
        return $this->respond(
            fn () => $this->missionService->acceptRequest($mission, $request->user()),
            'Demande acceptée.',
        );
    }

    /**
     * Artisan refuse la demande de devis.
     */
    public function rejectRequest(Request $request, Mission $mission): JsonResponse
    {
        return $this->respond(
            fn () => $this->missionService->rejectRequest($mission, $request->user()),
            'Demande refusée, mission remise en recherche d\'artisan.',
        );
    }

    /**
     * Client assigne ou réassigne un artisan pour une demande de devis.
     */
    public function assignArtisan(Request $request, Mission $mission): JsonResponse
    {
        // La propriété passe avant la validation : un tiers reçoit 403, pas le détail des champs.
        if ((int) $mission->client_id !== (int) $request->user()->id) {
            return response()->json(['success' => false, 'message' => 'Accès refusé.'], 403);
        }

        $validated = $request->validate([
            'artisan_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        return $this->respond(
            fn () => $this->missionService->assignArtisan($mission, $request->user(), (int) $validated['artisan_id']),
            'Demande de devis transmise au nouvel artisan.',
        );
    }

    /**
     * Le client valide la fin du chantier (Chantier 19).
     */
    public function approveCompletion(Request $request, Mission $mission): JsonResponse
    {
        return $this->respond(
            fn () => $this->missionService->approveCompletion($mission, $request->user()),
            'Fin du chantier validée. La mission est clôturée.',
        );
    }

    /**
     * Ce que coûterait l'annulation : séquestre, pénalité, remboursement,
     * calculés par le serveur.
     */
    public function cancellationPreview(Request $request, Mission $mission): JsonResponse
    {
        try {
            $preview = $this->cancellations->preview($mission, $request->user());
        } catch (MissionActionException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], $e->status);
        }

        return response()->json(['success' => true, 'data' => $preview]);
    }

    /**
     * Le client annule sa mission.
     */
    public function cancel(Request $request, Mission $mission): JsonResponse
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:255']]);

        return $this->respond(
            fn () => $this->cancellations->cancel($mission, $request->user(), $data['reason'] ?? null),
            'Mission annulée.',
        );
    }

    /**
     * Historique chronologique et inaltérable des transitions d'états de la mission (Chantier 13).
     */
    public function stateHistory(Request $request, Mission $mission, MissionHistoryService $history): JsonResponse
    {
        $user = $request->user();

        // Seuls le client, l'artisan assigné ou un administrateur peuvent consulter l'historique
        if ($user->role !== 'admin' && (int) $mission->client_id !== (int) $user->id && (int) $mission->artisan_id !== (int) $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Accès non autorisé à l\'historique de cette mission.',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => $history->timeline($mission),
        ]);
    }
}
