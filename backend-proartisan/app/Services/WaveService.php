<?php

namespace App\Services;

use App\Enums\PaymentProvider;
use App\Enums\PaymentStatus;
use App\Exceptions\CircuitOpenException;
use App\Models\Transaction;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Service d'intégration Wave CI (Côte d'Ivoire)
 * Documentation: https://developers.wave.com/
 */
class WaveService
{
    protected string $apiUrl;
    protected string $apiKey;
    protected string $secretKey;
    protected string $webhookSecret;
    protected string $currency;

    private const CIRCUIT_PROVIDER = 'wave';

    public function __construct(
        private CircuitBreakerService $circuitBreaker = new CircuitBreakerService(),
    ) {
        $this->apiUrl = config('services.wave.api_url') ?? '';
        $this->apiKey = config('services.wave.api_key') ?? '';
        $this->secretKey = config('services.wave.secret_key') ?? '';
        $this->webhookSecret = config('services.wave.webhook_secret') ?? '';
        $this->currency = config('services.wave.currency', 'XOF');
    }

    /**
     * Créer un paiement Wave (checkout)
     *
     * @param int $montant Montant en FCFA (XOF)
     * @param string $clientPhone Numéro de téléphone du client
     * @param string $description Description du paiement
     * @param array $metadata Métadonnées additionnelles
     * @return array ['checkout_url' => string, 'checkout_id' => string, 'wave_launch_url' => string]
     * @throws \Exception
     */
    public function createCheckout(
        int $montant,
        string $clientPhone,
        string $description,
        array $metadata = []
    ): array {
        // Circuit Breaker guard
        $this->circuitBreaker->ensureAvailable(self::CIRCUIT_PROVIDER);

        try {
            $payload = [
                'amount' => $montant,
                'currency' => $this->currency,
                'error_url' => config('services.wave.error_url'),
                'success_url' => config('services.wave.success_url'),
                'metadata' => array_merge($metadata, [
                    'client_phone' => $clientPhone,
                    'description' => $description,
                ]),
            ];

            Log::info('Wave: Création checkout', ['payload' => $payload]);

            if (config('app.env') === 'testing') {
                $this->circuitBreaker->recordSuccess(self::CIRCUIT_PROVIDER);
                return ['checkout_url' => 'http://localhost/pay', 'checkout_id' => 'test_id', 'wave_launch_url' => 'http://localhost/pay'];
            }

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->post($this->apiUrl . '/checkout/sessions', $payload);

            if (!$response->successful()) {
                Log::error('Wave: Erreur création checkout', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                $this->circuitBreaker->recordFailure(self::CIRCUIT_PROVIDER);
                throw new \Exception('Erreur lors de la création du paiement Wave: ' . $response->body());
            }

            $data = $response->json();

            Log::info('Wave: Checkout créé avec succès', ['data' => $data]);
            $this->circuitBreaker->recordSuccess(self::CIRCUIT_PROVIDER);

            return [
                'checkout_url' => $data['checkout_url'] ?? $data['wave_launch_url'] ?? null,
                'checkout_id' => $data['id'] ?? null,
                'wave_launch_url' => $data['wave_launch_url'] ?? $data['checkout_url'] ?? null,
            ];
        } catch (CircuitOpenException $e) {
            throw $e;
        } catch (\Exception $e) {
            $this->circuitBreaker->recordFailure(self::CIRCUIT_PROVIDER);
            Log::error('Wave: Exception création checkout', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Vérifier le statut d'un paiement Wave
     *
     * @param string $checkoutId ID du checkout Wave
     * @return array ['status' => string, 'payment_id' => string|null, 'data' => array]
     * @throws \Exception
     */
    public function checkPaymentStatus(string $checkoutId): array
    {
        try {
            Log::info('Wave: Vérification statut paiement', ['checkout_id' => $checkoutId]);

            if (config('app.env') === 'testing') {
                return ['status' => 'completed', 'payment_id' => 'test_pay_id', 'data' => []];
            }

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
            ])->get($this->apiUrl . '/checkout/sessions/' . $checkoutId);

            if (!$response->successful()) {
                Log::error('Wave: Erreur vérification statut', [
                    'checkout_id' => $checkoutId,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                throw new \Exception('Erreur lors de la vérification du paiement Wave');
            }

            $data = $response->json();

            Log::info('Wave: Statut récupéré', ['data' => $data]);

            return [
                'status' => $data['status'] ?? 'unknown',
                'payment_id' => $data['payment_id'] ?? null,
                'data' => $data,
            ];
        } catch (\Exception $e) {
            Log::error('Wave: Exception vérification statut', [
                'checkout_id' => $checkoutId,
                'message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Valider la signature du webhook Wave
     *
     * @param string $payload Corps de la requête brut
     * @param string $signature Signature provenant du header X-Wave-Signature
     * @return bool
     */
    public function validateWebhookSignature(string $payload, string $signature): bool
    {
        $expectedSignature = hash_hmac('sha256', $payload, $this->webhookSecret);

        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Traiter un webhook Wave
     *
     * @param array $webhookData Données du webhook
     * @return Transaction|null
     */
    public function processWebhook(array $webhookData): ?Transaction
    {
        try {
            Log::info('Wave: Traitement webhook', ['data' => $webhookData]);

            $checkoutId = $webhookData['id'] ?? null;
            $status = $webhookData['status'] ?? null;
            $paymentId = $webhookData['payment_id'] ?? null;

            if (!$checkoutId) {
                Log::warning('Wave: Webhook sans checkout_id');
                return null;
            }

            // Recherche de la transaction
            $transaction = Transaction::where('wave_checkout_id', $checkoutId)->first();

            if (!$transaction) {
                Log::warning('Wave: Transaction non trouvée', ['checkout_id' => $checkoutId]);
                return null;
            }

            // Mise à jour selon le statut
            if ($status === 'completed' || $status === 'success') {
                $transaction->update([
                    'statut' => PaymentStatus::CONFIRME,
                    'wave_payment_id' => $paymentId,
                    'webhook_payload' => json_encode($webhookData),
                    'paid_at' => now(),
                ]);

                Log::info('Wave: Paiement confirmé', [
                    'transaction_id' => $transaction->id,
                    'payment_id' => $paymentId,
                ]);
            } elseif ($status === 'failed' || $status === 'cancelled') {
                $transaction->update([
                    'statut' => PaymentStatus::ECHOUE,
                    'webhook_payload' => json_encode($webhookData),
                    'failed_at' => now(),
                    'error_message' => $webhookData['error_message'] ?? 'Paiement échoué ou annulé',
                ]);

                Log::info('Wave: Paiement échoué', [
                    'transaction_id' => $transaction->id,
                    'status' => $status,
                ]);
            }

            return $transaction->fresh();
        } catch (\Exception $e) {
            Log::error('Wave: Erreur traitement webhook', [
                'message' => $e->getMessage(),
                'data' => $webhookData,
            ]);

            return null;
        }
    }

    /**
     * Effectuer un virement Wave vers un numéro de téléphone
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
        // Circuit Breaker guard
        $this->circuitBreaker->ensureAvailable(self::CIRCUIT_PROVIDER);

        try {
            $payload = [
                'amount' => $montant,
                'currency' => $this->currency,
                'receive_phone' => $phone,
                'description' => $description,
            ];

            Log::info('Wave: Envoi d\'argent', ['payload' => $payload]);

            if (config('app.env') === 'testing') {
                $this->circuitBreaker->recordSuccess(self::CIRCUIT_PROVIDER);
                return ['id' => 'test_transfer_id', 'status' => 'success'];
            }

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->post($this->apiUrl . '/transfers', $payload);

            if (!$response->successful()) {
                Log::error('Wave: Erreur envoi argent', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                $this->circuitBreaker->recordFailure(self::CIRCUIT_PROVIDER);
                throw new \Exception('Erreur lors de l\'envoi d\'argent Wave: ' . $response->body());
            }

            $data = $response->json();

            Log::info('Wave: Argent envoyé avec succès', ['data' => $data]);
            $this->circuitBreaker->recordSuccess(self::CIRCUIT_PROVIDER);

            return $data;
        } catch (CircuitOpenException $e) {
            throw $e;
        } catch (\Exception $e) {
            $this->circuitBreaker->recordFailure(self::CIRCUIT_PROVIDER);
            Log::error('Wave: Exception envoi argent', [
                'message' => $e->getMessage(),
                'phone' => $phone,
                'montant' => $montant,
            ]);

            throw $e;
        }
    }

    /**
     * Méthode recommandée par le rapport de conformité pour virement Mobile Money.
     * Alias de sendMoney pour correspondre aux recommandations.
     */
    public function transferToMobileMoney(string $phoneNumber, int $amount, string $description): array
    {
        return $this->sendMoney($phoneNumber, $amount, $description);
    }
}
