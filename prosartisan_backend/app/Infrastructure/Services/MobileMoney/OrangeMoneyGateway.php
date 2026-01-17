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
use Exception;
use DateTime;

/**
 * Orange Money Gateway Implementation
 *
 * Integrates with Orange Money API for mobile money transactions in Côte d'Ivoire.
 * Orange Money supports phone numbers starting with +225 07, 08, 09.
 *
 * Requirements: 4.4, 4.5, 15.1, 15.4, 15.5
 */
final class OrangeMoneyGateway implements MobileMoneyGateway
{
    private HttpClient $httpClient;
    private string $clientId;
    private string $clientSecret;
    private string $baseUrl;
    private string $webhookSecret;
    private ?string $accessToken = null;
    private ?DateTime $tokenExpiresAt = null;

    public function __construct(
        HttpClient $httpClient,
        string $clientId,
        string $clientSecret,
        string $baseUrl,
        string $webhookSecret
    ) {
        $this->httpClient = $httpClient;
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
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
                    'Impossible d\'obtenir le token d\'accès Orange Money',
                    'ORANGE_AUTH_ERROR'
                );
            }

            $payload = [
                'amount' => [
                    'value' => $amount->toFloat(),
                    'currency' => 'XOF',
                ],
                'customer' => [
                    'msisdn' => $this->formatPhoneNumber($phoneNumber),
                ],
                'partner' => [
                    'reference' => $reference,
                    'fee' => 0, // No additional fee for escrow block
                ],
                'description' => 'Blocage de fonds pour séquestre - ProSartisan',
                'metadata' => [
                    'user_id' => $userId->getValue(),
                    'operation_type' => 'escrow_block',
                ],
            ];

            $response = $this->httpClient
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ])
                ->timeout(30)
                ->post($this->baseUrl . '/omcoreapis/1.0.2/mp/pay', $payload);

            if ($response->successful()) {
                $data = $response->json();

                return MobileMoneyTransactionResult::success(
                    $data['transactionId'],
                    $data['status'] ?? 'PENDING',
                    $data['partnerReference'] ?? null,
                    [
                        'orange_reference' => $data['orangeReference'] ?? null,
                        'customer_msisdn' => $data['customer']['msisdn'] ?? null,
                    ]
                );
            }

            $errorData = $response->json();
            Log::error('Orange Money API error for blockFunds', [
                'status' => $response->status(),
                'response' => $errorData,
                'reference' => $reference,
            ]);

            return MobileMoneyTransactionResult::failure(
                $errorData['message'] ?? 'Erreur lors du blocage des fonds',
                $errorData['code'] ?? 'ORANGE_API_ERROR',
                ['http_status' => $response->status()]
            );
        } catch (Exception $e) {
            Log::error('Orange Money blockFunds exception', [
                'message' => $e->getMessage(),
                'reference' => $reference,
            ]);

            return MobileMoneyTransactionResult::failure(
                'Erreur de connexion avec Orange Money: ' . $e->getMessage(),
                'ORANGE_CONNECTION_ERROR'
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
                    'Impossible d\'obtenir le token d\'accès Orange Money',
                    'ORANGE_AUTH_ERROR'
                );
            }

            $payload = [
                'amount' => [
                    'value' => $amount->toFloat(),
                    'currency' => 'XOF',
                ],
                'customer' => [
                    'msisdn' => $this->formatPhoneNumber($toPhone),
                ],
                'partner' => [
                    'reference' => $reference,
                    'fee' => 0,
                ],
                'description' => 'Transfert de fonds - ProSartisan',
                'metadata' => [
                    'from_user_id' => $fromUserId->getValue(),
                    'to_user_id' => $toUserId->getValue(),
                    'operation_type' => 'transfer',
                ],
            ];

            $response = $this->httpClient
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ])
                ->timeout(30)
                ->post($this->baseUrl . '/omcoreapis/1.0.2/mp/push', $payload);

            if ($response->successful()) {
                $data = $response->json();

                return MobileMoneyTransactionResult::success(
                    $data['transactionId'],
                    $data['status'] ?? 'PENDING',
                    $data['partnerReference'] ?? null,
                    [
                        'orange_reference' => $data['orangeReference'] ?? null,
                        'recipient_msisdn' => $data['customer']['msisdn'] ?? null,
                    ]
                );
            }

            $errorData = $response->json();
            Log::error('Orange Money API error for transferFunds', [
                'status' => $response->status(),
                'response' => $errorData,
                'reference' => $reference,
            ]);

            return MobileMoneyTransactionResult::failure(
                $errorData['message'] ?? 'Erreur lors du transfert',
                $errorData['code'] ?? 'ORANGE_API_ERROR',
                ['http_status' => $response->status()]
            );
        } catch (Exception $e) {
            Log::error('Orange Money transferFunds exception', [
                'message' => $e->getMessage(),
                'reference' => $reference,
            ]);

            return MobileMoneyTransactionResult::failure(
                'Erreur de connexion avec Orange Money: ' . $e->getMessage(),
                'ORANGE_CONNECTION_ERROR'
            );
        }
    }

    public function refundFunds(
        UserId $userId,
        PhoneNumber $phoneNumber,
        MoneyAmount $amount,
        string $reference
    ): MobileMoneyTransactionResult {
        // Orange Money refunds use the same push API as transfers
        return $this->transferFunds(
            $userId, // System user as sender
            $phoneNumber, // Dummy from phone (not used in push)
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
                    'Impossible d\'obtenir le token d\'accès Orange Money'
                );
            }

            $response = $this->httpClient
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Accept' => 'application/json',
                ])
                ->timeout(15)
                ->get($this->baseUrl . '/omcoreapis/1.0.2/mp/transactions/' . $providerTransactionId);

            if ($response->successful()) {
                $data = $response->json();

                return new MobileMoneyTransactionStatus(
                    $data['status'],
                    $data['transactionId'],
                    $data['partnerReference'] ?? null,
                    $data['errorMessage'] ?? null,
                    isset($data['completedAt']) ? new DateTime($data['completedAt']) : null,
                    [
                        'orange_reference' => $data['orangeReference'] ?? null,
                        'amount' => $data['amount']['value'] ?? null,
                        'currency' => $data['amount']['currency'] ?? null,
                    ]
                );
            }

            Log::error('Orange Money API error for checkTransactionStatus', [
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
            Log::error('Orange Money checkTransactionStatus exception', [
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
        return 'Orange Money';
    }

    public function supportsPhoneNumber(PhoneNumber $phoneNumber): bool
    {
        // Orange Money supports specific prefixes in Côte d'Ivoire
        $phone = $phoneNumber->getValue();

        // Remove any spaces or dashes
        $cleanPhone = preg_replace('/[\s\-]/', '', $phone);

        // Check Orange Money prefixes: +225 07, 08, 09
        return preg_match('/^(\+225|225|0)(07|08|09)[0-9]{6}$/', $cleanPhone) === 1;
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
                    'Content-Type' => 'application/x-www-form-urlencoded',
                    'Accept' => 'application/json',
                ])
                ->timeout(15)
                ->post($this->baseUrl . '/oauth/token', [
                    'grant_type' => 'client_credentials',
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret,
                ]);

            if ($response->successful()) {
                $data = $response->json();
                $this->accessToken = $data['access_token'];

                // Set expiration time (subtract 60 seconds for safety)
                $expiresIn = $data['expires_in'] ?? 3600;
                $this->tokenExpiresAt = (new DateTime())->modify('+' . ($expiresIn - 60) . ' seconds');

                return $this->accessToken;
            }

            Log::error('Orange Money token request failed', [
                'status' => $response->status(),
                'response' => $response->json(),
            ]);
        } catch (Exception $e) {
            Log::error('Orange Money token request exception', [
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

        // Ensure it starts with +225
        if (preg_match('/^(\+225|225)(.+)$/', $cleanPhone, $matches)) {
            return '+225' . $matches[2];
        } elseif (preg_match('/^0(.+)$/', $cleanPhone, $matches)) {
            return '+225' . $matches[1];
        }

        return $cleanPhone;
    }
}
