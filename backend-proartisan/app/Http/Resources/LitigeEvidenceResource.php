<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LitigeEvidenceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'partie' => $this->partie,
            'description' => $this->description,
            'mediaUrl' => $this->media_url,
            'coordinates' => $this->latitude !== null && $this->longitude !== null ? [
                'lat' => $this->latitude,
                'lng' => $this->longitude,
            ] : null,
            'takenAt' => $this->taken_at?->toISOString(),
            'createdAt' => $this->created_at?->toISOString(),
            'user' => $this->whenLoaded('user', fn () => [
                'id' => $this->user?->id,
                'name' => $this->user?->name,
                'role' => $this->user?->role,
            ]),
        ];
    }
}
