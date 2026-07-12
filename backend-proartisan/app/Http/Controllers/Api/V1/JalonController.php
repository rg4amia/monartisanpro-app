<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Jalon\SubmitJalonRequest;
use App\Http\Requests\Jalon\ValidateOtpRequest;
use App\Http\Requests\UploadJalonPhotosRequest;
use App\Http\Resources\JalonResource;
use App\Models\Jalon;
use App\Models\Mission;
use App\Services\JalonService;
use App\Services\PhotoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class JalonController extends Controller
{
    public function __construct(
        private JalonService $jalonService,
        private PhotoService $photoService
    ) {}

    public function index(Mission $mission): JsonResponse
    {
        $jalons = $mission->jalons()->orderBy('ordre')->get();

        return response()->json([
            'success' => true,
            'data'    => JalonResource::collection($jalons),
        ]);
    }

    /**
     * Artisan soumet un jalon terminé avec photos géolocalisées.
     */
    public function submit(SubmitJalonRequest $request, Jalon $jalon): JsonResponse
    {
        if ($jalon->statut !== 'en_attente') {
            return response()->json([
                'success' => false,
                'message' => 'Ce jalon ne peut plus être soumis.',
            ], 422);
        }

        $this->jalonService->submit($jalon, $request->validated()['photos'] ?? []);

        return response()->json([
            'success' => true,
            'message' => 'Jalon soumis. Le client recevra un OTP de validation.',
            'data'    => new JalonResource($jalon->fresh()),
        ]);
    }

    /**
     * Demande d'OTP : envoie un code SMS ou WhatsApp au client.
     */
    public function requestOtp(Request $request, Jalon $jalon): JsonResponse
    {
        if ($jalon->statut !== 'soumis') {
            return response()->json([
                'success' => false,
                'message' => 'Le jalon doit être soumis avant de demander un OTP.',
            ], 422);
        }

        $channel = $request->input('channel');
        if ($channel && !in_array($channel, ['sms', 'whatsapp'])) {
            return response()->json([
                'success' => false,
                'message' => 'Le canal de communication doit être "sms" ou "whatsapp".',
            ], 422);
        }

        $this->jalonService->requestOtp($jalon, $channel);

        $globalChannel = \App\Models\Setting::getValueByKey('otp_delivery_channel', 'sms');
        $effectiveChannel = $channel ?: $globalChannel;

        $msg = 'Code OTP envoyé au client.';
        if ($effectiveChannel === 'both') {
            $msg = 'Code OTP envoyé par SMS et WhatsApp au client.';
        } elseif ($effectiveChannel === 'whatsapp') {
            $msg = 'Code OTP envoyé par WhatsApp au client.';
        } else {
            $msg = 'Code OTP envoyé par SMS au client.';
        }

        return response()->json([
            'success' => true,
            'message' => $msg,
            'expires_in' => config('prosartisan.otp.ttl', 5) * 60,
        ]);
    }

    /**
     * Client valide le jalon avec son OTP.
     * RÈGLE CRITIQUE : libération fonds impossible sans OTP valide.
     */
    public function validateOtp(ValidateOtpRequest $request, Jalon $jalon): JsonResponse
    {
        if ($jalon->statut !== 'soumis') {
            return response()->json([
                'success' => false,
                'message' => 'Ce jalon n\'est pas en attente de validation.',
            ], 422);
        }

        $success = $this->jalonService->validateOtp($jalon, $request->otp);

        if (! $success) {
            return response()->json([
                'success' => false,
                'message' => 'Code OTP invalide ou expiré.',
            ], 422);
        }

        $jalon->refresh();
        $message = $jalon->mission->referent_required
            ? 'Jalon validé. Un référent de zone doit confirmer avant le paiement (mission > 2 000 000 FCFA).'
            : 'Jalon validé et paiement libéré vers l\'artisan.';

        return response()->json([
            'success' => true,
            'message' => $message,
            'data'    => new JalonResource($jalon),
        ]);
    }

    /**
     * Upload de photos géolocalisées pour un jalon
     * POST /api/v1/jalons/{jalon}/photos
     */
    public function uploadPhotos(UploadJalonPhotosRequest $request, Jalon $jalon): JsonResponse
    {
        try {
            $user = auth()->user();

            // Vérifier que l'utilisateur est bien l'artisan de la mission
            if ($jalon->mission->artisan_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Non autorisé'
                ], 403);
            }

            // Vérifier que le jalon n'est pas déjà validé
            if ($jalon->statut !== 'en_attente') {
                return response()->json([
                    'success' => false,
                    'message' => 'Ce jalon ne peut plus recevoir de photos'
                ], 400);
            }

            $photosData = [];

            foreach ($request->photos as $index => $photoInput) {
                try {
                    $uploaded = $this->photoService->uploadGeolocatedPhoto(
                        $photoInput['photo'],
                        (float) $photoInput['latitude'],
                        (float) $photoInput['longitude'],
                        'jalon',
                        $jalon->id
                    );

                    $photosData[] = array_merge($uploaded, [
                        'description' => $photoInput['description'] ?? null,
                    ]);

                } catch (\Exception $e) {
                    Log::warning('Échec upload photo jalon', [
                        'jalon_id' => $jalon->id,
                        'photo_index' => $index,
                        'error' => $e->getMessage(),
                    ]);
                    // Continue avec les autres photos
                }
            }

            if (empty($photosData)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Aucune photo n\'a pu être uploadée'
                ], 500);
            }

            // Ajouter les photos au jalon (merge avec existantes)
            $existingPhotos = $jalon->photos_json ?? [];
            $allPhotos = array_merge($existingPhotos, $photosData);

            $jalon->update([
                'photos_json' => $allPhotos
            ]);

            Log::info('Photos jalons uploadées', [
                'jalon_id' => $jalon->id,
                'count' => count($photosData),
            ]);

            return response()->json([
                'success' => true,
                'message' => count($photosData) . ' photo(s) uploadée(s) avec succès',
                'data' => [
                    'jalon_id' => $jalon->id,
                    'photos_uploaded' => count($photosData),
                    'total_photos' => count($allPhotos),
                    'photos' => $photosData,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur upload photos jalon', [
                'jalon_id' => $jalon->id,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'upload des photos'
            ], 500);
        }
    }
}
