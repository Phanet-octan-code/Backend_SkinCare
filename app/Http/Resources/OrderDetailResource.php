<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $isBundle = $this->bundle_id !== null;
        $name = $isBundle 
            ? ($this->bundle ? $this->bundle->name : 'Curated Bundle/Set')
            : ($this->product ? $this->product->name : 'Skincare Item');
        $image = $isBundle
            ? ($this->bundle ? $this->bundle->image_url : null)
            : ($this->product ? $this->product->image_url : null);

        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'bundle_id' => $this->bundle_id,
            'is_bundle' => $isBundle,
            'product_name' => $name,
            'product_image' => $image,
            'qty' => (int) $this->qty,
            'price' => (float) $this->price,
            'discount' => (float) $this->discount,
            'subtotal' => (float) $this->subtotal,
        ];
    }
}
