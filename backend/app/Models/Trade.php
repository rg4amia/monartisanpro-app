<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Trade extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'sector_id',
        'code',
        'name',
    ];

    /**
     * Get the sector that owns this trade.
     */
    public function sector(): BelongsTo
    {
        return $this->belongsTo(Sector::class);
    }

    /**
     * Get the artisan profiles for this trade.
     */
    public function artisanProfiles(): HasMany
    {
        return $this->hasMany(ArtisanProfile::class);
    }
}
