<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BundleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->localized_name,
            'name_en' => $this->name,
            'name_km' => $this->name_km,
            'slug' => $this->slug,
            'type' => $this->type,
            'description' => $this->localized_description,
            'description_en' => $this->description,
            'description_km' => $this->description_km,
            'image_url' => $this->image_url,
            'price' => (float) $this->price,
            'original_price' => (float) $this->calculated_original_price,
            'savings_amount' => (float) $this->savings_amount,
            'savings_percentage' => (int) $this->savings_percentage,
            'packaging_details' => $this->packaging_details,
            'available_qty' => (int) $this->available_qty,
            'in_stock' => (bool) $this->in_stock,
            'status' => $this->status,
            'items' => $this->items->map(function ($item) {
                return [
                    'id' => $item->id,
                    'product_id' => $item->product_id,
                    'product_name' => $item->product?->localized_name ?? $item->product?->name,
                    'product_name_en' => $item->product?->name,
                    'product_name_km' => $item->product?->name_km,
                    'product_image' => $item->product?->image_url,
                    'unit_price' => (float) ($item->product?->sale_price ?? 0.0),
                    'quantity' => (int) $item->quantity,
                    'is_free_gift' => (bool) $item->is_free_gift,
                ];
            }),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}

