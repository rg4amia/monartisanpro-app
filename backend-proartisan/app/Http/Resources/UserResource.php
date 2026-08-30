<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $coords = $this->getPositionCoords();
        $artisanProfile = $this->artisanProfile;

        return [
            'id'               => $this->id,
            'phone'            => $this->phone,
            'name'             => $this->name,
            'role'             => $this->role,
            'kycStatus'        => $this->kyc_status,
            'accountStatus'    => $this->account_status ?? 'actif',
            'accountStatusReason' => $this->account_status_reason,
            'scoreProsArtisan' => $this->score_prosartisan,
            'walletMateriaux'  => $this->wallet_materiaux,
            'walletMo'         => $this->wallet_mo,
            'cguAcceptedAt'    => $this->cgu_accepted_at?->toIso8601String(),
            'position'         => $coords,
            'paymentPhone'     => $this->payment_phone,
            'payment_phone'    => $this->payment_phone,
            'preferredPaymentProvider'  => $this->preferred_payment_provider,
            'preferred_payment_provider' => $this->preferred_payment_provider,
            'cnmciNumber'      => $this->cnmci_number,
            'cnmciCardUrl'     => $this->cnmci_card_url,
            'cnmciStatus'      => $this->cnmci_status,
            'nightInterventionAvailable' => (bool) ($artisanProfile?->intervient_la_nuit ?? false),
            'artisanProfile'   => $this->when(
                $this->role === 'artisan' && $this->relationLoaded('artisanProfile'),
                fn () => $artisanProfile ? [
                    'sector' => $artisanProfile->sector?->name,
                    'sectorId' => $artisanProfile->sector_id,
                    'trade' => $artisanProfile->trade?->name,
                    'tradeId' => $artisanProfile->trade_id,
                    'bio' => $artisanProfile->bio,
                    'experienceYears' => $artisanProfile->experience_years,
                    'photoUrl' => $artisanProfile->photo_url,
                    'nightInterventionAvailable' => (bool) $artisanProfile->intervient_la_nuit,
                ] : null
            ),
            'createdAt'        => $this->created_at?->toIso8601String(),
        ];
    }
}
