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
            'data'      => empty($this->data_json) ? new \stdClass() : $this->data_json,
            'read'      => $this->isRead(),
            'isRead'    => $this->isRead(),
            'readAt'    => $this->read_at?->toIso8601String(),
            'date'      => $this->created_at?->toIso8601String(),
            'createdAt' => $this->created_at?->toIso8601String(),
        ];
    }
}
