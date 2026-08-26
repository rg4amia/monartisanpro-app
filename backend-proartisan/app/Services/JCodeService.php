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
     * RÈGLE CRITIQUE : distance > 100 m → blocage + alerte admin.
     */
    public function scan(JCode $jcode, User $fournisseur, float $lat, float $lng): array
    {
        $jcode->loadMissing(['artisan', 'items.supplierProduct']);

        if (! $jcode->isActif()) {
            throw ValidationException::withMessages([
                'code' => ['Ce J-Code est expiré ou déjà utilisé.'],
            ]);
        }

        $this->supplierCatalogService->ensureApprovedSupplier($fournisseur);

        if ($jcode->fournisseur_id !== null && $jcode->fournisseur_id !== $fournisseur->id) {
            throw ValidationException::withMessages([
                'fournisseur' => ['Ce J-Code est réservé à un autre fournisseur.'],
            ]);
        }

        // Vérification GPS obligatoire
        $gpsCheck = $this->geoService->validateJCodeGps($fournisseur->id, $lat, $lng);

        if (! $gpsCheck['valid']) {
            // RÈGLE : blocage + alerte automatique admin
            Log::warning("[GPS FRAUD ALERT] J-Code {$jcode->code} | Fournisseur #{$fournisseur->id} | Distance: {$gpsCheck['distance']} m (max: {$gpsCheck['max']} m)");

            // En production : notifier l'admin immédiatement
            $this->notificationService->sendAdmin(
                'alert',
                'Tentative de fraude J-Code',
                "J-Code {$jcode->code} scanné à {$gpsCheck['distance']} m de la boutique (max {$gpsCheck['max']} m).",
                ['jcode_id' => $jcode->id, 'fournisseur_id' => $fournisseur->id]
            );

            // Enregistrer la tentative de fraude dans le Score Logistique du fournisseur
            $this->scoreService->recordGpsFraudAttempt($fournisseur, $jcode->mission_id, $jcode->code);

            throw ValidationException::withMessages([
                'gps' => ["Position GPS invalide. Distance {$gpsCheck['distance']} m (maximum {$gpsCheck['max']} m). Transaction bloquée."],
            ]);
        }

        DB::transaction(function () use ($jcode, $fournisseur, $lat, $lng) {
            $jcode->setPositionScan($lat, $lng);

            foreach ($jcode->items as $item) {
                if ($item->source === 'catalog') {
                    if (! $item->supplierProduct || $item->supplierProduct->supplier_id !== $fournisseur->id) {
                        throw ValidationException::withMessages([
                            'items' => ["L'article catalogue {$item->item_name} n'est plus valide pour ce fournisseur."],
                        ]);
                    }

                    $this->supplierCatalogService->decrementStockForServedItem(
                        $item->supplierProduct,
                        $item->quantity,
                    );
                }

                if ($item->status !== 'served') {
                    $item->update(['status' => 'served']);
                }
            }

            $jcode->update([
                'fournisseur_id' => $fournisseur->id,
                'statut' => 'utilise',
                'scanned_at' => now(),
                'paiement_status' => 'programme',
            ]);
        });

        \App\Jobs\PaySupplierJob::dispatch($jcode->id)->delay(now()->addDay());

        $this->notificationService->send(
            $jcode->artisan,
            'validation',
            'Matériaux livrés',
            "Le fournisseur a validé votre J-Code {$jcode->code}. Paiement J+1 garanti.",
            ['jcode_id' => $jcode->id, 'mission_id' => $jcode->mission_id]
        );

        // Enregistrer le succès du scan dans le Score Logistique du fournisseur
        $this->scoreService->recordJCodeSuccess($fournisseur, $jcode->mission_id, $jcode->code);

        return [
            'valid'    => true,
            'distance' => $gpsCheck['distance'],
            'montant'  => $jcode->montant,
            'items_served' => $jcode->items->count(),
            'artisan'  => ['id' => $jcode->artisan_id, 'name' => $jcode->artisan->name],
        ];
    }

    public function settleSupplierPayment(JCode $jcode, bool $force = false): void
    {
        $jcode->loadMissing(['mission', 'artisan', 'fournisseur']);

        if ($jcode->statut !== 'utilise' || $jcode->paiement_status === 'paye') {
            return;
        }

        if ($jcode->mission?->funds_frozen && ! $force) {
            Log::warning("Paiement fournisseur suspendu: mission #{$jcode->mission_id} en litige");
            return;
        }

        // Calculer les commissions basées sur le montant HT du J-Code
        $platformFeeRatio = \App\Models\Setting::getValueByKey('platform_fee_ratio', 0.03);
        $debitTtc = (int) round($jcode->montant * (1 + $platformFeeRatio));
        $platformFee = $debitTtc - $jcode->montant;

        $supplierCommissionRatio = \App\Models\Setting::getValueByKey('commission_fournisseur', 0.05);
        $supplierCommission = (int) round($jcode->montant * $supplierCommissionRatio);
        $gainNetSupplier = $jcode->montant - $supplierCommission;

        $this->walletService->debit(
            $jcode->artisan,
            \App\Enums\WalletType::WALLET_MATERIAUX,
            $debitTtc,
            "Paiement fournisseur J-Code {$jcode->code}",
            [
                'mission_id' => $jcode->mission_id,
                'jcode_id' => $jcode->id,
                'fournisseur_id' => $jcode->fournisseur_id,
                'type' => 'paiement_fournisseur',
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

        $provider = $jcode->fournisseur->preferred_payment_provider ?? 'wave';
        $description = "Paiement J-Code {$jcode->code} mission #{$jcode->mission_id}";

        if ($provider === 'orange_money') {
            $result = app(OrangeMoneyService::class)->transferToMobileMoney($jcode->fournisseur->payment_phone ?? $jcode->fournisseur->phone, $gainNetSupplier, $description);
            $reference = $result['txnid'] ?? null;
        } else {
            $result = app(WaveService::class)->transferToMobileMoney($jcode->fournisseur->payment_phone ?? $jcode->fournisseur->phone, $gainNetSupplier, $description);
            $reference = $result['id'] ?? null;
        }

        \App\Models\Transaction::create([
            'mission_id' => $jcode->mission_id,
            'user_id' => $jcode->fournisseur_id,
            'type' => 'paiement_fournisseur',
            'montant' => $gainNetSupplier,
            'wallet_source' => 'escrow_mission_' . $jcode->mission_id,
            'wallet_dest' => 'supplier_mobile_money_' . $jcode->fournisseur_id,
            'provider' => $provider,
            'statut' => 'confirme',
            'reference_externe' => $reference,
            'metadata' => ['jcode_id' => $jcode->id],
        ]);

        $this->notificationService->send(
            $jcode->fournisseur,
            'payment',
            'Paiement J-Code recu',
            "Vous avez recu {$jcode->montant} FCFA pour le J-Code {$jcode->code}.",
            ['jcode_id' => $jcode->id, 'montant' => $jcode->montant]
        );

        $jcode->update([
            'paiement_status' => 'paye',
            'paye_at' => now(),
        ]);
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
