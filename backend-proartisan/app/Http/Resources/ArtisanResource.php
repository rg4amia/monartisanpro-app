<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource artisan avec :
 * - position GPS FLOUTÉE (~50 m) — règle critique
 * - distance formatée
 * - rating moyen calculé depuis evaluations
 */
class ArtisanResource extends JsonResource
{
    private ?float $distanceMetres;
    private ?array $blurredPosition;

    public function __construct($resource, ?float $distanceMetres = null, ?array $blurredPosition = null)
    {
        parent::__construct($resource);
        $this->distanceMetres  = $distanceMetres;
        $this->blurredPosition = $blurredPosition;
    }

    public function toArray(Request $request): array
    {
        $profile          = $this->artisanProfile;
        $missionsCount    = $this->missionsArtisan()->where('status', 'completed')->count();
        $avgRating        = $this->evaluationsRecues()->avg('note') ?? 0;

        return [
            'id'                 => $this->id,
            'role'               => 'artisan',
            'name'               => $this->name,
            'phone'              => $this->phone,
            'telephone'          => $this->phone,
            'trade'              => $profile?->trade?->name,
            'category'           => $profile?->trade?->name,
            'sector'             => $profile?->sector?->name,
            'photo'              => $profile?->photo_url,
            'bio'                => $profile?->bio,
            'experienceYears'    => $profile?->experience_years ?? 0,
            'nightInterventionAvailable' => (bool) ($profile?->intervient_la_nuit ?? false),
            'scoreNzassa'        => $this->score_nzassa,
            'rating'             => round((float) $avgRating, 1),
            'completedMissions'  => $missionsCount,
            // POSITION FLOUTÉE — jamais la position exacte
            'location'           => $this->blurredPosition,
            'distance'           => $this->distanceMetres !== null
                ? $this->formatDistance($this->distanceMetres)
                : null,
            'distanceMetres'     => $this->distanceMetres,
            'isGoldenMarker'     => $this->score_nzassa >= 70,
            'kycStatus'          => $this->kyc_status,
            'commune'            => $this->commune?->name,
            'locationLabel'      => $this->commune?->name ?? $profile?->sector?->name,
            'price'              => 'Sur devis',
        ];
    }

    private function formatDistance(float $metres): string
    {
        if ($metres < 1000) {
            return round($metres) . ' m';
        }

        return round($metres / 1000, 1) . ' km';
    }
}
