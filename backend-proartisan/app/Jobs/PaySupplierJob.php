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

    /**
     * @param int      $jcodeId         ID du J-Code
     * @param int|null $fournisseurId   Fournisseur spécifique à payer (consommation partielle)
     * @param int|null $montantServi    Montant servi lors de ce scan (consommation partielle)
     */
    public function __construct(
        public int $jcodeId,
        public ?int $fournisseurId = null,
        public ?int $montantServi = null,
    ) {}

    public function handle(
        WalletService $walletService,
        NotificationService $notificationService,
        JCodeService $jCodeService,
        WaveService $waveService,
        OrangeMoneyService $orangeMoneyService
    ): void {
        $jcode = JCode::with(['mission', 'artisan', 'fournisseur'])->findOrFail($this->jcodeId);

        // Vérifier que le J-Code a bien été scanné (total ou partiel)
        if (! in_array($jcode->statut, ['utilise', 'partiellement_utilise']) || $jcode->paiement_status === 'paye') {
            Log::warning("PaySupplierJob: J-Code #{$jcode->id} n'est pas utilisé ou déjà payé, skip");
            return;
        }

        try {
            $jCodeService->settleSupplierPayment(
                $jcode,
                $this->fournisseurId,
                $this->montantServi,
            );
        } catch (\Exception $e) {
            Log::error('Erreur lors du virement automatique fournisseur J+1', [
                'jcode_id' => $jcode->id,
                'fournisseur_id' => $this->fournisseurId ?? $jcode->fournisseur_id,
                'montant_servi' => $this->montantServi,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
