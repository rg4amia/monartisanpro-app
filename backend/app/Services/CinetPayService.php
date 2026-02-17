<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Transaction;
use App\Models\EscrowWallet;
use App\Models\Project;

class CinetPayService
{
    protected string $apiKey;
    protected string $siteId;
    protected string $secretKey;
    protected string $mode;
    protected string $baseUrl;

    public function __construct()
    {
        $this->apiKey = config('services.cinetpay.api_key');
        $this->siteId = config('services.cinetpay.site_id');
        $this->secretKey = config('services.cinetpay.secret_key');
        $this->mode = config('services.cinetpay.mode', 'sandbox');

        $this->baseUrl = $this->mode === 'production'
            ? 'https://api-checkout.cinetpay.com/v2'
            : 'https://api-checkout.cinetpay.com/v2'; // Same URL for both
    }

    /**
     * Initialize a payment
     *
     * @param float $amount Amount in XOF
     * @param int $projectId Project ID
     * @param string $description Payment description
     * @param array $metadata Additional metadata
     * @return array Payment initialization response
     */
    public function initiatePayment(float $amount, int $projectId, string $description = '', array $metadata = []): array
    {
        $transactionId = $this->generateTransactionId($projectId);

        $data = [
            'apikey' => $this->apiKey,
            'site_id' => $this->siteId,
            'transaction_id' => $transactionId,
            'amount' => (int) $amount,
            'currency' => 'XOF',
            'description' => $description ?: "Paiement projet #$projectId",
            'return_url' => config('services.cinetpay.return_url'),
            'notify_url' => config('services.cinetpay.notify_url'),
            'channels' => 'ALL', // WALLET (Wave), MOBILE_MONEY (Orange, MTN, Moov), CARD
            'metadata' => json_encode(array_merge([
                'project_id' => $projectId,
                'mode' => $this->mode,
            ], $metadata)),
            'customer_name' => $metadata['customer_name'] ?? '',
            'customer_surname' => $metadata['customer_surname'] ?? '',
            'customer_email' => $metadata['customer_email'] ?? '',
            'customer_phone_number' => $metadata['customer_phone'] ?? '',
            'customer_address' => $metadata['customer_address'] ?? '',
            'customer_city' => $metadata['customer_city'] ?? 'Abidjan',
            'customer_country' => 'CI', // Côte d'Ivoire
            'customer_state' => 'CI',
            'customer_zip_code' => '',
        ];

        try {
            $response = Http::timeout(30)
                ->post("{$this->baseUrl}/payment", $data);

            $result = $response->json();

            Log::channel('payments')->info('CinetPay payment initiated', [
                'transaction_id' => $transactionId,
                'amount' => $amount,
                'project_id' => $projectId,
                'response' => $result,
            ]);

            // Create transaction record
            Transaction::create([
                'project_id' => $projectId,
                'transaction_ref' => $transactionId,
                'type' => 'deposit',
                'amount' => $amount,
                'payment_method' => null, // Will be updated on webhook
                'status' => 'pending',
                'metadata' => json_encode([
                    'cinetpay_data' => $result,
                    'initiated_at' => now()->toIso8601String(),
                ]),
            ]);

            return [
                'success' => true,
                'transaction_id' => $transactionId,
                'payment_url' => $result['data']['payment_url'] ?? null,
                'payment_token' => $result['data']['payment_token'] ?? null,
                'data' => $result,
            ];
        } catch (\Exception $e) {
            Log::channel('payments')->error('CinetPay payment initiation failed', [
                'transaction_id' => $transactionId,
                'amount' => $amount,
                'project_id' => $projectId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Verify webhook signature
     *
     * @param string $payload Request body as JSON string
     * @param string $signature Header signature
     * @return bool
     */
    public function verifyWebhookSignature(string $payload, string $signature): bool
    {
        $computedSignature = hash_hmac('sha256', $payload, $this->secretKey);
        return hash_equals($computedSignature, $signature);
    }

    /**
     * Handle webhook callback
     *
     * @param array $data Webhook data
     * @return bool
     */
    public function handleWebhook(array $data): bool
    {
        try {
            $transactionId = $data['cpm_trans_id'] ?? null;
            $status = $data['cpm_result'] ?? null;
            $amount = $data['cpm_amount'] ?? null;
            $paymentMethod = $data['payment_method'] ?? null;
            $customTransactionId = $data['cpm_custom'] ?? null;

            if (!$transactionId || !$status) {
                Log::channel('payments')->error('Invalid webhook data', ['data' => $data]);
                return false;
            }

            // Find transaction
            $transaction = Transaction::where('transaction_ref', $customTransactionId)->first();

            if (!$transaction) {
                Log::channel('payments')->error('Transaction not found', [
                    'transaction_ref' => $customTransactionId,
                ]);
                return false;
            }

            // Update transaction
            $transaction->update([
                'payment_method' => $paymentMethod,
                'status' => $status === '00' ? 'completed' : 'failed',
                'metadata' => json_encode(array_merge(
                    json_decode($transaction->metadata, true) ?? [],
                    [
                        'webhook_data' => $data,
                        'completed_at' => now()->toIso8601String(),
                    ]
                )),
            ]);

            // If payment successful, process escrow wallet creation
            if ($status === '00') {
                $this->processSuccessfulPayment($transaction);
            }

            Log::channel('payments')->info('Webhook processed successfully', [
                'transaction_id' => $transactionId,
                'status' => $status,
                'amount' => $amount,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::channel('payments')->error('Webhook processing failed', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);

            return false;
        }
    }

    /**
     * Process successful payment
     *
     * @param Transaction $transaction
     * @return void
     */
    protected function processSuccessfulPayment(Transaction $transaction): void
    {
        $project = Project::find($transaction->project_id);

        if (!$project) {
            Log::channel('payments')->error('Project not found', [
                'project_id' => $transaction->project_id,
            ]);
            return;
        }

        // Create escrow wallet with fragmentation
        $materialWallet = $transaction->amount * 0.70; // 70%
        $laborWallet = $transaction->amount * 0.30;    // 30%

        $escrowWallet = EscrowWallet::create([
            'project_id' => $project->id,
            'total_amount' => $transaction->amount,
            'material_wallet' => $materialWallet,
            'labor_wallet' => $laborWallet,
            'material_spent' => 0,
            'labor_released' => 0,
            'status' => 'active',
        ]);

        // Update project status
        $project->update([
            'status' => 'payment_pending',
            'final_amount' => $transaction->amount,
        ]);

        Log::channel('payments')->info('Escrow wallet created', [
            'project_id' => $project->id,
            'escrow_wallet_id' => $escrowWallet->id,
            'total_amount' => $transaction->amount,
            'material_wallet' => $materialWallet,
            'labor_wallet' => $laborWallet,
        ]);

        // Generate material token (handled by TokenService)
        // This will be called from the controller after wallet creation
    }

    /**
     * Verify payment status
     *
     * @param string $transactionId CinetPay transaction ID
     * @return array
     */
    public function verifyPayment(string $transactionId): array
    {
        try {
            $response = Http::timeout(30)->post("{$this->baseUrl}/payment/check", [
                'apikey' => $this->apiKey,
                'site_id' => $this->siteId,
                'transaction_id' => $transactionId,
            ]);

            $result = $response->json();

            Log::channel('payments')->info('Payment verification', [
                'transaction_id' => $transactionId,
                'result' => $result,
            ]);

            return [
                'success' => true,
                'data' => $result,
            ];
        } catch (\Exception $e) {
            Log::channel('payments')->error('Payment verification failed', [
                'transaction_id' => $transactionId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Process refund
     *
     * @param string $transactionId
     * @param float $amount
     * @param string $reason
     * @return array
     */
    public function refund(string $transactionId, float $amount, string $reason = ''): array
    {
        try {
            // Note: CinetPay refund API may vary - check documentation
            $response = Http::timeout(30)->post("{$this->baseUrl}/payment/refund", [
                'apikey' => $this->apiKey,
                'site_id' => $this->siteId,
                'transaction_id' => $transactionId,
                'amount' => (int) $amount,
                'description' => $reason,
            ]);

            $result = $response->json();

            Log::channel('payments')->info('Refund processed', [
                'transaction_id' => $transactionId,
                'amount' => $amount,
                'reason' => $reason,
                'result' => $result,
            ]);

            return [
                'success' => true,
                'data' => $result,
            ];
        } catch (\Exception $e) {
            Log::channel('payments')->error('Refund failed', [
                'transaction_id' => $transactionId,
                'amount' => $amount,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Generate unique transaction ID
     *
     * @param int $projectId
     * @return string
     */
    protected function generateTransactionId(int $projectId): string
    {
        return 'PRO-' . $projectId . '-' . time() . '-' . mt_rand(1000, 9999);
    }
}
