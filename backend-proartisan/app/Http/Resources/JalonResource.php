<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class JalonResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'mission_id'      => $this->mission_id,
            'ordre'           => $this->ordre,
            'description'     => $this->description,
            'montant'         => $this->montant,
            'statut'          => $this->statut,
            'otp_code'        => null, // Jamais exposer le code OTP
            'otp_expires_at'  => $this->otp_expires_at?->toISOString(),
            'photos_json'     => $this->photos_json ?? [],
            'valide_at'       => $this->valide_at?->toISOString(),
            'paye_at'         => $this->paye_at?->toISOString(),
        ];
    }
}
