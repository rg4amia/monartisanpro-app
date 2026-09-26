<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Litige\ArbitrateLitigeRequest;
use App\Http\Requests\Litige\CreateLitigeRequest;
use App\Http\Requests\Litige\StoreLitigeEvidenceRequest;
use App\Http\Resources\LitigeResource;
use App\Models\Litige;
use App\Models\Mission;
use App\Services\LitigeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LitigeController extends Controller
{
    public function __construct(private LitigeService $litigeService) {}

    public function index(Request $request): JsonResponse
    {
        $litiges = $this->litigeService->paginateForUser(
            $request->user(),
            $request->query('statut'),
            max(1, min((int) $request->query('per_page', 20), 100))
        );

        return response()->json([
            'success' => true,
            'data' => LitigeResource::collection($litiges->items()),
            'meta' => [
                'total' => $litiges->total(),
                'current_page' => $litiges->currentPage(),
                'last_page' => $litiges->lastPage(),
            ],
        ]);
    }

    public function store(CreateLitigeRequest $request): JsonResponse
    {
        $mission = Mission::findOrFail($request->validated('mission_id'));
        $litige = $this->litigeService->open($request->user(), $mission, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Litige ouvert. Les fonds sont geles et le dossier est en instruction.',
            'data' => new LitigeResource($litige),
        ], 201);
    }

    /**
     * PUT /api/v1/litiges/{litige}/refund-destination — moyen de remboursement
     * choisi par le client de la mission (Chantier 11).
     */
    public function refundDestination(Litige $litige, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'provider' => 'required|in:wave,orange_money',
            'phone' => ['required', 'string', 'regex:/^\+225[0-9]{10}$/'],
        ], [
            'phone.regex' => 'Numéro invalide : format attendu +225 suivi de 10 chiffres.',
        ]);

        try {
            $litige = $this->litigeService->setRefundDestination($litige, $request->user(), $validated['provider'], $validated['phone']);
        } catch (\DomainException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 403);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Moyen de remboursement enregistré.',
            'data' => [
                'refund_provider' => $litige->refund_provider,
                'refund_phone' => $litige->refund_phone,
            ],
        ]);
    }

    public function show(Litige $litige, Request $request): JsonResponse
    {
        $user = $request->user();
        $mission = $litige->mission;

        $isJuror = $litige->juryReviews()->where('jure_id', $user->id)->exists();

        if ($user->role !== 'admin' && $mission->client_id !== $user->id && $mission->artisan_id !== $user->id && ! $isJuror) {
            return response()->json([
                'success' => false,
                'message' => 'Acces refuse.',
            ], 403);
        }

        $litige = $this->litigeService->evaluateSla($litige);

        return response()->json([
            'success' => true,
            'data' => new LitigeResource($litige->load(['mission.client', 'mission.artisan', 'declencheur', 'preuves.user'])),
        ]);
    }

    public function storeEvidence(StoreLitigeEvidenceRequest $request, Litige $litige): JsonResponse
    {
        $litige = $this->litigeService->storeEvidence($litige, $request->user(), $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Vos preuves ont ete enregistrees.',
            'data' => new LitigeResource($litige),
        ]);
    }

    public function evaluateSla(Request $request, Litige $litige): JsonResponse
    {
        $user = $request->user();
        $mission = $litige->mission;

        if ($user->role !== 'admin' && $mission->client_id !== $user->id && $mission->artisan_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Acces refuse.',
            ], 403);
        }

        $litige = $this->litigeService->evaluateSla($litige);

        return response()->json([
            'success' => true,
            'data' => new LitigeResource($litige->load(['mission.client', 'mission.artisan', 'declencheur', 'preuves.user'])),
        ]);
    }

    public function arbitrage(ArbitrateLitigeRequest $request, Litige $litige): JsonResponse
    {
        $litige = $this->litigeService->arbitrate($request->user(), $litige, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Decision d arbitrage enregistree.',
            'data' => new LitigeResource($litige),
        ]);
    }

    /**
     * Déclenche la télé-expertise IA pour un litige (Chantier 9B).
     */
    public function requestTeleExpertise(Request $request, Litige $litige): JsonResponse
    {
        $user = $request->user();
        $mission = $litige->mission;

        if ($user->role !== 'admin' && $mission->client_id !== $user->id && $mission->artisan_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Accès refusé.',
            ], 403);
        }

        $analysis = $this->litigeService->requestTeleExpertise($litige);

        return response()->json([
            'success' => true,
            'message' => 'Analyse de télé-expertise générée avec succès.',
            'data' => $analysis,
        ]);
    }
}
