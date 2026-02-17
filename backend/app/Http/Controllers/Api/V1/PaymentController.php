<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\EscrowWallet;
use App\Models\MaterialToken;
use App\Models\Project;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class PaymentController extends Controller
{
    /**
     * Initialize payment with CinetPay
     */
    public function initialize(Request $request)
    {
        $validated = $request->validate([
            'project_id' => 'required|exists:projects,id',
            'payment_method' => 'required|in:WALLET,MOBILE_MONEY,CARD',
        ]);

        $project = Project::with('quotes')->findOrFail($validated['project_id']);

        // Verify client owns project
        if ($project->client_id !== $request->user()->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // Verify project has accepted quote
        if ($project->status !== 'quote_accepted' || !$project->final_amount) {
            return response()->json(['error' => 'Project not ready for payment'], 400);
        }

        // Check if payment already initialized
        if ($project->escrowWallet) {
            return response()->json(['error' => 'Payment already processed'], 400);
        }

        // Create transaction record
        $transactionRef = 'PRO-' . $project->id . '-' . time();
        $transaction = Transaction::create([
            'project_id' => $project->id,
            'user_id' => $request->user()->id,
            'transaction_ref' => $transactionRef,
            'type' => 'deposit',
            'amount' => $project->final_amount,
            'payment_method' => $validated['payment_method'],
            'status' => 'pending',
        ]);

        // Initialize CinetPay payment
        try {
            $response = Http::post('https://api-checkout.cinetpay.com/v2/payment', [
                'apikey' => config('services.cinetpay.api_key'),
                'site_id' => config('services.cinetpay.site_id'),
                'transaction_id' => $transactionRef,
                'amount' => (int) $project->final_amount,
                'currency' => 'XOF',
                'description' => "Paiement projet #{$project->id} - {$project->title}",
                'return_url' => config('services.cinetpay.return_url'),
                'notify_url' => config('services.cinetpay.notify_url'),
                'channels' => $validated['payment_method'],
                'metadata' => json_encode([
                    'project_id' => $project->id,
                    'client_id' => $request->user()->id,
                ]),
            ]);

            if ($response->successful() && $response->json('code') === '201') {
                $data = $response->json('data');

                $transaction->update([
                    'metadata' => [
                        'payment_url' => $data['payment_url'],
                        'payment_token' => $data['payment_token'],
                    ],
                ]);

                return response()->json([
                    'transaction_ref' => $transactionRef,
                    'payment_url' => $data['payment_url'],
                    'payment_token' => $data['payment_token'],
                ]);
            }

            throw new \Exception('CinetPay API error: ' . $response->json('message'));
        } catch (\Exception $e) {
            $transaction->update(['status' => 'failed']);
            return response()->json(['error' => 'Payment initialization failed'], 500);
        }
    }

    /**
     * CinetPay webhook handler
     */
    public function webhook(Request $request)
    {
        // Verify CinetPay signature
        $signature = $request->header('X-CinetPay-Signature');
        $computedSignature = hash_hmac('sha256', $request->getContent(), config('services.cinetpay.secret_key'));

        if ($signature !== $computedSignature) {
            return response()->json(['error' => 'Invalid signature'], 401);
        }

        $data = $request->all();

        // Find transaction
        $transaction = Transaction::where('transaction_ref', $data['cpm_trans_id'])->first();
        if (!$transaction) {
            return response()->json(['error' => 'Transaction not found'], 404);
        }

        // Process based on status
        if ($data['cpm_result'] === '00' && $data['payment_method']) {
            DB::beginTransaction();
            try {
                // Update transaction
                $transaction->update([
                    'status' => 'completed',
                    'payment_method' => $data['payment_method'],
                    'metadata' => array_merge($transaction->metadata ?? [], [
                        'cpm_trans_id' => $data['cpm_trans_id'],
                        'cpm_custom' => $data['cpm_custom'],
                        'operator_id' => $data['operator_id'] ?? null,
                    ]),
                ]);

                // Process successful payment
                $this->processSuccessfulPayment($transaction);

                DB::commit();

                return response()->json(['status' => 'success']);
            } catch (\Exception $e) {
                DB::rollBack();
                return response()->json(['error' => 'Processing failed'], 500);
            }
        } else {
            $transaction->update(['status' => 'failed']);
            return response()->json(['status' => 'failed']);
        }
    }

    /**
     * Verify payment status
     */
    public function verify(Request $request, string $transactionRef)
    {
        $transaction = Transaction::where('transaction_ref', $transactionRef)->firstOrFail();

        // Check with CinetPay
        try {
            $response = Http::post('https://api-checkout.cinetpay.com/v2/payment/check', [
                'apikey' => config('services.cinetpay.api_key'),
                'site_id' => config('services.cinetpay.site_id'),
                'transaction_id' => $transactionRef,
            ]);

            if ($response->successful()) {
                $data = $response->json('data');

                return response()->json([
                    'status' => $transaction->status,
                    'payment_status' => $data['payment_status'] ?? null,
                    'amount' => $transaction->amount,
                ]);
            }

            return response()->json(['error' => 'Verification failed'], 500);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Verification failed'], 500);
        }
    }

    /**
     * Process successful payment - Create escrow wallet and material token
     */
    protected function processSuccessfulPayment(Transaction $transaction)
    {
        $project = $transaction->project;
        $acceptedQuote = $project->quotes()->where('status', 'accepted')->first();

        if (!$acceptedQuote) {
            throw new \Exception('No accepted quote found');
        }

        // Calculate escrow fragmentation
        $materialWallet = ($project->final_amount * $acceptedQuote->material_percentage) / 100;
        $laborWallet = ($project->final_amount * $acceptedQuote->labor_percentage) / 100;

        // Create escrow wallet
        $escrowWallet = EscrowWallet::create([
            'project_id' => $project->id,
            'total_amount' => $project->final_amount,
            'material_wallet' => $materialWallet,
            'labor_wallet' => $laborWallet,
            'material_spent' => 0,
            'labor_released' => 0,
            'status' => 'active',
        ]);

        // Generate material token
        $this->generateMaterialToken($escrowWallet);

        // Update project status
        $project->update([
            'status' => 'payment_pending',
            'started_at' => now(),
        ]);

        return $escrowWallet;
    }

    /**
     * Generate material token with QR code
     */
    protected function generateMaterialToken(EscrowWallet $escrowWallet)
    {
        // Generate unique code
        $code = 'PA-' . strtoupper(substr(md5(uniqid()), 0, 6));

        // Create token
        $token = MaterialToken::create([
            'code' => $code,
            'project_id' => $escrowWallet->project_id,
            'escrow_wallet_id' => $escrowWallet->id,
            'total_value' => $escrowWallet->material_wallet,
            'remaining_value' => $escrowWallet->material_wallet,
            'expires_at' => now()->addDays(30), // 30 days validity
            'status' => 'active',
        ]);

        // Generate QR code
        $qrCodeData = json_encode([
            'code' => $code,
            'amount' => $escrowWallet->material_wallet,
            'project_id' => $escrowWallet->project_id,
            'expires' => $token->expires_at->toDateString(),
        ]);

        $qrCode = QrCode::format('png')
            ->size(400)
            ->errorCorrection('H')
            ->generate($qrCodeData);

        // Save QR code
        $path = "tokens/{$code}.png";
        Storage::disk('public')->put($path, $qrCode);

        $token->update(['qr_code_path' => $path]);

        return $token;
    }
}
