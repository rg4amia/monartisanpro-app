<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JuryReview extends Model
{
    protected $fillable = [
        'litige_id',
        'jure_id',
        'verdict',
        'voted_at',
        'compensation',
    ];

    protected function casts(): array
    {
        return [
            'voted_at' => 'datetime',
            'compensation' => 'integer',
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
