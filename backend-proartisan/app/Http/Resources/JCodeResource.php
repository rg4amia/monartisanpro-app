<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class JCodeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'tokenCode' => $this->code,
            'qrUrl' => $this->qr_url,
            'ussdCode' => $this->ussd_code,
            'montant' => $this->montant,
            'tokenAmount' => $this->montant,
            'montantConsomme' => $this->montant_consomme ?? 0,
            'montant_consomme' => $this->montant_consomme ?? 0,
            'montantRestant' => $this->montant_restant,
            'montant_restant' => $this->montant_restant,
            'statut' => $this->statut,
            'missionId' => $this->mission_id,
            'artisanId' => $this->artisan_id,
            'fournisseurId' => $this->fournisseur_id,
            'artisan' => $this->when(
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
            'expiresAt' => $this->expires_at?->toIso8601String(),
            'scannedAt' => $this->scanned_at?->toIso8601String(),
            'isActif' => $this->isActif(),
            'isMultiSupplier' => $this->isMultiSupplier(),
            'is_multi_supplier' => $this->isMultiSupplier(),
            'isPartiallyConsumed' => $this->isPartiallyConsumed(),
            'isFullyConsumed' => $this->isFullyConsumed(),
            'paymentStatus' => $this->paiement_status,
            'redemptions' => $this->when(
                $this->relationLoaded('redemptions'),
                fn () => JCodeRedemptionResource::collection($this->redemptions)
            ),
            'ttlHeures' => config('prosartisan.jcode.ttl_hours', 48),
        ];
    }
}
