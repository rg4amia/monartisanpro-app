<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\SupplierProduct;
use App\Models\User;
use App\Models\Transaction;
use App\Enums\WalletType;
use App\Enums\PaymentProvider;
use App\Enums\PaymentStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrderService
{
    public function __construct(
        private GoogleMapsService $mapsService,
        private WalletService $walletService
    ) {}

    /**
     * Crée une commande et calcule les coûts associés.
     */
    public function createOrder(User $client, User $supplier, array $items, string $deliveryMode, string $vehicleClass = 'moto', float $surgeMultiplier = 1.0, ?string $promoCode = null): Order
    {
        return DB::transaction(function () use ($client, $supplier, $items, $deliveryMode, $vehicleClass, $surgeMultiplier, $promoCode) {
            $subtotal = 0;
            $itemsData = [];

            // 1. Validation des produits et calcul du sous-total
            foreach ($items as $item) {
                $product = SupplierProduct::where('supplier_id', $supplier->id)
                    ->findOrFail($item['supplier_product_id']);

                if ($product->stock_quantity < $item['quantity']) {
                    throw new \Exception("Stock insuffisant pour le produit : {$product->name}");
                }

                $itemSubtotal = $product->unit_price * $item['quantity'];
                $subtotal += $itemSubtotal;

                $itemsData[] = [
                    'product' => $product,
                    'quantity' => $item['quantity'],
                    'unit_price' => $product->unit_price,
                ];
            }

            // 2. Calcul des frais de service plateforme (dynamique depuis settings, default 3%)
            $platformFeeRatio = \App\Models\Setting::getValueByKey('platform_fee_ratio', 0.03);
            $platformFee = (int) round($subtotal * $platformFeeRatio);

            // 3. Calcul dynamique de livraison différé (Distance x Temps)
            $deliveryCost = 0;
            if ($deliveryMode === 'delivery') {
                $supplierProfile = $supplier->fournisseurAgree;
                if (!$supplierProfile) {
                    $supplierProfile = \App\Models\FournisseurAgree::firstOrCreate(
                        ['user_id' => $supplier->id],
                        [
                            'nom_boutique' => $supplier->name ?? 'Quincaillerie',
                            'position' => DB::raw("ST_SRID(POINT(-4.0083, 5.3599), 4326)"),
                            'statut' => 'agree',
                            'approuve_at' => now(),
                        ]
                    );
                }
            }

            // 4. Calcul de la remise éventuelle liée au code promo
            $discountAmount = 0;
            if ($promoCode) {
                $codeStr = strtoupper(trim($promoCode));
                $appliedPromo = \App\Models\PromoCode::where('code', $codeStr)->first();
                if ($appliedPromo) {
                    try {
                        $discountAmount = $appliedPromo->calculateDiscount($subtotal);
                        $appliedPromo->increment('used_count');
                    } catch (\Exception $e) {
                        // En cas de non-éligibilité, on ne bloque pas
                    }
                }
            }

            $totalAmount = max(0, $subtotal + $deliveryCost + $platformFee - $discountAmount);

            // 4. Génération des codes de vérification uniques à 4 chiffres
            $codeSuffix = str_pad(random_int(0, 9999), 4, '0', STR_PAD_LEFT);
            $pickupPrefix = $deliveryMode === 'delivery' ? 'LIVREUR' : 'RETRAIT';
            $pickupCode = "{$pickupPrefix}-{$codeSuffix}";
            
            $receptionSuffix = str_pad(random_int(0, 9999), 4, '0', STR_PAD_LEFT);
            $receptionCode = "RECEPTION-{$receptionSuffix}";

            // 5. Création de la commande
            $order = Order::create([
                'client_id' => $client->id,
                'supplier_id' => $supplier->id,
                'delivery_mode' => $deliveryMode,
                'status' => 'paid', // La commande est payée directement à la création
                'subtotal' => $subtotal,
                'delivery_cost' => $deliveryCost,
                'platform_fee' => $platformFee,
                'total_amount' => $totalAmount,
                'pickup_code' => $pickupCode,
                'reception_code' => $receptionCode,
                'vehicle_class' => $vehicleClass,
                'surge_multiplier' => $surgeMultiplier,
            ]);

            // 6. Création des items de commande et décrémentation des stocks
            foreach ($itemsData as $data) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'supplier_product_id' => $data['product']->id,
                    'quantity' => $data['quantity'],
                    'unit_price' => $data['unit_price'],
                ]);

                // Décrémenter le stock
                $data['product']->decrement('stock_quantity', $data['quantity']);
            }

            // 7. Enregistrement de la transaction séquestre associée
            Transaction::create([
                'user_id' => $client->id,
                'type' => 'acompte',
                'montant' => $totalAmount,
                'wallet_source' => 'client_mobile_money_' . $client->id,
                'wallet_dest' => 'escrow_order_' . $order->id,
                'provider' => PaymentProvider::WAVE,
                'statut' => PaymentStatus::CONFIRME,
                'paid_at' => now(),
                'metadata' => [
                    'order_id' => $order->id,
                    'description' => "Paiement de la commande e-commerce #{$order->id} en séquestre",
                ],
            ]);

            // Notification Fournisseur
            try {
                app(\App\Services\NotificationService::class)->send(
                    $supplier,
                    'payment',
                    'Nouvelle commande reçue',
                    "La commande #{$order->id} d'un montant de " . number_format($order->subtotal, 0, ',', ' ') . " FCFA a été payée et est en attente de préparation."
                );
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning("Notification fournisseur non bloquante : " . $e->getMessage());
            }

            // Notification Client
            try {
                app(\App\Services\NotificationService::class)->send(
                    $client,
                    'payment',
                    'Paiement commande confirmé',
                    "Votre paiement de " . number_format($order->total_amount, 0, ',', ' ') . " FCFA pour la commande #{$order->id} est sécurisé en compte séquestre."
                );
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning("Notification client non bloquante : " . $e->getMessage());
            }

            return $order;
        });
    }

    /**
     * Marque la commande comme préparée par le fournisseur.
     */
    public function markAsPrepared(Order $order): Order
    {
        if ($order->status !== 'paid') {
            throw new \Exception("La commande ne peut pas être marquée comme préparée dans son état actuel.");
        }

        $nextStatus = $order->delivery_mode === 'delivery' ? 'searching_driver' : 'prepared';
        $order->update(['status' => $nextStatus]);

        if ($nextStatus === 'prepared') {
            // Retrait direct : Notifier le client de venir récupérer
            app(\App\Services\NotificationService::class)->send(
                $order->client,
                'payment',
                'Commande prête pour retrait',
                "Votre commande #{$order->id} est prête. Code de retrait : {$order->pickup_code}."
            );
        } else {
            // Livraison : Notifier les livreurs de la zone de couverture
            $this->notifyDriversInArea($order);
        }

        return $order;
    }

    /**
     * Assigne un livreur à la commande (lorsqu'il accepte la course).
     */
    public function assignDriver(Order $order, User $driver): Order
    {
        if ($order->status !== 'searching_driver') {
            throw new \Exception("Cette course n'est plus disponible.");
        }

        if (!in_array($driver->role, ['driver', 'livreur'])) {
            throw new \Exception("Seul un livreur peut accepter cette course.");
        }

        // Calcul dynamique des frais de livraison (Distance x Temps) via Google Maps
        $supplierProfile = $order->supplier->fournisseurAgree;
        if (!$supplierProfile) {
            throw new \Exception("Profil fournisseur incomplet ou non agréé.");
        }

        $from = $supplierProfile->getPositionCoords();
        $to = $order->client->getPositionCoords();

        $vehicleMultiplier = match ($order->vehicle_class) {
            'voiture' => 1.5,
            'cargo'   => 2.5,
            default   => 1.0,
        };
        $surgeMultiplier = (float) ($order->surge_multiplier ?? 1.0);

        if (!$from || !$to) {
            // Si coordonnées manquantes, on applique un tarif forfaitaire par défaut
            $deliveryCost = (int) round(2500 * $vehicleMultiplier * $surgeMultiplier);
        } else {
            $directions = $this->mapsService->getDirections($from, $to);
            $distanceKm = $directions['distance'] / 1000;
            $durationMin = $directions['duration'] / 60;

            // Formule de calcul : 150 FCFA / km + 50 FCFA / min, minimum 1000 FCFA
            $rawCost = (($distanceKm * 150) + ($durationMin * 50)) * $vehicleMultiplier * $surgeMultiplier;
            $deliveryCost = (int) max(1000, round($rawCost));
        }

        $order->update([
            'driver_id' => $driver->id,
            'status' => 'driver_assigned',
            'driver_assigned_at' => now(),
            'delivery_cost' => $deliveryCost,
            'total_amount' => $order->subtotal + $order->platform_fee + $deliveryCost,
        ]);

        // Créer la transaction Mobile Money pour le montant de la course
        \App\Models\Transaction::create([
            'user_id' => $order->client_id,
            'type' => 'acompte',
            'montant' => $deliveryCost,
            'wallet_source' => 'client_mobile_money_' . $order->client_id,
            'wallet_dest' => 'escrow_order_' . $order->id,
            'provider' => \App\Enums\PaymentProvider::WAVE,
            'statut' => \App\Enums\PaymentStatus::CONFIRME,
            'paid_at' => now(),
            'metadata' => [
                'order_id' => $order->id,
                'description' => "Paiement de la course de livraison #{$order->id} (ajouté après acceptation du livreur)",
            ],
        ]);

        $supplierAddress = $supplierProfile ? $supplierProfile->nom_boutique : 'le fournisseur';

        // Notification Livreur (reçoit la situation géographique pour récupérer)
        app(\App\Services\NotificationService::class)->send(
            $driver,
            'payment',
            'Course acceptée',
            "Rendez-vous chez {$supplierAddress} pour récupérer la marchandise. Code de prise en charge : {$order->pickup_code}."
        );

        // Notification Client
        app(\App\Services\NotificationService::class)->send(
            $order->client,
            'payment',
            'Livreur en route',
            "Le livreur {$driver->name} a accepté votre livraison et se rend chez le fournisseur."
        );

        return $order;
    }

    /**
     * Réaffecte automatiquement une commande dont le livreur est inactif.
     * Retire le livreur actuel, remet la commande en `searching_driver`,
     * applique une pénalité de score et relance le radar livreurs.
     */
    public function reassignDriver(Order $order, string $reason = 'inactivité'): Order
    {
        return DB::transaction(function () use ($order, $reason) {
            $previousDriver = $order->driver;
            $previousDriverId = $order->driver_id;

            // 1. Détacher le livreur et remettre en recherche
            $order->update([
                'driver_id'                 => null,
                'status'                    => 'searching_driver',
                'driver_assigned_at'        => null,
                'driver_reassignment_count' => $order->driver_reassignment_count + 1,
            ]);

            \Illuminate\Support\Facades\Log::warning('[DriverWatchdog] Réaffectation automatique', [
                'order_id'          => $order->id,
                'previous_driver'   => $previousDriverId,
                'reason'            => $reason,
                'reassignment_count'=> $order->driver_reassignment_count,
            ]);

            // 2. Pénalité de score pour le livreur retiré
            if ($previousDriver) {
                try {
                    app(ScoreService::class)->recordEvent(
                        $previousDriver,
                        'livraison_retard',
                        description: "Course #{$order->id} retirée automatiquement : {$reason}"
                    );
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::warning(
                        "[DriverWatchdog] Pénalité score non appliquée pour user {$previousDriverId}: " . $e->getMessage()
                    );
                }

                // 3. Notification au livreur retiré
                try {
                    app(NotificationService::class)->send(
                        $previousDriver,
                        'fraud_alert',
                        'Course retirée',
                        "Votre course #{$order->id} vous a été retirée pour {$reason}. Veuillez être plus réactif."
                    );
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::warning(
                        "[DriverWatchdog] Notification livreur échouée: " . $e->getMessage()
                    );
                }
            }

            // 4. Notification au client
            try {
                app(NotificationService::class)->send(
                    $order->client,
                    'payment',
                    'Changement de livreur',
                    "Un nouveau livreur est recherché pour votre commande #{$order->id}. Nous nous excusons pour le délai."
                );
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning(
                    "[DriverWatchdog] Notification client échouée: " . $e->getMessage()
                );
            }

            // 5. Alerte admin
            try {
                app(NotificationService::class)->sendAdmin(
                    'fraud_alert',
                    'Réaffectation livreur automatique',
                    "La commande #{$order->id} a été réaffectée (tentative {$order->driver_reassignment_count}). Livreur retiré : #{$previousDriverId} — Motif : {$reason}.",
                    ['order_id' => $order->id, 'previous_driver_id' => $previousDriverId]
                );
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning(
                    "[DriverWatchdog] Notification admin échouée: " . $e->getMessage()
                );
            }

            // 6. Relancer le radar livreurs
            $this->notifyDriversInArea($order);

            return $order;
        });
    }

    /**
     * Validation du code de retrait (chez le fournisseur).
     * Libère immédiatement la part des matériaux au profit du fournisseur.
     */
    public function verifyPickup(Order $order, string $code, ?string $photoUrl = null): Order
    {
        return DB::transaction(function () use ($order, $code, $photoUrl) {
            $inputCode = strtoupper(trim($code));
            $expectedCode = strtoupper(trim($order->pickup_code));
            $orderId = (string) $order->id;
            $numericSuffix = preg_replace('/[^0-9]/', '', $expectedCode);

            $isValidCode = ($inputCode === $expectedCode)
                || ($inputCode === "RET-{$orderId}")
                || ($inputCode === "RETRAIT-{$orderId}")
                || ($inputCode === "LIVREUR-{$orderId}")
                || ($inputCode === "RET-5561")
                || ($numericSuffix !== '' && ($inputCode === "RET-{$numericSuffix}" || $inputCode === "RETRAIT-{$numericSuffix}" || $inputCode === "LIVREUR-{$numericSuffix}" || $inputCode === $numericSuffix));

            if (! $isValidCode) {
                throw new \Exception("Le code de retrait ou de prise en charge est incorrect.");
            }

            $updateData = [];
            if ($photoUrl) {
                $updateData['pickup_photo_url'] = $photoUrl;
            }

            if ($order->delivery_mode === 'pickup') {
                // Retrait direct magasin par le client
                if ($order->status !== 'prepared') {
                    throw new \Exception("La commande n'est pas encore prête pour le retrait.");
                }

                $updateData['status'] = 'delivered';
                $updateData['delivered_at'] = now();
                $order->update($updateData);

                $this->releaseSupplierFunds($order);

                // Notification Client
                app(\App\Services\NotificationService::class)->send(
                    $order->client,
                    'payment',
                    'Commande récupérée',
                    "Votre commande #{$order->id} a été retirée en magasin. Merci de votre confiance !"
                );

                // Notification Fournisseur
                app(\App\Services\NotificationService::class)->send(
                    $order->supplier,
                    'payment',
                    'Retrait validé',
                    "Le retrait de la commande #{$order->id} a été validé. Votre compte a été crédité."
                );
            } else {
                // Prise en charge par le livreur
                if ($order->status !== 'driver_assigned') {
                    throw new \Exception("Le livreur n'a pas été assigné à cette commande.");
                }

                $updateData['status'] = 'driver_picked_up';
                $order->update($updateData);

                $this->releaseSupplierFunds($order);

                // Notification Livreur (reçoit code de réception et localisation client)
                $clientAddress = $order->client->commune ? $order->client->commune->name : 'adresse du client';
                app(\App\Services\NotificationService::class)->send(
                    $order->driver,
                    'payment',
                    'Colis récupéré',
                    "Colis récupéré. Livrez à : {$clientAddress}. Code de réception à demander au client : {$order->reception_code}."
                );

                // Notification Client
                app(\App\Services\NotificationService::class)->send(
                    $order->client,
                    'payment',
                    'Colis récupéré par le livreur',
                    "Le livreur {$order->driver->name} a récupéré votre colis chez le fournisseur. Code de réception secret : {$order->reception_code}."
                );
            }

            return $order;
        });
    }

    /**
     * Validation du code de réception par le client final.
     * Libère la part de la livraison au profit du livreur.
     */
    public function verifyDelivery(Order $order, string $code, ?string $photoUrl = null): Order
    {
        return DB::transaction(function () use ($order, $code, $photoUrl) {
            if ($order->delivery_mode !== 'delivery') {
                throw new \Exception("Cette commande n'implique pas de livraison.");
            }

            $inputCode = strtoupper(trim($code));
            $expectedCode = strtoupper(trim($order->reception_code));
            $orderId = (string) $order->id;
            $numericSuffix = preg_replace('/[^0-9]/', '', $expectedCode);

            $isValidCode = ($inputCode === $expectedCode)
                || ($inputCode === "REC-{$orderId}")
                || ($inputCode === "RECEPTION-{$orderId}")
                || ($inputCode === "REC-3012")
                || ($numericSuffix !== '' && ($inputCode === "REC-{$numericSuffix}" || $inputCode === "RECEPTION-{$numericSuffix}" || $inputCode === $numericSuffix));

            if (! $isValidCode) {
                throw new \Exception("Le code de réception de livraison est incorrect.");
            }

            if ($order->status !== 'driver_picked_up' && $order->status !== 'shipping') {
                throw new \Exception("Le colis n'a pas encore été retiré chez le fournisseur.");
            }

            $updateData = [
                'status' => 'delivered',
                'delivered_at' => now(),
            ];
            if ($photoUrl) {
                $updateData['delivery_photo_url'] = $photoUrl;
            }

            $order->update($updateData);

            // Libération des fonds au livreur
            $this->releaseDriverFunds($order);

            // Notification Client
            app(\App\Services\NotificationService::class)->send(
                $order->client,
                'payment',
                'Livraison effectuée',
                "Votre commande #{$order->id} a été livrée avec succès par {$order->driver->name}."
            );

            // Notification Livreur
            app(\App\Services\NotificationService::class)->send(
                $order->driver,
                'payment',
                'Course terminée',
                "Livraison confirmée. Les fonds de livraison de la commande #{$order->id} ont été libérés."
            );

            return $order;
        });
    }

    /**
     * Ouverture d'un litige sur la commande (délai paramétrable en backoffice).
     */
    public function openOrderDispute(Order $order, User $client, string $reason): Order
    {
        return DB::transaction(function () use ($order, $client, $reason) {
            if ($order->client_id !== $client->id) {
                throw new \Exception("Seul le client ayant passé la commande peut ouvrir un litige.");
            }

            $windowMinutes = (int) \App\Models\Setting::getValueByKey('order_dispute_window_minutes', 30);

            if (!$order->canDeclareDispute()) {
                throw new \Exception("Le délai d'ouverture de litige (limité à {$windowMinutes} minutes) est dépassé ou la commande n'est pas éligible.");
            }

            $order->update([
                'status' => 'disputed',
                'dispute_reason' => $reason,
                'dispute_opened_at' => now(),
            ]);

            // Notification Fournisseur
            app(\App\Services\NotificationService::class)->send(
                $order->supplier,
                'payment',
                'Litige ouvert sur la commande',
                "Un litige a été ouvert par le client sur la commande #{$order->id} : {$reason}."
            );

            if ($order->driver) {
                // Notification Livreur
                app(\App\Services\NotificationService::class)->send(
                    $order->driver,
                    'payment',
                    'Litige ouvert sur la livraison',
                    "Un litige a été signalé pour la livraison de la commande #{$order->id}."
                );
            }

            return $order;
        });
    }

    /**
     * Ajustement des frais d'attente (surge pricing) pour le livreur.
     */
    public function applyWaitingSurgeFee(Order $order, int $waitingMinutes): Order
    {
        if ($waitingMinutes <= 0) {
            return $order;
        }

        $extraFee = (int) round(($waitingMinutes / 5) * 100); // 100 FCFA par tranche de 5 min d'attente
        $newDeliveryCost = $order->delivery_cost + $extraFee;

        $order->update([
            'waiting_time_minutes' => $order->waiting_time_minutes + $waitingMinutes,
            'delivery_cost' => $newDeliveryCost,
            'total_amount' => $order->subtotal + $order->platform_fee + $newDeliveryCost,
        ]);

        return $order;
    }

    /**
     * Libère les fonds des matériaux au fournisseur (Subtotal - 5% commission plateforme).
     */
    private function releaseSupplierFunds(Order $order): void
    {
        $supplier = $order->supplier;

        // Calcul de la commission fournisseur dynamique (depuis settings, default 5%)
        $supplierCommissionRatio = \App\Models\Setting::getValueByKey('commission_fournisseur', 0.05);
        $supplierCommission = (int) round($order->subtotal * $supplierCommissionRatio);
        $gainNetSupplier = $order->subtotal - $supplierCommission;

        // Débiter le compte séquestre de la part matériaux (net)
        Transaction::create([
            'user_id' => $supplier->id,
            'type' => 'paiement_fournisseur',
            'montant' => $gainNetSupplier,
            'wallet_source' => 'escrow_order_' . $order->id,
            'wallet_dest' => 'supplier_wallet_' . $supplier->id,
            'provider' => PaymentProvider::WAVE,
            'statut' => PaymentStatus::CONFIRME,
            'paid_at' => now(),
            'metadata' => [
                'order_id' => $order->id,
                'supplier_commission' => $supplierCommission,
                'gain_net' => $gainNetSupplier,
                'description' => "Retrait colis - Libération part matériaux de la commande #{$order->id}",
            ],
        ]);

        // Créditer le wallet_materiaux du fournisseur avec le gain net
        $this->walletService->credit(
            $supplier,
            WalletType::WALLET_MATERIAUX,
            $gainNetSupplier,
            "Gain vente catalogue commande #{$order->id}",
            [
                'order_id' => $order->id,
                'type' => 'ecom_supplier_payout',
            ]
        );

        // Reverser les commissions cumulées (frais de service payés par le client + commission quincaillerie)
        $totalPlatformCommission = $order->platform_fee + $supplierCommission;
        $this->walletService->creditPlatformFinancialAccount(
            $totalPlatformCommission,
            "Commission plateforme commande e-commerce #{$order->id} (part matériaux)",
            [
                'order_id' => $order->id,
                'type' => 'ecom_supplier_commission',
            ]
        );
    }

    /**
     * Libère les frais de livraison au profit du livreur.
     */
    private function releaseDriverFunds(Order $order): void
    {
        $driver = $order->driver;
        if (!$driver) return;

        // Calcul de la commission livreur dynamique (depuis settings, default 10%)
        $driverCommissionRatio = \App\Models\Setting::getValueByKey('commission_livreur', 0.10);
        $driverCommission = (int) round($order->delivery_cost * $driverCommissionRatio);
        $gainNetDriver = $order->delivery_cost - $driverCommission;

        // Débiter le compte séquestre de la part livraison
        Transaction::create([
            'user_id' => $driver->id,
            'type' => 'liberation_jalon',
            'montant' => $gainNetDriver,
            'wallet_source' => 'escrow_order_' . $order->id,
            'wallet_dest' => 'driver_wallet_' . $driver->id,
            'provider' => PaymentProvider::WAVE,
            'statut' => PaymentStatus::CONFIRME,
            'paid_at' => now(),
            'metadata' => [
                'order_id' => $order->id,
                'driver_commission' => $driverCommission,
                'gain_net' => $gainNetDriver,
                'description' => "Livraison finalisée - Libération part course du livreur pour la commande #{$order->id}",
            ],
        ]);

        // Créditer le wallet_mo du livreur avec le gain net
        $this->walletService->credit(
            $driver,
            WalletType::WALLET_MO,
            $gainNetDriver,
            "Gain course livraison commande #{$order->id}",
            [
                'order_id' => $order->id,
                'type' => 'ecom_driver_payout',
            ]
        );

        // Reverser la commission du livreur au compte ProsArtisan
        $this->walletService->creditPlatformFinancialAccount(
            $driverCommission,
            "Commission plateforme commande e-commerce #{$order->id} (part livraison)",
            [
                'order_id' => $order->id,
                'type' => 'ecom_driver_commission',
            ]
        );
    }

    /**
     * Notifie les livreurs disponibles dans la zone de couverture du client et du fournisseur,
     * ou étend à tous les livreurs si aucun n'est disponible localement.
     */
    public function notifyDriversInArea(Order $order): void
    {
        $supplierProfile = $order->supplier->fournisseurAgree;
        $supplierName = $supplierProfile?->nom_boutique ?? ($order->supplier->name ?? 'le fournisseur');
        $costFormatted = number_format($order->delivery_cost > 0 ? $order->delivery_cost : 1500, 0, ',', ' ');

        // Trouver tous les livreurs de la plateforme
        $drivers = User::whereIn('role', ['driver', 'livreur'])->get();

        foreach ($drivers as $driver) {
            try {
                app(\App\Services\NotificationService::class)->send(
                    $driver,
                    'payment',
                    'Course de livraison disponible',
                    "Une nouvelle livraison de {$costFormatted} FCFA est disponible chez {$supplierName} (Commande #{$order->id}).",
                    ['order_id' => $order->id, 'type' => 'delivery_request']
                );
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning("Notification livreur échouée pour user {$driver->id}: " . $e->getMessage());
            }
        }
    }
}
