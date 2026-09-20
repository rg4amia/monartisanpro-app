<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CampagneParrainage extends Model
{
    protected $table = 'campagnes_parrainage';

    protected $fillable = [
        'libelle',
        'discount_type',
        'discount_value',
        'max_discount_amount',
        'min_montant',
        'starts_at',
        'expires_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'discount_value' => 'integer',
            'max_discount_amount' => 'integer',
            'min_montant' => 'integer',
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    /**
     * La campagne est-elle utilisable maintenant (active + dans sa période) ?
     */
    public function estActive(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->starts_at && now()->lt($this->starts_at)) {
            return false;
        }

        if ($this->expires_at && now()->gt($this->expires_at)) {
            return false;
        }

        return true;
    }
}
