<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Milestone extends Model
{
    protected $fillable = [
        'project_id',
        'title',
        'description',
        'labor_percentage',
        'sequence_order',
        'status',
        'requires_photo',
        'requires_otp',
        'photo_path',
        'otp_code',
        'otp_verified_at',
        'completed_at',
        'validated_at',
        'paid_at',
    ];

    protected $casts = [
        'labor_percentage' => 'decimal:2',
        'sequence_order' => 'integer',
        'requires_photo' => 'boolean',
        'requires_otp' => 'boolean',
        'otp_verified_at' => 'datetime',
        'completed_at' => 'datetime',
        'validated_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    // Relationships
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function laborPayment(): HasOne
    {
        return $this->hasOne(LaborPayment::class);
    }
}
