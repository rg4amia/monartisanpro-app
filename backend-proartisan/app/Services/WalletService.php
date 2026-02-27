<?php

namespace App\Services;

use App\Models\Jalon;
use App\Models\JCode;
use App\Models\Mission;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

class WalletService
{
    /**
     * Fragmente le séquestre lors de l'acceptation du devis.
     * RÈGLE : ratio_materiaux fixé ici, immuable ensuite.
     */
    public function fragmentEscrow(Mission $mission, int $montantTotal, float $ratioMat): void
    {
        $montantMat = (int) round($montantTotal * $ratioMat);
        $montantMo  = $montantTotal - $montantMat;

        DB::transaction(function () use ($mission, $montantTotal, $montantMat, $montantMo, $ratioMat) {
            $mission->update([
                'montant_total'     => $montantTotal,
                'montant_materiaux' => $montantMat,
                'montant_mo'        => $montantMo,
                'ratio_materiaux'   => $ratioMat,
                'status'            => 'financee',
            ]);

            Transaction::create([
                'mission_id'    => $mission->id,
                'user_id'       => $mission->client_id,
                'type'          => 'acompte',
                'montant'       => $montantTotal,
                'wallet_source' => 'client_mobile_money',
                'wallet_dest'   => 'escrow_mission_' . $mission->id,
                'provider'      => 'wave',
                'statut'        => 'confirme',
            ]);
        });
    }

    /**
     * Libère le montant d'un jalon vers l'artisan (main d'œuvre).
     */
    public function releaseJalon(Jalon $jalon): void
    {
        $mission = $jalon->mission;

        DB::transaction(function () use ($jalon, $mission) {
            $jalon->update([
                'statut'  => 'paye',
                'paye_at' => now(),
            ]);

            $mission->decrement('montant_mo', $jalon->montant);

            Transaction::create([
                'mission_id'    => $mission->id,
                'user_id'       => $mission->artisan_id,
                'type'          => 'liberation_jalon',
                'montant'       => $jalon->montant,
                'wallet_source' => 'escrow_mission_' . $mission->id,
                'wallet_dest'   => 'artisan_' . $mission->artisan_id,
                'provider'      => 'wave',
                'statut'        => 'confirme',
            ]);
        });
    }

    /**
     * Paye le fournisseur lors de la validation d'un J-Code.
     */
    public function payFournisseur(JCode $jcode): void
    {
        $mission = $jcode->mission;

        DB::transaction(function () use ($jcode, $mission) {
            $jcode->update([
                'statut'     => 'utilise',
                'scanned_at' => now(),
            ]);

            $mission->decrement('montant_materiaux', $jcode->montant);

            Transaction::create([
                'mission_id'    => $mission->id,
                'user_id'       => $jcode->fournisseur_id,
                'type'          => 'paiement_fournisseur',
                'montant'       => $jcode->montant,
                'wallet_source' => 'escrow_mission_' . $mission->id,
                'wallet_dest'   => 'fournisseur_' . $jcode->fournisseur_id,
                'provider'      => 'virement_bancaire',
                'statut'        => 'en_attente', // virement J+1
            ]);
        });
    }
}
