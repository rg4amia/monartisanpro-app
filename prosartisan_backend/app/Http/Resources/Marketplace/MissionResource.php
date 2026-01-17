<?php

namespace App\Http\Resources\Marketplace;

use App\Domain\Marketplace\Models\Mission\Mission;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API Resource for Mission entities
 *
 * Transforms Mission domain objects into JSON responses
 */
class MissionResource extends JsonResource
{
 /**
  * Transform the resource into an array.
  */
 public function toArray(Request $request): array
 {
  /** @var Mission $mission */
  $mission = $this->resource;

  return [
   'id' => $mission->getId()->getValue(),
   'client_id' => $mission->getClientId()->getValue(),
   'description' => $mission->getDescription(),
   'category' => [
    'value' => $mission->getCategory()->getValue(),
    'label' => $mission->getCategory()->getFrenchLabel()
   ],
   'location' => [
    'latitude' => $mission->getLocation()->getLatitude(),
    'longitude' => $mission->getLocation()->getLongitude()
   ],
   'budget' => [
    'min' => [
     'centimes' => $mission->getBudgetMin()->toCentimes(),
     'francs' => $mission->getBudgetMin()->toFloat(),
     'formatted' => $mission->getBudgetMin()->format()
    ],
    'max' => [
     'centimes' => $mission->getBudgetMax()->toCentimes(),
     'francs' => $mission->getBudgetMax()->toFloat(),
     'formatted' => $mission->getBudgetMax()->format()
    ]
   ],
   'status' => [
    'value' => $mission->getStatus()->getValue(),
    'label' => $mission->getStatus()->getFrenchLabel()
   ],
   'quotes_count' => count($mission->getQuotes()),
   'can_receive_more_quotes' => $mission->canReceiveMoreQuotes(),
   'quotes' => DevisResource::collection($mission->getQuotes()),
   'accepted_quote' => $mission->getAcceptedQuote() ? new DevisResource($mission->getAcceptedQuote()) : null,
   'created_at' => $mission->getCreatedAt()->format('Y-m-d H:i:s'),
   'created_at_human' => $mission->getCreatedAt()->format('d/m/Y à H:i')
  ];
 }
}
