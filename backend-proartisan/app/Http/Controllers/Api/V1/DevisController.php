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
            // La synchro du numéro de paiement ne doit jamais faire échouer la
            // soumission du devis elle-même (cf. MissionController::store).
            try {
                $user->update([
                    'payment_phone' => $request->input('payment_phone'),
                    'preferred_payment_provider' => $request->input('preferred_payment_provider'),
                ]);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Impossible de synchroniser le téléphone de paiement (devis): ' . $e->getMessage());
            }
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

        if ($devis->statut === 'accepte') {
            return response()->json([
                'success' => true,
                'message' => $devis->is_avenant
                    ? 'Cet avenant est déjà accepté et financé.'
                    : 'Ce devis est déjà accepté et financé.',
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

        try {
            app(\App\Services\RealtimeEventService::class)->broadcast(
                $devis->mission_id,
                'mission_status',
                [
                    'status' => (string) $devis->mission->fresh()->status,
                    'devis_id' => $devis->id,
                    'is_avenant' => (bool) $devis->is_avenant,
                ]
            );
        } catch (\Throwable $e) {}

        return response()->json([
            'success' => true,
            'message' => $devis->is_avenant
                ? 'Avenant accepté et séquestre mis à jour.'
                : 'Devis accepté. La mission est maintenant financée.',
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

    /**
     * Analyse un enregistrement audio pour pré-remplir le devis (Voice-to-Quote).
     */
    public function parseVoiceQuote(Mission $mission, Request $request, \App\Services\GeminiService $geminiService): JsonResponse
    {
        $user = $request->user();

        if ($user->role !== 'artisan') {
            return response()->json([
                'success' => false,
                'message' => 'Seul un artisan peut utiliser la dictée vocale de devis.',
            ], 403);
        }

        // Vérification des quotas IA
        if (! \App\Services\AiMonitoringService::checkUserLimit($user->id)) {
            return response()->json([
                'success' => false,
                'message' => "Quota d'assistance IA atteint pour aujourd'hui. Saisissez votre devis manuellement ou réessayez demain.",
            ], 429);
        }

        $request->validate([
            'audio' => ['required', 'file', 'max:10240'], // Max 10 Mo
        ]);

        $file = $request->file('audio');
        $mimeType = $file->getMimeType() ?: 'audio/m4a';
        if (str_contains($mimeType, 'octet-stream') || empty($mimeType)) {
            $ext = strtolower($file->getClientOriginalExtension());
            $mimeType = match ($ext) {
                'mp3' => 'audio/mp3',
                'wav' => 'audio/wav',
                'aac' => 'audio/aac',
                'ogg' => 'audio/ogg',
                default => 'audio/m4a',
            };
        }

        $audioBase64 = base64_encode(file_get_contents($file->getPathname()));

        $suggestion = $geminiService->parseVoiceQuote($mission, $audioBase64, $mimeType, $user->id);

        return response()->json([
            'success' => true,
            'data'    => $suggestion,
        ]);
    }
}
