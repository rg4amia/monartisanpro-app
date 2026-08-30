<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScoreLedgerEntry extends Model
{
    protected $fillable = [
        'user_id',
        'event_type',
        'points',
        'credibility_factor',
        'evaluation_id',
        'mission_id',
        'order_id',
        'description',
    ];

    protected $casts = [
        'points' => 'integer',
        'credibility_factor' => 'float',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function evaluation(): BelongsTo
    {
        return $this->belongsTo(Evaluation::class);
    }

    public function mission(): BelongsTo
    {
        return $this->belongsTo(Mission::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
