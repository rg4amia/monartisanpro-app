<?php

namespace App\Http\Resources\Reputation;

use App\Domain\Reputation\Models\ValueObjects\ScoreSnapshot;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ScoreHistoryResource extends JsonResource
{
 /**
  * Transform the resource into an array.
  *
  * @return array<string, mixed>
  */
 public function toArray(Request $request): array
 {
  /** @var ScoreSnapshot $this */
  return [
   'score' => $this->getScore()->getValue(),
   'reason' => $this->getReason(),
   'recorded_at' => $this->getRecordedAt()->format('Y-m-d H:i:s'),
  ];
 }
}
