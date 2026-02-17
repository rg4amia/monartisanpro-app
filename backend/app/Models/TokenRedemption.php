<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use MatanYadaev\EloquentSpatial\Objects\Point;
use MatanYadaev\EloquentSpatial\Traits\HasSpatial;

class TokenRedemption extends Model
{
    use HasSpatial;

    protected $fillable = [
        'token_id',
        'vendor_id',
        'artisan_id',
        'amount',
        'vendor_location',
        'artisan_location',
        'distance_meters',
        'validation_method',
        'receipt_photo',
        'redeemed_at',
    ];

    protected $casts = [
        'vendor_location' => Point::class,
        'artisan_location' => Point::class,
        'amount' => 'decimal:2',
        'distance_meters' => 'decimal:2',
        'redeemed_at' => 'datetime',
    ];

    // Relationships
    public function token(): BelongsTo
    {
        return $this->belongsTo(MaterialToken::class, 'token_id');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vendor_id');
    }

    public function artisan(): BelongsTo
    {
        return $this->belongsTo(User::class, 'artisan_id');
    }
}
