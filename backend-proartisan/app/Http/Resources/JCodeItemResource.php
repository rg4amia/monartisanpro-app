<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class JCodeItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'supplierProductId' => $this->supplier_product_id,
            'source' => $this->source,
            'name' => $this->item_name,
            'sku' => $this->item_sku,
            'quantity' => $this->quantity,
            'quantityServed' => $this->quantity_served ?? 0,
            'remainingQuantity' => $this->remaining_quantity,
            'unitPrice' => $this->unit_price,
            'subtotal' => $this->subtotal,
            'status' => $this->status,
            'servedBySupplier' => $this->when(
                $this->served_by_supplier_id !== null,
                fn () => [
                    'id'   => $this->served_by_supplier_id,
                    'name' => $this->servedBySupplier?->name,
                ]
            ),
        ];
    }
}
