<?php

namespace App\Http\Resources\Dispute;

use App\Domain\Dispute\Models\ValueObjects\Resolution;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource for resolution API responses
 */
class ResolutionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Resolution $resolution */
        $resolution = $this->resource;

        return [
            'outcome' => $resolution->getOutcome(),
            'amount' => $resolution->getAmount()?->toArray(),
            'notes' => $resolution->getNotes(),
            'resolved_at' => $resolution->getResolvedAt()->format('Y-m-d H:i:s'),
        ];
    }
}
