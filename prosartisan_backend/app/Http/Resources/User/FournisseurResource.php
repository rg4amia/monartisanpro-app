<?php

namespace App\Http\Resources\User;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API Resource for Fournisseur entity
 *
 * Transforms Fournisseur domain entity into JSON response
 */
class FournisseurResource extends JsonResource
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
   'business_name' => $this->resource->getBusinessName(),
   'shop_location' => [
    'latitude' => $this->resource->getShopLocation()->getLatitude(),
    'longitude' => $this->resource->getShopLocation()->getLongitude(),
    'accuracy' => $this->resource->getShopLocation()->getAccuracy(),
   ],
   'is_kyc_verified' => $this->resource->isKYCVerified(),
   'created_at' => $this->resource->getCreatedAt()->format('Y-m-d\TH:i:s\Z'),
  ];
 }
}
