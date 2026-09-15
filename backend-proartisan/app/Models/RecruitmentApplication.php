<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecruitmentApplication extends Model
{
    protected $fillable = [
        'offer_id', 'artisan_id', 'matching_score', 'status', 'applied_at',
    ];

    protected function casts(): array
    {
        return [
            'matching_score' => 'decimal:2',
            'applied_at' => 'datetime',
        ];
    }

    public function offer(): BelongsTo
    {
        return $this->belongsTo(RecruitmentOffer::class, 'offer_id');
    }

    public function artisan(): BelongsTo
    {
        return $this->belongsTo(User::class, 'artisan_id');
    }
}
