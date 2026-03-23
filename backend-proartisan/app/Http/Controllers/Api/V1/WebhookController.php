<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\OrangeMoneyService;
use App\Services\WaveService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    public function __construct(
        protected WaveService $waveService,
        protected OrangeMoneyService $orangeMoneyService
    ) {}

    /**
     * Webhook Wave CI
     * POST /api/webhooks/wave
     */
    public function wave(Request $request): JsonResponse
    {
        try {
            $payload = $request->getContent();
            $signature = $request->header('X-Wave-Signature');

            Log::info('Wave: Webhook reçu', [
                'payload' => $payload,
                'signature' => $signature,
            ]);

            // Valider la signature du webhook
            if (!$this->waveService->validateWebhookSignature($payload, $signature)) {
                Log::warning('Wave: Signature webhook invalide');

                return response()->json([
                    'success' => false,
                    'message' => 'Signature invalide'
                ], 401);
            }

            $data = json_decode($payload, true);

            // Traiter le webhook
            $transaction = $this->waveService->processWebhook($data);

            if (!$transaction) {
                return response()->json([
                    'success' => false,
                    'message' => 'Transaction non trouvée'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Webhook traité avec succès'
            ]);

        } catch (\Exception $e) {
            Log::error('Wave: Erreur traitement webhook', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur interne'
            ], 500);
        }
    }

    /**
     * Webhook Orange Money CI
     * POST /api/webhooks/orange-money
     */
    public function orangeMoney(Request $request): JsonResponse
    {
        try {
            $data = $request->all();

            Log::info('Orange Money: Webhook reçu', ['data' => $data]);

            // Traiter la notification
            $transaction = $this->orangeMoneyService->processNotification($data);

            if (!$transaction) {
                return response()->json([
                    'success' => false,
                    'message' => 'Transaction non trouvée'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Notification traitée avec succès'
            ]);

        } catch (\Exception $e) {
            Log::error('Orange Money: Erreur traitement notification', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur interne'
            ], 500);
        }
    }

}
