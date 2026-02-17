<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Quote extends Model
{
    protected $fillable = [
        'project_id',
        'artisan_id',
        'total_amount',
        'material_amount',
        'labor_amount',
        'material_percentage',
        'labor_percentage',
        'valid_days',
        'valid_until',
        'status',
        'notes',
        'rejection_reason',
        'sent_at',
        'accepted_at',
        'rejected_at',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'material_amount' => 'decimal:2',
        'labor_amount' => 'decimal:2',
        'material_percentage' => 'decimal:2',
        'labor_percentage' => 'decimal:2',
        'valid_days' => 'integer',
        'valid_until' => 'datetime',
        'sent_at' => 'datetime',
        'accepted_at' => 'datetime',
        'rejected_at' => 'datetime',
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

    public function items(): HasMany
    {
        return $this->hasMany(QuoteItem::class);
    }
}
