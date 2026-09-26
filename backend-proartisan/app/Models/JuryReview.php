<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JuryReview extends Model
{
    protected $fillable = [
        'litige_id',
        'jure_id',
        'status',
        'verdict',
        'split_artisan_percentage',
        'technical_comment',
        'compensation',
        'compensation_paid',
        'compensation_paid_at',
        'assigned_at',
        'voted_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'split_artisan_percentage' => 'integer',
            'compensation' => 'integer',
            'compensation_paid' => 'boolean',
            'compensation_paid_at' => 'datetime',
            'assigned_at' => 'datetime',
            'voted_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function litige(): BelongsTo
    {
        return $this->belongsTo(Litige::class);
    }

    public function jure(): BelongsTo
    {
        return $this->belongsTo(User::class, 'jure_id');
    }
}
