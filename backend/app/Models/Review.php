<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Review extends Model
{
    protected $fillable = [
        'project_id',
        'artisan_id',
        'client_id',
        'rating',
        'comment',
        'quality_rating',
        'communication_rating',
        'timeliness_rating',
        'professionalism_rating',
        'would_recommend',
        'photos',
        'artisan_response',
        'responded_at',
    ];

    protected $casts = [
        'rating' => 'integer',
        'quality_rating' => 'integer',
        'communication_rating' => 'integer',
        'timeliness_rating' => 'integer',
        'professionalism_rating' => 'integer',
        'would_recommend' => 'boolean',
        'photos' => 'array',
        'responded_at' => 'datetime',
    ];

    // Relationships
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function artisan(): BelongsTo
    {
        return $this->belongsTo(User::class, 'artisan_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }
}
