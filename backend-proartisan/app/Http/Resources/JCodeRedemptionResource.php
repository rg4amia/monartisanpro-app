<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class JCodeRedemptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'jcodeId' => $this->jcode_id,
            'fournisseurId' => $this->fournisseur_id,
            'fournisseur' => $this->when(
                $this->relationLoaded('fournisseur'),
                fn () => [
                    'id' => $this->fournisseur->id,
                    'name' => $this->fournisseur->name,
                    'phone' => $this->fournisseur->phone,
                    'nomBoutique' => $this->fournisseur->fournisseurAgree?->nom_boutique,
                ]
            ),
            'montant' => $this->montant,
            'recuPhotoUrl' => $this->recu_photo_url,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'items' => $this->items_json ?? [],
            'scannedAt' => $this->scanned_at?->toIso8601String() ?? $this->created_at?->toIso8601String(),
            'createdAt' => $this->created_at?->toIso8601String(),
        ];
    }
}
