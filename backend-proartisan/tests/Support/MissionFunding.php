<?php

namespace Tests\Support;

use App\Models\Devis;
use App\Models\Mission;
use App\Models\Transaction;

/**
 * Preuves de financement d'une mission : la machine à états refuse le passage
 * à `funded_locked` sans devis accepté ni paiement confirmé (Chantier 19).
 * Les tests qui pilotent une transition à la main posent ces preuves ici.
 */
final class MissionFunding
{
    public static function prove(Mission $mission, int $montant = 100000): Transaction
    {
        $devis = Devis::create([
            'mission_id' => $mission->id,
            'artisan_id' => $mission->artisan_id,
            'materials_required' => false,
            'commission_service_ratio' => 0.10,
            'lignes_json' => [['type' => 'mo', 'description' => 'Main d\'œuvre', 'montant' => $montant]],
            'jalons_json' => [['ordre' => 1, 'description' => 'Étape unique', 'montant' => $montant, 'date_cible' => now()->addDays(5)->toDateString()]],
            'statut' => 'accepte',
            'is_avenant' => false,
        ]);

        return Transaction::create([
            'mission_id' => $mission->id,
            'user_id' => $mission->client_id,
            'type' => 'acompte',
            'montant' => $montant,
            'wallet_source' => 'client_mobile_money',
            'wallet_dest' => 'escrow_mission_'.$mission->id,
            'provider' => 'wave',
            'statut' => 'confirme',
            'reference_externe' => 'TXN-TEST-'.$mission->id.'-'.uniqid(),
            'metadata' => ['devis_id' => $devis->id],
        ]);
    }
}
