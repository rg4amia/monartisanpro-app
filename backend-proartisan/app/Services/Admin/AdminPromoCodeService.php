<?php

namespace App\Services\Admin;

use App\Models\PromoCode;

class AdminPromoCodeService
{
    public function __construct(private AdminActivityLogger $audit) {}

    /**
     * @param  array<string, mixed>  $data  Données validées par StorePromoCodeRequest.
     */
    public function create(array $data): PromoCode
    {
        $promoCode = PromoCode::create($this->normalize($data, true));

        $this->audit->log('promo_code.created', $promoCode, [
            'discount_type' => $promoCode->discount_type,
            'discount_value' => $promoCode->discount_value,
        ]);

        return $promoCode;
    }

    /**
     * @param  array<string, mixed>  $data  Données validées par StorePromoCodeRequest.
     */
    public function update(PromoCode $promoCode, array $data): PromoCode
    {
        $promoCode->update($this->normalize($data, $promoCode->is_active));

        $this->audit->log('promo_code.updated', $promoCode, [
            'discount_type' => $promoCode->discount_type,
            'discount_value' => $promoCode->discount_value,
        ]);

        return $promoCode;
    }

    public function toggle(PromoCode $promoCode): bool
    {
        $promoCode->update(['is_active' => ! $promoCode->is_active]);

        $active = (bool) $promoCode->is_active;

        $this->audit->log('promo_code.toggled', $promoCode, [
            'is_active' => $active,
        ]);

        return $active;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalize(array $data, bool $activeDefault): array
    {
        $data['code'] = strtoupper(trim($data['code']));
        $data['min_order_amount'] = $data['min_order_amount'] ?? 0;
        $data['is_active'] = filter_var(
            $data['is_active'] ?? $activeDefault,
            FILTER_VALIDATE_BOOLEAN,
            FILTER_NULL_ON_FAILURE
        ) ?? $activeDefault;

        return $data;
    }
}
