<?php

namespace App\Services\Admin;

use App\Models\CampagneParrainage;

class AdminCampagneParrainageService
{
    public function __construct(private AdminActivityLogger $audit) {}

    /**
     * @param  array<string, mixed>  $data  Données validées par StoreCampagneParrainageRequest.
     */
    public function create(array $data): CampagneParrainage
    {
        $campagne = CampagneParrainage::create($this->normalize($data, true));

        $this->audit->log('campagne_parrainage.created', $campagne, [
            'discount_type' => $campagne->discount_type,
            'discount_value' => $campagne->discount_value,
        ]);

        return $campagne;
    }

    /**
     * @param  array<string, mixed>  $data  Données validées par StoreCampagneParrainageRequest.
     */
    public function update(CampagneParrainage $campagne, array $data): CampagneParrainage
    {
        $campagne->update($this->normalize($data, $campagne->is_active));

        $this->audit->log('campagne_parrainage.updated', $campagne, [
            'discount_type' => $campagne->discount_type,
            'discount_value' => $campagne->discount_value,
        ]);

        return $campagne;
    }

    public function toggle(CampagneParrainage $campagne): bool
    {
        $campagne->update(['is_active' => ! $campagne->is_active]);

        $active = (bool) $campagne->is_active;

        $this->audit->log('campagne_parrainage.toggled', $campagne, [
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
        $data['min_montant'] = $data['min_montant'] ?? 0;
        $data['is_active'] = filter_var(
            $data['is_active'] ?? $activeDefault,
            FILTER_VALIDATE_BOOLEAN,
            FILTER_NULL_ON_FAILURE
        ) ?? $activeDefault;

        return $data;
    }
}
