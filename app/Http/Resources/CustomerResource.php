<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'avatar' => $this->avatar ? (str_starts_with($this->avatar, 'http') || str_starts_with($this->avatar, 'data:') ? $this->avatar : asset('storage/' . $this->avatar)) : null,
            'phone' => $this->phone,
            'address' => $this->address,
            'membership_number' => $this->membership_number,
            'membership_level' => $this->membership_level ?? 'Bronze',
            'discount_percentage' => (float) $this->membership_discount_percentage,
            'total_spent' => (float) $this->total_spent,
            'completed_orders_count' => (int) $this->completed_sales_count,
            'default_address' => new CustomerAddressResource($this->addresses()->where('is_default', true)->first()),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
