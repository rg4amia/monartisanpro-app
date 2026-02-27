<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class JalonResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'ordre'       => $this->ordre,
            'description' => $this->description,
            'montant'     => $this->montant,
            'statut'      => $this->statut,
            'photos'      => $this->photos_json ?? [],
            'otpExpires'  => $this->otp_expires_at?->toISOString(),
            'valideAt'    => $this->valide_at?->toISOString(),
            'payeAt'      => $this->paye_at?->toISOString(),
        ];
    }
}
