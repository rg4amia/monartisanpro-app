<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\JuryReview;
use App\Models\Litige;
use App\Services\LitigeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class JuryController extends Controller
{
    public function __construct(
        private LitigeService $litigeService
    ) {}

    /**
     * Liste des dossiers d'arbitrage assignés à l'artisan juré connecté.
     * Les données sont strictement anonymisées (aucun nom ni numéro de client/artisan).
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();

        $reviews = JuryReview::where('jure_id', $user->id)
            ->with(['litige.mission.interventionType', 'litige.preuves'])
            ->orderByDesc('assigned_at')
            ->paginate($request->integer('per_page', 15));

        $anonymized = $reviews->through(function (JuryReview $review) {
            $litige = $review->litige;
            return [
                'review_id' => $review->id,
                'litige_id' => $litige->id,
                'status' => $review->status,
                'compensation' => $review->compensation,
                'expires_at' => $review->expires_at?->toIso8601String(),
                'voted_at' => $review->voted_at?->toIso8601String(),
                'verdict' => $review->verdict,
                'dossier' => [
                    'intervention_type' => $litige->mission?->interventionType?->name ?? 'Non spécifié',
                    'montant_mission' => $litige->mission?->montant_total ?? 0,
                    'motif_litige' => $litige->type,
                    'created_at' => $litige->created_at->toIso8601String(),
                    'preuves_count' => $litige->preuves->count(),
                ],
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $anonymized,
        ]);
    }

    /**
     * Détail anonymisé complet d'un dossier pour examen par le juré.
     */
    public function show(int $litigeId): JsonResponse
    {
        $user = Auth::user();

        $review = JuryReview::where('litige_id', $litigeId)
            ->where('jure_id', $user->id)
            ->first();

        if (! $review) {
            return response()->json([
                'success' => false,
                'message' => 'Dossier non trouvé ou vous n\'êtes pas juré sur ce dossier.',
            ], 404);
        }

        $litige = Litige::with([
            'mission.interventionType',
            'preuves.evidenceVault',
            'mission.jalons',
        ])->findOrFail($litigeId);

        // Preuves présentées avec neutralité et intégrité SHA-256
        $preuves = $litige->preuves->map(function ($preuve) {
            return [
                'id' => $preuve->id,
                'partie' => $preuve->partie, // 'client' ou 'artisan'
                'media_url' => $preuve->media_url,
                'description' => $preuve->description,
                'taken_at' => $preuve->taken_at,
                'sha256_hash' => $preuve->evidenceVault?->sha256_hash,
                'is_certified' => $preuve->evidenceVault !== null,
                'tampered' => $preuve->evidenceVault?->is_tampered ?? false,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'review' => [
                    'id' => $review->id,
                    'status' => $review->status,
                    'compensation' => $review->compensation,
                    'assigned_at' => $review->assigned_at?->toIso8601String(),
                    'expires_at' => $review->expires_at?->toIso8601String(),
                    'voted_at' => $review->voted_at?->toIso8601String(),
                    'verdict' => $review->verdict,
                    'split_artisan_percentage' => $review->split_artisan_percentage,
                    'technical_comment' => $review->technical_comment,
                ],
                'litige' => [
                    'id' => $litige->id,
                    'intervention_type' => $litige->mission?->interventionType?->name ?? 'Standard',
                    'montant_mission' => $litige->mission?->montant_total ?? 0,
                    'motif' => $litige->type,
                    'created_at' => $litige->created_at->toIso8601String(),
                    'preuves' => $preuves,
                    'jalons_summary' => $litige->mission?->jalons->map(fn ($j) => [
                        'ordre' => $j->ordre,
                        'description' => $j->description,
                        'montant' => $j->montant,
                        'statut' => $j->statut,
                    ]),
                ],
            ],
        ]);
    }

    /**
     * Envoi du vote motivé du juré.
     */
    public function vote(Request $request, int $litigeId): JsonResponse
    {
        $user = Auth::user();

        $validated = $request->validate([
            'verdict' => ['required', 'string', 'in:CONFORME,NON_CONFORME,RESPONSABILITE_PARTAGEE'],
            'split_artisan_percentage' => ['nullable', 'integer', 'min:0', 'max:100'],
            'technical_comment' => ['nullable', 'string', 'max:1000'],
        ]);

        $litige = Litige::findOrFail($litigeId);

        try {
            $review = $this->litigeService->submitJuryVote(
                litige: $litige,
                jure: $user,
                verdict: $validated['verdict'],
                splitPercentage: $validated['split_artisan_percentage'] ?? null,
                technicalComment: $validated['technical_comment'] ?? null
            );

            return response()->json([
                'success' => true,
                'message' => 'Votre avis d\'arbitrage a été enregistré avec succès. Une indemnité de 5 000 FCFA a été créditée sur votre wallet.',
                'data' => [
                    'review_id' => $review->id,
                    'verdict' => $review->verdict,
                    'compensation' => $review->compensation,
                    'status' => $review->status,
                ],
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        }
    }
}
