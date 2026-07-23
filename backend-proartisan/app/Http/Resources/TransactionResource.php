<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'type'              => $this->type,
            'montant'           => $this->montant,
            'walletSource'      => $this->wallet_source,
            'walletDest'        => $this->wallet_dest,
            'provider'          => $this->provider,
            'statut'            => $this->statut,
            'referenceExterne'  => $this->reference_externe,
            'missionId'         => $this->mission_id,
            'createdAt'         => $this->created_at?->toIso8601String(),
        ];
    }
}
