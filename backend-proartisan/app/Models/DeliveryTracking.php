<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryTracking extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'order_id',
        'driver_id',
        'latitude',
        'longitude',
        'speed_kmh',
        'heading',
        'battery_level',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'latitude'      => 'float',
            'longitude'     => 'float',
            'speed_kmh'     => 'float',
            'heading'       => 'float',
            'battery_level' => 'integer',
            'created_at'    => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_id');
    }
}
