<?php

namespace App\Infrastructure\Services\MobileMoney;

use App\Domain\Financial\Services\MobileMoneyService;
use App\Domain\Financial\Repositories\TransactionRepository;
use App\Domain\Financial\Repositories\SequestreRepository;
use App\Domain\Financial\Models\ValueObjects\TransactionStatus;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use Exception;
use DateTime;

/**
 * Mobile Money Webhook Handler
 *
 * Processes webhook callbacks from mobile money providers
 * to update transaction and escrow statuses.
 *
 * Requirements: 15.4, 15.5
 */
final class MobileMoneyWebhookHandler
{
    private array $gateways;
    private TransactionRepository $transactionRepository;
    private SequestreRepository $sequestreRepository;
    private MobileMoneyService $mobileMoneyService;

    public function __construct(
        array $gateways,
        TransactionRepository $transactionRepository,
        SequestreRepository $sequestreRepository,
        MobileMoneyService $mobileMoneyService
    ) {
        $this->gateways = $gateways;
        $this->transactionRepository = $transactionRepository;
        $this->sequestreRepository = $sequestreRepository;
        $this->mobileMoneyService = $mobileMoneyService;
    }

    /**
     * Handle Wave webhook
     *
     * Requirement 15.4: Receive webhook callback and update status
     */
    public function handleWaveWebhook(Request $request): array
    {
        try {
            $payload = $request->getContent();
            $signature = $request->header('X-Wave-Signature');

            // Verify webhook signature
            $waveGateway = $this->getGatewayByName('Wave');
            if (!$waveGateway || !$waveGateway->verifyWebhookSignature($payload, $signature)) {
                Log::warning('Invalid Wave webhook signature', [
                    'signature' => $signature,
                    'payload_length' => strlen($payload),
                ]);
                return ['status' => 'error', 'message' => 'Invalid signature'];
            }

            $data = json_decode($payload, true);
            if (!$data) {
                Log::error('Invalid Wave webhook payload', ['payload' => $payload]);
                return ['status' => 'error', 'message' => 'Invalid payload'];
            }

            return $this->processWebhookData($data, 'Wave');
        } catch (Exception $e) {
            Log::error('Wave webhook processing error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return ['status' => 'error', 'message' => 'Processing error'];
        }
    }

    /**
     * Handle Orange Money webhook
     */
    public function handleOrangeMoneyWebhook(Request $request): array
    {
        try {
            $payload = $request->getContent();
            $signature = $request->header('X-Orange-Signature');

            // Verify webhook signature
            $orangeGateway = $this->getGatewayByName('Orange Money');
            if (!$orangeGateway || !$orangeGateway->verifyWebhookSignature($payload, $signature)) {
                Log::warning('Invalid Orange Money webhook signature', [
                    'signature' => $signature,
                    'payload_length' => strlen($payload),
                ]);
                return ['status' => 'error', 'message' => 'Invalid signature'];
            }

            $data = json_decode($payload, true);
            if (!$data) {
                Log::error('Invalid Orange Money webhook payload', ['payload' => $payload]);
                return ['status' => 'error', 'message' => 'Invalid payload'];
            }

            return $this->processWebhookData($data, 'Orange Money');
        } catch (Exception $e) {
            Log::error('Orange Money webhook processing error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return ['status' => 'error', 'message' => 'Processing error'];
        }
    }

    /**
     * Handle MTN Mobile Money webhook
     */
    public function handleMTNWebhook(Request $request): array
    {
        try {
            $payload = $request->getContent();
            $signature = $request->header('X-MTN-Signature');

            // Verify webhook signature
            $mtnGateway = $this->getGatewayByName('MTN Mobile Money');
            if (!$mtnGateway || !$mtnGateway->verifyWebhookSignature($payload, $signature)) {
                Log::warning('Invalid MTN webhook signature', [
                    'signature' => $signature,
                    'payload_length' => strlen($payload),
                ]);
                return ['status' => 'error', 'message' => 'Invalid signature'];
            }

            $data = json_decode($payload, true);
            if (!$data) {
                Log::error('Invalid MTN webhook payload', ['payload' => $payload]);
                return ['status' => 'error', 'message' => 'Invalid payload'];
            }

            return $this->processWebhookData($data, 'MTN Mobile Money');
        } catch (Exception $e) {
            Log::error('MTN webhook processing error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return ['status' => 'error', 'message' => 'Processing error'];
        }
    }

    /**
     * Query transaction status when webhook is not received within 5 minutes
     *
     * Requirement 15.5: Query payment status via API if webhook not received
     */
    public function queryPendingTransactions(): void
    {
        try {
            // Find transactions that are pending for more than 5 minutes
            $cutoffTime = (new DateTime())->modify('-5 minutes');
            $pendingTransactions = $this->transactionRepository->findPendingOlderThan($cutoffTime);

            foreach ($pendingTransactions as $transaction) {
                $metadata = $transaction->getMetadata();
                $providerName = $metadata['provider'] ?? null;
                $providerTransactionId = $transaction->getMobileMoneyReference();

                if (!$providerTransactionId || !$providerName) {
                    continue;
                }

                Log::info('Querying status for pending transaction', [
                    'transaction_id' => $transaction->getId()->getValue(),
                    'provider' => $providerName,
                    'provider_transaction_id' => $providerTransactionId,
                ]);

                $status = $this->mobileMoneyService->checkTransactionStatus($providerTransactionId, $providerName);

                // Update transaction based on status
                if ($status->isCompleted()) {
                    $transaction->complete($providerTransactionId);
                    $this->transactionRepository->save($transaction);

                    // Update related escrow if applicable
                    $this->updateRelatedEscrow($transaction, 'COMPLETED');

                    Log::info('Transaction status updated to completed', [
                        'transaction_id' => $transaction->getId()->getValue(),
                    ]);
                } elseif ($status->isFailed()) {
                    $transaction->fail($status->getErrorMessage() ?? 'Transaction failed');
                    $this->transactionRepository->save($transaction);

                    Log::info('Transaction status updated to failed', [
                        'transaction_id' => $transaction->getId()->getValue(),
                        'error' => $status->getErrorMessage(),
                    ]);
                }
            }
        } catch (Exception $e) {
            Log::error('Error querying pending transactions', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Process webhook data from any provider
     */
    private function processWebhookData(array $data, string $providerName): array
    {
        $transactionId = $this->extractTransactionId($data, $providerName);
        $status = $this->extractStatus($data, $providerName);
        $reference = $this->extractReference($data, $providerName);

        if (!$transactionId) {
            Log::error('Missing transaction ID in webhook', [
                'provider' => $providerName,
                'data' => $data,
            ]);
            return ['status' => 'error', 'message' => 'Missing transaction ID'];
        }

        // Find the transaction by mobile money reference or metadata
        $transaction = $this->findTransactionByReference($transactionId, $reference);
        if (!$transaction) {
            Log::warning('Transaction not found for webhook', [
                'provider' => $providerName,
                'transaction_id' => $transactionId,
                'reference' => $reference,
            ]);
            return ['status' => 'error', 'message' => 'Transaction not found'];
        }

        // Update transaction status
        if ($status === 'COMPLETED' || $status === 'SUCCESSFUL') {
            $transaction->complete($transactionId);
            $this->updateRelatedEscrow($transaction, 'COMPLETED');

            Log::info('Transaction completed via webhook', [
                'transaction_id' => $transaction->getId()->getValue(),
                'provider' => $providerName,
                'provider_transaction_id' => $transactionId,
            ]);
        } elseif ($status === 'FAILED' || $status === 'CANCELLED') {
            $errorMessage = $this->extractErrorMessage($data, $providerName);
            $transaction->fail($errorMessage ?? 'Transaction failed');

            Log::info('Transaction failed via webhook', [
                'transaction_id' => $transaction->getId()->getValue(),
                'provider' => $providerName,
                'error' => $errorMessage,
            ]);
        }

        $this->transactionRepository->save($transaction);

        return ['status' => 'success', 'message' => 'Webhook processed'];
    }

    /**
     * Extract transaction ID from webhook data based on provider
     */
    private function extractTransactionId(array $data, string $providerName): ?string
    {
        switch ($providerName) {
            case 'Wave':
                return $data['transaction_id'] ?? $data['id'] ?? null;
            case 'Orange Money':
                return $data['transactionId'] ?? null;
            case 'MTN Mobile Money':
                return $data['referenceId'] ?? $data['financialTransactionId'] ?? null;
            default:
                return null;
        }
    }

    /**
     * Extract status from webhook data based on provider
     */
    private function extractStatus(array $data, string $providerName): ?string
    {
        switch ($providerName) {
            case 'Wave':
                return $data['status'] ?? null;
            case 'Orange Money':
                return $data['status'] ?? null;
            case 'MTN Mobile Money':
                return $data['status'] ?? null;
            default:
                return null;
        }
    }

    /**
     * Extract reference from webhook data based on provider
     */
    private function extractReference(array $data, string $providerName): ?string
    {
        switch ($providerName) {
            case 'Wave':
                return $data['reference'] ?? null;
            case 'Orange Money':
                return $data['partnerReference'] ?? null;
            case 'MTN Mobile Money':
                return $data['externalId'] ?? null;
            default:
                return null;
        }
    }

    /**
     * Extract error message from webhook data based on provider
     */
    private function extractErrorMessage(array $data, string $providerName): ?string
    {
        switch ($providerName) {
            case 'Wave':
                return $data['error_message'] ?? $data['message'] ?? null;
            case 'Orange Money':
                return $data['errorMessage'] ?? $data['message'] ?? null;
            case 'MTN Mobile Money':
                return $data['reason']['message'] ?? $data['message'] ?? null;
            default:
                return null;
        }
    }

    /**
     * Find transaction by mobile money reference or internal reference
     */
    private function findTransactionByReference(string $transactionId, ?string $reference)
    {
        // Try to find by mobile money reference first
        $transaction = $this->transactionRepository->findSingleByMobileMoneyReference($transactionId);
        if ($transaction) {
            return $transaction;
        }

        // Try to find by internal reference in metadata
        if ($reference) {
            return $this->transactionRepository->findByMetadataReference($reference);
        }

        return null;
    }

    /**
     * Update related escrow status when payment is completed
     */
    private function updateRelatedEscrow($transaction, string $status): void
    {
        $metadata = $transaction->getMetadata();
        $reference = $metadata['reference'] ?? null;

        if ($reference && str_contains($reference, 'escrow')) {
            // Find sequestre by mission reference
            $sequestre = $this->sequestreRepository->findByReference($reference);
            if ($sequestre && $status === 'COMPLETED') {
                // This would trigger the escrow fragmentation process
                // Implementation depends on the specific business logic
                Log::info('Escrow payment completed, ready for fragmentation', [
                    'sequestre_id' => $sequestre->getId()->getValue(),
                    'transaction_id' => $transaction->getId()->getValue(),
                ]);
            }
        }
    }

    /**
     * Get gateway by provider name
     */
    private function getGatewayByName(string $providerName): ?MobileMoneyGateway
    {
        foreach ($this->gateways as $gateway) {
            if ($gateway->getProviderName() === $providerName) {
                return $gateway;
            }
        }
        return null;
    }
}
