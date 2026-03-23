<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\JCode\GenerateJCodeRequest;
use App\Http\Requests\JCode\ScanJCodeRequest;
use App\Http\Requests\UploadJCodePhotoRequest;
use App\Http\Resources\JCodeResource;
use App\Models\JCode;
use App\Models\Mission;
use App\Models\User;
use App\Services\JCodeService;
use App\Services\NotificationService;
use App\Services\PhotoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class JCodeController extends Controller
{
    public function __construct(
        private JCodeService $jCodeService,
        private PhotoService $photoService,
        private NotificationService $notificationService
    ) {}

    /**
     * Artisan génère un J-Code pour acheter des matériaux.
     */
    public function store(GenerateJCodeRequest $request): JsonResponse
    {
        $user    = $request->user();
        $mission = Mission::findOrFail($request->mission_id);
        $fournisseur = User::findOrFail($request->fournisseur_id);

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

        $jcode = $this->jCodeService->generate(
            $mission,
            $user,
            $fournisseur,
            $request->validated('items'),
            $request->validated('montant'),
        );

        return response()->json([
            'success' => true,
            'data'    => new JCodeResource($jcode),
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
            ->with(['artisan', 'mission', 'fournisseur.fournisseurAgree', 'items.supplierProduct'])
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
            'data'    => new JCodeResource($jcode->load('artisan', 'fournisseur.fournisseurAgree', 'items.supplierProduct')),
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

    /**
     * Upload photo géolocalisée des matériaux reçus sur chantier
     * POST /api/v1/jcodes/{jcode}/photo-materiaux
     */
    public function uploadPhotoMateriaux(UploadJCodePhotoRequest $request, JCode $jcode): JsonResponse
    {
        try {
            $user = auth()->user();

            // Vérifier que l'utilisateur est bien l'artisan du J-Code
            if ($jcode->artisan_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Non autorisé'
                ], 403);
            }

            // Vérifier que le J-Code a été scanné (utilisé)
            if ($jcode->statut !== 'utilise') {
                return response()->json([
                    'success' => false,
                    'message' => 'Le J-Code doit être utilisé avant d\'uploader une photo'
                ], 400);
            }

            // Vérifier qu'il n'y a pas déjà une photo
            if ($jcode->photo_materiaux_url) {
                return response()->json([
                    'success' => false,
                    'message' => 'Une photo a déjà été uploadée pour ce J-Code'
                ], 400);
            }

            // Upload de la photo
            $uploaded = $this->photoService->uploadGeolocatedPhoto(
                $request->file('photo'),
                (float) $request->latitude,
                (float) $request->longitude,
                'jcode',
                $jcode->id
            );

            // Mise à jour du J-Code
            $jcode->update([
                'photo_materiaux_url' => $uploaded['url'],
                'photo_latitude' => $uploaded['latitude'],
                'photo_longitude' => $uploaded['longitude'],
                'photo_taken_at' => now(),
            ]);

            // Notifier le client
            $client = $jcode->mission->client;
            $this->notificationService->send(
                $client,
                'materials',
                'Vos matériaux sont arrivés !',
                "L'artisan {$jcode->artisan->name} a reçu les matériaux pour votre mission #{$jcode->mission_id}. Photo géolocalisée disponible.",
                [
                    'mission_id' => $jcode->mission_id,
                    'jcode_id' => $jcode->id,
                    'photo_url' => $uploaded['url'],
                ]
            );

            Log::info('Photo matériaux J-Code uploadée et client notifié', [
                'jcode_id' => $jcode->id,
                'mission_id' => $jcode->mission_id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Photo des matériaux uploadée avec succès',
                'data' => [
                    'jcode_id' => $jcode->id,
                    'photo_url' => $uploaded['url'],
                    'latitude' => $uploaded['latitude'],
                    'longitude' => $uploaded['longitude'],
                    'taken_at' => $uploaded['taken_at'],
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur upload photo matériaux J-Code', [
                'jcode_id' => $jcode->id,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'upload de la photo: ' . $e->getMessage()
            ], 500);
        }
    }
}
