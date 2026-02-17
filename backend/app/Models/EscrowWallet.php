<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class EscrowWallet extends Model
{
    protected $fillable = [
        'project_id',
        'total_amount',
        'material_wallet',
        'labor_wallet',
        'material_spent',
        'labor_released',
        'status',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'material_wallet' => 'decimal:2',
        'labor_wallet' => 'decimal:2',
        'material_spent' => 'decimal:2',
        'labor_released' => 'decimal:2',
    ];

    // Relationships
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function materialToken(): HasOne
    {
        return $this->hasOne(MaterialToken::class);
    }

    public function laborPayments(): HasMany
    {
        return $this->hasMany(LaborPayment::class);
    }
}
