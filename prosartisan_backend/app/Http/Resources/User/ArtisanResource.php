<?php

namespace App\Http\Resources\User;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API Resource for Artisan entity
 *
 * Transforms Artisan domain entity into JSON response
 */
class ArtisanResource extends JsonResource
{
 /**
  * Transform the resource into an array.
  *
  * @return array<string, mixed>
  */
 public function toArray(Request $request): array
 {
  return [
   'id' => $this->resource->getId()->getValue(),
   'email' => $this->resource->getEmail()->getValue(),
   'user_type' => $this->resource->getType()->getValue(),
   'account_status' => $this->resource->getStatus()->getValue(),
   'phone_number' => $this->resource->getPhoneNumber()->getValue(),
   'trade_category' => $this->resource->getTradeCategory()->getValue(),
   'location' => [
    'latitude' => $this->resource->getLocation()->getLatitude(),
    'longitude' => $this->resource->getLocation()->getLongitude(),
    'accuracy' => $this->resource->getLocation()->getAccuracy(),
   ],
   'is_kyc_verified' => $this->resource->isKYCVerified(),
   'can_accept_missions' => $this->resource->canAcceptMissions(),
   'created_at' => $this->resource->getCreatedAt()->format('Y-m-d\TH:i:s\Z'),
  ];
 }
}
