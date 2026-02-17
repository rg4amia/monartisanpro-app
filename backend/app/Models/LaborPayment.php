<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LaborPayment extends Model
{
    protected $fillable = [
        'milestone_id',
        'artisan_id',
        'escrow_wallet_id',
        'amount',
        'transaction_id',
        'status',
        'scheduled_at',
        'processed_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'scheduled_at' => 'datetime',
        'processed_at' => 'datetime',
    ];

    // Relationships
    public function milestone(): BelongsTo
    {
        return $this->belongsTo(Milestone::class);
    }

    public function artisan(): BelongsTo
    {
        return $this->belongsTo(User::class, 'artisan_id');
    }

    public function escrowWallet(): BelongsTo
    {
        return $this->belongsTo(EscrowWallet::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }
}
