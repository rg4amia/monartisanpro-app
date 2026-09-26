<?php

namespace App\Services;

use App\Enums\PaymentProvider;
use App\Enums\PaymentStatus;
use App\Enums\WalletType;
use App\Models\Address;
use App\Models\DeliveryTracking;
use App\Models\FournisseurAgree;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PromoCode;
use App\Models\Setting;
use App\Models\SupplierProduct;
use App\Models\Transaction;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class OrderService
{
    /** Courses acceptées par un livreur et pas encore livrées. */
    public const DRIVER_IN_PROGRESS_STATUSES = ['driver_assigned', 'driver_picked_up', 'shipping'];

    /** Commande créée, en attente du paiement du client (stock réservé). */
    public const STATUS_PENDING_PAYMENT = 'pending';

    /** Délai laissé à un virement bancaire (confirmé par un admin). */
    public const BANK_TRANSFER_PAYMENT_HOURS = 72;

    private DeliveryPricingService $pricingService;

    public function __construct(
        private GoogleMapsService $mapsService,
        private WalletService $walletService,
        ?DeliveryPricingService $pricingService = null,
    ) {
        $this->pricingService = $pricingService ?? app(DeliveryPricingService::class);
    }

    /**
     * Crée une commande et calcule les coûts associés.
     */
    public function createOrder(User $client, User $supplier, array $items, string $deliveryMode, string $vehicleClass = 'moto', float $surgeMultiplier = 1.0, ?string $promoCode = null, ?Address $address = null, ?int $missionId = null): Order
    {
        return DB::transaction(function () use ($client, $supplier, $items, $deliveryMode, $vehicleClass, $surgeMultiplier, $promoCode, $address, $missionId) {
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
            $platformFeeRatio = Setting::getValueByKey('platform_fee_ratio', 0.03);
            $platformFee = (int) round($subtotal * $platformFeeRatio);

            // 3. Calcul dynamique de livraison différé (Distance x Temps)
            $deliveryCost = 0;
            if ($deliveryMode === 'delivery') {
                $supplierProfile = $supplier->fournisseurAgree;
                if (! $supplierProfile) {
                    $supplierProfile = FournisseurAgree::firstOrCreate(
                        ['user_id' => $supplier->id],
                        [
                            'nom_boutique' => $supplier->name ?? 'Quincaillerie',
                            'position' => DB::raw('POINT(-4.0083, 5.3599)'),
                            'statut' => 'agree',
                            'approuve_at' => now(),
                        ]
                    );
                }
            }

            // 4. Calcul de la remise éventuelle liée au code promo
            $discountAmount = 0;
            $appliedPromo = null;
            if ($promoCode) {
                $codeStr = strtoupper(trim($promoCode));
                $promo = PromoCode::where('code', $codeStr)->first();
                if ($promo) {
                    try {
                        $discountAmount = $promo->calculateDiscount($subtotal);
                        $promo->increment('used_count');
                        $appliedPromo = $promo;
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
                'mission_id' => $missionId,
                'client_id' => $client->id,
                'supplier_id' => $supplier->id,
                'delivery_mode' => $deliveryMode,
                // Encaissement réel (Chantier 11) : la commande attend le
                // paiement Wave / Orange Money du client, stock réservé.
                'status' => self::STATUS_PENDING_PAYMENT,
                'payment_expires_at' => $this->paymentDeadline(),
                'promo_code' => $appliedPromo?->code,
                'subtotal' => $subtotal,
                'delivery_cost' => $deliveryCost,
                'platform_fee' => $platformFee,
                'total_amount' => $totalAmount,
                'pickup_code' => $pickupCode,
                'reception_code' => $receptionCode,
                'vehicle_class' => $vehicleClass,
                'surge_multiplier' => $surgeMultiplier,
                'address_id' => $address?->id,
                'recipient_name' => $address?->recipient_name,
                'recipient_phone' => $address?->recipient_phone,
                'delivery_address_line' => $address?->address_line,
                'delivery_city' => $address?->city,
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

            return $order;
        });
    }

    /**
     * Crée un ensemble de sous-commandes pour un panier multi-fournisseurs avec paiement séquestre groupé.
     *
     * @param  array  $packages  Liste des colis par fournisseur: [
     *                           [
     *                           'supplier_id' => int,
     *                           'delivery_mode' => 'delivery'|'pickup',
     *                           'vehicle_class' => 'moto'|'voiture'|'cargo',
     *                           'surge_multiplier' => float,
     *                           'items' => [ ['supplier_product_id' => int, 'quantity' => int], ... ]
     *                           ], ...
     *                           ]
     */
    public function createMultiSupplierOrders(User $client, array $packages, ?string $promoCode = null, ?Address $address = null): array
    {
        return DB::transaction(function () use ($client, $packages, $promoCode, $address) {
            $orderGroupId = 'GRP-'.strtoupper(Str::random(10));
            $platformFeeRatio = Setting::getValueByKey('platform_fee_ratio', 0.03);

            $createdOrders = [];
            $totalSubtotal = 0;
            $totalDeliveryCost = 0;
            $totalPlatformFee = 0;
            $grandTotal = 0;

            foreach ($packages as $pkg) {
                $supplier = User::where('role', 'fournisseur')->findOrFail($pkg['supplier_id']);
                $deliveryMode = $pkg['delivery_mode'] ?? 'delivery';
                $vehicleClass = $pkg['vehicle_class'] ?? 'moto';
                $surgeMultiplier = (float) ($pkg['surge_multiplier'] ?? 1.0);

                $pkgSubtotal = 0;
                $itemsData = [];

                foreach ($pkg['items'] as $item) {
                    $product = SupplierProduct::where('supplier_id', $supplier->id)
                        ->findOrFail($item['supplier_product_id']);

                    if ($product->stock_quantity < $item['quantity']) {
                        throw new \Exception("Stock insuffisant pour le produit : {$product->name} chez {$supplier->name}");
                    }

                    $itemSubtotal = $product->unit_price * $item['quantity'];
                    $pkgSubtotal += $itemSubtotal;

                    $itemsData[] = [
                        'product' => $product,
                        'quantity' => $item['quantity'],
                        'unit_price' => $product->unit_price,
                    ];
                }

                $pkgPlatformFee = (int) round($pkgSubtotal * $platformFeeRatio);
                $pkgDeliveryCost = 0;
                if ($deliveryMode === 'delivery') {
                    $from = $supplier->fournisseurAgree?->getPositionCoords() ?? $supplier->getPositionCoords();
                    $to = $address?->getPositionCoords() ?? $client->getPositionCoords();
                    $fare = $this->pricingService->estimateFare([
                        'from' => $from,
                        'to' => $to,
                        'vehicle_class' => $vehicleClass,
                        'surge_multiplier' => $surgeMultiplier,
                    ]);
                    $pkgDeliveryCost = (int) ($fare['delivery_cost'] ?? 0);
                }

                // Modèle « à la Yango » : la course n'est qu'estimée ici et payée
                // par le client à la livraison, une fois son montant final connu
                // (course + bonus d'attente). Elle n'entre donc pas dans le paiement
                // groupé, qui la comptait auparavant une seconde fois à l'acceptation.
                $pkgTotal = max(0, $pkgSubtotal + $pkgPlatformFee);

                $codeSuffix = str_pad(random_int(0, 9999), 4, '0', STR_PAD_LEFT);
                $pickupPrefix = $deliveryMode === 'delivery' ? 'LIVREUR' : 'RETRAIT';
                $pickupCode = "{$pickupPrefix}-{$codeSuffix}";

                $receptionSuffix = str_pad(random_int(0, 9999), 4, '0', STR_PAD_LEFT);
                $receptionCode = "RECEPTION-{$receptionSuffix}";

                $order = Order::create([
                    'client_id' => $client->id,
                    'supplier_id' => $supplier->id,
                    'delivery_mode' => $deliveryMode,
                    'status' => self::STATUS_PENDING_PAYMENT,
                    'payment_expires_at' => $this->paymentDeadline(),
                    'subtotal' => $pkgSubtotal,
                    'delivery_cost' => $pkgDeliveryCost,
                    'platform_fee' => $pkgPlatformFee,
                    'total_amount' => $pkgTotal,
                    'pickup_code' => $pickupCode,
                    'reception_code' => $receptionCode,
                    'vehicle_class' => $vehicleClass,
                    'surge_multiplier' => $surgeMultiplier,
                    'order_group_id' => $orderGroupId,
                    'is_parent_group' => false,
                    'address_id' => $address?->id,
                    'recipient_name' => $address?->recipient_name,
                    'recipient_phone' => $address?->recipient_phone,
                    'delivery_address_line' => $address?->address_line,
                    'delivery_city' => $address?->city,
                ]);

                foreach ($itemsData as $data) {
                    OrderItem::create([
                        'order_id' => $order->id,
                        'supplier_product_id' => $data['product']->id,
                        'quantity' => $data['quantity'],
                        'unit_price' => $data['unit_price'],
                    ]);

                    $data['product']->decrement('stock_quantity', $data['quantity']);
                }

                $createdOrders[] = $order;
                $totalSubtotal += $pkgSubtotal;
                $totalDeliveryCost += $pkgDeliveryCost;
                $totalPlatformFee += $pkgPlatformFee;
                $grandTotal += $pkgTotal;
            }

            // Code promo global : la remise est imputée sur les sous-commandes
            // (dans l'ordre), pour que le montant à encaisser reste la somme
            // exacte de leurs `total_amount` — jamais un montant à part.
            if ($promoCode) {
                $codeStr = strtoupper(trim($promoCode));
                $appliedPromo = PromoCode::where('code', $codeStr)->first();
                if ($appliedPromo) {
                    try {
                        $remaining = $appliedPromo->calculateDiscount($totalSubtotal);
                        $appliedPromo->increment('used_count');

                        foreach ($createdOrders as $index => $created) {
                            $cut = min($remaining, (int) $created->total_amount);
                            $created->update([
                                'total_amount' => (int) $created->total_amount - $cut,
                                // Porté par la première sous-commande : restitué une fois à l'expiration.
                                'promo_code' => $index === 0 ? $appliedPromo->code : null,
                            ]);
                            $remaining -= $cut;
                            $grandTotal -= $cut;
                        }
                    } catch (\Exception $e) {
                        // Non éligible : aucune remise.
                    }
                }
            }

            $createdOrders = array_map(
                fn (Order $created) => $created->load('items.product', 'supplier.fournisseurAgree'),
                $createdOrders
            );

            return [
                'order_group_id' => $orderGroupId,
                'orders' => $createdOrders,
                'total_subtotal' => $totalSubtotal,
                'total_delivery_cost' => $totalDeliveryCost,
                'delivery_cost_is_estimate' => true,
                'total_platform_fee' => $totalPlatformFee,
                'total_amount' => $grandTotal,
                'packages_count' => count($createdOrders),
                'payment_expires_at' => $createdOrders[0]->payment_expires_at?->toIso8601String(),
            ];
        });
    }

    /**
     * Échéance de paiement d'une commande créée maintenant.
     */
    public function paymentDeadline(bool $bankTransfer = false): CarbonInterface
    {
        if ($bankTransfer) {
            return now()->addHours(self::BANK_TRANSFER_PAYMENT_HOURS);
        }

        $minutes = (int) Setting::getValueByKey('order_payment_timeout_minutes', 30);

        return now()->addMinutes(max(5, $minutes));
    }

    /**
     * Commandes réglées ensemble : tout le panier multi-quincailleries, sinon
     * la commande seule.
     *
     * @return Collection<int, Order>
     */
    public function ordersPaidTogether(Order $order): Collection
    {
        if ($order->order_group_id) {
            return Order::where('order_group_id', $order->order_group_id)->orderBy('id')->get();
        }

        return new Collection([$order]);
    }

    /**
     * Encaisse une commande (ou un panier) après confirmation du paiement du
     * client. Idempotent : webhook, interrogation de statut et simulateur
     * peuvent confirmer le même paiement (Règle d'or 36). Le montant et le
     * payeur sont revérifiés contre ce que le serveur a établi.
     *
     * Un paiement confirmé après l'annulation de la commande (délai dépassé)
     * la réactive si le stock le permet ; sinon il est signalé à l'admin pour
     * remboursement, jamais perdu en silence.
     *
     * @return bool vrai si ce paiement vient d'encaisser la commande
     */
    public function confirmOrderPayment(Transaction $payment): bool
    {
        $ids = array_values(array_unique(array_map('intval', (array) ($payment->metadata['order_ids'] ?? []))));
        if ($ids === []) {
            return false;
        }

        $confirmed = DB::transaction(function () use ($payment, $ids) {
            $orders = Order::whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get();

            if ($orders->count() !== count($ids)
                || $orders->every(fn (Order $order) => ! in_array($order->status, [self::STATUS_PENDING_PAYMENT, 'cancelled'], true))) {
                return null;
            }

            $expected = (int) $orders->sum('total_amount');
            if ((int) $payment->montant !== $expected || $orders->contains(fn (Order $order) => (int) $order->client_id !== (int) $payment->user_id)) {
                Log::warning("[Commande] Paiement #{$payment->id} non conforme", ['attendu' => $expected, 'recu' => $payment->montant]);

                return null;
            }

            $cancelled = $orders->where('status', 'cancelled');
            if ($cancelled->isNotEmpty() && ! $this->reserveStockAgain($cancelled)) {
                $payment->update(['metadata' => array_merge($payment->metadata ?? [], ['refund_required' => true])]);

                try {
                    app(NotificationService::class)->sendAdmin(
                        'payment',
                        'Paiement reçu pour une commande annulée',
                        "Le paiement #{$payment->id} (".number_format($expected, 0, ',', ' ').' FCFA) est arrivé après l\'annulation des commandes #'.implode(', #', $ids).' et le stock ne permet plus de les honorer : remboursement du client à effectuer.'
                    );
                } catch (\Throwable $e) {
                    Log::warning('[Commande] Alerte admin non envoyée : '.$e->getMessage());
                }

                return null;
            }

            foreach ($orders as $order) {
                if (in_array($order->status, [self::STATUS_PENDING_PAYMENT, 'cancelled'], true)) {
                    $order->update(['status' => 'paid', 'paid_at' => now(), 'payment_expires_at' => null]);
                }
            }

            return $orders;
        });

        if (! $confirmed) {
            return false;
        }

        $this->notifyOrderPaid($confirmed, (int) $payment->montant);

        return true;
    }

    /**
     * Annule les commandes dont le délai de paiement est dépassé, après avoir
     * interrogé l'opérateur (un paiement abouti sans webhook est encaissé, pas
     * annulé). Stock et code promo sont restitués.
     *
     * @return array{checked: int, cancelled: int, confirmed: int}
     */
    public function expireUnpaidOrders(): array
    {
        $expired = Order::where('status', self::STATUS_PENDING_PAYMENT)
            ->whereNotNull('payment_expires_at')
            ->where('payment_expires_at', '<=', now())
            ->orderBy('id')
            ->limit(200)
            ->get();

        $seen = [];
        $cancelled = 0;
        $confirmed = 0;

        foreach ($expired as $order) {
            $key = $order->order_group_id ?: 'order-'.$order->id;
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            foreach ($this->pendingOrderPayments($order) as $transaction) {
                try {
                    app(PaymentService::class)->refreshStatus($transaction);
                } catch (\Throwable $e) {
                    Log::warning("[Commande] Statut du paiement #{$transaction->id} indisponible : {$e->getMessage()}");
                }
            }

            if ($order->fresh()->status !== self::STATUS_PENDING_PAYMENT) {
                $confirmed++;

                continue;
            }

            $this->cancelUnpaidOrders($this->ordersPaidTogether($order));
            $cancelled++;
        }

        return ['checked' => count($seen), 'cancelled' => $cancelled, 'confirmed' => $confirmed];
    }

    /**
     * Virements bancaires de commandes en attente de confirmation par un
     * admin (backoffice, sous-onglet « Encaissements »).
     */
    public function pendingBankTransferPayments(): array
    {
        try {
            return Transaction::with('user:id,name,phone')
                ->where('statut', PaymentStatus::EN_ATTENTE)
                ->where('provider', PaymentProvider::VIREMENT_BANCAIRE)
                ->where('type', 'acompte')
                ->orderBy('created_at')
                ->limit(100)
                ->get()
                ->filter(fn (Transaction $transaction) => is_array($transaction->metadata) && ($transaction->metadata['payment_type'] ?? null) === 'order')
                ->map(fn (Transaction $transaction) => [
                    'transaction_id' => $transaction->id,
                    'reference' => $transaction->reference_externe,
                    'montant' => (int) $transaction->montant,
                    'client' => $transaction->user ? ['id' => $transaction->user->id, 'name' => $transaction->user->name, 'phone' => $transaction->user->phone] : null,
                    'order_ids' => array_map('intval', (array) ($transaction->metadata['order_ids'] ?? [])),
                    'created_at' => $transaction->created_at?->toIso8601String(),
                ])
                ->values()
                ->all();
        } catch (\Throwable $e) {
            Log::warning('[OrderService] pendingBankTransferPayments failed: '.$e->getMessage());
            return [];
        }
    }

    /**
     * Paiements en cours (non aboutis) d'une commande.
     *
     * @return Collection<int, Transaction>
     */
    public function pendingOrderPayments(Order $order): Collection
    {
        return Transaction::where('statut', PaymentStatus::EN_ATTENTE)
            ->where('user_id', $order->client_id)
            ->whereJsonContains('metadata->order_ids', $order->id)
            ->get();
    }

    /**
     * Annule des commandes restées impayées : stock et code promo restitués,
     * paiements en cours clos.
     */
    private function cancelUnpaidOrders(Collection $orders): void
    {
        $cancelledIds = DB::transaction(function () use ($orders) {
            $ids = [];

            foreach ($orders as $order) {
                $order = Order::lockForUpdate()->find($order->id);
                if (! $order || $order->status !== self::STATUS_PENDING_PAYMENT) {
                    continue;
                }

                foreach ($order->items as $item) {
                    SupplierProduct::whereKey($item->supplier_product_id)->increment('stock_quantity', $item->quantity);
                }

                if ($order->promo_code) {
                    PromoCode::where('code', $order->promo_code)->where('used_count', '>', 0)->decrement('used_count');
                }

                foreach ($this->pendingOrderPayments($order) as $transaction) {
                    $transaction->update([
                        'statut' => PaymentStatus::ECHOUE,
                        'failed_at' => now(),
                        'error_message' => 'Délai de paiement de la commande dépassé.',
                    ]);
                }

                $order->update(['status' => 'cancelled', 'payment_expires_at' => null]);
                $ids[] = $order->id;
            }

            return $ids;
        });

        if ($cancelledIds === []) {
            return;
        }

        try {
            app(NotificationService::class)->send(
                $orders->first()->client,
                'payment',
                'Commande annulée',
                'Votre commande #'.implode(', #', $cancelledIds)." n'a pas été réglée dans le délai imparti : elle est annulée et les articles sont remis en vente."
            );
        } catch (\Throwable $e) {
            Log::warning('[Commande] Notification d\'annulation non envoyée : '.$e->getMessage());
        }
    }

    /**
     * Réserve à nouveau le stock de commandes annulées (paiement tardif).
     */
    private function reserveStockAgain(Collection $orders): bool
    {
        $needed = [];
        foreach ($orders as $order) {
            foreach ($order->items as $item) {
                $needed[$item->supplier_product_id] = ($needed[$item->supplier_product_id] ?? 0) + (int) $item->quantity;
            }
        }

        $products = SupplierProduct::whereIn('id', array_keys($needed))->lockForUpdate()->get()->keyBy('id');
        foreach ($needed as $productId => $quantity) {
            if (! $products->has($productId) || $products[$productId]->stock_quantity < $quantity) {
                return false;
            }
        }

        foreach ($needed as $productId => $quantity) {
            $products[$productId]->decrement('stock_quantity', $quantity);
        }

        foreach ($orders as $order) {
            if ($order->promo_code) {
                PromoCode::where('code', $order->promo_code)->increment('used_count');
            }
        }

        return true;
    }

    private function notifyOrderPaid(Collection $orders, int $amount): void
    {
        $notifications = app(NotificationService::class);
        $fmt = fn (int $value) => number_format($value, 0, ',', ' ').' FCFA';

        foreach ($orders as $order) {
            try {
                $notifications->send(
                    $order->supplier,
                    'payment',
                    'Nouvelle commande reçue',
                    "La commande #{$order->id} d'un montant de {$fmt((int) $order->subtotal)} a été payée et est en attente de préparation."
                );
            } catch (\Throwable $e) {
                Log::warning('Notification fournisseur non bloquante : '.$e->getMessage());
            }
        }

        try {
            $notifications->send(
                $orders->first()->client,
                'payment',
                'Paiement commande confirmé',
                "Votre paiement de {$fmt($amount)} pour la commande #".implode(', #', $orders->pluck('id')->all()).' est sécurisé en compte séquestre.'
            );
        } catch (\Throwable $e) {
            Log::warning('Notification client non bloquante : '.$e->getMessage());
        }
    }

    /**
     * Marque la commande comme préparée par le fournisseur.
     */
    public function markAsPrepared(Order $order): Order
    {
        if ($order->status !== 'paid') {
            throw new \Exception('La commande ne peut pas être marquée comme préparée dans son état actuel.');
        }

        $nextStatus = $order->delivery_mode === 'delivery' ? 'searching_driver' : 'prepared';
        $order->update(['status' => $nextStatus]);

        if ($nextStatus === 'prepared') {
            // Retrait direct : le client vient lui-même chercher sa commande.
            // Il reçoit le code, le fournisseur le contrôle au comptoir.
            app(NotificationService::class)->send(
                $order->client,
                'payment',
                'Commande prête pour retrait',
                "Votre commande #{$order->id} est prête. Code de retrait à présenter au comptoir : {$order->pickup_code}."
            );

            app(NotificationService::class)->send(
                $order->supplier,
                'payment',
                'Commande à remettre au client',
                "La commande #{$order->id} attend son retrait. Code à vérifier auprès du client : {$order->pickup_code}."
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

        if (! in_array($driver->role, ['driver', 'livreur'])) {
            throw new \Exception('Seul un livreur peut accepter cette course.');
        }

        // Calcul dynamique des frais de livraison via DeliveryPricingService
        $supplierProfile = $order->supplier->fournisseurAgree;
        if (! $supplierProfile) {
            throw new \Exception('Profil fournisseur incomplet ou non agréé.');
        }

        // Peut réévaluer et relever order->vehicle_class si les articles sont
        // plus lourds que la classe initialement choisie (voir Règle d'Or logistique).
        // Modèle « à la Yango » : à l'acceptation, la course n'est qu'estimée.
        // Aucun paiement n'est enregistré — l'ancienne transaction Wave
        // « confirmée » créée ici ne correspondait à aucun débit du client.
        // Le montant final (course + bonus d'attente) est révélé à la
        // livraison et payé par le client à ce moment-là (revealDeliveryFare).
        $estimatedCost = $this->pricingService->calculateOrderDeliveryCost($order);

        $order->update([
            'driver_id' => $driver->id,
            'status' => 'driver_assigned',
            'driver_assigned_at' => now(),
            'delivery_cost' => $estimatedCost,
            'vehicle_class' => $order->vehicle_class,
        ]);

        $supplierAddress = $supplierProfile ? $supplierProfile->nom_boutique : 'le fournisseur';

        // Notification Livreur : la localisation, mais pas le code. Le livreur
        // doit le demander au comptoir — c'est ce qui atteste sa présence.
        app(NotificationService::class)->send(
            $driver,
            'payment',
            'Course acceptée',
            "Rendez-vous chez {$supplierAddress} pour récupérer la marchandise. Demandez le code de prise en charge au fournisseur."
        );

        // Notification Fournisseur : il détient le code et contrôle qui se
        // présente. Sans cela, il n'avait aucun moyen de vérifier le livreur.
        app(NotificationService::class)->send(
            $order->supplier,
            'payment',
            'Livreur en route',
            "Un livreur vient récupérer la commande #{$order->id}. Code de prise en charge à lui communiquer après contrôle : {$order->pickup_code}."
        );

        // Notification Client
        app(NotificationService::class)->send(
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
                'driver_id' => null,
                'status' => 'searching_driver',
                'driver_assigned_at' => null,
                'driver_reassignment_count' => $order->driver_reassignment_count + 1,
            ]);

            Log::warning('[DriverWatchdog] Réaffectation automatique', [
                'order_id' => $order->id,
                'previous_driver' => $previousDriverId,
                'reason' => $reason,
                'reassignment_count' => $order->driver_reassignment_count,
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
                    Log::warning(
                        "[DriverWatchdog] Pénalité score non appliquée pour user {$previousDriverId}: ".$e->getMessage()
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
                    Log::warning(
                        '[DriverWatchdog] Notification livreur échouée: '.$e->getMessage()
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
                Log::warning(
                    '[DriverWatchdog] Notification client échouée: '.$e->getMessage()
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
                Log::warning(
                    '[DriverWatchdog] Notification admin échouée: '.$e->getMessage()
                );
            }

            // 6. Relancer le radar livreurs
            $this->notifyDriversInArea($order);

            return $order;
        });
    }

    /**
     * Compare un code saisi au code attendu de la commande.
     *
     * Le secret est le suffixe numérique tiré au hasard à la création
     * (« LIVREUR-4821 » → « 4821 »). On tolère les variantes de préfixe et la
     * saisie du seul suffixe, parce qu'un livreur au comptoir ou sur un menu
     * USSD ne recopie pas toujours le libellé exact — mais toutes ces formes
     * exigent de connaître le secret. Aucune ne peut être devinée à partir de
     * l'identifiant de commande.
     *
     * @param  list<string>  $prefixes  Préfixes acceptés pour ce type de code
     */
    private function codeMatches(string $inputCode, string $expectedCode, array $prefixes): bool
    {
        if ($expectedCode === '') {
            return false;
        }

        $candidates = [$expectedCode];
        $secret = preg_replace('/[^0-9]/', '', $expectedCode);

        if ($secret !== '') {
            $candidates[] = $secret;

            foreach ($prefixes as $prefix) {
                $candidates[] = "{$prefix}-{$secret}";
            }
        }

        foreach ($candidates as $candidate) {
            // hash_equals : le code est un secret court, on ne laisse pas le
            // temps de réponse en révéler les premiers caractères.
            if (hash_equals($candidate, $inputCode)) {
                return true;
            }
        }

        return false;
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

            // Seules sont acceptées les formes qui contiennent réellement le
            // secret tiré à la création de la commande (4 chiffres aléatoires,
            // cf. la génération de `pickup_code`). Les variantes dérivées de
            // l'identifiant de commande — « RET-42 » — ont été retirées : cet
            // identifiant n'est pas secret, et les accepter permettait à un
            // livreur de valider un retrait sans jamais s'être présenté chez le
            // fournisseur, donc de déclencher une libération de fonds. Une
            // constante universelle (« RET-5561 »), valable pour n'importe
            // quelle commande, traînait également.
            if (! $this->codeMatches($inputCode, $expectedCode, ['RET', 'RETRAIT', 'LIVREUR'])) {
                throw new \Exception('Le code de retrait ou de prise en charge est incorrect.');
            }

            // Idempotence : la même validation peut arriver deux fois — le
            // fournisseur confirme depuis son appareil pendant que celle du
            // livreur, mise en file hors connexion, se rejoue plus tard. Une
            // commande déjà dans l'état visé n'est pas une erreur, et ne doit
            // surtout pas déclencher un second versement.
            $alreadyDone = $order->delivery_mode === 'pickup'
                ? $order->status === 'delivered'
                : in_array($order->status, ['driver_picked_up', 'delivered'], true);

            if ($alreadyDone) {
                return $order;
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
                app(NotificationService::class)->send(
                    $order->client,
                    'payment',
                    'Commande récupérée',
                    "Votre commande #{$order->id} a été retirée en magasin. Merci de votre confiance !"
                );

                // Notification Fournisseur
                app(NotificationService::class)->send(
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
                $updateData['driver_picked_up_at'] = now();
                $order->update($updateData);

                $this->releaseSupplierFunds($order);

                // Notification Livreur (reçoit code de réception et localisation client)
                $clientAddress = $order->client->commune ? $order->client->commune->name : 'adresse du client';
                app(NotificationService::class)->send(
                    $order->driver,
                    'payment',
                    'Colis récupéré',
                    "Colis récupéré. Livrez à : {$clientAddress}. Le client vous remettra son code de réception une fois le colis en main."
                );

                // Notification Client
                app(NotificationService::class)->send(
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

            // Même resserrement qu'au retrait : le code de réception est remis
            // au livreur par le client, en main propre. C'est sa seule preuve
            // de présence. « REC-42 », dérivable de l'identifiant de commande,
            // permettait au livreur de se payer sans avoir livré.
            if (! $this->codeMatches($inputCode, $expectedCode, ['REC', 'RECEPTION'])) {
                throw new \Exception('Le code de réception de livraison est incorrect.');
            }

            // Idempotence : le client peut confirmer la réception depuis son
            // appareil au moment où celle du livreur, mise en file faute de
            // réseau, se rejoue. Le second passage ne doit ni échouer, ni
            // reverser la part livraison une seconde fois.
            if ($order->status === 'delivered') {
                return $order;
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

            // Révélation du montant final de la course (modèle « à la Yango »)
            // et demande de paiement au client, ou règlement immédiat si la
            // course était déjà couverte (ancien modèle prépayé).
            $this->revealDeliveryFare($order);

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
                throw new \Exception('Seul le client ayant passé la commande peut ouvrir un litige.');
            }

            $windowMinutes = (int) Setting::getValueByKey('order_dispute_window_minutes', 30);

            if (! $order->canDeclareDispute()) {
                throw new \Exception("Le délai d'ouverture de litige (limité à {$windowMinutes} minutes) est dépassé ou la commande n'est pas éligible.");
            }

            $order->update([
                'status' => 'disputed',
                'dispute_reason' => $reason,
                'dispute_opened_at' => now(),
            ]);

            // Notification Fournisseur
            app(NotificationService::class)->send(
                $order->supplier,
                'payment',
                'Litige ouvert sur la commande',
                "Un litige a été ouvert par le client sur la commande #{$order->id} : {$reason}."
            );

            if ($order->driver) {
                // Notification Livreur
                app(NotificationService::class)->send(
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

        // Plafond cumulé par commande (indépendant du plafond par appel déjà
        // imposé par la validation de la requête) : sans lui, un livreur
        // pouvait répéter l'appel autant de fois que voulu pour accumuler un
        // surcoût sans limite, chaque appel restant sous le plafond unitaire.
        $cumulativeCapMinutes = 180;
        if ($order->waiting_time_minutes + $waitingMinutes > $cumulativeCapMinutes) {
            throw new \Exception("Le temps d'attente cumulé déclaré pour cette commande dépasse le plafond autorisé ({$cumulativeCapMinutes} minutes).");
        }

        if (! in_array($order->status, self::DRIVER_IN_PROGRESS_STATUSES, true)) {
            throw new \Exception("Le temps d'attente ne peut être déclaré que pendant la course.");
        }

        $extraFee = (int) round(($waitingMinutes / 5) * 100); // 100 FCFA par tranche de 5 min d'attente

        // Bonus d'attente cumulé à part du tarif de la course : il s'ajoute au
        // montant révélé à la livraison et payé alors par le client. Aucune
        // transaction n'est créée ici — l'ancien « acompte » Wave confirmé ne
        // correspondait à aucun débit réel du client.
        $order->update([
            'waiting_time_minutes' => $order->waiting_time_minutes + $waitingMinutes,
            'waiting_fee' => (int) $order->waiting_fee + $extraFee,
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
        $supplierCommissionRatio = Setting::getValueByKey('commission_fournisseur', 0.05);
        $supplierCommission = (int) round($order->subtotal * $supplierCommissionRatio);
        $gainNetSupplier = $order->subtotal - $supplierCommission;

        // Débiter le compte séquestre de la part matériaux (net)
        Transaction::create([
            'user_id' => $supplier->id,
            'type' => 'paiement_fournisseur',
            'montant' => $gainNetSupplier,
            'wallet_source' => 'escrow_order_'.$order->id,
            'wallet_dest' => 'supplier_wallet_'.$supplier->id,
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
     * Répartition d'un coût de course entre le livreur et la plateforme
     * (commission paramétrable `commission_livreur`, 10 % par défaut).
     *
     * @return array{commission: int, net: int}
     */
    private function driverShare(int $deliveryCost): array
    {
        $ratio = (float) Setting::getValueByKey('commission_livreur', 0.10);
        $commission = (int) round($deliveryCost * $ratio);

        return ['commission' => $commission, 'net' => $deliveryCost - $commission];
    }

    /**
     * Révèle le montant final de la course à la livraison : tarif recalculé
     * au moment de l'arrivée, plus le bonus d'attente cumulé. La part déjà
     * encaissée sous l'ancien modèle (`delivery_fare_prepaid`) est déduite ;
     * le reste est demandé au client (Wave / Orange Money) et le livreur est
     * crédité à la confirmation de ce paiement (`settleDeliveryFare`).
     */
    public function revealDeliveryFare(Order $order): Order
    {
        $prepaid = (int) $order->delivery_fare_prepaid;
        $waitingFee = (int) $order->waiting_fee;

        // Montant final calculé sur le trajet GPS réel (Chantier 11), borné
        // par l'itinéraire calculé : trace insuffisante → itinéraire ;
        // trajet anormalement long (détour, fraude) → plafonné.
        $routed = $this->pricingService->calculateOrderDeliveryCost($order);
        $trip = $this->measureTrip($order);
        ['base' => $base, 'source' => $source] = $this->finalFare($order, $routed, $trip);

        $order->forceFill([
            'actual_distance_km' => $trip['distance_km'] ?? null,
            'actual_duration_min' => $trip['duration_min'] ?? null,
            'fare_source' => $source,
        ]);

        // Course prépayée sous l'ancien modèle : le client ne paie jamais moins
        // que ce qu'il a déjà réglé, et le livreur ne touche pas moins.
        $total = max($base + $waitingFee, $prepaid);
        $due = $total - $prepaid;

        $order->update([
            'delivery_cost' => $total - $waitingFee,
            'total_amount' => (int) $order->total_amount + $due,
            'delivery_fare_status' => $due > 0 ? 'a_payer' : 'paye',
            'delivery_fare_settled_at' => $due > 0 ? null : now(),
        ]);

        $notifications = app(NotificationService::class);
        $fmt = fn (int $amount) => number_format($amount, 0, ',', ' ').' FCFA';
        $bonus = $waitingFee > 0 ? " (dont bonus d'attente {$fmt($waitingFee)})" : '';

        if ($due === 0) {
            $this->releaseDriverFunds($order);

            $notifications->send($order->client, 'payment', 'Livraison effectuée',
                "Votre commande #{$order->id} a été livrée par {$order->driver->name}.");
            $notifications->send($order->driver, 'payment', 'Course terminée',
                "Montant de la course #{$order->id} : {$fmt($total)}{$bonus}. Vos gains ont été crédités.");

            return $order;
        }

        $notifications->send($order->client, 'payment', 'Livraison effectuée — course à régler',
            "Votre commande #{$order->id} a été livrée. Montant de la course : {$fmt($due)}{$bonus}. Réglez-la depuis l'application (Wave ou Orange Money).",
            ['order_id' => $order->id, 'action' => 'pay_delivery_fare']);
        $notifications->send($order->driver, 'payment', 'Course terminée',
            "Montant de la course #{$order->id} : {$fmt($total)}{$bonus}. Vos gains seront crédités dès le paiement du client.");

        return $order;
    }

    /** Plafond du tarif GPS, en multiple du tarif de l'itinéraire calculé. */
    public const GPS_FARE_CAP_RATIO = 1.5;

    /** Vitesse au-delà de laquelle un point GPS est un saut aberrant (km/h). */
    private const MAX_PLAUSIBLE_SPEED_KMH = 130;

    /**
     * Trajet réellement parcouru entre le retrait en quincaillerie et
     * maintenant (livraison), d'après la télémétrie du livreur. Les points
     * aberrants (saut impossible entre deux relevés) sont écartés ; le temps
     * d'attente, déjà rémunéré par le bonus d'attente, est retiré de la durée.
     *
     * @return array{distance_km: float, duration_min: float, points: int}|null
     */
    public function measureTrip(Order $order): ?array
    {
        $start = $order->driver_picked_up_at;
        if (! $order->driver_id || ! $start) {
            return null;
        }

        $points = DeliveryTracking::where('order_id', $order->id)
            ->where('driver_id', $order->driver_id)
            ->where('created_at', '>=', $start)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get(['latitude', 'longitude', 'created_at']);

        $kept = 0;
        $distanceKm = 0.0;
        $previous = null;

        foreach ($points as $point) {
            if ($previous === null) {
                $previous = $point;
                $kept = 1;

                continue;
            }

            $segmentKm = $this->haversineKm(
                (float) $previous->latitude,
                (float) $previous->longitude,
                (float) $point->latitude,
                (float) $point->longitude
            );
            $hours = max(1, $previous->created_at->diffInSeconds($point->created_at, true)) / 3600;

            if ($segmentKm / $hours > self::MAX_PLAUSIBLE_SPEED_KMH) {
                continue;
            }

            $distanceKm += $segmentKm;
            $previous = $point;
            $kept++;
        }

        $durationMin = max(0.0, $start->diffInSeconds(now(), true) / 60 - (int) $order->waiting_time_minutes);

        return [
            'distance_km' => round($distanceKm, 2),
            'duration_min' => round($durationMin, 1),
            'points' => $kept,
        ];
    }

    /**
     * Tarif final de la course et sa provenance (`gps`, `gps_plafonne`,
     * `estimation`).
     *
     * @return array{base: int, source: string}
     */
    private function finalFare(Order $order, int $routed, ?array $trip): array
    {
        // Moins de trois relevés ou quasi aucun déplacement : la trace ne
        // décrit pas le trajet (GPS coupé, application en arrière-plan).
        if (! $trip || $trip['points'] < 3 || $trip['distance_km'] < 0.2) {
            return ['base' => $routed, 'source' => 'estimation'];
        }

        $gps = $this->pricingService->fareFromTrip(
            $trip['distance_km'],
            $trip['duration_min'],
            (string) ($order->vehicle_class ?? 'moto'),
            (float) ($order->surge_multiplier ?? 1.0)
        );

        $cap = (int) round($routed * self::GPS_FARE_CAP_RATIO);
        if ($gps > $cap) {
            return ['base' => $cap, 'source' => 'gps_plafonne'];
        }

        return ['base' => $gps, 'source' => 'gps'];
    }

    private function haversineKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return 6371.0 * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    /**
     * Message de fin de livraison annonçant le montant de la course.
     */
    public function deliveryFareMessage(Order $order): string
    {
        $fare = $order->delivery_fare;
        if (! $fare) {
            return 'Livraison confirmée.';
        }

        $amount = number_format($fare['total'], 0, ',', ' ').' FCFA';
        $bonus = $fare['waiting_bonus'] > 0 ? " (dont bonus d'attente ".number_format($fare['waiting_bonus'], 0, ',', ' ').' FCFA)' : '';

        return $fare['status'] === 'a_payer'
            ? "Livraison confirmée. Montant de la course : {$amount}{$bonus}. Vos gains seront crédités dès le paiement du client."
            : "Livraison confirmée. Montant de la course : {$amount}{$bonus}. Vos gains ont été crédités.";
    }

    /**
     * Règle la course après confirmation du paiement du client et crédite le
     * livreur. Idempotent : webhook, interrogation de statut et simulateur
     * peuvent confirmer le même paiement (Règle d'or 36).
     *
     * @return bool vrai si ce paiement vient de régler la course
     */
    public function settleDeliveryFare(Order $order, Transaction $payment): bool
    {
        return DB::transaction(function () use ($order, $payment) {
            $order = Order::lockForUpdate()->find($order->id);

            if (! $order || $order->delivery_fare_status !== 'a_payer') {
                return false;
            }

            // Le montant est celui établi par le serveur à la livraison, jamais
            // un montant posté par le client (Règle d'or 36).
            if ((int) $payment->montant !== $order->deliveryFareDue() || (int) $payment->user_id !== (int) $order->client_id) {
                Log::warning("[Course] Paiement #{$payment->id} non conforme pour la commande #{$order->id}", [
                    'attendu' => $order->deliveryFareDue(),
                    'recu' => $payment->montant,
                ]);

                return false;
            }

            $order->update([
                'delivery_fare_status' => 'paye',
                'delivery_fare_settled_at' => now(),
            ]);

            $this->releaseDriverFunds($order);

            if ($order->driver) {
                ['net' => $net] = $this->driverShare($order->deliveryFareTotal());
                app(NotificationService::class)->send($order->driver, 'payment', 'Course réglée',
                    "Le client a réglé la course #{$order->id}. ".number_format($net, 0, ',', ' ').' FCFA ont été crédités sur votre portefeuille.');
            }

            // Dernière course due réglée : la restriction du client tombe.
            if ($order->client) {
                app(DeliveryFareCollectionService::class)->liftIfSettled($order->client->fresh());
            }

            return true;
        });
    }

    /**
     * Synthèse des gains d'un livreur, en montants nets de commission.
     *
     * - `earned` : parts de course effectivement libérées (ledger des
     *   transactions vers `driver_wallet_{id}`), et non la somme brute des
     *   `delivery_cost`, qui incluait la commission plateforme ;
     * - `pending` : gain net attendu des courses acceptées et pas encore
     *   livrées — y compris `driver_picked_up`, le statut réel du transit
     *   après retrait, qu'omettait l'ancien calcul — estimé sur le tarif et
     *   le bonus d'attente déjà cumulé ;
     * - `pending_count` : nombre de ces courses ;
     * - `awaiting_payment` / `awaiting_payment_count` : courses livrées dont
     *   le client n'a pas encore réglé le montant.
     *
     * @return array{earned: int, pending: int, pending_count: int, awaiting_payment: int, awaiting_payment_count: int}
     */
    public function driverEarningsSummary(User $driver): array
    {
        $earned = (int) Transaction::where('user_id', $driver->id)
            ->where('wallet_dest', 'driver_wallet_'.$driver->id)
            ->where('statut', PaymentStatus::CONFIRME)
            ->sum('montant');

        $net = fn ($order) => $this->driverShare((int) $order->delivery_cost + (int) $order->waiting_fee)['net'];

        $inProgress = Order::where('driver_id', $driver->id)
            ->whereIn('status', self::DRIVER_IN_PROGRESS_STATUSES)
            ->get(['delivery_cost', 'waiting_fee']);

        $awaiting = Order::where('driver_id', $driver->id)
            ->where('status', 'delivered')
            ->where('delivery_fare_status', 'a_payer')
            ->get(['delivery_cost', 'waiting_fee']);

        return [
            'earned' => $earned,
            'pending' => (int) $inProgress->sum($net),
            'pending_count' => $inProgress->count(),
            'awaiting_payment' => (int) $awaiting->sum($net),
            'awaiting_payment_count' => $awaiting->count(),
        ];
    }

    /**
     * Libère les frais de livraison au profit du livreur.
     */
    private function releaseDriverFunds(Order $order): void
    {
        $driver = $order->driver;
        if (! $driver) {
            return;
        }

        ['commission' => $driverCommission, 'net' => $gainNetDriver] = $this->driverShare($order->deliveryFareTotal());

        // Débiter le compte séquestre de la part livraison
        Transaction::create([
            'user_id' => $driver->id,
            'type' => 'liberation_jalon',
            'montant' => $gainNetDriver,
            'wallet_source' => 'escrow_order_'.$order->id,
            'wallet_dest' => 'driver_wallet_'.$driver->id,
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
                app(NotificationService::class)->send(
                    $driver,
                    'payment',
                    'Course de livraison disponible',
                    "Une nouvelle livraison de {$costFormatted} FCFA est disponible chez {$supplierName} (Commande #{$order->id}).",
                    ['order_id' => $order->id, 'type' => 'delivery_request']
                );
            } catch (\Throwable $e) {
                Log::warning("Notification livreur échouée pour user {$driver->id}: ".$e->getMessage());
            }
        }
    }
}
