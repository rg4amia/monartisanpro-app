<?php

namespace App\Http\Resources\Dispute;

use App\Domain\Dispute\Models\Litige\Litige;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource for dispute API responses
 *
 * Transforms Litige domain objects into JSON responses
 */
class DisputeResource extends JsonResource
{
 /**
  * Transform the resource into an array.
  *
  * @param Request $request
  * @return array<string, mixed>
  */
 public function toArray(Request $request): array
 {
  /** @var Litige $litige */
  $litige = $this->resource;

  return [
   'id' => $litige->getId()->getValue(),
   'mission_id' => $litige->getMissionId()->getValue(),
   'reporter_id' => $litige->getReporterId()->getValue(),
   'defendant_id' => $litige->getDefendantId()->getValue(),
   'type' => [
    'value' => $litige->getType()->getValue(),
    'label' => $litige->getType()->getFrenchLabel(),
   ],
   'description' => $litige->getDescription(),
   'evidence' => $litige->getEvidence(),
   'status' => [
    'value' => $litige->getStatus()->getValue(),
    'label' => $litige->getStatus()->getFrenchLabel(),
   ],
   'mediation' => $this->when(
    $litige->getMediation(),
    fn() => new MediationResource($litige->getMediation())
   ),
   'arbitration' => $this->when(
    $litige->getArbitration(),
    fn() => new ArbitrationResource($litige->getArbitration())
   ),
   'resolution' => $this->when(
    $litige->getResolution(),
    fn() => new ResolutionResource($litige->getResolution())
   ),
   'created_at' => $litige->getCreatedAt()->format('Y-m-d H:i:s'),
   'resolved_at' => $litige->getResolvedAt()?->format('Y-m-d H:i:s'),
  ];
 }
}
