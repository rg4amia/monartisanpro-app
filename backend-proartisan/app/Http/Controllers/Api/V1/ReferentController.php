<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\MissionResource;
use App\Models\Mission;
use App\Services\GeoService;
use App\Services\NotificationService;
use App\Services\ReferentHistoryService;
use App\Services\WalletService;
use App\States\Mission\FundedLockedState;
use App\States\Mission\InProgressState;
use App\States\Mission\PendingApprovalState;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ReferentController extends Controller
{
    public function __construct(
        private GeoService $geoService,
        private WalletService $walletService,
        private NotificationService $notificationService
    ) {}

    /**
     * Liste des missions nécessitant la validation d'un référent de zone.
     * GET /api/v1/referent/missions
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->role !== 'referent' && $user->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Seul un référent de zone peut accéder à cette liste.',
            ], 403);
        }

        $query = Mission::query()
            ->where(function ($q) {
                $q->where('referent_required', true)
                    ->orWhere('montant_total', '>', (int) config('prosartisan.mission.referent_threshold', 2000000));
            })
            ->whereIn('status', [
                'funded_locked',
                'in_progress',
                'pending_approval',
                // Litige à trancher : la visite précède l'arbitrage en faveur de l'artisan.
                'disputed',
                FundedLockedState::class,
                InProgressState::class,
                PendingApprovalState::class,
            ]);

        $lat = $request->filled('latitude') ? (float) $request->latitude : null;
        $lng = $request->filled('longitude') ? (float) $request->longitude : null;

        if ($lat !== null && $lng !== null && config('database.default') !== 'sqlite') {
            $query->selectRaw('missions.*, CASE WHEN client_latitude IS NOT NULL AND client_longitude IS NOT NULL THEN ST_Distance_Sphere(POINT(client_longitude, client_latitude), POINT(?, ?)) ELSE NULL END as distance_metres', [$lng, $lat])
                ->orderByRaw('distance_metres IS NULL, distance_metres ASC');
        } else {
            $query->orderBy('created_at', 'desc');
        }

        $missions = $query->with(['client', 'artisan', 'jalons', 'interventionType'])
            ->paginate($request->input('per_page', 20));

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
     * Inspections réalisées par le Référent connecté.
     * GET /api/v1/referent/inspections
     */
    public function inspections(Request $request, ReferentHistoryService $history): JsonResponse
    {
        if ($request->user()->role !== 'referent') {
            return response()->json(['success' => false, 'message' => 'Seul un référent de zone peut consulter ses inspections.'], 403);
        }

        $data = $request->validate(['per_page' => ['nullable', 'integer', 'min:1', 'max:100']]);
        $page = $history->inspections($request->user(), (int) ($data['per_page'] ?? 20));

        return response()->json([
            'success' => true,
            'data' => $page->items(),
            'meta' => ['total' => $page->total(), 'current_page' => $page->currentPage(), 'last_page' => $page->lastPage()],
        ]);
    }

    /**
     * Litiges des chantiers que le Référent doit visiter ou a visités.
     * GET /api/v1/referent/litiges
     */
    public function litiges(Request $request, ReferentHistoryService $history): JsonResponse
    {
        if ($request->user()->role !== 'referent') {
            return response()->json(['success' => false, 'message' => 'Seul un référent de zone peut consulter ces litiges.'], 403);
        }

        $data = $request->validate([
            'statut' => ['nullable', Rule::in(array_keys(ReferentHistoryService::LITIGE_STATUS_LABELS))],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        $page = $history->litiges($request->user(), $data['statut'] ?? null, (int) ($data['per_page'] ?? 20));

        return response()->json([
            'success' => true,
            'data' => $page->items(),
            'meta' => ['total' => $page->total(), 'current_page' => $page->currentPage(), 'last_page' => $page->lastPage()],
        ]);
    }

    /**
     * Référent valide physiquement la mission sur site.
     * POST /api/v1/missions/{mission}/referent-validate
     */
    public function validateMission(Request $request, Mission $mission): JsonResponse
    {
        $user = $request->user();

        if ($user->role !== 'referent') {
            return response()->json([
                'success' => false,
                'message' => 'Seul un référent de zone peut valider une mission.',
            ], 403);
        }

        if (! $mission->referent_required) {
            return response()->json([
                'success' => false,
                'message' => 'Cette mission ne nécessite pas de validation référent.',
            ], 422);
        }

        $data = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'photos' => ['required', 'array', 'min:2'],
            'photos.*' => ['file', 'image', 'max:5120'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $gpsCheck = $this->geoService->validateReferentMissionGps(
            $mission,
            (float) $data['latitude'],
            (float) $data['longitude']
        );

        if (! $gpsCheck['valid']) {
            return response()->json([
                'success' => false,
                'message' => $gpsCheck['reason'] ??
                    "Position GPS invalide. Distance {$gpsCheck['distance']} m (maximum {$gpsCheck['max']} m).",
                'errors' => [
                    'gps' => [
                        $gpsCheck['reason'] ??
                            "Le référent doit être à moins de {$gpsCheck['max']} m du chantier.",
                    ],
                ],
            ], 422);
        }

        // La levée du blocage précède la libération des jalons : WalletService::
        // releaseJalon() refuse de payer tant que referent_required est vrai sur
        // une mission au-delà du seuil (garde-fou anti-contournement), ce flag
        // doit donc déjà être retombé à false lorsque la boucle ci-dessous appelle
        // releaseJalon().
        $mission->update([
            'referent_required' => false,
            'referent_validated_at' => now(),
            'referent_validated_by' => $user->id,
        ]);

        // Fonds gelés par un litige : la visite est enregistrée, la répartition
        // des fonds revient à l'arbitrage. Aucune étape n'est payée ici.
        $gelLitige = $mission->isFundsFrozen();

        // Libérer tous les jalons validés en attente de paiement
        $jalonsEnAttente = $gelLitige
            ? collect()
            : $mission->jalons()
                ->where('statut', 'valide')
                ->whereNull('paye_at')
                ->get();

        foreach ($jalonsEnAttente as $jalon) {
            $this->walletService->releaseJalon($jalon);
        }

        if ($gelLitige) {
            $this->notificationService->notifyAdmins(
                'litige.visite_referent_faite.admin',
                ['mission' => $mission->id],
                ['mission_id' => $mission->id]
            );
        } else {
            $this->notificationService->notify(
                $mission->artisan,
                'mission.validee_referent.artisan',
                ['mission' => $mission->id],
                ['mission_id' => $mission->id]
            );
        }

        return response()->json([
            'success' => true,
            'message' => $gelLitige
                ? 'Visite enregistrée. Le litige peut maintenant être arbitré.'
                : 'Mission validée. Paiements libérés.',
            'data' => [
                'mission_id' => $mission->id,
                'jalons_liberes' => $jalonsEnAttente->count(),
                'distance_metres' => $gpsCheck['distance'],
            ],
        ]);
    }
}
