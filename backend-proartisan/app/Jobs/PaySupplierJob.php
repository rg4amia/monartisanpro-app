<?php

namespace App\Jobs;

use App\Enums\WalletType;
use App\Models\JCode;
use App\Services\NotificationService;
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
        WaveService $waveService,
        OrangeMoneyService $orangeMoneyService
    ): void {
        $jcode = JCode::with(['mission', 'artisan', 'fournisseur'])->findOrFail($this->jcodeId);

        // Vérifier que le J-Code a bien été scanné
        if ($jcode->statut !== 'utilise') {
            Log::warning("PaySupplierJob: J-Code #{$jcode->id} n'est pas utilisé, skip");
            return;
        }

        // Débit wallet_materiaux artisan
        $walletService->debit(
            $jcode->artisan,
            WalletType::WALLET_MATERIAUX,
            $jcode->montant,
            "Paiement fournisseur J-Code {$jcode->code}",
            ['jcode_id' => $jcode->id, 'fournisseur_id' => $jcode->fournisseur_id]
        );

        // Virement Mobile Money réel fournisseur
        $provider = $jcode->fournisseur->preferred_payment_provider ?? 'wave';
        $description = "Paiement J-Code {$jcode->code} mission #{$jcode->mission_id}";

        try {
            if ($provider === 'wave') {
                $waveService->transferToMobileMoney($jcode->fournisseur->phone, $jcode->montant, $description);
            } elseif ($provider === 'orange_money') {
                $orangeMoneyService->transferToMobileMoney($jcode->fournisseur->phone, $jcode->montant, $description);
            }

            // Notification fournisseur
            $notificationService->send(
                $jcode->fournisseur,
                'payment',
                'Paiement J-Code reçu',
                "Vous avez reçu {$jcode->montant} FCFA pour le J-Code {$jcode->code}. Virement effectué.",
                ['jcode_id' => $jcode->id, 'montant' => $jcode->montant]
            );

            $jcode->update([
                'paiement_status' => 'paye',
                'paye_at' => now()
            ]);

            Log::info('Paiement fournisseur J+1 effectué avec succès', [
                'jcode_id' => $jcode->id,
                'fournisseur_id' => $jcode->fournisseur_id,
                'montant' => $jcode->montant,
            ]);
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
