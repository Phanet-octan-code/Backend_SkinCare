<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->localized_name,
            'name_en' => $this->name,
            'name_km' => $this->name_km,
            'raw_name' => $this->name,
            'description' => $this->localized_description,
            'description_en' => $this->description,
            'description_km' => $this->description_km,
            'image' => $this->image,
            'image_url' => $this->image_url,
            'products_count' => $this->whenCounted('products'),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
