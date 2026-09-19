<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AddressResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label,
            'recipientName' => $this->recipient_name,
            'recipientPhone' => $this->recipient_phone,
            'addressLine' => $this->address_line,
            'city' => $this->city,
            'region' => $this->region,
            'country' => $this->country,
            'isDefault' => (bool) $this->is_default,
            'location' => $this->coordinates,
            'createdAt' => $this->created_at?->toIso8601String(),
        ];
    }
}
