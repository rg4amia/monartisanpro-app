<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\PaymentProvider;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Devis;
use App\Models\Mission;
use App\Models\Transaction;
use App\Services\OrangeMoneyService;
use App\Services\WaveService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class PaymentController extends Controller
{
    public function __construct(
        protected WaveService $waveService,
        protected OrangeMoneyService $orangeMoneyService
    ) {}

    /**
     * Initier un paiement pour une mission (acompte).
     * POST /api/v1/payments/initiate
     */
    public function initiatePayment(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'mission_id' => 'required|exists:missions,id',
            'devis_id' => 'required|exists:devis,id',
            'montant' => 'required|integer|min:100',
            'provider' => 'required|in:wave,orange_money,virement_bancaire',
            'phone' => 'required_if:provider,wave,orange_money|nullable|string|max:20',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Données de validation invalides',
                'errors' => $validator->errors(),
            ], 422);
        }

        // RÈGLE SÉCURITÉ GRANDS COMPTES: Enforcer virement_bancaire si montant >= 2 000 000 FCFA
        $seuil = config('prosartisan.mission.referent_threshold', 2000000);
        $montant = (int) $request->montant;
        if ($montant >= $seuil && in_array($request->provider, ['wave', 'orange_money'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Les paiements Mobile Money sont limités à ' . number_format($seuil, 0, ',', ' ') . ' FCFA. Veuillez effectuer un virement bancaire.',
            ], 422);
        }

        try {
            $mission = Mission::findOrFail($request->mission_id);
            $devis = Devis::where('mission_id', $mission->id)->findOrFail($request->devis_id);
            $client = $request->user();

            if ($mission->client_id !== $client->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous n\'êtes pas autorisé à payer pour cette mission',
                ], 403);
            }

            if ((string) $mission->status !== 'draft') {
                return response()->json([
                    'success' => false,
                    'message' => 'Cette mission n\'est pas en attente de paiement',
                ], 400);
            }

            if (! in_array($devis->statut, ['soumis', 'accepte'], true)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ce devis ne peut pas être payé dans son état actuel.',
                ], 422);
            }

            $montant = (int) $request->montant;
            $montantAttendu = $devis->montant_total;

            if ($montant !== $montantAttendu) {
                return response()->json([
                    'success' => false,
                    'message' => 'Le montant du paiement doit correspondre exactement au montant du devis.',
                ], 422);
            }

            $provider = PaymentProvider::from($request->provider);
            $phone = (string) ($request->phone ?? $client->phone ?? '');

            $transaction = Transaction::create([
                'mission_id' => $mission->id,
                'user_id' => $client->id,
                'type' => 'acompte',
                'montant' => $montant,
                'wallet_source' => $provider === PaymentProvider::VIREMENT_BANCAIRE ? 'client_bank_' . $client->id : 'client_mobile_money_' . $client->id,
                'wallet_dest' => 'escrow_mission_' . $mission->id,
                'provider' => $provider,
                'statut' => PaymentStatus::EN_ATTENTE,
                'client_phone' => $phone,
                'metadata' => [
                    'mission_id' => $mission->id,
                    'devis_id' => $devis->id,
                    'description' => "Acompte mission #{$mission->id}",
                ],
            ]);

            if ($provider === PaymentProvider::WAVE) {
                $result = $this->waveService->createCheckout(
                    $montant,
                    $phone,
                    "Acompte mission #{$mission->id}",
                    ['transaction_id' => $transaction->id, 'devis_id' => $devis->id]
                );

                $transaction->update([
                    'wave_checkout_id' => $result['checkout_id'],
                    'wave_client_reference' => $result['checkout_id'],
                    'reference_externe' => $result['checkout_id'],
                    'metadata' => array_merge($transaction->metadata ?? [], [
                        'payment_url' => $result['checkout_url'],
                        'wave_launch_url' => $result['wave_launch_url'],
                    ]),
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Paiement Wave initié avec succès',
                    'data' => [
                        'transaction_id' => $transaction->id,
                        'devis_id' => $devis->id,
                        'payment_url' => $result['checkout_url'],
                        'wave_launch_url' => $result['wave_launch_url'],
                        'provider' => 'wave',
                    ],
                ]);
            }

            if ($provider === PaymentProvider::ORANGE_MONEY) {
                $result = $this->orangeMoneyService->createPayment(
                    $montant,
                    $phone,
                    "Acompte mission #{$mission->id}",
                    ['transaction_id' => $transaction->id, 'devis_id' => $devis->id]
                );

                $transaction->update([
                    'orange_order_id' => $result['order_id'],
                    'orange_payment_token' => $result['payment_token'],
                    'reference_externe' => $result['order_id'],
                    'metadata' => array_merge($transaction->metadata ?? [], [
                        'payment_url' => $result['payment_url'],
                        'order_id' => $result['order_id'],
                    ]),
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Paiement Orange Money initié avec succès',
                    'data' => [
                        'transaction_id' => $transaction->id,
                        'devis_id' => $devis->id,
                        'payment_url' => $result['payment_url'],
                        'order_id' => $result['order_id'],
                        'provider' => 'orange_money',
                    ],
                ]);
            }

            if ($provider === PaymentProvider::VIREMENT_BANCAIRE) {
                $reference = 'REF-' . str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

                $transaction->update([
                    'reference_externe' => $reference,
                    'metadata' => array_merge($transaction->metadata ?? [], [
                        'bank_name' => 'ECOBANK CI',
                        'bank_account_name' => 'PROSARTISAN ESCROW',
                        'bank_iban' => 'CI59 CI05 9012 3456 7890 12',
                        'bank_reference' => $reference,
                        'description' => 'Instructions de virement bancaire pour acompte',
                    ]),
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Paiement par Virement Bancaire initié avec succès',
                    'data' => [
                        'transaction_id' => $transaction->id,
                        'devis_id' => $devis->id,
                        'provider' => 'virement_bancaire',
                        'virement_instructions' => [
                            'bank_name' => 'ECOBANK CI',
                            'account_name' => 'PROSARTISAN ESCROW',
                            'iban' => 'CI59 CI05 9012 3456 7890 12',
                            'reference' => $reference,
                        ],
                    ],
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Provider de paiement non supporté',
            ], 400);
        } catch (\Exception $e) {
            Log::error('Erreur initiation paiement', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'initiation du paiement: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Vérifier le statut d'un paiement.
     * GET /api/v1/payments/{transaction}/status
     */
    public function checkStatus(Request $request, Transaction $transaction): JsonResponse
    {
        try {
            $client = $request->user();

            if ($transaction->user_id !== $client->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Non autorisé',
                ], 403);
            }

            if ($transaction->statut->isFinalized()) {
                return response()->json([
                    'success' => true,
                    'data' => $this->serializeStatus($transaction),
                ]);
            }

            if ($transaction->isWave()) {
                $result = $this->waveService->checkPaymentStatus($transaction->wave_checkout_id);

                if (in_array($result['status'], ['completed', 'success'], true)) {
                    $transaction->update([
                        'statut' => PaymentStatus::CONFIRME,
                        'wave_payment_id' => $result['payment_id'],
                        'paid_at' => now(),
                    ]);
                } elseif (in_array($result['status'], ['failed', 'cancelled'], true)) {
                    $transaction->update([
                        'statut' => PaymentStatus::ECHOUE,
                        'failed_at' => now(),
                        'error_message' => 'Paiement Wave échoué ou annulé.',
                    ]);
                }
            } elseif ($transaction->isOrangeMoney()) {
                $result = $this->orangeMoneyService->checkPaymentStatus(
                    $transaction->orange_order_id,
                    $transaction->orange_payment_token
                );

                if (in_array($result['status'], ['SUCCESS', 'SUCCESSFUL'], true)) {
                    $transaction->update([
                        'statut' => PaymentStatus::CONFIRME,
                        'orange_tx_reference' => $result['tx_reference'],
                        'paid_at' => now(),
                    ]);
                } elseif (in_array($result['status'], ['FAILED', 'CANCELLED'], true)) {
                    $transaction->update([
                        'statut' => PaymentStatus::ECHOUE,
                        'failed_at' => now(),
                        'error_message' => 'Paiement Orange Money échoué ou annulé.',
                    ]);
                }
            }

            $transaction->refresh();

            return response()->json([
                'success' => true,
                'data' => $this->serializeStatus($transaction),
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur vérification statut paiement', [
                'transaction_id' => $transaction->id,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la vérification du statut',
            ], 500);
        }
    }

    /**
     * Obtenir l'historique des paiements d'un utilisateur.
     * GET /api/v1/payments/history
     */
    public function history(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
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
                ]),
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur récupération historique paiements', [
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération de l\'historique',
            ], 500);
        }
    }

    protected function serializeStatus(Transaction $transaction): array
    {
        return [
            'transaction_id' => $transaction->id,
            'status' => $transaction->statut->value,
            'montant' => $transaction->montant,
            'provider' => $transaction->provider->value,
            'mission_id' => $transaction->mission_id,
            'devis_id' => $transaction->metadata['devis_id'] ?? null,
            'paid_at' => $transaction->paid_at?->toIso8601String(),
            'failed_at' => $transaction->failed_at?->toIso8601String(),
        ];
    }
}
