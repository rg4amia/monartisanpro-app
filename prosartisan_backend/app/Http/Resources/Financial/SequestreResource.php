<?php

namespace App\Http\Resources\Financial;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource for Sequestre (Escrow) API responses
 *
 * Requirements: 4.1, 4.2
 */
class SequestreResource extends JsonResource
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
   'mission_id' => $this->getMissionId()->getValue(),
   'client_id' => $this->getClientId()->getValue(),
   'artisan_id' => $this->getArtisanId()->getValue(),
   'total_amount' => [
    'centimes' => $this->getTotalAmount()->getAmountInCentimes(),
    'formatted' => $this->getTotalAmount()->format(),
   ],
   'materials_amount' => [
    'centimes' => $this->getMaterialsAmount()->getAmountInCentimes(),
    'formatted' => $this->getMaterialsAmount()->format(),
   ],
   'labor_amount' => [
    'centimes' => $this->getLaborAmount()->getAmountInCentimes(),
    'formatted' => $this->getLaborAmount()->format(),
   ],
   'materials_released' => [
    'centimes' => $this->getMaterialsReleased()->getAmountInCentimes(),
    'formatted' => $this->getMaterialsReleased()->format(),
   ],
   'labor_released' => [
    'centimes' => $this->getLaborReleased()->getAmountInCentimes(),
    'formatted' => $this->getLaborReleased()->format(),
   ],
   'remaining_materials' => [
    'centimes' => $this->getRemainingMaterials()->getAmountInCentimes(),
    'formatted' => $this->getRemainingMaterials()->format(),
   ],
   'remaining_labor' => [
    'centimes' => $this->getRemainingLabor()->getAmountInCentimes(),
    'formatted' => $this->getRemainingLabor()->format(),
   ],
   'status' => $this->getStatus()->getValue(),
   'created_at' => $this->getCreatedAt()->format('Y-m-d H:i:s'),
  ];
 }
}
