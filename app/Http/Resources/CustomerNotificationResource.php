<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerNotificationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        $data = $this->data ?? [];
        return [
            'id' => (string) $this->id,
            'type' => $this->type,
            'title' => $data['title'] ?? 'Dispensary Notification',
            'message' => $data['message'] ?? ($data['body'] ?? ''),
            'data' => $data,
            'is_read' => $this->read_at !== null,
            'read_at' => $this->read_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'formatted_date' => $this->created_at?->diffForHumans(),
        ];
    }
}

