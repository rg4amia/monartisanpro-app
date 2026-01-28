<?php

namespace App\Http\Resources\Marketplace;

use App\Domain\Marketplace\Models\Devis\Devis;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API Resource for Devis entities
 *
 * Transforms Devis domain objects into JSON responses
 */
class DevisResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        /** @var Devis $devis */
        $devis = $this->resource;

        return [
            'id' => $devis->getId()->getValue(),
            'mission_id' => $devis->getMissionId()->getValue(),
            'artisan_id' => $devis->getArtisanId()->getValue(),
            'amounts' => [
                'total' => [
                    'centimes' => $devis->getTotalAmount()->toCentimes(),
                    'francs' => $devis->getTotalAmount()->toFloat(),
                    'formatted' => $devis->getTotalAmount()->format(),
                ],
                'materials' => [
                    'centimes' => $devis->getMaterialsAmount()->toCentimes(),
                    'francs' => $devis->getMaterialsAmount()->toFloat(),
                    'formatted' => $devis->getMaterialsAmount()->format(),
                ],
                'labor' => [
                    'centimes' => $devis->getLaborAmount()->toCentimes(),
                    'francs' => $devis->getLaborAmount()->toFloat(),
                    'formatted' => $devis->getLaborAmount()->format(),
                ],
            ],
            'line_items' => array_map(function ($lineItem) {
                return [
                    'description' => $lineItem->getDescription(),
                    'quantity' => $lineItem->getQuantity(),
                    'unit_price' => [
                        'centimes' => $lineItem->getUnitPrice()->toCentimes(),
                        'francs' => $lineItem->getUnitPrice()->toFloat(),
                        'formatted' => $lineItem->getUnitPrice()->format(),
                    ],
                    'total' => [
                        'centimes' => $lineItem->getTotal()->toCentimes(),
                        'francs' => $lineItem->getTotal()->toFloat(),
                        'formatted' => $lineItem->getTotal()->format(),
                    ],
                    'type' => [
                        'value' => $lineItem->getType()->getValue(),
                        'label' => $lineItem->getType()->getFrenchLabel(),
                    ],
                ];
            }, $devis->getLineItems()),
            'status' => [
                'value' => $devis->getStatus()->getValue(),
                'label' => $devis->getStatus()->getFrenchLabel(),
            ],
            'is_expired' => $devis->isExpired(),
            'expires_at' => $devis->getExpiresAt()?->format('Y-m-d H:i:s'),
            'expires_at_human' => $devis->getExpiresAt()?->format('d/m/Y à H:i'),
            'created_at' => $devis->getCreatedAt()->format('Y-m-d H:i:s'),
            'created_at_human' => $devis->getCreatedAt()->format('d/m/Y à H:i'),
        ];
    }
}
