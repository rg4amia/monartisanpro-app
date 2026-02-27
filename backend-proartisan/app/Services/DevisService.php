<?php

namespace App\Services;

use App\Models\Devis;
use App\Models\Jalon;
use App\Models\Mission;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DevisService
{
    public function __construct(
        private WalletService $walletService,
        private NotificationService $notificationService,
    ) {}

    /**
     * Crée un devis pour une mission.
     */
    public function create(Mission $mission, User $artisan, array $data): Devis
    {
        return Devis::create([
            'mission_id'  => $mission->id,
            'artisan_id'  => $artisan->id,
            'lignes_json' => $data['lignes'],
            'jalons_json' => $data['jalons'],
            'statut'      => 'soumis',
        ]);
    }

    /**
     * Accepte un devis.
     * RÈGLE : ratio_materiaux fixé ici, immuable. Crée les jalons en base.
     */
    public function accept(Devis $devis, string $provider = 'wave'): void
    {
        DB::transaction(function () use ($devis, $provider) {
            // 1. Calcul du ratio matériaux
            $lignes         = collect($devis->lignes_json);
            $montantTotal   = $lignes->sum('montant');
            $montantMat     = $lignes->where('type', 'mat')->sum('montant');
            $ratioMat       = $montantTotal > 0 ? round($montantMat / $montantTotal, 4) : 0.6500;

            // 2. Mise à jour du devis
            $devis->update(['statut' => 'accepte']);

            // 3. Association artisan ↔ mission
            $devis->mission->update(['artisan_id' => $devis->artisan_id]);

            // 4. Création des jalons depuis jalons_json
            foreach ($devis->jalons_json as $jalonData) {
                Jalon::create([
                    'mission_id'  => $devis->mission_id,
                    'ordre'       => $jalonData['ordre'],
                    'description' => $jalonData['description'],
                    'montant'     => $jalonData['montant'],
                    'statut'      => 'en_attente',
                ]);
            }

            // 5. Fragmentation du séquestre
            $this->walletService->fragmentEscrow(
                $devis->mission,
                $montantTotal,
                $ratioMat
            );

            // 6. Notifier l'artisan
            $this->notificationService->send(
                $devis->artisan,
                'payment',
                'Mission financée !',
                "Votre mission #{$devis->mission_id} est financée. Vous pouvez commencer les travaux.",
                ['mission_id' => $devis->mission_id]
            );
        });
    }

    /**
     * Refuse un devis.
     */
    public function refuse(Devis $devis): void
    {
        $devis->update(['statut' => 'refuse']);
    }
}
