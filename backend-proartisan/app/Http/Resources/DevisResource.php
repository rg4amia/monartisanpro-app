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
            'statut'       => $this->statut,
            'lignes'       => $this->lignes_json ?? [],
            'jalons'       => $this->jalons_json ?? [],
            'montantTotal' => $this->montant_total,
            'montantMat'   => $this->montant_materiaux,
            'montantMo'    => $this->montant_mo,
            'artisan'      => $this->when(
                $this->relationLoaded('artisan'),
                fn () => ['id' => $this->artisan->id, 'name' => $this->artisan->name]
            ),
            'createdAt'    => $this->created_at?->toISOString(),
        ];
    }
}
