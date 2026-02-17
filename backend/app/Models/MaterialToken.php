<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MaterialToken extends Model
{
    protected $fillable = [
        'code',
        'project_id',
        'escrow_wallet_id',
        'vendor_id',
        'total_value',
        'remaining_value',
        'qr_code_path',
        'expires_at',
        'status',
    ];

    protected $casts = [
        'total_value' => 'decimal:2',
        'remaining_value' => 'decimal:2',
        'expires_at' => 'datetime',
    ];

    // Relationships
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function escrowWallet(): BelongsTo
    {
        return $this->belongsTo(EscrowWallet::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vendor_id');
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(TokenRedemption::class, 'token_id');
    }
}
