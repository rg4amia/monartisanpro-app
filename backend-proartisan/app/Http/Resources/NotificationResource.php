<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'        => $this->id,
            'type'      => $this->type,
            'title'     => $this->title,
            'message'   => $this->body,
            'data'      => $this->data_json ?? [],
            'read'      => $this->isRead(),
            'readAt'    => $this->read_at?->toISOString(),
            'date'      => $this->created_at?->toISOString(),
        ];
    }
}
