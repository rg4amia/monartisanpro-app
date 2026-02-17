<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ArtisanScore extends Model
{
    protected $fillable = [
        'artisan_id',
        'total_score',
        'reliability_score',
        'integrity_score',
        'quality_score',
        'responsiveness_score',
        'professionalism_score',
        'projects_completed',
        'average_rating',
        'total_reviews',
        'badge_level',
        'last_calculated_at',
    ];

    protected $casts = [
        'total_score' => 'decimal:2',
        'reliability_score' => 'decimal:2',
        'integrity_score' => 'decimal:2',
        'quality_score' => 'decimal:2',
        'responsiveness_score' => 'decimal:2',
        'professionalism_score' => 'decimal:2',
        'projects_completed' => 'integer',
        'average_rating' => 'decimal:2',
        'total_reviews' => 'integer',
        'last_calculated_at' => 'datetime',
    ];

    // Relationships
    public function artisan(): BelongsTo
    {
        return $this->belongsTo(User::class, 'artisan_id');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(ScoreHistory::class, 'artisan_id', 'artisan_id');
    }
}
