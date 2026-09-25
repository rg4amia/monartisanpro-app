<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CreditApplication extends Model
{
    protected $fillable = [
        'user_id', 'amount', 'score_prosartisan_at_application', 'status',
        'external_reference', 'approved_at', 'disbursed_at',
        'repaid_amount', 'repaid_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'score_prosartisan_at_application' => 'integer',
            'repaid_amount' => 'integer',
            'approved_at' => 'datetime',
            'disbursed_at' => 'datetime',
            'repaid_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getRemainingAmountAttribute(): int
    {
        return max(0, $this->amount - ((int) ($this->repaid_amount ?? 0)));
    }

    public function isFullyRepaid(): bool
    {
        return $this->status === 'rembourse' || ($this->remaining_amount === 0 && in_array($this->status, ['approuve', 'debourse']));
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', ['en_attente', 'approuve', 'debourse'])
            ->where(function ($q) {
                $q->whereNull('repaid_amount')
                    ->orWhereColumn('repaid_amount', '<', 'amount');
            });
    }
}
