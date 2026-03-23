<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LitigeEvidence extends Model
{
    protected $table = 'litige_preuves';

    protected $fillable = [
        'litige_id',
        'user_id',
        'partie',
        'description',
        'media_url',
        'media_path',
        'latitude',
        'longitude',
        'taken_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'taken_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function litige(): BelongsTo
    {
        return $this->belongsTo(Litige::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
