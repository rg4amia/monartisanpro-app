<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupplierProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $imageUrl = $this->image_url;
        if ($imageUrl) {
            $imageUrl = trim($imageUrl);
            if (str_starts_with($imageUrl, 'http://localhost') || str_starts_with($imageUrl, 'http://127.0.0.1')) {
                $imageUrl = preg_replace('#^http://(localhost|127\.0\.0\.1)(:\d+)?#', 'https://prosartisan.net', $imageUrl);
            }
            if (str_starts_with($imageUrl, 'http://prosartisan.net')) {
                $imageUrl = str_replace('http://', 'https://', $imageUrl);
            }
            if (!str_starts_with($imageUrl, 'http://') && !str_starts_with($imageUrl, 'https://')) {
                $trimmed = ltrim($imageUrl, '/');
                $imageUrl = str_starts_with($trimmed, 'storage/')
                    ? 'https://prosartisan.net/' . $trimmed
                    : 'https://prosartisan.net/storage/' . $trimmed;
            }
        }

        return [
            'id' => $this->id,
            'supplierId' => $this->supplier_id,
            'supplier_id' => $this->supplier_id,
            'name' => $this->name,
            'sku' => $this->sku,
            'description' => $this->description,
            'unitPrice' => $this->unit_price,
            'unit_price' => $this->unit_price,
            'stockQuantity' => $this->stock_quantity,
            'stock_quantity' => $this->stock_quantity,
            'imageUrl' => $imageUrl,
            'image_url' => $imageUrl,
            'isActive' => (bool) $this->is_active,
            'is_active' => (bool) $this->is_active,
            'supplier' => $this->when(
                $this->relationLoaded('supplier'),
                fn () => ['id' => $this->supplier->id, 'name' => $this->supplier->name, 'phone' => $this->supplier->phone]
            ),
            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }
}
