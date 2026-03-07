<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\PaymentStatus;
use App\Enums\WalletType;
use App\Http\Controllers\Controller;
use App\Services\OrangeMoneyService;
use App\Services\WalletService;
use App\Services\WaveService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    public function __construct(
        protected WaveService $waveService,
        protected OrangeMoneyService $orangeMoneyService,
        protected WalletService $walletService
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

            // Si le paiement est confirmé, fragmenter le séquestre
            if ($transaction->statut === PaymentStatus::CONFIRME) {
                $this->handlePaymentSuccess($transaction);
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

            // Si le paiement est confirmé, fragmenter le séquestre
            if ($transaction->statut === PaymentStatus::CONFIRME) {
                $this->handlePaymentSuccess($transaction);
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

    /**
     * Gérer le succès d'un paiement (fragmenter le séquestre)
     *
     * @param \App\Models\Transaction $transaction
     * @return void
     */
    protected function handlePaymentSuccess($transaction): void
    {
        try {
            $mission = $transaction->mission;

            if (!$mission || $mission->status !== 'en_attente') {
                Log::info('Mission déjà traitée ou statut incorrect', [
                    'mission_id' => $mission?->id,
                    'status' => $mission?->status,
                ]);
                return;
            }

            $artisan = $mission->artisan;
            $client = $mission->client;

            // Récupérer le ratio depuis le devis accepté
            $devis = $mission->devis()->where('statut', 'accepte')->first();

            if (!$devis) {
                Log::warning('Aucun devis accepté trouvé pour la mission', ['mission_id' => $mission->id]);
                return;
            }

            // Calculer le ratio matériaux (par défaut 65% si non spécifié)
            $ratioMat = $devis->ratio_materiaux ?? 0.65;

            // Fragmenter le séquestre
            $this->walletService->fragmentEscrow(
                $mission,
                $client,
                $artisan,
                $transaction->montant,
                $ratioMat,
                $transaction
            );

            Log::info('Webhook: Paiement traité avec succès et séquestre fragmenté', [
                'transaction_id' => $transaction->id,
                'mission_id' => $mission->id,
            ]);

        } catch (\Exception $e) {
            Log::error('Webhook: Erreur lors de la gestion du succès du paiement', [
                'transaction_id' => $transaction->id,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
