<?php

namespace App\Http\Resources\Dispute;

use App\Domain\Dispute\Models\Mediation\MediationCommunication;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource for mediation communication API responses
 */
class CommunicationResource extends JsonResource
{
 /**
  * Transform the resource into an array.
  *
  * @param Request $request
  * @return array<string, mixed>
  */
 public function toArray(Request $request): array
 {
  /** @var MediationCommunication $communication */
  $communication = $this->resource;

  return [
   'message' => $communication->getMessage(),
   'sender_id' => $communication->getSenderId()->getValue(),
   'sent_at' => $communication->getSentAt()->format('Y-m-d H:i:s'),
  ];
 }
}
