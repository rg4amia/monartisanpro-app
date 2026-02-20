<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use MatanYadaev\EloquentSpatial\Objects\Point;
use MatanYadaev\EloquentSpatial\Traits\HasSpatial;

class ArtisanProfile extends Model
{
    use HasSpatial;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'trade_id',
        'location',
        'zone_name',
        'bio',
        'experience_years',
        'available',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'location' => Point::class,
            'available' => 'boolean',
            'experience_years' => 'integer',
        ];
    }

    /**
     * Get the user that owns this artisan profile.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the trade for this artisan profile.
     */
    public function trade(): BelongsTo
    {
        return $this->belongsTo(Trade::class);
    }

    /**
     * Get the latitude from the location point.
     */
    public function getLatitudeAttribute(): ?float
    {
        return $this->location?->latitude;
    }

    /**
     * Get the longitude from the location point.
     */
    public function getLongitudeAttribute(): ?float
    {
        return $this->location?->longitude;
    }
}
