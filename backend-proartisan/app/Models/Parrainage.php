<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Parrainage extends Model
{
    protected $fillable = [
        'parrain_id',
        'filleul_id',
        'score_caution',
    ];

    protected function casts(): array
    {
        return [
            'score_caution' => 'integer',
        ];
    }

    public function parrain(): BelongsTo
    {
        return $this->belongsTo(User::class, 'parrain_id');
    }

    public function filleul(): BelongsTo
    {
        return $this->belongsTo(User::class, 'filleul_id');
    }
}
