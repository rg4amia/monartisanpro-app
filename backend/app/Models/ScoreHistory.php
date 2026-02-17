<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScoreHistory extends Model
{
    protected $fillable = [
        'artisan_id',
        'total_score',
        'reliability_score',
        'integrity_score',
        'quality_score',
        'responsiveness_score',
        'professionalism_score',
        'event_type',
        'event_description',
        'score_change',
        'triggered_by',
        'project_id',
    ];

    protected $casts = [
        'total_score' => 'decimal:2',
        'reliability_score' => 'decimal:2',
        'integrity_score' => 'decimal:2',
        'quality_score' => 'decimal:2',
        'responsiveness_score' => 'decimal:2',
        'professionalism_score' => 'decimal:2',
        'score_change' => 'decimal:2',
    ];

    // Relationships
    public function artisan(): BelongsTo
    {
        return $this->belongsTo(User::class, 'artisan_id');
    }

    public function triggeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
