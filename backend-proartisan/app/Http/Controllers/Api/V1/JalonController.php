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
    public function __construct(private JalonService $jalonService) {}

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
     * Demande d'OTP : envoie un code SMS au client.
     */
    public function requestOtp(Request $request, Jalon $jalon): JsonResponse
    {
        if ($jalon->statut !== 'soumis') {
            return response()->json([
                'success' => false,
                'message' => 'Le jalon doit être soumis avant de demander un OTP.',
            ], 422);
        }

        $this->jalonService->requestOtp($jalon);

        return response()->json([
            'success' => true,
            'message' => 'Code OTP envoyé par SMS au client.',
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
}
