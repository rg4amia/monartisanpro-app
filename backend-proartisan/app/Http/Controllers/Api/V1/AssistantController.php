<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Litige;
use App\Services\AiMonitoringService;
use App\Services\Llm\AssistantService;
use App\Services\Llm\DisputeMediationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Assistant IA des artisans (chat BTP, recherche de fiches) et médiation IA
 * d'un litige. Soumis au quota IA par utilisateur (Règle d'or 25).
 */
class AssistantController extends Controller
{
    private const QUOTA_MESSAGE = "Quota d'interactions IA atteint. Réessayez plus tard.";

    public function __construct(private AssistantService $assistant) {}

    public function search(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tags' => 'nullable|array|max:20',
            'tags.*' => 'string|max:60',
            'filters' => 'nullable|array',
            // ~6 Mo de fichier une fois encodé en base64.
            'image_b64' => 'nullable|string|max:8500000',
        ]);

        if (! AiMonitoringService::checkUserLimit()) {
            return response()->json(['success' => false, 'error' => self::QUOTA_MESSAGE], 429);
        }

        $tags = $validated['tags'] ?? [];

        if (! empty($validated['image_b64'])) {
            $analysis = $this->assistant->analyzeImage($validated['image_b64']);

            if ($analysis !== null) {
                return response()->json([$analysis]);
            }

            // La photo n'a pas pu être analysée : on le dit, sauf si des
            // mots-clés permettent de chercher une fiche validée.
            if ($tags === []) {
                return response()->json([
                    'success' => false,
                    'error' => "La photo n'a pas pu être analysée. Réessayez plus tard ou décrivez le problème.",
                ], 503);
            }
        }

        return response()->json($this->assistant->search($tags, $validated['filters'] ?? []));
    }

    public function chat(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message' => 'required|string|max:2000',
            'trade' => 'nullable|string|max:60',
        ]);

        if (! AiMonitoringService::checkUserLimit()) {
            return response()->json(['success' => false, 'error' => self::QUOTA_MESSAGE], 429);
        }

        // Le métier entre dans la consigne de l'IA : une seule ligne, sans mise en forme.
        $trade = trim((string) preg_replace('/[^\p{L}\p{N} \'-]+/u', ' ', $validated['trade'] ?? '')) ?: 'Maçon';

        $payload = $this->assistant->chat(trim($validated['message']), $trade, $request->user()?->id);

        if ($request->user()) {
            $payload['quota'] = AiMonitoringService::remainingFor($request->user()->id);
        }

        return response()->json($payload);
    }

    public function mediation(Request $request, Litige $litige, DisputeMediationService $mediation): JsonResponse
    {
        $validated = $request->validate(['message' => 'required|string|max:3000']);

        $litige->loadMissing('mission');

        if (! $mediation->canRequest($litige, $request->user())) {
            return response()->json(['success' => false, 'error' => "Vous n'êtes pas partie à ce litige."], 403);
        }

        if (! AiMonitoringService::checkUserLimit()) {
            return response()->json(['success' => false, 'error' => self::QUOTA_MESSAGE], 429);
        }

        $reply = $mediation->mediate($litige, $request->user(), trim($validated['message']));

        if ($reply === null) {
            return response()->json([
                'success' => false,
                'error' => 'La médiation par IA est momentanément indisponible. Réessayez plus tard.',
            ], 503);
        }

        return response()->json(['success' => true, 'mediation' => $reply]);
    }
}
