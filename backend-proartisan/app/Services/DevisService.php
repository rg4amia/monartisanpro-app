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

        $isAvenant = isset($data['is_avenant']) ? filter_var($data['is_avenant'], FILTER_VALIDATE_BOOLEAN) : false;
        $initialDevis = null;

        if ($isAvenant) {
            // Un avenant requiert obligatoirement un devis initial accepté
            $initialDevis = Devis::where('mission_id', $mission->id)
                ->where('is_avenant', false)
                ->where('statut', 'accepte')
                ->first();
            if (!$initialDevis) {
                throw new \InvalidArgumentException("Impossible de créer un avenant sans devis initial accepté.");
            }

            // Seul l'artisan déjà assigné à la mission peut soumettre un avenant
            if ($mission->artisan_id !== $artisan->id) {
                throw new \InvalidArgumentException("Seul l'artisan assigné à cette mission peut créer un avenant.");
            }

            // Statuts de mission autorisés pour un avenant
            $allowedStates = [
                \App\States\Mission\FundedLockedState::class,
                \App\States\Mission\InProgressState::class,
                \App\States\Mission\PendingApprovalState::class,
                \App\States\Mission\DisputedState::class,
            ];
            $currentStatusClass = get_class($mission->status);
            if (!in_array($currentStatusClass, $allowedStates)) {
                throw new \InvalidArgumentException("Impossible de créer un avenant pour une mission dans cet état.");
            }

            // Un seul avenant en attente d'examen à la fois
            $hasPendingAvenant = Devis::where('mission_id', $mission->id)
                ->where('is_avenant', true)
                ->where('statut', 'soumis')
                ->exists();
            if ($hasPendingAvenant) {
                throw new \InvalidArgumentException("Un avenant est déjà en cours d'examen pour cette mission.");
            }
        } else {
            // RÈGLE INITIALE : Un artisan ne peut pas soumettre plusieurs devis tant que le précédent n'est pas refusé
            $existingArtisanDevis = Devis::where('mission_id', $mission->id)
                ->where('artisan_id', $artisan->id)
                ->where('is_avenant', false)
                ->where('statut', '!=', 'refuse')
                ->exists();
            if ($existingArtisanDevis) {
                throw new \InvalidArgumentException("Vous avez déjà soumis un devis pour cette mission. Vous devez attendre que le client le refuse ou l'accepte.");
            }

            // RÈGLE INITIALE : La mission concernée par un devis en attente ne doit plus pouvoir recevoir d'autres devis
            $hasPendingDevis = Devis::where('mission_id', $mission->id)
                ->where('is_avenant', false)
                ->where('statut', 'soumis')
                ->exists();
            if ($hasPendingDevis) {
                throw new \InvalidArgumentException("Cette mission a déjà un devis en cours d'examen par le client.");
            }
        }

        // RÈGLE : Si l'artisan n'indique pas explicitement si le matériel est requis,
        // on le déduit de la présence ou non de lignes de type 'mat' (matériaux) dans le devis.
        $hasMaterials = collect($payload['lignes_json'] ?? [])
            ->where('type', 'mat')
            ->isNotEmpty();

        $materialsRequired = isset($data['materials_required'])
            ? filter_var($data['materials_required'], FILTER_VALIDATE_BOOLEAN)
            : $hasMaterials;

        $interventionTypeId = $payload['intervention_type_id'] ?? null;

        // 1. Si les matériaux sont requis, on doit sélectionner au moins un article du catalogue fournisseur agréé
        if ($materialsRequired) {
            $hasCatalogMaterial = collect($payload['lignes_json'])
                ->where('type', 'mat')
                ->where('source', 'catalog')
                ->isNotEmpty();
            if (!$hasCatalogMaterial) {
                throw new \InvalidArgumentException("Le devis doit contenir au moins un article d'un fournisseur agréé car l'acquisition de matériel est requise.");
            }
        } else {
            // Si pas de matériel requis et pas d'intervention_type_id fourni:
            if (empty($interventionTypeId)) {
                // Si materials_required n'a pas été fourni explicitement (vieux client mobile),
                // on applique un type d'intervention par défaut.
                if (!isset($data['materials_required'])) {
                    $defaultType = \App\Models\InterventionType::first();
                    if ($defaultType) {
                        $interventionTypeId = $defaultType->id;
                    }
                }
                
                // Si après cela c'est toujours vide
                if (empty($interventionTypeId)) {
                    throw new \InvalidArgumentException("Veuillez indiquer le type d'intervention pour ce devis sans matériel.");
                }
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
            'commission_service_ratio' => \App\Models\Setting::getLaborCommissionForArtisan($artisan),
            'lignes_json' => $payload['lignes_json'],
            'jalons_json' => $payload['jalons_json'],
            'statut'      => 'soumis',
            'is_avenant'  => $isAvenant,
            'parent_devis_id' => $isAvenant ? $initialDevis->id : null,
        ]);

        $this->notificationService->send(
            $mission->client,
            $isAvenant ? 'devis_avenant' : 'devis',
            $isAvenant ? 'Nouvel avenant de devis reçu' : 'Nouveau devis reçu',
            $isAvenant
                ? "L'artisan {$artisan->name} a soumis un avenant pour la mission #{$mission->id}."
                : "L'artisan {$artisan->name} vous a transmis un devis pour la mission #{$mission->id}.",
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

            if ($devis->statut === 'accepte') {
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

            // 3. Association artisan ↔ mission (seulement pour devis initial)
            if (!$devis->is_avenant) {
                $devis->mission->update(['artisan_id' => $devis->artisan_id]);
            }

            // 4. Création des jalons depuis jalons_json (convertis en TTC)
            $commissionService = $devis->commission_service_ratio !== null ? (float) $devis->commission_service_ratio : 0.10;
            if ($devis->is_avenant) {
                $maxOrdre = $devis->mission->jalons()->max('ordre') ?? 0;
                foreach ($devis->jalons_json as $jalonData) {
                    $montantTtc = (int) round($jalonData['montant'] * (1 + $commissionService));
                    Jalon::create([
                        'mission_id'  => $devis->mission_id,
                        'ordre'       => $maxOrdre + $jalonData['ordre'],
                        'description' => $jalonData['description'],
                        'montant'     => $montantTtc,
                        'statut'      => 'en_attente',
                    ]);
                }
            } else {
                if (! $devis->mission->jalons()->exists()) {
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
            }

            // 5. Fragmentation du séquestre
            if ($devis->is_avenant) {
                $this->walletService->applyAvenantEscrow(
                    $devis->mission,
                    $devis,
                    $paymentTransaction
                );
            } else {
                $this->walletService->fragmentEscrow(
                    $devis->mission,
                    $devis->mission->client,
                    $devis->artisan,
                    $montantTotal,
                    $ratioMat,
                    $paymentTransaction
                );
            }

            // 6. Notifier l'artisan
            $this->notificationService->send(
                $devis->artisan,
                'payment',
                $devis->is_avenant ? 'Avenant validé et fonds séquestrés !' : 'Devis validé et fonds séquestrés !',
                $devis->is_avenant
                    ? "Votre avenant pour la mission #{$devis->mission_id} a été approuvé. Les fonds supplémentaires sont disponibles et sécurisés en séquestre."
                    : "Votre devis pour la mission #{$devis->mission_id} a été approuvé. Les fonds sont disponibles et sécurisés en séquestre.",
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
        
        if ($devis->mission) {
            $devis->mission->update(['artisan_id' => null]);
        }

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
