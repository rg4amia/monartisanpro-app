<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Devis\CreateDevisRequest;
use App\Http\Resources\DevisResource;
use App\Models\Devis;
use App\Models\Mission;
use App\Models\Transaction;
use App\Services\DevisService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DevisController extends Controller
{
    public function __construct(private DevisService $devisService) {}

    public function index(Mission $mission): JsonResponse
    {
        $devis = $mission->devis()->with(['artisan', 'mission'])->orderBy('created_at', 'desc')->get();

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

        if ($request->has('payment_phone')) {
            $user->update([
                'payment_phone' => $request->input('payment_phone'),
                'preferred_payment_provider' => $request->input('preferred_payment_provider'),
            ]);
        }

        try {
            $devis = $this->devisService->create($mission, $user, $request->validated());
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'data'    => new DevisResource($devis->load(['artisan', 'mission'])),
        ], 201);
    }

    public function show(Devis $devis): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => new DevisResource($devis->load(['artisan', 'mission'])),
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
        $payload = $this->devisService->normalizePayload($data, $request->user());

        $updateData = [
            'lignes_json' => $payload['lignes_json'] ?: $devis->lignes_json,
            'jalons_json' => $payload['jalons_json'] ?: $devis->jalons_json,
            'materials_required' => isset($payload['materials_required']) ? $payload['materials_required'] : $devis->materials_required,
            'intervention_type_id' => isset($payload['intervention_type_id']) ? $payload['intervention_type_id'] : $devis->intervention_type_id,
        ];

        $devis->update($updateData);

        return response()->json([
            'success' => true,
            'data'    => new DevisResource($devis->fresh()->load(['artisan', 'mission'])),
        ]);
    }

    /**
     * Client accepte le devis après confirmation du paiement.
     */
    public function accept(Request $request, Devis $devis): JsonResponse
    {
        $user = $request->user();
        $devis->loadMissing('mission');

        if ($devis->mission->client_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Seul le client de la mission peut accepter ce devis.',
            ], 403);
        }

        $data = $request->validate([
            'transaction_id' => ['required', 'integer', 'exists:transactions,id'],
        ]);

        $transaction = Transaction::findOrFail($data['transaction_id']);

        if ($transaction->user_id !== $user->id || $transaction->mission_id !== $devis->mission_id) {
            return response()->json([
                'success' => false,
                'message' => 'Cette transaction ne correspond pas à ce devis.',
            ], 422);
        }

        if ($transaction->type !== 'acompte') {
            return response()->json([
                'success' => false,
                'message' => 'Seule une transaction d\'acompte peut financer un devis.',
            ], 422);
        }

        if (($transaction->metadata['devis_id'] ?? null) !== $devis->id) {
            return response()->json([
                'success' => false,
                'message' => 'Cette transaction n\'a pas été initiée pour ce devis.',
            ], 422);
        }

        if (! $transaction->statut->isSuccessful()) {
            return response()->json([
                'success' => false,
                'message' => 'Le paiement doit être confirmé avant de financer la mission.',
            ], 422);
        }

        if ($devis->statut === 'accepte' && (string) $devis->mission->status === 'funded_locked') {
            return response()->json([
                'success' => true,
                'message' => 'Ce devis est déjà accepté et financé.',
                'data'    => new DevisResource($devis->fresh()->load(['artisan', 'mission'])),
            ]);
        }

        if ($devis->statut !== 'soumis') {
            return response()->json([
                'success' => false,
                'message' => 'Ce devis ne peut plus être accepté.',
            ], 422);
        }

        $this->devisService->accept($devis, $transaction);

        return response()->json([
            'success' => true,
            'message' => 'Devis accepté. La mission est maintenant financée.',
            'data'    => new DevisResource($devis->fresh()->load(['artisan', 'mission'])),
        ]);
    }

    public function refuse(Request $request, Devis $devis): JsonResponse
    {
        $devis->loadMissing('mission');

        if ($devis->mission->client_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Seul le client de la mission peut refuser ce devis.',
            ], 403);
        }

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

    /**
     * Suggère des lignes et jalons de devis via Gemini pour aider l'artisan.
     */
    public function suggest(Mission $mission, Request $request, \App\Services\GeminiService $geminiService): JsonResponse
    {
        $user = $request->user();

        if ($user->role !== 'artisan') {
            return response()->json([
                'success' => false,
                'message' => 'Seul un artisan peut demander une suggestion de devis.',
            ], 403);
        }

        $suggestion = $geminiService->suggestDevis($mission);

        return response()->json([
            'success' => true,
            'data'    => $suggestion,
        ]);
    }
}
