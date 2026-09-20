<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ParrainageClient extends Model
{
    protected $table = 'parrainages_clients';

    protected $fillable = [
        'parrain_id',
        'filleul_id',
        'campagne_id',
        'promo_code_id',
        'statut',
        'recompense_at',
    ];

    protected function casts(): array
    {
        return [
            'recompense_at' => 'datetime',
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

    public function campagne(): BelongsTo
    {
        return $this->belongsTo(CampagneParrainage::class, 'campagne_id');
    }

    public function promoCode(): BelongsTo
    {
        return $this->belongsTo(PromoCode::class, 'promo_code_id');
    }
}
