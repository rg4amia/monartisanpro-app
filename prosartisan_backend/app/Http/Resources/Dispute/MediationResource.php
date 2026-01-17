<?php

namespace App\Http\Resources\Dispute;

use App\Domain\Dispute\Models\Mediation\Mediation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource for mediation API responses
 */
class MediationResource extends JsonResource
{
 /**
  * Transform the resource into an array.
  *
  * @param Request $request
  * @return array<string, mixed>
  */
 public function toArray(Request $request): array
 {
  /** @var Mediation $mediation */
  $mediation = $this->resource;

  return [
   'mediator_id' => $mediation->getMediatorId()->getValue(),
   'is_active' => $mediation->isActive(),
   'communications_count' => $mediation->getCommunicationsCount(),
   'communications' => CommunicationResource::collection($mediation->getCommunications()),
   'started_at' => $mediation->getStartedAt()->format('Y-m-d H:i:s'),
   'ended_at' => $mediation->getEndedAt()?->format('Y-m-d H:i:s'),
  ];
 }
}
