<?php

namespace App\Http\Resources\Dispute;

use App\Domain\Dispute\Models\Arbitrage\Arbitration;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource for arbitration API responses
 */
class ArbitrationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param Request $request
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Arbitration $arbitration */
        $arbitration = $this->resource;

        return [
            'arbitrator_id' => $arbitration->getArbitratorId()->getValue(),
            'decision' => [
                'type' => [
                    'value' => $arbitration->getDecision()->getType()->getValue(),
                    'label' => $arbitration->getDecision()->getType()->getFrenchLabel(),
                ],
                'amount' => $arbitration->getDecision()->getAmount()?->toArray(),
            ],
            'justification' => $arbitration->getJustification(),
            'rendered_at' => $arbitration->getRenderedAt()->format('Y-m-d H:i:s'),
        ];
    }
}
