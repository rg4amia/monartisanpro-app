<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecruitmentWorkday extends Model
{
    protected $fillable = [
        'engagement_id', 'day_number', 'montant', 'status', 'validated_at',
    ];

    protected function casts(): array
    {
        return [
            'day_number' => 'integer',
            'montant' => 'integer',
            'validated_at' => 'datetime',
        ];
    }

    public function engagement(): BelongsTo
    {
        return $this->belongsTo(RecruitmentEngagement::class, 'engagement_id');
    }
}
