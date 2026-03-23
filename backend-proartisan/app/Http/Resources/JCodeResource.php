<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class JCodeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'code'       => $this->code,
            'tokenCode'  => $this->code,
            'qrUrl'      => $this->qr_url,
            'ussdCode'   => $this->ussd_code,
            'montant'    => $this->montant,
            'tokenAmount'=> $this->montant,
            'statut'     => $this->statut,
            'missionId'  => $this->mission_id,
            'artisanId'  => $this->artisan_id,
            'fournisseurId' => $this->fournisseur_id,
            'artisan'    => $this->when(
                $this->relationLoaded('artisan'),
                fn () => ['id' => $this->artisan->id, 'name' => $this->artisan->name]
            ),
            'fournisseur' => $this->when(
                $this->relationLoaded('fournisseur'),
                fn () => new FournisseurResource($this->fournisseur)
            ),
            'items' => $this->when(
                $this->relationLoaded('items'),
                fn () => JCodeItemResource::collection($this->items)
            ),
            'expiresAt'  => $this->expires_at?->toISOString(),
            'scannedAt'  => $this->scanned_at?->toISOString(),
            'isActif'    => $this->isActif(),
            'paymentStatus' => $this->paiement_status,
            'ttlHeures'  => config('prosartisan.jcode.ttl_hours', 48),
        ];
    }
}
