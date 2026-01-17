<?php

namespace App\Http\Resources\Reputation;

use App\Domain\Reputation\Models\Rating\Rating;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RatingResource extends JsonResource
{
 /**
  * Transform the resource into an array.
  *
  * @return array<string, mixed>
  */
 public function toArray(Request $request): array
 {
  /** @var Rating $this */
  return [
   'id' => $this->getId()->getValue(),
   'mission_id' => $this->getMissionId()->getValue(),
   'client_id' => $this->getClientId()->getValue(),
   'artisan_id' => $this->getArtisanId()->getValue(),
   'rating' => $this->getRating()->getValue(),
   'comment' => $this->getComment(),
   'created_at' => $this->getCreatedAt()->format('Y-m-d H:i:s'),
   'updated_at' => $this->getUpdatedAt()->format('Y-m-d H:i:s'),
  ];
 }
}
