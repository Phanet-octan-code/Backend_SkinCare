<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PromotionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'discount_type' => $this->discount_type,
            'discount_value' => (float) $this->discount_value,
            'discount_formatted' => $this->discount_type === 'percentage'
                ? ((int) $this->discount_value . '% OFF')
                : ('$' . number_format($this->discount_value, 2) . ' OFF'),
            'is_storewide' => $this->isGeneral(),
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'applies_to_product_ids' => $this->products->pluck('id'),
        ];
    }
}
