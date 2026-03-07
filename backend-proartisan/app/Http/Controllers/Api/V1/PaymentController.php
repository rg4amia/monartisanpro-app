<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\PaymentProvider;
use App\Enums\PaymentStatus;
use App\Enums\WalletType;
use App\Http\Controllers\Controller;
use App\Models\Mission;
use App\Models\Transaction;
use App\Models\User;
use App\Services\OrangeMoneyService;
use App\Services\WalletService;
use App\Services\WaveService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class PaymentController extends Controller
{
    public function __construct(
        protected WaveService $waveService,
        protected OrangeMoneyService $orangeMoneyService,
        protected WalletService $walletService
    ) {}

    /**
     * Initier un paiement pour une mission (acompte)
     * POST /api/v1/payments/initiate
     */
    public function initiatePayment(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'mission_id' => 'required|exists:missions,id',
            'montant' => 'required|integer|min:100',
            'provider' => 'required|in:wave,orange_money',
            'phone' => 'required|string|max:20',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Données de validation invalides',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $mission = Mission::findOrFail($request->mission_id);
            $client = auth()->user();

            // Vérifier que l'utilisateur est bien le client de la mission
            if ($mission->client_id !== $client->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous n\'êtes pas autorisé à payer pour cette mission'
                ], 403);
            }

            // Vérifier que la mission est en attente de paiement
            if ($mission->status !== 'en_attente') {
                return response()->json([
                    'success' => false,
                    'message' => 'Cette mission n\'est pas en attente de paiement'
                ], 400);
            }

            $provider = PaymentProvider::from($request->provider);
            $montant = $request->montant;
            $phone = $request->phone;

            // Créer la transaction en attente
            $transaction = Transaction::create([
                'mission_id' => $mission->id,
                'user_id' => $client->id,
                'type' => 'acompte',
                'montant' => $montant,
                'wallet_source' => 'client_mobile_money_' . $client->id,
                'wallet_dest' => 'escrow_mission_' . $mission->id,
                'provider' => $provider,
                'statut' => PaymentStatus::EN_ATTENTE,
                'client_phone' => $phone,
                'metadata' => [
                    'mission_id' => $mission->id,
                    'description' => "Acompte mission #{$mission->id}",
                ],
            ]);

            // Initier le paiement selon le provider
            if ($provider === PaymentProvider::WAVE) {
                $result = $this->waveService->createCheckout(
                    $montant,
                    $phone,
                    "Acompte mission #{$mission->id}",
                    ['transaction_id' => $transaction->id]
                );

                $transaction->update([
                    'wave_checkout_id' => $result['checkout_id'],
                    'wave_client_reference' => $result['checkout_id'],
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Paiement Wave initié avec succès',
                    'data' => [
                        'transaction_id' => $transaction->id,
                        'payment_url' => $result['checkout_url'],
                        'wave_launch_url' => $result['wave_launch_url'],
                        'provider' => 'wave',
                    ]
                ]);
            } elseif ($provider === PaymentProvider::ORANGE_MONEY) {
                $result = $this->orangeMoneyService->createPayment(
                    $montant,
                    $phone,
                    "Acompte mission #{$mission->id}",
                    ['transaction_id' => $transaction->id]
                );

                $transaction->update([
                    'orange_order_id' => $result['order_id'],
                    'orange_payment_token' => $result['payment_token'],
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Paiement Orange Money initié avec succès',
                    'data' => [
                        'transaction_id' => $transaction->id,
                        'payment_url' => $result['payment_url'],
                        'order_id' => $result['order_id'],
                        'provider' => 'orange_money',
                    ]
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Provider de paiement non supporté'
            ], 400);

        } catch (\Exception $e) {
            Log::error('Erreur initiation paiement', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'initiation du paiement: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Vérifier le statut d'un paiement
     * GET /api/v1/payments/{transaction_id}/status
     */
    public function checkStatus(int $transactionId): JsonResponse
    {
        try {
            $transaction = Transaction::findOrFail($transactionId);
            $client = auth()->user();

            // Vérifier que l'utilisateur est autorisé à voir cette transaction
            if ($transaction->user_id !== $client->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Non autorisé'
                ], 403);
            }

            // Si déjà confirmé ou échoué, retourner le statut
            if ($transaction->statut->isFinalized()) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'transaction_id' => $transaction->id,
                        'status' => $transaction->statut->value,
                        'montant' => $transaction->montant,
                        'provider' => $transaction->provider->value,
                        'paid_at' => $transaction->paid_at?->toIso8601String(),
                        'failed_at' => $transaction->failed_at?->toIso8601String(),
                    ]
                ]);
            }

            // Vérifier auprès du provider
            if ($transaction->isWave()) {
                $result = $this->waveService->checkPaymentStatus($transaction->wave_checkout_id);

                if ($result['status'] === 'completed' || $result['status'] === 'success') {
                    $transaction->update([
                        'statut' => PaymentStatus::CONFIRME,
                        'wave_payment_id' => $result['payment_id'],
                        'paid_at' => now(),
                    ]);

                    // Fragmenter le séquestre si paiement confirmé
                    $this->handlePaymentSuccess($transaction);
                }
            } elseif ($transaction->isOrangeMoney()) {
                $result = $this->orangeMoneyService->checkPaymentStatus(
                    $transaction->orange_order_id,
                    $transaction->orange_payment_token
                );

                if ($result['status'] === 'SUCCESS' || $result['status'] === 'SUCCESSFUL') {
                    $transaction->update([
                        'statut' => PaymentStatus::CONFIRME,
                        'orange_tx_reference' => $result['tx_reference'],
                        'paid_at' => now(),
                    ]);

                    // Fragmenter le séquestre si paiement confirmé
                    $this->handlePaymentSuccess($transaction);
                }
            }

            $transaction->refresh();

            return response()->json([
                'success' => true,
                'data' => [
                    'transaction_id' => $transaction->id,
                    'status' => $transaction->statut->value,
                    'montant' => $transaction->montant,
                    'provider' => $transaction->provider->value,
                    'paid_at' => $transaction->paid_at?->toIso8601String(),
                    'failed_at' => $transaction->failed_at?->toIso8601String(),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur vérification statut paiement', [
                'transaction_id' => $transactionId,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la vérification du statut'
            ], 500);
        }
    }

    /**
     * Obtenir l'historique des paiements d'un utilisateur
     * GET /api/v1/payments/history
     */
    public function history(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();
            $limit = $request->query('limit', 20);

            $transactions = Transaction::where('user_id', $user->id)
                ->orderBy('created_at', 'desc')
                ->limit($limit)
                ->get();

            return response()->json([
                'success' => true,
                'data' => $transactions->map(fn($tx) => [
                    'id' => $tx->id,
                    'type' => $tx->type,
                    'montant' => $tx->montant,
                    'provider' => $tx->provider->value,
                    'statut' => $tx->statut->value,
                    'mission_id' => $tx->mission_id,
                    'created_at' => $tx->created_at->toIso8601String(),
                    'paid_at' => $tx->paid_at?->toIso8601String(),
                ])
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur récupération historique paiements', [
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération de l\'historique'
            ], 500);
        }
    }

    /**
     * Gérer le succès d'un paiement (fragmenter le séquestre)
     *
     * @param Transaction $transaction
     * @return void
     */
    protected function handlePaymentSuccess(Transaction $transaction): void
    {
        try {
            $mission = $transaction->mission;

            if (!$mission || $mission->status !== 'en_attente') {
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

            Log::info('Paiement traité avec succès et séquestre fragmenté', [
                'transaction_id' => $transaction->id,
                'mission_id' => $mission->id,
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur lors de la gestion du succès du paiement', [
                'transaction_id' => $transaction->id,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
