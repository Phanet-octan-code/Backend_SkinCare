<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductReviewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $isVerifiedPurchase = $this->is_verified_purchase ?? null;
        if ($isVerifiedPurchase === null) {
            static $verifiedCache = [];
            $cacheKey = "{$this->customer_id}_{$this->product_id}";
            if (!array_key_exists($cacheKey, $verifiedCache)) {
                $verifiedCache[$cacheKey] = ($this->customer_id && $this->product_id)
                    ? \App\Models\SaleDetail::whereHas('sale', function ($q) {
                        $q->where('customer_id', $this->customer_id)
                          ->where('status', '!=', 'cancelled');
                    })->where('product_id', $this->product_id)->exists()
                    : false;
            }
            $isVerifiedPurchase = $verifiedCache[$cacheKey];
        }

        return [
            'id' => $this->id,
            'rating' => (int) $this->rating,
            'title' => $this->title,
            'comment' => $this->comment,
            'customer_name' => $this->customer ? $this->customer->name : 'Customer',
            'customer' => [
                'id' => $this->customer?->id,
                'name' => $this->customer ? $this->customer->name : 'Customer',
            ],
            'is_verified_purchase' => (bool) $isVerifiedPurchase,
            'created_at' => $this->created_at?->diffForHumans(),
        ];
    }
}
