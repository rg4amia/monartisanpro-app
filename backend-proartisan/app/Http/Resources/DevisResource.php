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
            'lignesJson'   => $this->lignes_json ?? [],
            'jalonsJson'   => $this->jalons_json ?? [],
            'artisanName'  => $this->when(
                $this->relationLoaded('artisan'),
                fn () => $this->artisan->name ?? $this->artisan->phone
            ),
            'createdAt'    => $this->created_at?->toISOString(),
        ];
    }
}
