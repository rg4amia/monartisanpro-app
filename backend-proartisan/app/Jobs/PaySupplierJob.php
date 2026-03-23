<?php

namespace App\Jobs;

use App\Enums\WalletType;
use App\Models\JCode;
use App\Services\NotificationService;
use App\Services\JCodeService;
use App\Services\WalletService;
use App\Services\WaveService;
use App\Services\OrangeMoneyService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PaySupplierJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $jcodeId) {}

    public function handle(
        WalletService $walletService,
        NotificationService $notificationService,
        JCodeService $jCodeService,
        WaveService $waveService,
        OrangeMoneyService $orangeMoneyService
    ): void {
        $jcode = JCode::with(['mission', 'artisan', 'fournisseur'])->findOrFail($this->jcodeId);

        // Vérifier que le J-Code a bien été scanné
        if ($jcode->statut !== 'utilise' || $jcode->paiement_status === 'paye') {
            Log::warning("PaySupplierJob: J-Code #{$jcode->id} n'est pas utilisé, skip");
            return;
        }

        try {
            $jCodeService->settleSupplierPayment($jcode);
        } catch (\Exception $e) {
            Log::error('Erreur lors du virement automatique fournisseur J+1', [
                'jcode_id' => $jcode->id,
                'fournisseur_id' => $jcode->fournisseur_id,
                'error' => $e->getMessage(),
            ]);
            // On peut laisser le job échouer pour qu'il soit retenté par la queue si configuré
            throw $e;
        }
    }
}
