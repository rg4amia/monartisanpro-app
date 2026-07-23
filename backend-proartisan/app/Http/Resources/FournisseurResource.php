<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FournisseurResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $agree = $this->relationLoaded('fournisseurAgree') ? $this->fournisseurAgree : null;
        $coords = $agree?->getPositionCoords();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'phone' => $this->phone,
            'shopName' => $agree?->nom_boutique ?? $this->name,
            'status' => $agree?->statut,
            'location' => $coords,
            'activeProductsCount' => $this->whenCounted('supplierProducts'),
            'createdAt' => $this->created_at?->toIso8601String(),
        ];
    }
}
