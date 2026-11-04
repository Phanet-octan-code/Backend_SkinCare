<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'product_name' => $this->product ? $this->product->name : 'Skincare Item',
            'product_image' => $this->product ? $this->product->image_url : null,
            'qty' => (int) $this->qty,
            'price' => (float) $this->price,
            'discount' => (float) $this->discount,
            'subtotal' => (float) $this->subtotal,
        ];
    }
}
