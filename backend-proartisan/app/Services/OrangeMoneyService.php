<?php

namespace App\Services;

use App\Enums\PaymentProvider;
use App\Enums\PaymentStatus;
use App\Models\Transaction;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Service d'intégration Orange Money CI (Côte d'Ivoire)
 * Documentation: https://developer.orange.com/apis/orange-money-webpay/
 */
class OrangeMoneyService
{
    protected string $apiUrl;
    protected string $merchantKey;
    protected string $merchantId;
    protected string $authHeader;
    protected string $currency;

    public function __construct()
    {
        $this->apiUrl = config('services.orange_money.api_url');
        $this->merchantKey = config('services.orange_money.merchant_key');
        $this->merchantId = config('services.orange_money.merchant_id');
        $this->authHeader = config('services.orange_money.auth_header');
        $this->currency = config('services.orange_money.currency', 'XOF');
    }

    /**
     * Obtenir un token d'authentification OAuth
     *
     * @return string
     * @throws \Exception
     */
    protected function getAccessToken(): string
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Basic ' . $this->authHeader,
                'Content-Type' => 'application/x-www-form-urlencoded',
            ])->asForm()->post($this->apiUrl . '/oauth/v3/token', [
                'grant_type' => 'client_credentials',
            ]);

            if (!$response->successful()) {
                Log::error('Orange Money: Erreur obtention token', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                throw new \Exception('Erreur lors de l\'authentification Orange Money');
            }

            $data = $response->json();

            return $data['access_token'] ?? throw new \Exception('Token non reçu');
        } catch (\Exception $e) {
            Log::error('Orange Money: Exception obtention token', [
                'message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Créer un paiement Orange Money (Web Payment)
     *
     * @param int $montant Montant en FCFA (XOF)
     * @param string $clientPhone Numéro de téléphone du client
     * @param string $description Description du paiement
     * @param array $metadata Métadonnées additionnelles
     * @return array ['payment_url' => string, 'order_id' => string, 'payment_token' => string]
     * @throws \Exception
     */
    public function createPayment(
        int $montant,
        string $clientPhone,
        string $description,
        array $metadata = []
    ): array {
        try {
            $token = $this->getAccessToken();

            // Génération d'un Order ID unique
            $orderId = 'OM-' . strtoupper(Str::random(16));

            $payload = [
                'merchant_key' => $this->merchantKey,
                'currency' => $this->currency,
                'order_id' => $orderId,
                'amount' => $montant,
                'return_url' => config('services.orange_money.return_url'),
                'cancel_url' => config('services.orange_money.cancel_url'),
                'notif_url' => config('services.orange_money.notif_url'),
                'lang' => 'fr',
                'reference' => $description,
            ];

            Log::info('Orange Money: Création paiement', ['payload' => $payload]);

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
            ])->post($this->apiUrl . '/webpayment/v1/webpaymentcommand', $payload);

            if (!$response->successful()) {
                Log::error('Orange Money: Erreur création paiement', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                throw new \Exception('Erreur lors de la création du paiement Orange Money: ' . $response->body());
            }

            $data = $response->json();

            Log::info('Orange Money: Paiement créé avec succès', ['data' => $data]);

            return [
                'payment_url' => $data['payment_url'] ?? null,
                'order_id' => $orderId,
                'payment_token' => $data['payment_token'] ?? $data['pay_token'] ?? null,
            ];
        } catch (\Exception $e) {
            Log::error('Orange Money: Exception création paiement', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Vérifier le statut d'un paiement Orange Money
     *
     * @param string $orderId ID de la commande
     * @param string $paymentToken Token du paiement
     * @return array ['status' => string, 'tx_reference' => string|null, 'data' => array]
     * @throws \Exception
     */
    public function checkPaymentStatus(string $orderId, string $paymentToken): array
    {
        try {
            $token = $this->getAccessToken();

            Log::info('Orange Money: Vérification statut paiement', [
                'order_id' => $orderId,
                'payment_token' => $paymentToken,
            ]);

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
            ])->post($this->apiUrl . '/webpayment/v1/transactionstatus', [
                'order_id' => $orderId,
                'amount' => 0, // 0 pour récupérer le statut sans montant
                'pay_token' => $paymentToken,
            ]);

            if (!$response->successful()) {
                Log::error('Orange Money: Erreur vérification statut', [
                    'order_id' => $orderId,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                throw new \Exception('Erreur lors de la vérification du paiement Orange Money');
            }

            $data = $response->json();

            Log::info('Orange Money: Statut récupéré', ['data' => $data]);

            return [
                'status' => $data['status'] ?? 'unknown',
                'tx_reference' => $data['txnid'] ?? $data['tx_reference'] ?? null,
                'data' => $data,
            ];
        } catch (\Exception $e) {
            Log::error('Orange Money: Exception vérification statut', [
                'order_id' => $orderId,
                'message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Traiter une notification Orange Money (callback)
     *
     * @param array $notificationData Données de la notification
     * @return Transaction|null
     */
    public function processNotification(array $notificationData): ?Transaction
    {
        try {
            Log::info('Orange Money: Traitement notification', ['data' => $notificationData]);

            $orderId = $notificationData['order_id'] ?? null;
            $status = $notificationData['status'] ?? null;
            $txReference = $notificationData['txnid'] ?? $notificationData['tx_reference'] ?? null;

            if (!$orderId) {
                Log::warning('Orange Money: Notification sans order_id');
                return null;
            }

            // Recherche de la transaction
            $transaction = Transaction::where('orange_order_id', $orderId)->first();

            if (!$transaction) {
                Log::warning('Orange Money: Transaction non trouvée', ['order_id' => $orderId]);
                return null;
            }

            // Mise à jour selon le statut
            if ($status === 'SUCCESS' || $status === 'SUCCESSFUL' || $status === 'INITIATED') {
                $transaction->update([
                    'statut' => PaymentStatus::CONFIRME,
                    'orange_tx_reference' => $txReference,
                    'webhook_payload' => json_encode($notificationData),
                    'paid_at' => now(),
                ]);

                Log::info('Orange Money: Paiement confirmé', [
                    'transaction_id' => $transaction->id,
                    'tx_reference' => $txReference,
                ]);
            } elseif ($status === 'FAILED' || $status === 'CANCELLED' || $status === 'EXPIRED') {
                $transaction->update([
                    'statut' => PaymentStatus::ECHOUE,
                    'webhook_payload' => json_encode($notificationData),
                    'failed_at' => now(),
                    'error_message' => $notificationData['error_message'] ?? 'Paiement échoué, annulé ou expiré',
                ]);

                Log::info('Orange Money: Paiement échoué', [
                    'transaction_id' => $transaction->id,
                    'status' => $status,
                ]);
            }

            return $transaction->fresh();
        } catch (\Exception $e) {
            Log::error('Orange Money: Erreur traitement notification', [
                'message' => $e->getMessage(),
                'data' => $notificationData,
            ]);

            return null;
        }
    }

    /**
     * Effectuer un transfert Orange Money vers un numéro de téléphone
     * (Utile pour les paiements sortants vers artisans/fournisseurs)
     *
     * @param string $phone Numéro de téléphone destinataire
     * @param int $montant Montant en FCFA
     * @param string $description
     * @return array
     * @throws \Exception
     */
    public function sendMoney(string $phone, int $montant, string $description): array
    {
        try {
            $token = $this->getAccessToken();

            $payload = [
                'partner_id' => $this->merchantId,
                'order_id' => 'TRF-' . strtoupper(Str::random(16)),
                'amount' => $montant,
                'currency' => $this->currency,
                'receiver_phone_number' => $phone,
                'description' => $description,
            ];

            Log::info('Orange Money: Envoi d\'argent', ['payload' => $payload]);

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
            ])->post($this->apiUrl . '/cashin/v1/payments', $payload);

            if (!$response->successful()) {
                Log::error('Orange Money: Erreur envoi argent', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                throw new \Exception('Erreur lors de l\'envoi d\'argent Orange Money: ' . $response->body());
            }

            $data = $response->json();

            Log::info('Orange Money: Argent envoyé avec succès', ['data' => $data]);

            return $data;
        } catch (\Exception $e) {
            Log::error('Orange Money: Exception envoi argent', [
                'message' => $e->getMessage(),
                'phone' => $phone,
                'montant' => $montant,
            ]);

            throw $e;
        }
    }
}
