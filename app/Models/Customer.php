<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Customer extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'phone',
        'email',
        'password',
        'address',
        'membership_number',
        'membership_level',
        'status',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(CustomerAddress::class);
    }

    public function wishlists(): HasMany
    {
        return $this->hasMany(CustomerWishlist::class);
    }

    public function wishlistProducts(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'customer_wishlists')
            ->withTimestamps();
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(ProductReview::class);
    }

    public function cartItems(): HasMany
    {
        return $this->hasMany(CartItem::class);
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

