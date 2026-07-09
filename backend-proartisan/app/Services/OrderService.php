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
    public function createOrder(User $client, User $supplier, array $items, string $deliveryMode, string $vehicleClass = 'moto', float $surgeMultiplier = 1.0): Order
    {
        return DB::transaction(function () use ($client, $supplier, $items, $deliveryMode, $vehicleClass, $surgeMultiplier) {
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

            // 3. Calcul dynamique de livraison (Distance x Temps) via Google Maps
            $deliveryCost = 0;
            if ($deliveryMode === 'delivery') {
                $supplierProfile = $supplier->fournisseurAgree;
                if (!$supplierProfile) {
                    throw new \Exception("Profil fournisseur incomplet ou non agréé.");
                }

                $from = $supplierProfile->getPositionCoords();
                $to = $client->getPositionCoords();

                $vehicleMultiplier = match ($vehicleClass) {
                    'voiture' => 1.5,
                    'cargo'   => 2.5,
                    default   => 1.0,
                };

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
            }

            $totalAmount = $subtotal + $deliveryCost + $platformFee;

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

        if ($driver->role !== 'driver') {
            throw new \Exception("Seul un livreur peut accepter cette course.");
        }

        $order->update([
            'driver_id' => $driver->id,
            'status' => 'driver_assigned',
        ]);

        return $order;
    }

    /**
     * Validation du code de retrait (chez le fournisseur).
     * Libère immédiatement la part des matériaux au profit du fournisseur.
     */
    public function verifyPickup(Order $order, string $code): Order
    {
        return DB::transaction(function () use ($order, $code) {
            if ($order->pickup_code !== trim($code)) {
                throw new \Exception("Le code de retrait ou de prise en charge est incorrect.");
            }

            if ($order->delivery_mode === 'pickup') {
                // Retrait direct magasin par le client
                if ($order->status !== 'prepared') {
                    throw new \Exception("La commande n'est pas encore prête pour le retrait.");
                }

                $order->update(['status' => 'delivered']);
                $this->releaseSupplierFunds($order);
            } else {
                // Prise en charge par le livreur
                if ($order->status !== 'driver_assigned') {
                    throw new \Exception("Le livreur n'a pas été assigné à cette commande.");
                }

                $order->update(['status' => 'driver_picked_up']); // ou shipping
                $this->releaseSupplierFunds($order);
            }

            return $order;
        });
    }

    /**
     * Validation du code de réception par le client final.
     * Libère la part de la livraison au profit du livreur.
     */
    public function verifyDelivery(Order $order, string $code): Order
    {
        return DB::transaction(function () use ($order, $code) {
            if ($order->delivery_mode !== 'delivery') {
                throw new \Exception("Cette commande n'implique pas de livraison.");
            }

            if ($order->reception_code !== trim($code)) {
                throw new \Exception("Le code de réception de livraison est incorrect.");
            }

            if ($order->status !== 'driver_picked_up' && $order->status !== 'shipping') {
                throw new \Exception("Le colis n'a pas encore été retiré chez le fournisseur.");
            }

            $order->update(['status' => 'delivered']);

            // Libération des fonds au livreur
            $this->releaseDriverFunds($order);

            return $order;
        });
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

        // Débiter le compte séquestre de la part matériaux
        Transaction::create([
            'user_id' => $supplier->id,
            'type' => 'paiement_fournisseur',
            'montant' => $order->subtotal,
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

        // Créditer le wallet_materiaux du fournisseur
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
            'montant' => $order->delivery_cost,
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
    }
}
