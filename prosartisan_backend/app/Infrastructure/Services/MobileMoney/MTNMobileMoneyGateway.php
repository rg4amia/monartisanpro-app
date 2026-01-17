<?php

namespace App\Infrastructure\Services\MobileMoney;

use App\Domain\Financial\Services\MobileMoneyGateway;
use App\Domain\Financial\Services\MobileMoneyTransactionResult;
use App\Domain\Financial\Services\MobileMoneyTransactionStatus;
use App\Domain\Identity\Models\ValueObjects\UserId;
use App\Domain\Identity\Models\ValueObjects\PhoneNumber;
use App\Domain\Shared\ValueObjects\MoneyAmount;
use Illuminate\Http\Client\Factory as HttpClient;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Exception;
use DateTime;

/**
 * MTN Mobile Money Gateway Implementation
 *
 * Integrates with MTN Mobile Money API for mobile money transactions in Côte d'Ivoire.
 * MTN Mobile Money supports phone numbers starting with +225 05, 06.
 *
 * Requirements: 4.4, 4.5, 15.1, 15.4, 15.5
 */
final class MTNMobileMoneyGateway implements MobileMoneyGateway
{
    private HttpClient $httpClient;
    private string $subscriptionKey;
    private string $apiUserId;
    private string $apiKey;
    private string $baseUrl;
    private string $webhookSecret;
    private ?string $accessToken = null;
    private ?DateTime $tokenExpiresAt = null;

    public function __construct(
        HttpClient $httpClient,
        string $subscriptionKey,
        string $apiUserId,
        string $apiKey,
        string $baseUrl,
        string $webhookSecret
    ) {
        $this->httpClient = $httpClient;
        $this->subscriptionKey = $subscriptionKey;
        $this->apiUserId = $apiUserId;
        $this->apiKey = $apiKey;
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->webhookSecret = $webhookSecret;
    }

    public function blockFunds(
        UserId $userId,
        PhoneNumber $phoneNumber,
        MoneyAmount $amount,
        string $reference
    ): MobileMoneyTransactionResult {
        try {
            $accessToken = $this->getAccessToken();
            if (!$accessToken) {
                return MobileMoneyTransactionResult::failure(
                    'Impossible d\'obtenir le token d\'accès MTN',
                    'MTN_AUTH_ERROR'
                );
            }

            $transactionId = Str::uuid()->toString();

            $payload = [
                'amount' => (string) $amount->toFloat(),
                'currency' => 'XOF',
                'externalId' => $reference,
                'payer' => [
                    'partyIdType' => 'MSISDN',
                    'partyId' => $this->formatPhoneNumber($phoneNumber),
                ],
                'payerMessage' => 'Blocage de fonds pour séquestre - ProSartisan',
                'payeeNote' => 'Escrow block for mission #' . $reference,
            ];

            $response = $this->httpClient
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $accessToken,
                    'X-Reference-Id' => $transactionId,
                    'X-Target-Environment' => config('services.mtn.environment', 'sandbox'),
                    'Ocp-Apim-Subscription-Key' => $this->subscriptionKey,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ])
                ->timeout(30)
                ->post($this->baseUrl . '/collection/v1_0/requesttopay', $payload);

            if ($response->successful() || $response->status() === 202) {
                return MobileMoneyTransactionResult::success(
                    $transactionId,
                    'PENDING',
                    $reference,
                    [
                        'payer_msisdn' => $this->formatPhoneNumber($phoneNumber),
                        'external_id' => $reference,
                    ]
                );
            }

            $errorData = $response->json();
            Log::error('MTN API error for blockFunds', [
                'status' => $response->status(),
                'response' => $errorData,
                'reference' => $reference,
            ]);

            return MobileMoneyTransactionResult::failure(
                $errorData['message'] ?? 'Erreur lors du blocage des fonds',
                $errorData['code'] ?? 'MTN_API_ERROR',
                ['http_status' => $response->status()]
            );
        } catch (Exception $e) {
            Log::error('MTN blockFunds exception', [
                'message' => $e->getMessage(),
                'reference' => $reference,
            ]);

            return MobileMoneyTransactionResult::failure(
                'Erreur de connexion avec MTN: ' . $e->getMessage(),
                'MTN_CONNECTION_ERROR'
            );
        }
    }

    public function transferFunds(
        UserId $fromUserId,
        PhoneNumber $fromPhone,
        UserId $toUserId,
        PhoneNumber $toPhone,
        MoneyAmount $amount,
        string $reference
    ): MobileMoneyTransactionResult {
        try {
            $accessToken = $this->getAccessToken();
            if (!$accessToken) {
                return MobileMoneyTransactionResult::failure(
                    'Impossible d\'obtenir le token d\'accès MTN',
                    'MTN_AUTH_ERROR'
                );
            }

            $transactionId = Str::uuid()->toString();

            $payload = [
                'amount' => (string) $amount->toFloat(),
                'currency' => 'XOF',
                'externalId' => $reference,
                'payee' => [
                    'partyIdType' => 'MSISDN',
                    'partyId' => $this->formatPhoneNumber($toPhone),
                ],
                'payerMessage' => 'Transfert de fonds - ProSartisan',
                'payeeNote' => 'Payment from ProSartisan platform',
            ];

            $response = $this->httpClient
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $accessToken,
                    'X-Reference-Id' => $transactionId,
                    'X-Target-Environment' => config('services.mtn.environment', 'sandbox'),
                    'Ocp-Apim-Subscription-Key' => $this->subscriptionKey,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ])
                ->timeout(30)
                ->post($this->baseUrl . '/disbursement/v1_0/transfer', $payload);

            if ($response->successful() || $response->status() === 202) {
                return MobileMoneyTransactionResult::success(
                    $transactionId,
                    'PENDING',
                    $reference,
                    [
                        'payee_msisdn' => $this->formatPhoneNumber($toPhone),
                        'external_id' => $reference,
                    ]
                );
            }

            $errorData = $response->json();
            Log::error('MTN API error for transferFunds', [
                'status' => $response->status(),
                'response' => $errorData,
                'reference' => $reference,
            ]);

            return MobileMoneyTransactionResult::failure(
                $errorData['message'] ?? 'Erreur lors du transfert',
                $errorData['code'] ?? 'MTN_API_ERROR',
                ['http_status' => $response->status()]
            );
        } catch (Exception $e) {
            Log::error('MTN transferFunds exception', [
                'message' => $e->getMessage(),
                'reference' => $reference,
            ]);

            return MobileMoneyTransactionResult::failure(
                'Erreur de connexion avec MTN: ' . $e->getMessage(),
                'MTN_CONNECTION_ERROR'
            );
        }
    }

    public function refundFunds(
        UserId $userId,
        PhoneNumber $phoneNumber,
        MoneyAmount $amount,
        string $reference
    ): MobileMoneyTransactionResult {
        // MTN refunds use the same transfer API
        return $this->transferFunds(
            $userId, // System user as sender
            $phoneNumber, // Dummy from phone (not used in disbursement)
            $userId, // User as recipient
            $phoneNumber,
            $amount,
            $reference
        );
    }

    public function checkTransactionStatus(string $providerTransactionId): MobileMoneyTransactionStatus
    {
        try {
            $accessToken = $this->getAccessToken();
            if (!$accessToken) {
                return new MobileMoneyTransactionStatus(
                    'FAILED',
                    $providerTransactionId,
                    null,
                    'Impossible d\'obtenir le token d\'accès MTN'
                );
            }

            // Try collection API first (for payments)
            $response = $this->httpClient
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $accessToken,
                    'X-Target-Environment' => config('services.mtn.environment', 'sandbox'),
                    'Ocp-Apim-Subscription-Key' => $this->subscriptionKey,
                    'Accept' => 'application/json',
                ])
                ->timeout(15)
                ->get($this->baseUrl . '/collection/v1_0/requesttopay/' . $providerTransactionId);

            if (!$response->successful()) {
                // Try disbursement API (for transfers)
                $response = $this->httpClient
                    ->withHeaders([
                        'Authorization' => 'Bearer ' . $accessToken,
                        'X-Target-Environment' => config('services.mtn.environment', 'sandbox'),
                        'Ocp-Apim-Subscription-Key' => $this->subscriptionKey,
                        'Accept' => 'application/json',
                    ])
                    ->timeout(15)
                    ->get($this->baseUrl . '/disbursement/v1_0/transfer/' . $providerTransactionId);
            }

            if ($response->successful()) {
                $data = $response->json();

                return new MobileMoneyTransactionStatus(
                    $data['status'],
                    $providerTransactionId,
                    $data['externalId'] ?? null,
                    $data['reason']['message'] ?? null,
                    isset($data['finishedAt']) ? new DateTime($data['finishedAt']) : null,
                    [
                        'amount' => $data['amount'] ?? null,
                        'currency' => $data['currency'] ?? null,
                        'payer_party_id' => $data['payer']['partyId'] ?? null,
                        'payee_party_id' => $data['payee']['partyId'] ?? null,
                    ]
                );
            }

            Log::error('MTN API error for checkTransactionStatus', [
                'status' => $response->status(),
                'transaction_id' => $providerTransactionId,
            ]);

            return new MobileMoneyTransactionStatus(
                'FAILED',
                $providerTransactionId,
                null,
                'Impossible de vérifier le statut de la transaction'
            );
        } catch (Exception $e) {
            Log::error('MTN checkTransactionStatus exception', [
                'message' => $e->getMessage(),
                'transaction_id' => $providerTransactionId,
            ]);

            return new MobileMoneyTransactionStatus(
                'FAILED',
                $providerTransactionId,
                null,
                'Erreur de connexion: ' . $e->getMessage()
            );
        }
    }

    public function getProviderName(): string
    {
        return 'MTN Mobile Money';
    }

    public function supportsPhoneNumber(PhoneNumber $phoneNumber): bool
    {
        // MTN Mobile Money supports specific prefixes in Côte d'Ivoire
        $phone = $phoneNumber->getValue();

        // Remove any spaces or dashes
        $cleanPhone = preg_replace('/[\s\-]/', '', $phone);

        // Check MTN prefixes: +225 05, 06
        return preg_match('/^(\+225|225|0)(05|06)[0-9]{6}$/', $cleanPhone) === 1;
    }

    /**
     * Verify webhook signature for security
     */
    public function verifyWebhookSignature(string $payload, string $signature): bool
    {
        $expectedSignature = hash_hmac('sha256', $payload, $this->webhookSecret);
        return hash_equals($expectedSignature, $signature);
    }

    private function getAccessToken(): ?string
    {
        // Check if current token is still valid
        if ($this->accessToken && $this->tokenExpiresAt && $this->tokenExpiresAt > new DateTime()) {
            return $this->accessToken;
        }

        try {
            $response = $this->httpClient
                ->withHeaders([
                    'Ocp-Apim-Subscription-Key' => $this->subscriptionKey,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ])
                ->withBasicAuth($this->apiUserId, $this->apiKey)
                ->timeout(15)
                ->post($this->baseUrl . '/collection/token/');

            if ($response->successful()) {
                $data = $response->json();
                $this->accessToken = $data['access_token'];

                // Set expiration time (subtract 60 seconds for safety)
                $expiresIn = $data['expires_in'] ?? 3600;
                $this->tokenExpiresAt = (new DateTime())->modify('+' . ($expiresIn - 60) . ' seconds');

                return $this->accessToken;
            }

            Log::error('MTN token request failed', [
                'status' => $response->status(),
                'response' => $response->json(),
            ]);
        } catch (Exception $e) {
            Log::error('MTN token request exception', [
                'message' => $e->getMessage(),
            ]);
        }

        return null;
    }

    private function formatPhoneNumber(PhoneNumber $phoneNumber): string
    {
        $phone = $phoneNumber->getValue();

        // Remove any spaces or dashes
        $cleanPhone = preg_replace('/[\s\-]/', '', $phone);

        // Remove country code and leading zero for MTN API
        if (preg_match('/^(\+225|225)(.+)$/', $cleanPhone, $matches)) {
            return $matches[2];
        } elseif (preg_match('/^0(.+)$/', $cleanPhone, $matches)) {
            return $matches[1];
        }

        return $cleanPhone;
    }
}
