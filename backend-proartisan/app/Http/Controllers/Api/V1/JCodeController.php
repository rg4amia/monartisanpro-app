<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\JCode\GenerateJCodeRequest;
use App\Http\Requests\JCode\ScanJCodeRequest;
use App\Http\Resources\JCodeResource;
use App\Models\JCode;
use App\Models\Mission;
use App\Services\JCodeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class JCodeController extends Controller
{
    public function __construct(private JCodeService $jCodeService) {}

    /**
     * Artisan génère un J-Code pour acheter des matériaux.
     */
    public function store(GenerateJCodeRequest $request): JsonResponse
    {
        $user    = $request->user();
        $mission = Mission::findOrFail($request->mission_id);

        if ($mission->artisan_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Vous n\'êtes pas l\'artisan de cette mission.',
            ], 403);
        }

        if ($mission->status !== 'financee' && $mission->status !== 'en_cours') {
            return response()->json([
                'success' => false,
                'message' => 'La mission doit être financée pour générer un J-Code.',
            ], 422);
        }

        $jcode = $this->jCodeService->generate($mission, $user, $request->montant);

        return response()->json([
            'success' => true,
            'data'    => new JCodeResource($jcode->load('artisan')),
        ], 201);
    }

    /**
     * J-Codes actifs de l'artisan connecté.
     */
    public function active(Request $request): JsonResponse
    {
        $jcodes = JCode::where('artisan_id', $request->user()->id)
            ->where('statut', 'actif')
            ->where('expires_at', '>', now())
            ->with(['artisan', 'mission'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => JCodeResource::collection($jcodes),
        ]);
    }

    public function show(JCode $jcode): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => new JCodeResource($jcode->load('artisan', 'fournisseur')),
        ]);
    }

    /**
     * Fournisseur scanne le J-Code.
     * RÈGLE CRITIQUE : vérification GPS obligatoire (< 100 m).
     */
    public function scan(ScanJCodeRequest $request, JCode $jcode): JsonResponse
    {
        $user = $request->user();

        if ($user->role !== 'fournisseur') {
            return response()->json([
                'success' => false,
                'message' => 'Seul un fournisseur agréé peut valider un J-Code.',
            ], 403);
        }

        $result = $this->jCodeService->scan(
            $jcode,
            $user,
            (float) $request->lat,
            (float) $request->lng
        );

        return response()->json([
            'success'  => true,
            'message'  => 'J-Code validé. Paiement J+1 garanti.',
            'data'     => $result,
        ]);
    }
}
