<?php

namespace App\Http\Resources\Financial;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource for Transaction API responses
 *
 * Requirements: 4.6, 13.6
 */
class TransactionResource extends JsonResource
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
   'from_user_id' => $this->getFromUserId()->getValue(),
   'to_user_id' => $this->getToUserId()->getValue(),
   'amount' => [
    'centimes' => $this->getAmount()->toCentimes(),
    'formatted' => $this->getAmount()->format(),
   ],
   'type' => $this->getType()->getValue(),
   'status' => $this->getStatus()->getValue(),
   'mobile_money_reference' => $this->getMobileMoneyReference(),
   'created_at' => $this->getCreatedAt()->format('Y-m-d H:i:s'),
   'completed_at' => $this->getCompletedAt()?->format('Y-m-d H:i:s'),
  ];
 }
}
