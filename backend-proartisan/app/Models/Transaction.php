<?php

namespace App\Models;

use App\Enums\PaymentProvider;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    protected $fillable = [
        'mission_id',
        'user_id',
        'type',
        'montant',
        'wallet_source',
        'wallet_dest',
        'provider',
        'statut',
        'reference_externe',
        'wave_checkout_id',
        'wave_payment_id',
        'wave_client_reference',
        'orange_order_id',
        'orange_payment_token',
        'orange_tx_reference',
        'webhook_payload',
        'paid_at',
        'failed_at',
        'error_message',
        'client_phone',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'montant' => 'integer',
            'provider' => PaymentProvider::class,
            'statut' => PaymentStatus::class,
            'metadata' => 'array',
            'paid_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    // Relations
    public function mission(): BelongsTo
    {
        return $this->belongsTo(Mission::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Scopes
    public function scopeWave($query)
    {
        return $query->where('provider', PaymentProvider::WAVE);
    }

    public function scopeOrangeMoney($query)
    {
        return $query->where('provider', PaymentProvider::ORANGE_MONEY);
    }

    public function scopeConfirmed($query)
    {
        return $query->where('statut', PaymentStatus::CONFIRME);
    }

    public function scopePending($query)
    {
        return $query->where('statut', PaymentStatus::EN_ATTENTE);
    }

    public function scopeFailed($query)
    {
        return $query->where('statut', PaymentStatus::ECHOUE);
    }

    // Helpers
    public function isWave(): bool
    {
        return $this->provider === PaymentProvider::WAVE;
    }

    public function isOrangeMoney(): bool
    {
        return $this->provider === PaymentProvider::ORANGE_MONEY;
    }

    public function isPending(): bool
    {
        return $this->statut === PaymentStatus::EN_ATTENTE;
    }

    public function isConfirmed(): bool
    {
        return $this->statut === PaymentStatus::CONFIRME;
    }

    public function isFailed(): bool
    {
        return $this->statut === PaymentStatus::ECHOUE;
    }
}
