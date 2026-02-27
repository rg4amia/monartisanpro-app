<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MissionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                  => $this->id,
            'description'         => $this->description,
            'photos'              => $this->photos_json ?? [],
            'status'              => $this->status,
            'geminiCategory'      => $this->gemini_category,
            'geminiUrgency'       => $this->gemini_urgency,
            'geminiEstimation'    => $this->gemini_estimation_min && $this->gemini_estimation_max ? [
                'min' => $this->gemini_estimation_min,
                'max' => $this->gemini_estimation_max,
            ] : null,
            'montantTotal'        => $this->montant_total,
            'montantMateriaux'    => $this->montant_materiaux,
            'montantMo'           => $this->montant_mo,
            'ratioMateriaux'      => $this->ratio_materiaux,
            'referentRequired'    => $this->referent_required,
            'client'              => $this->when(
                $this->relationLoaded('client'),
                fn () => ['id' => $this->client->id, 'name' => $this->client->name, 'phone' => $this->client->phone]
            ),
            'artisan'             => $this->when(
                $this->relationLoaded('artisan') && $this->artisan,
                fn () => ['id' => $this->artisan->id, 'name' => $this->artisan->name]
            ),
            'jalons'              => $this->when(
                $this->relationLoaded('jalons'),
                fn () => JalonResource::collection($this->jalons)
            ),
            'wallets' => [
                'materiaux' => $this->montant_materiaux,
                'mo'        => $this->montant_mo,
            ],
            'createdAt'           => $this->created_at?->toISOString(),
            'updatedAt'           => $this->updated_at?->toISOString(),
        ];
    }
}
