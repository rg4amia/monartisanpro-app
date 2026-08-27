<?php
 
namespace App\Http\Controllers\Api\V1;
 
use App\Http\Controllers\Controller;
use App\Http\Requests\Evaluation\CreateEvaluationRequest;
use App\Models\Evaluation;
use App\Models\Mission;
use App\Models\User;
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
 
        $evalue = User::findOrFail($request->evalue_id);
 
        // Validation que l'évalué est associé à la mission
        $isValidRecipient = false;
        if ($evalue->id === $mission->artisan_id) {
            $isValidRecipient = true;
        } elseif ($evalue->isFournisseur()) {
            // Le fournisseur doit avoir scanné un J-Code ou servi un article pour cette mission
            $jcodeIds = $mission->jcodes()->pluck('id');
            $hasServed = $mission->jcodes()->where('fournisseur_id', $evalue->id)->exists()
                || \App\Models\JCodeItem::whereIn('jcode_id', $jcodeIds)->where('served_by_supplier_id', $evalue->id)->exists();
            if ($hasServed) {
                $isValidRecipient = true;
            }
        } elseif ($evalue->isLivreur()) {
            // Le livreur doit être un livreur enregistré (rôle livreur)
            $isValidRecipient = true;
        }
 
        if (!$isValidRecipient) {
            return response()->json([
                'success' => false,
                'message' => 'L\'utilisateur évalué n\'est pas associé à cette mission.',
            ], 422);
        }
 
        // Vérifie que l'évaluateur n'a pas déjà évalué cette personne spécifique pour cette mission
        $exists = Evaluation::where('mission_id', $mission->id)
            ->where('evaluateur_id', $user->id)
            ->where('evalue_id', $evalue->id)
            ->exists();
 
        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => 'Vous avez déjà évalué cette personne pour cette mission.',
            ], 422);
        }
 
        $evaluation = Evaluation::create([
            'mission_id'   => $mission->id,
            'evaluateur_id' => $user->id,
            'evalue_id'    => $evalue->id,
            'note'         => $request->note,
            'commentaire'  => $request->commentaire,
            'fiabilite'    => $request->fiabilite,
            'integrite'    => $request->integrite,
            'qualite'      => $request->qualite,
            'reactivite'   => $request->reactivite,
        ]);
 
        // Recalcul Score ProsArtisan
        if ($evalue->isArtisan()) {
            $newScore = $this->scoreService->recalculate($evalue);
        } elseif ($evalue->isFournisseur() || $evalue->isLivreur()) {
            $newScore = $this->scoreService->recalculateLogistic($evalue);
        }
 
        return response()->json([
            'success'       => true,
            'message'       => 'Évaluation enregistrée.',
            'data'          => [
                'id'            => $evaluation->id,
                'note'          => $evaluation->note,
                'scoreProsArtisan' => $newScore ?? null,
            ],
        ], 201);
    }
}
