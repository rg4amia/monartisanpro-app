<?php

namespace App\Http\Resources\User;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API Resource for User entity
 *
 * Transforms User domain entity into JSON response
 */
class UserResource extends JsonResource
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
   'phone_number' => $this->resource->getPhoneNumber()?->getValue(),
   'created_at' => $this->resource->getCreatedAt()->format('Y-m-d\TH:i:s\Z'),
  ];
 }
}
