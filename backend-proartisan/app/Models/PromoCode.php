<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PromoCode extends Model
{
    protected $fillable = [
        'code',
        'description',
        'discount_type',
        'discount_value',
        'min_order_amount',
        'max_discount_amount',
        'usage_limit',
        'used_count',
        'starts_at',
        'expires_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'discount_value' => 'integer',
            'min_order_amount' => 'integer',
            'max_discount_amount' => 'integer',
            'usage_limit' => 'integer',
            'used_count' => 'integer',
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Valide et calcule la remise pour un montant donné en FCFA.
     */
    public function calculateDiscount(int $orderAmount): int
    {
        if (!$this->is_active) {
            throw new \Exception("Ce code promo est inactif.");
        }

        if ($this->starts_at && now()->lt($this->starts_at)) {
            throw new \Exception("Ce code promo n'est pas encore actif.");
        }

        if ($this->expires_at && now()->gt($this->expires_at)) {
            throw new \Exception("Ce code promo a expiré.");
        }

        if ($this->usage_limit && $this->used_count >= $this->usage_limit) {
            throw new \Exception("Ce code promo a atteint sa limite maximale d'utilisation.");
        }

        if ($orderAmount < $this->min_order_amount) {
            $minFormatted = number_format($this->min_order_amount, 0, ',', ' ');
            throw new \Exception("Ce code promo exige un montant minimum de {$minFormatted} FCFA.");
        }

        $discount = 0;
        if ($this->discount_type === 'percent') {
            $discount = (int) round(($orderAmount * $this->discount_value) / 100);
        } else {
            $discount = (int) $this->discount_value;
        }

        if ($this->max_discount_amount && $discount > $this->max_discount_amount) {
            $discount = (int) $this->max_discount_amount;
        }

        return min($discount, $orderAmount);
    }
}
