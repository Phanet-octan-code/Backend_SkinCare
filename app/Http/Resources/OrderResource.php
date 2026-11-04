<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'invoice_number' => $this->invoice_number,
            'order_source' => $this->order_source ?? 'online',
            'subtotal' => (float) $this->subtotal,
            'discount' => (float) $this->discount,
            'tax' => (float) $this->tax,
            'shipping_fee' => (float) ($this->shipping_fee ?? 0.00),
            'final_total' => (float) $this->final_total,
            'currency' => $this->currency ?? 'USD',
            'formatted_final_total' => $this->formatted_final_total,
            'payment_type' => $this->payment_type,
            'payment_status' => $this->payment_status,
            'status' => $this->status,
            'shipping_address' => $this->shipping_address,
            'tracking_number' => $this->tracking_number,
            'order_notes' => $this->order_notes,
            'customer_name' => $this->customer ? $this->customer->name : 'Guest Customer',
            'items' => OrderDetailResource::collection($this->whenLoaded('details')),
            'created_at' => $this->created_at?->toIso8601String(),
            'formatted_date' => $this->created_at?->format('M d, Y h:i A'),
        ];
    }
}
