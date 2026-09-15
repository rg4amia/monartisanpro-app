<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RecruitmentOffer extends Model
{
    protected $fillable = [
        'creator_id', 'creator_type', 'trade_id', 'title', 'description',
        'mission_type', 'commune', 'sous_quartier', 'date_debut', 'daily_rate_min', 'daily_rate_max',
        'openings_count', 'deadline_at', 'status', 'metadata',
        'applicants_escrow_transaction_id', 'applicants_unlocked_at', 'applicants_escrow_reserved',
    ];

    protected function casts(): array
    {
        return [
            'date_debut' => 'date',
            'daily_rate_min' => 'integer',
            'daily_rate_max' => 'integer',
            'openings_count' => 'integer',
            'deadline_at' => 'datetime',
            'metadata' => 'array',
            'applicants_unlocked_at' => 'datetime',
            'applicants_escrow_reserved' => 'boolean',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function applicantsEscrowTransaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'applicants_escrow_transaction_id');
    }

    public function applicantsUnlocked(): bool
    {
        return $this->applicants_unlocked_at !== null;
    }

    /**
     * Nombre de jours inclusifs couverts par la période de l'offre, ou null
     * si l'une des deux bornes n'est pas définie.
     */
    public function inclusiveDurationDays(): ?int
    {
        if (! $this->date_debut || ! $this->deadline_at) {
            return null;
        }

        $days = ($this->deadline_at->startOfDay()->timestamp - $this->date_debut->copy()->startOfDay()->timestamp) / 86400;

        return $days >= 0 ? ((int) $days) + 1 : null;
    }

    public function trade(): BelongsTo
    {
        return $this->belongsTo(Trade::class);
    }

    public function applications(): HasMany
    {
        return $this->hasMany(RecruitmentApplication::class, 'offer_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function scopePendingReview(Builder $query): Builder
    {
        return $query->where('status', 'pending_review');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
