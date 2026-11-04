<?php

namespace App\Http\Resources;

use App\Models\Promotion;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $today = Carbon::today()->toDateString();
        $price = (float) $this->sale_price;

        // Calculate best active discount
        $bestDiscountAmount = 0.00;
        $bestPromoName = null;
        $bestPromoType = null;
        $bestDiscountLabel = null;

        // Check product-specific and storewide promotions
        $activePromotions = $this->relationLoaded('promotions')
            ? $this->promotions->where('status', 'active')
            : $this->promotions()
                ->where('status', 'active')
                ->whereDate('start_date', '<=', $today)
                ->whereDate('end_date', '>=', $today)
                ->get();

        foreach ($activePromotions as $promo) {
            $val = (float) $promo->discount_value;
            $calc = $promo->discount_type === 'percentage'
                ? round($price * ($val / 100), 2)
                : min($price, $val);

            if ($calc > $bestDiscountAmount) {
                $bestDiscountAmount = $calc;
                $bestPromoName = $promo->name;
                $bestPromoType = $promo->discount_type;
                $bestDiscountLabel = $promo->discount_type === 'percentage'
                    ? ((int) $val . '% OFF')
                    : ('$' . number_format($val, 2) . ' OFF');
            }
        }

        $finalPrice = max(0.00, round($price - $bestDiscountAmount, 2));

        return [
            'id' => $this->id,
            'name' => $this->name,
            'barcode' => $this->barcode,
            'sale_price' => $price,
            'final_price' => $finalPrice,
            'has_discount' => $bestDiscountAmount > 0,
            'discount_amount' => $bestDiscountAmount,
            'discount_label' => $bestDiscountLabel,
            'promotion_name' => $bestPromoName,
            'qty' => (int) $this->qty,
            'in_stock' => $this->qty > 0,
            'is_low_stock' => $this->isLowStock(),
            'image_url' => $this->image_url,
            'description' => $this->description,
            'category' => new CategoryResource($this->whenLoaded('category')),
            'average_rating' => $this->average_rating,
            'reviews_count' => $this->reviews_count,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
