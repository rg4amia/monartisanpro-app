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

    protected function casts(): array
    {
        return [
            'subtotal'             => 'integer',
            'delivery_cost'        => 'integer',
            'platform_fee'         => 'integer',
            'total_amount'         => 'integer',
            'surge_multiplier'     => 'float',
            'waiting_time_minutes' => 'integer',
            'delivered_at'         => 'datetime',
            'dispute_opened_at'    => 'datetime',
        ];
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
