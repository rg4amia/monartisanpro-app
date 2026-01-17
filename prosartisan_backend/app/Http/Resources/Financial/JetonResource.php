<?php

namespace App\Http\Resources\Financial;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource for JetonMateriel API responses
 *
 * Requirements: 5.1, 5.2
 */
class JetonResource extends JsonResource
{
 /**
  * Transform the resource into an array.
  *
  * @return array<string, mixed>
  */
 public function toArray(Request $request): array
 {
  return [
   'id' => $this->getId()->getValue(),
   'code' => $this->getCode()->toString(),
   'sequestre_id' => $this->getSequestreId()->getValue(),
   'artisan_id' => $this->getArtisanId()->getValue(),
   'total_amount' => [
    'centimes' => $this->getTotalAmount()->toCentimes(),
    'formatted' => $this->getTotalAmount()->format(),
   ],
   'used_amount' => [
    'centimes' => $this->getUsedAmount()->toCentimes(),
    'formatted' => $this->getUsedAmount()->format(),
   ],
   'remaining_amount' => [
    'centimes' => $this->getRemainingAmount()->toCentimes(),
    'formatted' => $this->getRemainingAmount()->format(),
   ],
   'authorized_suppliers' => $this->getAuthorizedSuppliers(),
   'status' => $this->getStatus()->getValue(),
   'created_at' => $this->getCreatedAt()->format('Y-m-d H:i:s'),
   'expires_at' => $this->getExpiresAt()->format('Y-m-d H:i:s'),
   'is_expired' => $this->isExpired(),
   'qr_code_data' => [
    'code' => $this->getCode()->toString(),
    'amount' => $this->getRemainingAmount()->toCentimes(),
    'expires_at' => $this->getExpiresAt()->format('Y-m-d H:i:s'),
   ],
  ];
 }
}
