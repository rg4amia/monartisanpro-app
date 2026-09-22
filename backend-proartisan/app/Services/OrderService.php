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
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class OrderService
{
    private OsrmRoutingService $osrmService;

    private RealtimeEventService $realtimeEventService;

    private DeliveryPricingService $pricingService;

    public function __construct(
        private GoogleMapsService $mapsService,
        private WalletService $walletService,
        ?OsrmRoutingService $osrmService = null,
        ?RealtimeEventService $realtimeEventService = null,
        ?DeliveryPricingService $pricingService = null,
    ) {
        $this->osrmService = $osrmService ?? app(OsrmRoutingService::class);
        $this->realtimeEventService = $realtimeEventService ?? app(RealtimeEventService::class);
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
                            'position' => DB::raw('ST_SRID(POINT(-4.0083, 5.3599), 4326)'),
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
                $appliedPromo = PromoCode::where('code', $codeStr)->first();
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
                'mission_id' => $missionId,
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

            // 7. Enregistrement de la transaction séquestre associée
            Transaction::create([
                'user_id' => $client->id,
                'type' => 'acompte',
                'montant' => $totalAmount,
                'wallet_source' => 'client_mobile_money_'.$client->id,
                'wallet_dest' => 'escrow_order_'.$order->id,
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
                app(NotificationService::class)->send(
                    $supplier,
                    'payment',
                    'Nouvelle commande reçue',
                    "La commande #{$order->id} d'un montant de ".number_format($order->subtotal, 0, ',', ' ').' FCFA a été payée et est en attente de préparation.'
                );
            } catch (\Throwable $e) {
                Log::warning('Notification fournisseur non bloquante : '.$e->getMessage());
            }

            // Notification Client
            try {
                app(NotificationService::class)->send(
                    $client,
                    'payment',
                    'Paiement commande confirmé',
                    'Votre paiement de '.number_format($order->total_amount, 0, ',', ' ')." FCFA pour la commande #{$order->id} est sécurisé en compte séquestre."
                );
            } catch (\Throwable $e) {
                Log::warning('Notification client non bloquante : '.$e->getMessage());
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

                $pkgTotal = max(0, $pkgSubtotal + $pkgDeliveryCost + $pkgPlatformFee);

                $codeSuffix = str_pad(random_int(0, 9999), 4, '0', STR_PAD_LEFT);
                $pickupPrefix = $deliveryMode === 'delivery' ? 'LIVREUR' : 'RETRAIT';
                $pickupCode = "{$pickupPrefix}-{$codeSuffix}";

                $receptionSuffix = str_pad(random_int(0, 9999), 4, '0', STR_PAD_LEFT);
                $receptionCode = "RECEPTION-{$receptionSuffix}";

                $order = Order::create([
                    'client_id' => $client->id,
                    'supplier_id' => $supplier->id,
                    'delivery_mode' => $deliveryMode,
                    'status' => 'paid',
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

                $createdOrders[] = $order->load('items.product', 'supplier.fournisseurAgree');
                $totalSubtotal += $pkgSubtotal;
                $totalDeliveryCost += $pkgDeliveryCost;
                $totalPlatformFee += $pkgPlatformFee;
                $grandTotal += $pkgTotal;

                // Notification individuelle au fournisseur
                try {
                    app(NotificationService::class)->send(
                        $supplier,
                        'payment',
                        'Nouvelle commande reçue (Panier multi-fournisseurs)',
                        "La commande #{$order->id} (Groupe {$orderGroupId}) d'un montant de ".number_format($order->subtotal, 0, ',', ' ').' FCFA a été payée et est en attente de préparation.'
                    );
                } catch (\Throwable $e) {
                    Log::warning('Notification fournisseur non bloquante : '.$e->getMessage());
                }
            }

            // Gestion éventuelle du code promo global
            $discountAmount = 0;
            if ($promoCode) {
                $codeStr = strtoupper(trim($promoCode));
                $appliedPromo = PromoCode::where('code', $codeStr)->first();
                if ($appliedPromo) {
                    try {
                        $discountAmount = $appliedPromo->calculateDiscount($totalSubtotal);
                        $appliedPromo->increment('used_count');
                        $grandTotal = max(0, $grandTotal - $discountAmount);
                    } catch (\Exception $e) {
                        // ignore
                    }
                }
            }

            // Enregistrement de la transaction financière unique pour le groupe
            Transaction::create([
                'user_id' => $client->id,
                'type' => 'acompte',
                'montant' => $grandTotal,
                'wallet_source' => 'client_mobile_money_'.$client->id,
                'wallet_dest' => 'escrow_group_'.$orderGroupId,
                'provider' => PaymentProvider::WAVE,
                'statut' => PaymentStatus::CONFIRME,
                'paid_at' => now(),
                'metadata' => [
                    'order_group_id' => $orderGroupId,
                    'order_ids' => array_column($createdOrders, 'id'),
                    'packages_count' => count($createdOrders),
                    'description' => "Paiement groupé panier multi-fournisseurs {$orderGroupId}",
                ],
            ]);

            // Notification Client
            try {
                app(NotificationService::class)->send(
                    $client,
                    'payment',
                    'Paiement groupé confirmé',
                    "Votre commande multi-fournisseurs ({$orderGroupId}) pour ".count($createdOrders)." quincailleries d'un montant total de ".number_format($grandTotal, 0, ',', ' ').' FCFA est sécurisée en compte séquestre.'
                );
            } catch (\Throwable $e) {
                Log::warning('Notification client non bloquante : '.$e->getMessage());
            }

            return [
                'order_group_id' => $orderGroupId,
                'orders' => $createdOrders,
                'total_subtotal' => $totalSubtotal,
                'total_delivery_cost' => $totalDeliveryCost,
                'total_platform_fee' => $totalPlatformFee,
                'total_amount' => $grandTotal,
                'packages_count' => count($createdOrders),
            ];
        });
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
        $deliveryCost = $this->pricingService->calculateOrderDeliveryCost($order);

        $order->update([
            'driver_id' => $driver->id,
            'status' => 'driver_assigned',
            'driver_assigned_at' => now(),
            'delivery_cost' => $deliveryCost,
            'total_amount' => $order->subtotal + $order->platform_fee + $deliveryCost,
            'vehicle_class' => $order->vehicle_class,
        ]);

        // Créer la transaction Mobile Money pour le montant de la course
        Transaction::create([
            'user_id' => $order->client_id,
            'type' => 'acompte',
            'montant' => $deliveryCost,
            'wallet_source' => 'client_mobile_money_'.$order->client_id,
            'wallet_dest' => 'escrow_order_'.$order->id,
            'provider' => PaymentProvider::WAVE,
            'statut' => PaymentStatus::CONFIRME,
            'paid_at' => now(),
            'metadata' => [
                'order_id' => $order->id,
                'description' => "Paiement de la course de livraison #{$order->id} (ajouté après acceptation du livreur)",
            ],
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

            // Libération des fonds au livreur
            $this->releaseDriverFunds($order);

            // Notification Client
            app(NotificationService::class)->send(
                $order->client,
                'payment',
                'Livraison effectuée',
                "Votre commande #{$order->id} a été livrée avec succès par {$order->driver->name}."
            );

            // Notification Livreur
            app(NotificationService::class)->send(
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

        $extraFee = (int) round(($waitingMinutes / 5) * 100); // 100 FCFA par tranche de 5 min d'attente
        $newDeliveryCost = $order->delivery_cost + $extraFee;

        return DB::transaction(function () use ($order, $waitingMinutes, $extraFee, $newDeliveryCost) {
            $order->update([
                'waiting_time_minutes' => $order->waiting_time_minutes + $waitingMinutes,
                'delivery_cost' => $newDeliveryCost,
                'total_amount' => $order->subtotal + $order->platform_fee + $newDeliveryCost,
            ]);

            // Trace le surcoût dans le séquestre de la commande : sans cette
            // écriture, le montant reversé au livreur à la livraison
            // (`releaseDriverFunds`, calculé sur `delivery_cost`) n'avait
            // aucune contrepartie dans le ledger financier.
            Transaction::create([
                'user_id' => $order->client_id,
                'type' => 'acompte',
                'montant' => $extraFee,
                'wallet_source' => 'client_mobile_money_'.$order->client_id,
                'wallet_dest' => 'escrow_order_'.$order->id,
                'provider' => PaymentProvider::WAVE,
                'statut' => PaymentStatus::CONFIRME,
                'paid_at' => now(),
                'metadata' => [
                    'order_id' => $order->id,
                    'waiting_minutes' => $waitingMinutes,
                    'description' => "Majoration frais d'attente livreur - commande #{$order->id}",
                ],
            ]);

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
     * Libère les frais de livraison au profit du livreur.
     */
    private function releaseDriverFunds(Order $order): void
    {
        $driver = $order->driver;
        if (! $driver) {
            return;
        }

        // Calcul de la commission livreur dynamique (depuis settings, default 10%)
        $driverCommissionRatio = Setting::getValueByKey('commission_livreur', 0.10);
        $driverCommission = (int) round($order->delivery_cost * $driverCommissionRatio);
        $gainNetDriver = $order->delivery_cost - $driverCommission;

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

    /**
     * Enregistre un lot de points GPS télémétriques (batching économie réseau/batterie)
     * et diffuse la dernière position connue.
     *
     * @param  array  $points  [ ['latitude' => ..., 'longitude' => ..., 'speed_kmh' => ..., 'heading' => ..., 'battery_level' => ..., 'recorded_at' => ...], ... ]
     */
    public function recordDriverBatchLocations(Order $order, User $driver, array $points): DeliveryTracking
    {
        if ($order->driver_id !== $driver->id) {
            throw new \Exception("Ce livreur n'est pas assigné à cette commande.");
        }

        if (empty($points)) {
            throw new \Exception('Aucun point de télémétrie fourni.');
        }

        $records = [];
        $now = now();
        foreach ($points as $pt) {
            $records[] = [
                'order_id' => $order->id,
                'driver_id' => $driver->id,
                'latitude' => (float) $pt['latitude'],
                'longitude' => (float) $pt['longitude'],
                'speed_kmh' => isset($pt['speed_kmh']) ? (float) $pt['speed_kmh'] : null,
                'heading' => isset($pt['heading']) ? (float) $pt['heading'] : null,
                'battery_level' => isset($pt['battery_level']) ? (int) $pt['battery_level'] : null,
                'created_at' => ! empty($pt['recorded_at']) ? Carbon::parse($pt['recorded_at']) : $now,
            ];
        }

        DeliveryTracking::insert($records);

        $lastPoint = end($points);
        $lastLat = (float) $lastPoint['latitude'];
        $lastLng = (float) $lastPoint['longitude'];
        $lastSpeed = isset($lastPoint['speed_kmh']) ? (float) $lastPoint['speed_kmh'] : null;
        $lastHeading = isset($lastPoint['heading']) ? (float) $lastPoint['heading'] : null;
        $lastBattery = isset($lastPoint['battery_level']) ? (int) $lastPoint['battery_level'] : null;

        try {
            $driver->setPosition($lastLat, $lastLng);
        } catch (\Throwable $e) {
        }

        $latestTracking = DeliveryTracking::where('order_id', $order->id)
            ->where('driver_id', $driver->id)
            ->latest('id')
            ->first();

        // Diffusion temps réel SSE
        $payload = [
            'order_id' => $order->id,
            'driver_id' => $driver->id,
            'driver_name' => $driver->name,
            'latitude' => $lastLat,
            'longitude' => $lastLng,
            'speed_kmh' => $lastSpeed,
            'heading' => $lastHeading,
            'battery_level' => $lastBattery,
            'recorded_at' => $now->toIso8601String(),
            'batch_size' => count($points),
        ];

        $this->realtimeEventService->publish(
            'order',
            $order->id,
            'driver_position_updated',
            $payload,
            'Livreur en mouvement (Batch)'
        );

        return $latestTracking ?? new DeliveryTracking($records[0]);
    }

    /**
     * Enregistre un point GPS télémétrique du livreur et diffuse l'événement SSE en temps réel.
     */
    public function recordDriverLocation(
        Order $order,
        User $driver,
        float $lat,
        float $lng,
        ?float $speed = null,
        ?float $heading = null,
        ?int $battery = null,
    ): DeliveryTracking {
        if ($order->driver_id !== $driver->id) {
            throw new \Exception("Ce livreur n'est pas assigné à cette commande.");
        }

        $tracking = DeliveryTracking::create([
            'order_id' => $order->id,
            'driver_id' => $driver->id,
            'latitude' => $lat,
            'longitude' => $lng,
            'speed_kmh' => $speed,
            'heading' => $heading,
            'battery_level' => $battery,
            'created_at' => now(),
        ]);

        // Mettre à jour la position courante du profil livreur.
        // On passe par User::setPosition, qui utilise des requêtes préparées et
        // gère le cas SQLite : l'ancienne interpolation directe des coordonnées
        // dans du SQL brut n'était pas injectable (les paramètres sont typés
        // `float`), mais elle restait fragile et contournait la convention du
        // projet — les colonnes POINT s'écrivent avec des bindings.
        try {
            $driver->setPosition($lat, $lng);
        } catch (\Throwable $e) {
            // Ignorer si la colonne spatiale a des spécificités en environnement de test
        }

        // Diffusion temps réel SSE via RealtimeEventService (Lot 2)
        $payload = [
            'order_id' => $order->id,
            'driver_id' => $driver->id,
            'driver_name' => $driver->name,
            'latitude' => $lat,
            'longitude' => $lng,
            'speed_kmh' => $speed,
            'heading' => $heading,
            'battery_level' => $battery,
            'recorded_at' => now()->toIso8601String(),
        ];

        // 1. Diffusion au canal de la commande
        $this->realtimeEventService->publish(
            'order',
            $order->id,
            'driver_position_updated',
            $payload,
            'Livreur en mouvement'
        );

        // 2. Si rattaché à une mission chantier, diffusion au canal de la mission
        $missionId = $order->items()->whereNotNull('mission_id')->value('mission_id');
        if ($missionId) {
            $this->realtimeEventService->publish(
                'mission',
                $missionId,
                'driver_position_updated',
                $payload,
                'Livreur de matériaux en approche'
            );
        }

        return $tracking;
    }

    /**
     * Récupère le package complet de suivi 360° de la livraison pour mobile et backoffice.
     */
    public function getDeliveryTrackingData(Order $order, ?User $viewer = null): array
    {
        $order->loadMissing(['supplier.fournisseurAgree', 'client', 'driver', 'latestTracking']);

        $supplierPos = $order->supplier?->fournisseurAgree?->getPositionCoords();
        $clientPos = $order->client?->getPositionCoords();
        if (! $clientPos && $order->delivery_latitude && $order->delivery_longitude) {
            $clientPos = [
                'lat' => (float) $order->delivery_latitude,
                'lng' => (float) $order->delivery_longitude,
            ];
        }
        $latest = $order->latestTracking;

        $route = null;
        if ($supplierPos && $clientPos) {
            $route = $this->osrmService->calculateRoute(
                (float) $supplierPos['lat'],
                (float) $supplierPos['lng'],
                (float) $clientPos['lat'],
                (float) $clientPos['lng']
            );
        }

        return [
            'order_id' => $order->id,
            'status' => $order->status,
            'delivery_mode' => $order->delivery_mode,
            'supplier' => [
                'id' => $order->supplier_id,
                'name' => $order->supplier?->fournisseurAgree?->nom_boutique ?? $order->supplier?->name,
                'phone' => $order->supplier?->phone,
                'position' => $supplierPos,
            ],
            'client' => [
                'id' => $order->client_id,
                'name' => $order->client?->name,
                'phone' => $order->client?->phone,
                'position' => $clientPos,
            ],
            'driver' => $order->driver ? [
                'id' => $order->driver->id,
                'name' => $order->driver->name,
                'phone' => $order->driver->phone,
                'position' => $latest ? [
                    'lat' => $latest->latitude,
                    'lng' => $latest->longitude,
                    'speed' => $latest->speed_kmh,
                    'heading' => $latest->heading,
                    'updated_at' => $latest->created_at?->toIso8601String(),
                ] : null,
            ] : null,
            'route' => $route,
            'routing' => $route,
            'driver_latest_position' => $latest ? [
                'latitude' => $latest->latitude,
                'longitude' => $latest->longitude,
                'speed_kmh' => $latest->speed_kmh,
                'heading' => $latest->heading,
                'battery_level' => $latest->battery_level,
                'recorded_at' => $latest->created_at?->toIso8601String(),
            ] : null,
            // Uniquement les codes que cet acteur doit connaître : le livreur
            // n'en reçoit aucun, il doit les demander au fournisseur puis au
            // client. Auparavant les deux étaient renvoyés à tout le monde.
            'codes' => $order->codesVisibleTo($viewer),
            'pickup_photo_url' => $order->pickup_photo_url,
            'delivery_photo_url' => $order->delivery_photo_url,
            'delivery_cost' => $order->delivery_cost,
        ];
    }

    /**
     * Recherche les livreurs vérifiés à proximité du point d'enlèvement (magasin).
     */
    public function findNearbyDrivers(float $lat, float $lng, float $radiusKm = 10.0, ?string $vehicleClass = null): Collection
    {
        $query = User::whereIn('role', ['livreur', 'driver'])
            ->where('kyc_status', 'actif');

        return $query->get()->filter(function ($driver) use ($lat, $lng, $radiusKm) {
            $coords = $driver->getPositionCoords();
            if (! $coords) {
                return false;
            }

            $dist = $this->osrmService->haversineDistanceKm($lat, $lng, (float) $coords['lat'], (float) $coords['lng']);

            return $dist <= $radiusKm;
        })->values();
    }
}
