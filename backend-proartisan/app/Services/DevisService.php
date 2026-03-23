<?php

namespace App\Services;

use App\Models\Devis;
use App\Models\Jalon;
use App\Models\Mission;
use App\Models\Transaction;
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
        $payload = $this->normalizePayload($data);

        return Devis::create([
            'mission_id'  => $mission->id,
            'artisan_id'  => $artisan->id,
            'lignes_json' => $payload['lignes_json'],
            'jalons_json' => $payload['jalons_json'],
            'statut'      => 'soumis',
        ]);
    }

    public function normalizePayload(array $data): array
    {
        $lignes = collect($data['lignes_json'] ?? $data['lignes'] ?? [])
            ->map(fn (array $ligne) => $this->normalizeLigne($ligne))
            ->values()
            ->all();

        return [
            'lignes_json' => $lignes,
            'jalons_json' => $data['jalons_json'] ?? $data['jalons'] ?? [],
        ];
    }

    /**
     * Accepte un devis.
     * RÈGLE : ratio_materiaux fixé ici, immuable. Crée les jalons en base.
     */
    public function accept(Devis $devis, Transaction $paymentTransaction): void
    {
        DB::transaction(function () use ($devis, $paymentTransaction) {
            $devis = Devis::query()
                ->with(['mission.client', 'artisan'])
                ->lockForUpdate()
                ->findOrFail($devis->id);

            $paymentTransaction = Transaction::query()
                ->lockForUpdate()
                ->findOrFail($paymentTransaction->id);

            if ($paymentTransaction->mission_id !== $devis->mission_id) {
                throw new \InvalidArgumentException('La transaction ne correspond pas à ce devis.');
            }

            if (($paymentTransaction->metadata['devis_id'] ?? null) !== $devis->id) {
                throw new \InvalidArgumentException('La transaction n\'a pas été initiée pour ce devis.');
            }

            if (! $paymentTransaction->statut->isSuccessful()) {
                throw new \InvalidArgumentException('Le paiement doit être confirmé avant d\'accepter le devis.');
            }

            if ($devis->statut === 'accepte' && $devis->mission->status === 'financee') {
                return;
            }

            if ($devis->statut !== 'soumis') {
                throw new \InvalidArgumentException('Ce devis ne peut plus être accepté.');
            }

            // 1. Calcul du ratio matériaux
            $lignes         = collect($devis->lignes_json);
            $montantTotal   = $lignes->sum('montant');
            $montantMat     = $lignes->where('type', 'mat')->sum('montant');
            $ratioMat       = $montantTotal > 0 ? round($montantMat / $montantTotal, 4) : 0.6500;

            // 2. Mise à jour du devis
            $devis->update([
                'statut' => 'accepte',
                'ratio_materiaux' => $ratioMat,
            ]);

            // 3. Association artisan ↔ mission
            $devis->mission->update(['artisan_id' => $devis->artisan_id]);

            // 4. Création des jalons depuis jalons_json
            if (! $devis->mission->jalons()->exists()) {
                foreach ($devis->jalons_json as $jalonData) {
                    Jalon::create([
                        'mission_id'  => $devis->mission_id,
                        'ordre'       => $jalonData['ordre'],
                        'description' => $jalonData['description'],
                        'montant'     => $jalonData['montant'],
                        'statut'      => 'en_attente',
                    ]);
                }
            }

            // 5. Fragmentation du séquestre
            $this->walletService->fragmentEscrow(
                $devis->mission,
                $devis->mission->client,
                $devis->artisan,
                $montantTotal,
                $ratioMat,
                $paymentTransaction
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

    private function normalizeLigne(array $ligne): array
    {
        $type = $ligne['type'] ?? 'mo';
        $montant = (int) ($ligne['montant'] ?? 0);

        $normalized = [
            'type' => $type,
            'description' => $ligne['description'] ?? '',
            'montant' => $montant,
        ];

        if ($type !== 'mat') {
            return $normalized;
        }

        $quantity = max(1, (int) ($ligne['quantity'] ?? 1));
        $unitPrice = isset($ligne['unit_price']) ? (int) $ligne['unit_price'] : null;

        if ($unitPrice !== null && $unitPrice > 0) {
            $montant = $quantity * $unitPrice;
        } else {
            $unitPrice = $quantity > 0 ? (int) round($montant / $quantity) : $montant;
        }

        $normalized['montant'] = $montant;
        $normalized['quantity'] = $quantity;
        $normalized['unit_price'] = $unitPrice;
        $normalized['source'] = $ligne['source'] ?? (! empty($ligne['supplier_product_id']) ? 'catalog' : 'custom');

        if (! empty($ligne['sku'])) {
            $normalized['sku'] = trim((string) $ligne['sku']);
        }

        if (! empty($ligne['supplier_product_id'])) {
            $normalized['supplier_product_id'] = (int) $ligne['supplier_product_id'];
        }

        return $normalized;
    }
}
