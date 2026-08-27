<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DevisResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'missionId'    => $this->mission_id,
            'artisanId'    => $this->artisan_id,
            'statut'       => $this->statut,
            'materialsRequired' => $this->materials_required,
            'interventionTypeId' => $this->intervention_type_id,
            'isAvenant'    => $this->is_avenant,
            'parentDevisId'=> $this->parent_devis_id,
            'missionStatus'=> $this->when(
                $this->relationLoaded('mission'),
                fn () => $this->mission->status
            ),
            'ratioMateriaux' => $this->ratio_materiaux !== null ? (float) $this->ratio_materiaux : null,
            'commissionServiceRatio' => $this->commission_service_ratio !== null ? (float) $this->commission_service_ratio : null,
            'montantTotal' => $this->montant_total,
            'montantMateriaux' => $this->montant_materiaux,
            'montantMo' => $this->montant_mo,
            'lignesJson'   => $this->lignes_json ?? [],
            'jalonsJson'   => $this->jalons_json ?? [],
            'artisanName'  => $this->when(
                $this->relationLoaded('artisan'),
                fn () => $this->artisan->name ?? $this->artisan->phone
            ),
            'createdAt'    => $this->created_at?->toIso8601String(),
        ];
    }
}
