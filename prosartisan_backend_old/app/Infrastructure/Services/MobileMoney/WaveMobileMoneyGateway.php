<?php

namespace App\Infrastructure\Services\MobileMoney;

use App\Domain\Financial\Services\MobileMoneyGateway;
use App\Domain\Financial\Services\MobileMoneyTransactionResult;
use App\Domain\Financial\Services\MobileMoneyTransactionStatus;
use App\Domain\Identity\Models\ValueObjects\PhoneNumber;
use App\Domain\Identity\Models\ValueObjects\UserId;
use App\Domain\Shared\ValueObjects\MoneyAmount;
use DateTime;
use Exception;
use Illuminate\Http\Client\Factory as HttpClient;
use Illuminate\Support\Facades\Log;

/**
 * Wave Mobile Money Gateway Implementation
 *
 * Integrates with Wave API for mobile money transactions in Côte d'Ivoire.
 * Wave supports phone numbers starting with +225 (Côte d'Ivoire country code).
 *
 * Requirements: 4.4, 4.5, 15.1, 15.4, 15.5
 */
final class WaveMobileMoneyGateway implements MobileMoneyGateway
{
    private HttpClient $httpClient;

    private string $apiKey;

    private string $apiSecret;

    private string $baseUrl;

    private string $webhookSecret;

    public function __construct(
        HttpClient $httpClient,
        string $apiKey,
        string $apiSecret,
        string $baseUrl,
        string $webhookSecret
    ) {
        $this->httpClient = $httpClient;
        $this->apiKey = $apiKey;
        $this->apiSecret = $apiSecret;
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
            $payload = [
                'amount' => $amount->toFloat(),
                'currency' => 'XOF',
                'phone_number' => $phoneNumber->getValue(),
                'reference' => $reference,
                'description' => 'Blocage de fonds pour séquestre - ProSartisan',
                'callback_url' => config('app.url').'/api/v1/payments/webhook/wave',
                'metadata' => [
                    'user_id' => $userId->getValue(),
                    'operation_type' => 'escrow_block',
                ],
            ];

            $response = $this->httpClient
                ->withHeaders($this->getAuthHeaders())
                ->timeout(30)
                ->post($this->baseUrl.'/v1/payments/collect', $payload);

            if ($response->successful()) {
                $data = $response->json();

                return MobileMoneyTransactionResult::success(
                    $data['transaction_id'],
                    $data['status'] ?? 'PENDING',
                    $data['reference'] ?? null,
                    [
                        'wave_payment_url' => $data['payment_url'] ?? null,
                        'expires_at' => $data['expires_at'] ?? null,
                    ]
                );
            }

            $errorData = $response->json();
            Log::error('Wave API error for blockFunds', [
                'status' => $response->status(),
                'response' => $errorData,
                'reference' => $reference,
            ]);

            return MobileMoneyTransactionResult::failure(
                $errorData['message'] ?? 'Erreur lors du blocage des fonds',
                $errorData['code'] ?? 'WAVE_API_ERROR',
                ['http_status' => $response->status()]
            );
        } catch (Exception $e) {
            Log::error('Wave blockFunds exception', [
                'message' => $e->getMessage(),
                'reference' => $reference,
            ]);

            return MobileMoneyTransactionResult::failure(
                'Erreur de connexion avec Wave: '.$e->getMessage(),
                'WAVE_CONNECTION_ERROR'
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
            $payload = [
                'amount' => $amount->toFloat(),
                'currency' => 'XOF',
                'recipient_phone' => $toPhone->getValue(),
                'reference' => $reference,
                'description' => 'Transfert de fonds - ProSartisan',
                'callback_url' => config('app.url').'/api/v1/payments/webhook/wave',
                'metadata' => [
                    'from_user_id' => $fromUserId->getValue(),
                    'to_user_id' => $toUserId->getValue(),
                    'operation_type' => 'transfer',
                ],
            ];

            $response = $this->httpClient
                ->withHeaders($this->getAuthHeaders())
                ->timeout(30)
                ->post($this->baseUrl.'/v1/payments/send', $payload);

            if ($response->successful()) {
                $data = $response->json();

                return MobileMoneyTransactionResult::success(
                    $data['transaction_id'],
                    $data['status'] ?? 'PENDING',
                    $data['reference'] ?? null,
                    [
                        'recipient_phone' => $toPhone->getValue(),
                        'processing_fee' => $data['fee'] ?? null,
                    ]
                );
            }

            $errorData = $response->json();
            Log::error('Wave API error for transferFunds', [
                'status' => $response->status(),
                'response' => $errorData,
                'reference' => $reference,
            ]);

            return MobileMoneyTransactionResult::failure(
                $errorData['message'] ?? 'Erreur lors du transfert',
                $errorData['code'] ?? 'WAVE_API_ERROR',
                ['http_status' => $response->status()]
            );
        } catch (Exception $e) {
            Log::error('Wave transferFunds exception', [
                'message' => $e->getMessage(),
                'reference' => $reference,
            ]);

            return MobileMoneyTransactionResult::failure(
                'Erreur de connexion avec Wave: '.$e->getMessage(),
                'WAVE_CONNECTION_ERROR'
            );
        }
    }

    public function refundFunds(
        UserId $userId,
        PhoneNumber $phoneNumber,
        MoneyAmount $amount,
        string $reference
    ): MobileMoneyTransactionResult {
        try {
            $payload = [
                'amount' => $amount->toFloat(),
                'currency' => 'XOF',
                'recipient_phone' => $phoneNumber->getValue(),
                'reference' => $reference,
                'description' => 'Remboursement - ProSartisan',
                'callback_url' => config('app.url').'/api/v1/payments/webhook/wave',
                'metadata' => [
                    'user_id' => $userId->getValue(),
                    'operation_type' => 'refund',
                ],
            ];

            $response = $this->httpClient
                ->withHeaders($this->getAuthHeaders())
                ->timeout(30)
                ->post($this->baseUrl.'/v1/payments/send', $payload);

            if ($response->successful()) {
                $data = $response->json();

                return MobileMoneyTransactionResult::success(
                    $data['transaction_id'],
                    $data['status'] ?? 'PENDING',
                    $data['reference'] ?? null,
                    [
                        'recipient_phone' => $phoneNumber->getValue(),
                        'refund_reason' => 'Remboursement séquestre',
                    ]
                );
            }

            $errorData = $response->json();
            Log::error('Wave API error for refundFunds', [
                'status' => $response->status(),
                'response' => $errorData,
                'reference' => $reference,
            ]);

            return MobileMoneyTransactionResult::failure(
                $errorData['message'] ?? 'Erreur lors du remboursement',
                $errorData['code'] ?? 'WAVE_API_ERROR',
                ['http_status' => $response->status()]
            );
        } catch (Exception $e) {
            Log::error('Wave refundFunds exception', [
                'message' => $e->getMessage(),
                'reference' => $reference,
            ]);

            return MobileMoneyTransactionResult::failure(
                'Erreur de connexion avec Wave: '.$e->getMessage(),
                'WAVE_CONNECTION_ERROR'
            );
        }
    }

    public function checkTransactionStatus(string $providerTransactionId): MobileMoneyTransactionStatus
    {
        try {
            $response = $this->httpClient
                ->withHeaders($this->getAuthHeaders())
                ->timeout(15)
                ->get($this->baseUrl.'/v1/payments/'.$providerTransactionId);

            if ($response->successful()) {
                $data = $response->json();

                return new MobileMoneyTransactionStatus(
                    $data['status'],
                    $data['transaction_id'],
                    $data['reference'] ?? null,
                    $data['error_message'] ?? null,
                    isset($data['completed_at']) ? new DateTime($data['completed_at']) : null,
                    [
                        'amount' => $data['amount'] ?? null,
                        'currency' => $data['currency'] ?? null,
                        'phone_number' => $data['phone_number'] ?? null,
                    ]
                );
            }

            Log::error('Wave API error for checkTransactionStatus', [
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
            Log::error('Wave checkTransactionStatus exception', [
                'message' => $e->getMessage(),
                'transaction_id' => $providerTransactionId,
            ]);

            return new MobileMoneyTransactionStatus(
                'FAILED',
                $providerTransactionId,
                null,
                'Erreur de connexion: '.$e->getMessage()
            );
        }
    }

    public function getProviderName(): string
    {
        return 'Wave';
    }

    public function supportsPhoneNumber(PhoneNumber $phoneNumber): bool
    {
        // Wave supports Côte d'Ivoire phone numbers (+225)
        $phone = $phoneNumber->getValue();

        // Remove any spaces or dashes
        $cleanPhone = preg_replace('/[\s\-]/', '', $phone);

        // Check if it starts with +225 or 225 or is a local number
        return preg_match('/^(\+225|225|0)[0-9]{8,10}$/', $cleanPhone) === 1;
    }

    /**
     * Verify webhook signature for security
     */
    public function verifyWebhookSignature(string $payload, string $signature): bool
    {
        $expectedSignature = hash_hmac('sha256', $payload, $this->webhookSecret);

        return hash_equals($expectedSignature, $signature);
    }

    private function getAuthHeaders(): array
    {
        return [
            'Authorization' => 'Bearer '.$this->apiKey,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'X-API-Secret' => $this->apiSecret,
        ];
    }
}
