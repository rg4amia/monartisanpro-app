<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'supplier_id',
        'driver_id',
        'driver_assigned_at',
        'driver_reassignment_count',
        'delivery_mode',
        'status',
        'subtotal',
        'delivery_cost',
        'platform_fee',
        'total_amount',
        'pickup_code',
        'reception_code',
        'vehicle_class',
        'surge_multiplier',
        'delivered_at',
        'pickup_photo_url',
        'delivery_photo_url',
        'waiting_time_minutes',
        'dispute_reason',
        'dispute_opened_at',
    ];

    /**
     * Les codes de retrait et de réception ne sont jamais sérialisés par
     * défaut.
     *
     * Ce sont des secrets de contrôle : leur détenteur légitime prouve, en les
     * communiquant, qu'il était bien présent. Tant qu'ils figuraient dans la
     * sérialisation du modèle, tout endpoint renvoyant une commande les
     * livrait à quiconque y avait accès — y compris au livreur, qui pouvait
     * alors valider seul sa propre course. On les masque ici, à la source, et
     * on les réexpose explicitement à l'acteur qui en a besoin via
     * [codesVisibleTo].
     *
     * @var list<string>
     */
    protected $hidden = [
        'pickup_code',
        'reception_code',
    ];

    protected function casts(): array
    {
        return [
            'subtotal'                  => 'integer',
            'delivery_cost'             => 'integer',
            'platform_fee'              => 'integer',
            'total_amount'              => 'integer',
            'surge_multiplier'          => 'float',
            'waiting_time_minutes'      => 'integer',
            'driver_reassignment_count' => 'integer',
            'delivered_at'              => 'datetime',
            'driver_assigned_at'        => 'datetime',
            'dispute_opened_at'         => 'datetime',
        ];
    }

    /**
     * Codes que cet acteur a légitimement besoin de connaître.
     *
     * Le principe : celui qui **remet** la marchandise détient le code et le
     * vérifie ; celui qui la **reçoit** doit le lui demander. C'est ce qui fait
     * du code une preuve de rencontre physique.
     *
     *  - Fournisseur : le code de retrait, pour contrôler qui se présente au
     *    comptoir — le livreur en mode livraison, le client en retrait magasin.
     *  - Client : en retrait magasin, le code de retrait, puisqu'il vient
     *    lui-même chercher sa commande ; en livraison, le code de réception,
     *    qu'il remettra au livreur une fois le colis en main.
     *  - Livreur : aucun. Il doit demander les deux, sans quoi il pourrait
     *    valider retrait et livraison sans avoir rencontré personne.
     *  - Admin : les deux, pour l'arbitrage des litiges.
     *
     * @return array<string, string>
     */
    public function codesVisibleTo(?User $user): array
    {
        if ($user === null) {
            return [];
        }

        if ($user->role === 'admin') {
            return array_filter([
                'pickup_code' => $this->pickup_code,
                'reception_code' => $this->reception_code,
            ]);
        }

        if ($this->supplier_id === $user->id) {
            return array_filter(['pickup_code' => $this->pickup_code]);
        }

        if ($this->client_id === $user->id) {
            return array_filter($this->delivery_mode === 'pickup'
                ? ['pickup_code' => $this->pickup_code]
                : ['reception_code' => $this->reception_code]);
        }

        return [];
    }

    /**
     * Qui peut valider la prise en charge / le retrait.
     *
     * Deux acteurs, et c'est volontaire : celui qui **reçoit** la marchandise
     * en saisissant le code (le livreur, ou le client en retrait magasin), et
     * celui qui la **remet** (le fournisseur), qui peut confirmer depuis son
     * propre appareil. Ce second chemin est ce qui permet à la validation
     * d'aboutir quand le premier n'a pas de réseau.
     */
    public function canValidatePickup(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        if ($user->role === 'admin' || $this->supplier_id === $user->id) {
            return true;
        }

        return $this->delivery_mode === 'pickup'
            ? $this->client_id === $user->id
            : $this->driver_id === $user->id;
    }

    /**
     * Qui peut valider la livraison : le livreur qui saisit le code du client,
     * ou le client lui-même, qui confirme avoir reçu son colis.
     */
    public function canValidateDelivery(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        return $user->role === 'admin'
            || $this->driver_id === $user->id
            || $this->client_id === $user->id;
    }

    // Relations
    public function client()
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function supplier()
    {
        return $this->belongsTo(User::class, 'supplier_id');
    }

    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function trackings()
    {
        return $this->hasMany(DeliveryTracking::class);
    }

    public function latestTracking()
    {
        return $this->hasOne(DeliveryTracking::class)->latestOfMany();
    }

    // Helpers d'état
    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    public function isPrepared(): bool
    {
        return $this->status === 'prepared';
    }

    public function isSearchingDriver(): bool
    {
        return $this->status === 'searching_driver';
    }

    public function isDriverAssigned(): bool
    {
        return $this->status === 'driver_assigned';
    }

    public function isDriverPickedUp(): bool
    {
        return $this->status === 'driver_picked_up' || $this->status === 'shipping';
    }

    public function isDelivered(): bool
    {
        return $this->status === 'delivered';
    }

    /**
     * Vérifie si le livreur assigné a dépassé le délai d'inactivité.
     */
    public function isDriverStale(int $timeoutMinutes = 15): bool
    {
        if ($this->status !== 'driver_assigned' || !$this->driver_assigned_at) {
            return false;
        }

        return now()->gte($this->driver_assigned_at->copy()->addMinutes($timeoutMinutes));
    }

    public function isDisputed(): bool
    {
        return $this->status === 'disputed';
    }

    public function canDeclareDispute(): bool
    {
        if ($this->status !== 'delivered' || !$this->delivered_at) {
            return false;
        }

        if ($this->dispute_opened_at || $this->status === 'disputed') {
            return false;
        }

        $windowMinutes = (int) Setting::getValueByKey('order_dispute_window_minutes', 30);
        return now()->diffInMinutes($this->delivered_at) <= $windowMinutes;
    }
}
