<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $coords = $this->getPositionCoords();

        return [
            'id'               => $this->id,
            'phone'            => $this->phone,
            'name'             => $this->name,
            'role'             => $this->role,
            'kycStatus'        => $this->kyc_status,
            'accountStatus'    => $this->account_status ?? 'actif',
            'accountStatusReason' => $this->account_status_reason,
            'scoreNzassa'      => $this->score_nzassa,
            'walletMateriaux'  => $this->wallet_materiaux,
            'walletMo'         => $this->wallet_mo,
            'position'         => $coords,
            'artisanProfile'   => $this->when(
                $this->role === 'artisan' && $this->relationLoaded('artisanProfile'),
                fn () => $this->artisanProfile ? [
                    'sector'          => $this->artisanProfile->sector?->name,
                    'trade'           => $this->artisanProfile->trade?->name,
                    'bio'             => $this->artisanProfile->bio,
                    'experienceYears' => $this->artisanProfile->experience_years,
                    'photoUrl'        => $this->artisanProfile->photo_url,
                ] : null
            ),
            'createdAt'        => $this->created_at?->toISOString(),
        ];
    }
}
