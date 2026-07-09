<?php

namespace App\Models;

use App\Enums\WalletOperation;
use App\Enums\WalletType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class WalletTransaction extends Model
{
    protected $fillable = [
        'user_id',
        'wallet_type',
        'operation',
        'montant',
        'solde_avant',
        'solde_apres',
        'mission_id',
        'jalon_id',
        'transaction_id',
        'reference',
        'cle_idempotence',
        'description',
        'metadata',
    ];

    protected $casts = [
        'wallet_type' => WalletType::class,
        'operation' => WalletOperation::class,
        'montant' => 'integer',
        'solde_avant' => 'integer',
        'solde_apres' => 'integer',
        'metadata' => 'array',
    ];

    /**
     * Boot du model - génération automatique de référence unique
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($walletTransaction) {
            if (empty($walletTransaction->reference)) {
                $walletTransaction->reference = 'WT-' . strtoupper(Str::random(12));
            }
        });
    }

    // Relations
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function mission(): BelongsTo
    {
        return $this->belongsTo(Mission::class);
    }

    public function jalon(): BelongsTo
    {
        return $this->belongsTo(Jalon::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    // Scopes
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForWallet($query, WalletType $walletType)
    {
        return $query->where('wallet_type', $walletType);
    }

    public function scopeForMission($query, int $missionId)
    {
        return $query->where('mission_id', $missionId);
    }

    public function scopeRecent($query)
    {
        return $query->orderBy('created_at', 'desc');
    }
}
