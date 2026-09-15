<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RecruitmentEngagement extends Model
{
    protected $fillable = [
        'offer_id', 'application_id', 'artisan_id', 'recruiter_id',
        'daily_rate', 'total_days', 'montant_total', 'commission_rate',
        'status', 'accepted_at',
    ];

    protected function casts(): array
    {
        return [
            'daily_rate' => 'integer',
            'total_days' => 'integer',
            'montant_total' => 'integer',
            'commission_rate' => 'decimal:4',
            'accepted_at' => 'datetime',
        ];
    }

    public function offer(): BelongsTo
    {
        return $this->belongsTo(RecruitmentOffer::class, 'offer_id');
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(RecruitmentApplication::class, 'application_id');
    }

    public function artisan(): BelongsTo
    {
        return $this->belongsTo(User::class, 'artisan_id');
    }

    public function recruiter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recruiter_id');
    }

    public function workdays(): HasMany
    {
        return $this->hasMany(RecruitmentWorkday::class, 'engagement_id')->orderBy('day_number');
    }
}
