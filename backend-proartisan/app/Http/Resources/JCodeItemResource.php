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
            'unitPrice' => $this->unit_price,
            'subtotal' => $this->subtotal,
            'status' => $this->status,
        ];
    }
}
