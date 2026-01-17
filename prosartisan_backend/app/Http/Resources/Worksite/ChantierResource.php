<?php

namespace App\Http\Resources\Worksite;

use App\Domain\Worksite\Models\Chantier\Chantier;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API Resource for Chantier (Worksite)
 *
 * Transforms Chantier domain entity to JSON response
 * Requirements: 6.1, 6.2
 */
class ChantierResource extends JsonResource
{
 /**
  * Transform the resource into an array.
  */
 public function toArray(Request $request): array
 {
  /** @var Chantier $chantier */
  $chantier = $this->resource;

  return [
   'id' => $chantier->getId()->getValue(),
   'mission_id' => $chantier->getMissionId()->getValue(),
   'client_id' => $chantier->getClientId()->getValue(),
   'artisan_id' => $chantier->getArtisanId()->getValue(),
   'status' => $chantier->getStatus()->getValue(),
   'status_label' => $chantier->getStatus()->getFrenchLabel(),
   'started_at' => $chantier->getStartedAt()->format('Y-m-d H:i:s'),
   'completed_at' => $chantier->getCompletedAt()?->format('Y-m-d H:i:s'),
   'progress_percentage' => round($chantier->getProgressPercentage(), 2),
   'can_be_completed' => $chantier->canBeCompleted(),

   // Milestone counts
   'milestones_count' => count($chantier->getAllMilestones()),
   'completed_milestones_count' => count($chantier->getCompletedMilestones()),
   'pending_milestones_count' => count($chantier->getPendingMilestones()),

   // Financial information
   'total_labor_amount' => [
    'centimes' => $chantier->getTotalLaborAmount()->toCentimes(),
    'formatted' => $chantier->getTotalLaborAmount()->format(),
    'currency' => 'XOF'
   ],
   'completed_labor_amount' => [
    'centimes' => $chantier->getCompletedLaborAmount()->toCentimes(),
    'formatted' => $chantier->getCompletedLaborAmount()->format(),
    'currency' => 'XOF'
   ],

   // Next milestone
   'next_milestone' => $chantier->getNextMilestone() ?
    new JalonResource($chantier->getNextMilestone()) : null,

   // All milestones
   'milestones' => JalonResource::collection($chantier->getAllMilestones()),

   // Timestamps
   'created_at' => $chantier->getStartedAt()->format('Y-m-d H:i:s'),
   'updated_at' => $chantier->getCompletedAt()?->format('Y-m-d H:i:s') ??
    $chantier->getStartedAt()->format('Y-m-d H:i:s'),
  ];
 }
}
