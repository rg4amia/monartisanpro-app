<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupplierProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'supplierId' => $this->supplier_id,
            'name' => $this->name,
            'sku' => $this->sku,
            'description' => $this->description,
            'unitPrice' => $this->unit_price,
            'stockQuantity' => $this->stock_quantity,
            'imageUrl' => $this->image_url,
            'isActive' => $this->is_active,
            'supplier' => $this->when(
                $this->relationLoaded('supplier'),
                fn () => ['id' => $this->supplier->id, 'name' => $this->supplier->name, 'phone' => $this->supplier->phone]
            ),
            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),
        ];
    }
}
