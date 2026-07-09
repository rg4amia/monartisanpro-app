<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Evaluation\CreateEvaluationRequest;
use App\Models\Evaluation;
use App\Models\Mission;
use App\Services\ScoreService;
use Illuminate\Http\JsonResponse;

class EvaluationController extends Controller
{
    public function __construct(private ScoreService $scoreService) {}

    public function store(CreateEvaluationRequest $request): JsonResponse
    {
        $user    = $request->user();
        $mission = Mission::findOrFail($request->mission_id);

        if ((string) $mission->status !== 'completed') {
            return response()->json([
                'success' => false,
                'message' => 'La mission doit être terminée pour pouvoir évaluer.',
            ], 422);
        }

        // Vérifie que l'évaluateur n'a pas déjà évalué
        $exists = Evaluation::where('mission_id', $mission->id)
            ->where('evaluateur_id', $user->id)
            ->exists();

        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => 'Vous avez déjà évalué cette mission.',
            ], 422);
        }

        $evaluation = Evaluation::create([
            'mission_id'   => $mission->id,
            'evaluateur_id' => $user->id,
            'evalue_id'    => $request->evalue_id,
            'note'         => $request->note,
            'commentaire'  => $request->commentaire,
            'fiabilite'    => $request->fiabilite,
            'integrite'    => $request->integrite,
            'qualite'      => $request->qualite,
            'reactivite'   => $request->reactivite,
        ]);

        // Recalcul Score N'Zassa de l'artisan évalué
        $evalue = $evaluation->evalue;
        if ($evalue->isArtisan()) {
            $newScore = $this->scoreService->recalculate($evalue);
        }

        return response()->json([
            'success'       => true,
            'message'       => 'Évaluation enregistrée.',
            'data'          => [
                'id'            => $evaluation->id,
                'note'          => $evaluation->note,
                'scoreNzassa'   => $newScore ?? null,
            ],
        ], 201);
    }
}
