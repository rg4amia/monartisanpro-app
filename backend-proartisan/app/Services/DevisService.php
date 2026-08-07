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
        $payload = $this->normalizePayload($data, $artisan);

        // RÈGLE : Un artisan ne peut pas soumettre plusieurs devis tant que le précédent n'est pas refusé
        $existingArtisanDevis = Devis::where('mission_id', $mission->id)
            ->where('artisan_id', $artisan->id)
            ->where('statut', '!=', 'refuse')
            ->exists();
        if ($existingArtisanDevis) {
            throw new \InvalidArgumentException("Vous avez déjà soumis un devis pour cette mission. Vous devez attendre que le client le refuse ou l'accepte.");
        }

        // RÈGLE : La mission concernée par un devis en attente ne doit plus pouvoir recevoir d'autres devis
        $hasPendingDevis = Devis::where('mission_id', $mission->id)
            ->where('statut', 'soumis')
            ->exists();
        if ($hasPendingDevis) {
            throw new \InvalidArgumentException("Cette mission a déjà un devis en cours d'examen par le client.");
        }

        $materialsRequired = filter_var($payload['materials_required'] ?? true, FILTER_VALIDATE_BOOLEAN);
        $interventionTypeId = $payload['intervention_type_id'] ?? null;

        // 1. If materials_required is true, must select articles from supplier catalog
        if ($materialsRequired) {
            $hasCatalogMaterial = collect($payload['lignes_json'])
                ->where('type', 'mat')
                ->where('source', 'catalog')
                ->isNotEmpty();
            if (!$hasCatalogMaterial) {
                throw new \InvalidArgumentException("Le devis doit contenir au moins un article d'un fournisseur agréé car l'acquisition de matériel est requise.");
            }
        } else {
            // If materials_required is false, intervention_type_id must be specified
            if (empty($interventionTypeId)) {
                throw new \InvalidArgumentException("Veuillez indiquer le type d'intervention pour ce devis sans matériel.");
            }
        }

        // 2. Validate Labor (Main d'œuvre - MO) obligation
        $requiresLabor = true;
        if (!$materialsRequired && !empty($interventionTypeId)) {
            $intType = \App\Models\InterventionType::find($interventionTypeId);
            if ($intType) {
                $requiresLabor = (bool) $intType->requires_labor;
            }
        }
        
        if ($requiresLabor) {
            $hasMo = collect($payload['lignes_json'])
                ->where('type', 'mo')
                ->isNotEmpty();
            $moSum = collect($payload['lignes_json'])
                ->where('type', 'mo')
                ->sum('montant');
            if (!$hasMo || $moSum <= 0) {
                throw new \InvalidArgumentException("La main d'œuvre est obligatoire pour ce devis.");
            }
        }

        // 3. Night Mode / Artisan Stock check
        $isNightMode = now()->hour >= 18 || now()->hour < 6;
        foreach ($payload['lignes_json'] as $ligne) {
            if (($ligne['type'] ?? '') === 'mat') {
                $isArtisanStock = ($ligne['source'] ?? '') === 'artisan_stock' || !empty($ligne['artisan_stock_id']);
                if ($isArtisanStock) {
                    // Check if it's strictly night mode
                    if (!$isNightMode) {
                        throw new \InvalidArgumentException("L'utilisation du stock de matériel de l'artisan est strictement réservée au mode nuit (18h-06h).");
                    }
                    
                    if (empty($ligne['artisan_stock_id'])) {
                        throw new \InvalidArgumentException("Veuillez spécifier l'identifiant du stock de l'artisan pour l'article : " . ($ligne['description'] ?? ''));
                    }
                    
                    $stock = \App\Models\ArtisanStock::where('id', $ligne['artisan_stock_id'])
                        ->where('artisan_id', $artisan->id)
                        ->first();
                    
                    if (!$stock) {
                        throw new \InvalidArgumentException("L'article spécifié n'existe pas dans votre stock.");
                    }
                    
                    $requestedQty = (int) ($ligne['quantity'] ?? 1);
                    if ($stock->quantity < $requestedQty) {
                        throw new \InvalidArgumentException("Quantité insuffisante en stock pour : {$stock->description} (disponible: {$stock->quantity}, demandé: {$requestedQty}).");
                    }
                }
            }
        }

        if ($mission->status instanceof \App\States\Mission\PendingArtisanAcceptanceState) {
            $mission->status->transitionTo(\App\States\Mission\DraftState::class);
            $mission->refresh();
        }

        $devis = Devis::create([
            'mission_id'  => $mission->id,
            'artisan_id'  => $artisan->id,
            'materials_required' => $materialsRequired,
            'intervention_type_id' => $interventionTypeId,
            'lignes_json' => $payload['lignes_json'],
            'jalons_json' => $payload['jalons_json'],
            'statut'      => 'soumis',
        ]);

        $this->notificationService->send(
            $mission->client,
            'devis',
            'Nouveau devis reçu',
            "L'artisan {$artisan->name} vous a transmis un devis pour la mission #{$mission->id}.",
            ['mission_id' => $mission->id, 'devis_id' => $devis->id]
        );

        return $devis;
    }

    public function normalizePayload(array $data, User $artisan): array
    {
        $lignes = collect($data['lignes_json'] ?? $data['lignes'] ?? [])
            ->map(fn (array $ligne) => $this->normalizeLigne($ligne, $artisan))
            ->values()
            ->all();

        return [
            'materials_required' => isset($data['materials_required']) ? filter_var($data['materials_required'], FILTER_VALIDATE_BOOLEAN) : true,
            'intervention_type_id' => isset($data['intervention_type_id']) ? (int) $data['intervention_type_id'] : null,
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

            if ($devis->statut === 'accepte' && (string) $devis->mission->status === 'funded_locked') {
                return;
            }

            if ($devis->statut !== 'soumis') {
                throw new \InvalidArgumentException('Ce devis ne peut plus être accepté.');
            }

            // 1. Calcul du ratio matériaux (TTC)
            $montantTotal   = $devis->montant_total;
            $montantMat     = $devis->montant_materiaux;
            $ratioMat       = $montantTotal > 0 ? round($montantMat / $montantTotal, 4) : 0.6500;

            // 2. Mise à jour du devis
            $devis->update([
                'statut' => 'accepte',
                'ratio_materiaux' => $ratioMat,
            ]);

            // 3. Association artisan ↔ mission
            $devis->mission->update(['artisan_id' => $devis->artisan_id]);

            // 4. Création des jalons depuis jalons_json (convertis en TTC)
            if (! $devis->mission->jalons()->exists()) {
                $commissionService = \App\Models\Setting::getValueByKey('commission_service', 0.10);
                foreach ($devis->jalons_json as $jalonData) {
                    $montantTtc = (int) round($jalonData['montant'] * (1 + $commissionService));
                    Jalon::create([
                        'mission_id'  => $devis->mission_id,
                        'ordre'       => $jalonData['ordre'],
                        'description' => $jalonData['description'],
                        'montant'     => $montantTtc,
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
                'Devis validé et fonds séquestrés !',
                "Votre devis pour la mission #{$devis->mission_id} a été approuvé. Les fonds sont disponibles et sécurisés en séquestre.",
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

        $devis->loadMissing(['artisan', 'mission']);
        $this->notificationService->send(
            $devis->artisan,
            'devis',
            'Devis refusé',
            "Le client a refusé votre devis pour la mission #{$devis->mission_id}.",
            ['mission_id' => $devis->mission_id, 'devis_id' => $devis->id]
        );
    }

    private function normalizeLigne(array $ligne, User $artisan): array
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

        if (! empty($ligne['artisan_stock_id'])) {
            $normalized['artisan_stock_id'] = (int) $ligne['artisan_stock_id'];
            $stock = \App\Models\ArtisanStock::where('id', $ligne['artisan_stock_id'])
                ->where('artisan_id', $artisan->id)
                ->first();
            if ($stock) {
                $normalized['condition'] = $stock->condition;
                $normalized['source'] = 'artisan_stock';
            }
        }

        return $normalized;
    }
}
