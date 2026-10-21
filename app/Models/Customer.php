<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'phone',
        'email',
        'address',
        'membership_number',
        'membership_level',
        'status',
    ];

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function getMembershipDiscountPercentageAttribute(): float
    {
        return match (strtolower($this->membership_level ?? '')) {
            'platinum' => 15.0,
            'gold' => 10.0,
            'silver' => 5.0,
            default => 0.0,
        };
    }

    public function getTotalSpentAttribute(): float
    {
        return (float) $this->sales()->where('payment_status', 'paid')->sum('final_total');
    }

    public function getCompletedSalesCountAttribute(): int
    {
        return $this->sales()->where('payment_status', 'paid')->count();
    }
}
