<?php

namespace App\Services;

use App\Models\JCode;
use App\Models\Mission;
use App\Models\SupplierProduct;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class JCodeService
{
    public function __construct(
        private GeoService $geoService,
        private WalletService $walletService,
        private NotificationService $notificationService,
        private SupplierCatalogService $supplierCatalogService,
        private ScoreService $scoreService,
    ) {}

    /**
     * Génère un J-Code pour l'achat de matériaux.
     * Format : PA-XXXX (4 caractères alphanumériques majuscules).
     */
    public function generate(
        Mission $mission,
        User $artisan,
        User $fournisseur,
        array $items,
        ?int $montant = null
    ): JCode
    {
        $this->supplierCatalogService->ensureApprovedSupplier($fournisseur);

        return DB::transaction(function () use ($mission, $artisan, $fournisseur, $items, $montant) {
            $normalizedItems = [];
            $computedTotal = 0;

            foreach ($items as $index => $item) {
                $quantity = (int) ($item['quantity'] ?? 0);
                $productId = $item['supplier_product_id'] ?? null;

                if ($productId) {
                    $product = SupplierProduct::query()
                        ->whereKey($productId)
                        ->where('supplier_id', $fournisseur->id)
                        ->where('is_active', true)
                        ->first();

                    if (! $product) {
                        throw ValidationException::withMessages([
                            "items.$index.supplier_product_id" => ['Cet article n\'appartient pas au fournisseur sélectionné.'],
                        ]);
                    }

                    if ($product->stock_quantity < $quantity) {
                        throw ValidationException::withMessages([
                            "items.$index.quantity" => ["Stock insuffisant pour {$product->name}. Disponible : {$product->stock_quantity}."],
                        ]);
                    }

                    $unitPrice = (int) $product->unit_price;
                    $name = $product->name;
                    $sku = $product->sku;
                    $source = 'catalog';
                    $supplierProductId = $product->id;
                } else {
                    $name = trim((string) ($item['name'] ?? ''));
                    $sku = isset($item['sku']) ? trim((string) $item['sku']) : null;
                    $unitPrice = (int) ($item['unit_price'] ?? 0);

                    if ($name === '') {
                        throw ValidationException::withMessages([
                            "items.$index.name" => ['Le nom de l\'article personnalisé est obligatoire.'],
                        ]);
                    }

                    if ($unitPrice <= 0) {
                        throw ValidationException::withMessages([
                            "items.$index.unit_price" => ['Le prix unitaire doit être supérieur à zéro.'],
                        ]);
                    }

                    $source = 'custom';
                    $supplierProductId = null;
                    $sku = $sku !== '' ? $sku : null;
                }

                $subtotal = $unitPrice * $quantity;
                $computedTotal += $subtotal;

                $normalizedItems[] = [
                    'supplier_product_id' => $supplierProductId,
                    'source' => $source,
                    'item_name' => $name,
                    'item_sku' => $sku,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'subtotal' => $subtotal,
                    'status' => 'requested',
                ];
            }

            if ($computedTotal < 1000) {
                throw ValidationException::withMessages([
                    'montant' => ['Le montant minimum est de 1 000 FCFA.'],
                ]);
            }

            if ($montant !== null && $montant !== $computedTotal) {
                throw ValidationException::withMessages([
                    'montant' => ['Le montant ne correspond pas à la somme des articles sélectionnés.'],
                ]);
            }

            $code = $this->generateUniqueCode();

            $jcode = JCode::create([
                'mission_id' => $mission->id,
                'artisan_id' => $artisan->id,
                'fournisseur_id' => $fournisseur->id,
                'code' => $code,
                'ussd_code' => '*555*' . str_replace('PA-', '', $code) . '#',
                'qr_url' => null,
                'montant' => $computedTotal,
                'statut' => 'actif',
                'expires_at' => now()->addHours(config('prosartisan.jcode.ttl_hours', 48)),
            ]);

            $jcode->items()->createMany($normalizedItems);

            return $jcode->load(['artisan', 'fournisseur.fournisseurAgree', 'items.supplierProduct']);
        });
    }

    /**
     * Valide un J-Code lors du scan par le fournisseur.
     * Supporte la consommation partielle : le fournisseur indique les items qu'il sert.
     *
     * RÈGLE CRITIQUE : distance > 100 m → blocage + alerte admin.
     *
     * @param  array  $servedItems  [{jcode_item_id: int, quantity_served: int}, ...]
     */
    public function scan(JCode $jcode, User $fournisseur, float $lat, float $lng, array $servedItems = []): array
    {
        $jcode->loadMissing(['artisan', 'items.supplierProduct']);

        if (! $jcode->isActif()) {
            throw ValidationException::withMessages([
                'code' => ['Ce J-Code est expiré ou déjà entièrement utilisé.'],
            ]);
        }

        $this->supplierCatalogService->ensureApprovedSupplier($fournisseur);

        // Vérification GPS obligatoire (pour CHAQUE fournisseur, à CHAQUE scan)
        $gpsCheck = $this->geoService->validateJCodeGps($fournisseur->id, $lat, $lng);

        if (! $gpsCheck['valid']) {
            Log::warning("[GPS FRAUD ALERT] J-Code {$jcode->code} | Fournisseur #{$fournisseur->id} | Distance: {$gpsCheck['distance']} m (max: {$gpsCheck['max']} m)");

            $this->notificationService->sendAdmin(
                'alert',
                'Tentative de fraude J-Code',
                "J-Code {$jcode->code} scanné à {$gpsCheck['distance']} m de la boutique (max {$gpsCheck['max']} m).",
                ['jcode_id' => $jcode->id, 'fournisseur_id' => $fournisseur->id]
            );

            $this->scoreService->recordGpsFraudAttempt($fournisseur, $jcode->mission_id, $jcode->code);

            throw ValidationException::withMessages([
                'gps' => ["Position GPS invalide. Distance {$gpsCheck['distance']} m (maximum {$gpsCheck['max']} m). Transaction bloquée."],
            ]);
        }

        // Si aucun article n'est spécifié, on sert tout ce qui reste disponible
        if (empty($servedItems)) {
            $servedItems = [];
            foreach ($jcode->items as $item) {
                $remainingQty = $item->quantity - ($item->quantity_served ?? 0);
                if ($remainingQty > 0) {
                    $servedItems[] = [
                        'jcode_item_id'   => $item->id,
                        'quantity_served' => $remainingQty,
                    ];
                }
            }
        }

        // Indexer les items du J-Code par ID
        $jcodeItemsById = $jcode->items->keyBy('id');
        $montantServiCeScan = 0;

        $scanResult = DB::transaction(function () use ($jcode, $fournisseur, $lat, $lng, $servedItems, $jcodeItemsById, &$montantServiCeScan) {
            $jcode->setPositionScan($lat, $lng);

            foreach ($servedItems as $served) {
                $itemId = $served['jcode_item_id'];
                $qtyServed = (int) $served['quantity_served'];

                $item = $jcodeItemsById->get($itemId);

                if (! $item) {
                    throw ValidationException::withMessages([
                        'served_items' => ["L'article #{$itemId} n'appartient pas à ce J-Code."],
                    ]);
                }

                $remainingQty = $item->quantity - ($item->quantity_served ?? 0);

                if ($qtyServed > $remainingQty) {
                    throw ValidationException::withMessages([
                        'served_items' => ["Quantité servie ({$qtyServed}) dépasse la quantité restante ({$remainingQty}) pour {$item->item_name}."],
                    ]);
                }

                // Décrémenter le stock catalogue si applicable
                if ($item->source === 'catalog') {
                    if (! $item->supplierProduct || $item->supplierProduct->supplier_id !== $fournisseur->id) {
                        // Item catalogue d'un autre fournisseur → custom-serve autorisé
                        // Le fournisseur sert l'item même sans référence catalogue chez lui
                    } else {
                        $this->supplierCatalogService->decrementStockForServedItem(
                            $item->supplierProduct,
                            $qtyServed,
                        );
                    }
                }

                $montantItem = $item->unit_price * $qtyServed;
                $montantServiCeScan += $montantItem;

                $newQtyServed = ($item->quantity_served ?? 0) + $qtyServed;
                $isFullyServed = ($newQtyServed >= $item->quantity);

                $item->update([
                    'quantity_served'       => $newQtyServed,
                    'status'                => $isFullyServed ? 'served' : 'partial',
                    'served_by_supplier_id' => $fournisseur->id,
                ]);
            }

            // Mettre à jour le montant consommé total du J-Code
            $newMontantConsomme = ($jcode->montant_consomme ?? 0) + $montantServiCeScan;
            $isFullyConsumed = ($newMontantConsomme >= $jcode->montant);

            $jcode->update([
                'montant_consomme' => $newMontantConsomme,
                'statut'           => $isFullyConsumed ? 'utilise' : 'partiellement_utilise',
                'scanned_at'       => now(),
                'paiement_status'  => 'programme',
            ]);

            return [
                'fully_consumed' => $isFullyConsumed,
            ];
        });

        // Dispatcher le paiement J+1 pour le montant servi par CE fournisseur lors de CE scan
        \App\Jobs\PaySupplierJob::dispatch(
            $jcode->id,
            $fournisseur->id,
            $montantServiCeScan
        )->delay(now()->addDay());

        $this->notificationService->send(
            $jcode->artisan,
            'validation',
            $scanResult['fully_consumed'] ? 'Matériaux entièrement livrés' : 'Matériaux partiellement livrés',
            $scanResult['fully_consumed']
                ? "Le fournisseur a validé votre J-Code {$jcode->code}. Tous les matériaux sont livrés. Paiement J+1 garanti."
                : "Le fournisseur a servi une partie de votre J-Code {$jcode->code} ({$montantServiCeScan} FCFA). Solde restant : {$jcode->montant_restant} FCFA.",
            ['jcode_id' => $jcode->id, 'mission_id' => $jcode->mission_id]
        );

        $this->scoreService->recordJCodeSuccess($fournisseur, $jcode->mission_id, $jcode->code);

        return [
            'valid'             => true,
            'distance'          => $gpsCheck['distance'],
            'montant_servi'     => $montantServiCeScan,
            'montant_consomme'  => $jcode->montant_consomme,
            'montant_restant'   => $jcode->montant_restant,
            'statut'            => $jcode->statut,
            'items_served'      => count($servedItems),
            'fully_consumed'    => $scanResult['fully_consumed'],
            'artisan'           => ['id' => $jcode->artisan_id, 'name' => $jcode->artisan->name],
        ];
    }

    /**
     * Règle le paiement fournisseur pour un scan (total ou partiel).
     *
     * @param int|null $specificFournisseurId  Fournisseur à payer (pour scan partiel)
     * @param int|null $montantServi           Montant servi lors de ce scan spécifique
     */
    public function settleSupplierPayment(JCode $jcode, ?int $specificFournisseurId = null, ?int $montantServi = null, bool $force = false): void
    {
        $jcode->loadMissing(['mission', 'artisan', 'fournisseur']);

        if (! in_array($jcode->statut, ['utilise', 'partiellement_utilise']) || $jcode->paiement_status === 'paye') {
            return;
        }

        if ($jcode->mission?->funds_frozen && ! $force) {
            Log::warning("Paiement fournisseur suspendu: mission #{$jcode->mission_id} en litige");
            return;
        }

        // Déterminer le fournisseur et le montant à payer
        $fournisseurId = $specificFournisseurId ?? $jcode->fournisseur_id;
        $fournisseur = User::findOrFail($fournisseurId);
        $montantBase = $montantServi ?? $jcode->montant;

        // Calculer les commissions basées sur le montant servi
        $platformFeeRatio = \App\Models\Setting::getValueByKey('platform_fee_ratio', 0.03);
        $debitTtc = (int) round($montantBase * (1 + $platformFeeRatio));
        $platformFee = $debitTtc - $montantBase;

        $supplierCommissionRatio = \App\Models\Setting::getValueByKey('commission_fournisseur', 0.05);
        $supplierCommission = (int) round($montantBase * $supplierCommissionRatio);
        $gainNetSupplier = $montantBase - $supplierCommission;

        $this->walletService->debit(
            $jcode->artisan,
            \App\Enums\WalletType::WALLET_MATERIAUX,
            $debitTtc,
            "Paiement fournisseur J-Code {$jcode->code} (partiel: {$montantBase} FCFA)",
            [
                'mission_id' => $jcode->mission_id,
                'jcode_id' => $jcode->id,
                'fournisseur_id' => $fournisseurId,
                'type' => 'paiement_fournisseur',
                'montant_servi' => $montantBase,
            ]
        );

        // Créditer le compte financier ProsArtisan
        $totalPlatformCommission = $platformFee + $supplierCommission;
        $this->walletService->creditPlatformFinancialAccount(
            $totalPlatformCommission,
            "Commission plateforme J-Code {$jcode->code} - Mission #{$jcode->mission_id}",
            [
                'mission_id' => $jcode->mission_id,
                'jcode_id' => $jcode->id,
            ]
        );

        $provider = $fournisseur->preferred_payment_provider ?? 'wave';
        $description = "Paiement J-Code {$jcode->code} mission #{$jcode->mission_id} ({$montantBase} FCFA)";

        if ($provider === 'orange_money') {
            $result = app(OrangeMoneyService::class)->transferToMobileMoney($fournisseur->payment_phone ?? $fournisseur->phone, $gainNetSupplier, $description);
            $reference = $result['txnid'] ?? null;
        } else {
            $result = app(WaveService::class)->transferToMobileMoney($fournisseur->payment_phone ?? $fournisseur->phone, $gainNetSupplier, $description);
            $reference = $result['id'] ?? null;
        }

        \App\Models\Transaction::create([
            'mission_id' => $jcode->mission_id,
            'user_id' => $fournisseurId,
            'type' => 'paiement_fournisseur',
            'montant' => $gainNetSupplier,
            'wallet_source' => 'escrow_mission_' . $jcode->mission_id,
            'wallet_dest' => 'supplier_mobile_money_' . $fournisseurId,
            'provider' => $provider,
            'statut' => 'confirme',
            'reference_externe' => $reference,
            'metadata' => [
                'jcode_id' => $jcode->id,
                'montant_servi' => $montantBase,
                'partial' => ($montantServi !== null),
            ],
        ]);

        $this->notificationService->send(
            $fournisseur,
            'payment',
            'Paiement J-Code recu',
            "Vous avez recu {$gainNetSupplier} FCFA pour le J-Code {$jcode->code}.",
            ['jcode_id' => $jcode->id, 'montant' => $gainNetSupplier]
        );

        // Marquer comme payé seulement si le J-Code est entièrement consommé
        if ($jcode->isFullyConsumed()) {
            $jcode->update([
                'paiement_status' => 'paye',
                'paye_at' => now(),
            ]);
        }
    }

    /**
     * Génère un code PA-XXXX unique.
     */
    private function generateUniqueCode(): string
    {
        $prefix = config('prosartisan.jcode.prefix', 'PA-');
        $chars  = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // sans I, O, 0, 1

        do {
            $suffix = '';
            for ($i = 0; $i < 4; $i++) {
                $suffix .= $chars[random_int(0, strlen($chars) - 1)];
            }
            $code = $prefix . $suffix;
        } while (JCode::where('code', $code)->exists());

        return $code;
    }
}
