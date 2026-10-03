<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Evaluation\CreateEvaluationRequest;
use App\Models\Mission;
use App\Models\Order;
use App\Services\EvaluationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class EvaluationController extends Controller
{
    public function __construct(private EvaluationService $evaluations) {}

    public function store(CreateEvaluationRequest $request): JsonResponse
    {
        try {
            $result = $this->evaluations->create($request->user(), $request->validated());
        } catch (AccessDeniedHttpException $e) {
            return $this->refusal($e->getMessage(), 403);
        } catch (ValidationException $e) {
            return $this->refusal($e->validator->errors()->first(), 422);
        } catch (\Throwable $e) {
            Log::error("Erreur lors de l'enregistrement de l'évaluation", [
                'user_id' => $request->user()->id,
                'evalue_id' => $request->input('evalue_id'),
                'exception' => $e,
            ]);

            // Le détail technique reste dans le journal, jamais dans la réponse.
            return $this->refusal("Votre évaluation n'a pas pu être enregistrée. Réessayez dans un instant.", 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Évaluation enregistrée avec succès.',
            'data' => [
                'id' => $result['evaluation']->id,
                'note' => $result['evaluation']->note,
                'scoreProsArtisan' => $result['score'],
            ],
        ], 201);
    }

    /**
     * Acteurs à évaluer pour une mission.
     * GET /api/v1/missions/{mission}/evaluations-status
     */
    public function actorsForMission(Mission $mission, Request $request): JsonResponse
    {
        if (! $this->evaluations->canSeeMission($mission, $request->user())) {
            return $this->refusal('Accès non autorisé.', 403);
        }

        return response()->json(['success' => true, 'data' => $this->evaluations->actorsForMission($mission, $request->user())]);
    }

    /**
     * Acteurs à évaluer pour une commande.
     * GET /api/v1/orders/{order}/evaluations-status
     */
    public function actorsForOrder(Order $order, Request $request): JsonResponse
    {
        if (! $this->evaluations->canSeeOrder($order, $request->user())) {
            return $this->refusal('Accès non autorisé.', 403);
        }

        return response()->json(['success' => true, 'data' => $this->evaluations->actorsForOrder($order, $request->user())]);
    }

    /**
     * Évaluations données et reçues par l'utilisateur connecté.
     * GET /api/v1/evaluations/my
     */
    public function myEvaluations(Request $request): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->evaluations->listFor($request->user())]);
    }

    private function refusal(string $message, int $status): JsonResponse
    {
        return response()->json(['success' => false, 'message' => $message], $status);
    }
}
