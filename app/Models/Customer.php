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
        'avatar',
        'password',
        'address',
        'membership_number',
        'membership_level',
        'loyalty_points',
        'lifetime_points',
        'referral_code',
        'referred_by_id',
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

    protected static function booted(): void
    {
        static::creating(function ($customer) {
            if (empty($customer->referral_code)) {
                $code = 'SKIN-' . strtoupper(substr(uniqid(), -6));
                while (static::where('referral_code', $code)->exists()) {
                    $code = 'SKIN-' . strtoupper(substr(uniqid(), -6));
                }
                $customer->referral_code = $code;
            }
        });
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

    public function loyaltyTransactions(): HasMany
    {
        return $this->hasMany(LoyaltyTransaction::class);
    }

    public function referralsGiven(): HasMany
    {
        return $this->hasMany(CustomerReferral::class, 'referrer_id');
    }

    public function referralReceived(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(CustomerReferral::class, 'referee_id');
    }

    public function referredBy(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Customer::class, 'referred_by_id');
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function getReferralCodeAttribute(?string $value): string
    {
        if (!empty($value)) {
            return $value;
        }

        $code = 'SKIN-' . strtoupper(substr(uniqid(), -6));
        $this->attributes['referral_code'] = $code;
        if ($this->exists) {
            $this->updateQuietly(['referral_code' => $code]);
        }
        return $code;
    }

    public function addLoyaltyPoints(int $points, string $type, ?int $saleId = null, ?string $description = null): void
    {
        if ($points <= 0) {
            return;
        }

        $this->increment('loyalty_points', $points);
        $this->increment('lifetime_points', $points);

        $this->loyaltyTransactions()->create([
            'sale_id' => $saleId,
            'points' => $points,
            'type' => $type,
            'description' => $description ?? "Earned {$points} loyalty points",
        ]);

        $this->syncLoyaltyTier();
    }

    public function deductLoyaltyPoints(int $points, string $type, ?int $saleId = null, ?string $description = null): void
    {
        if ($points <= 0) {
            return;
        }

        $deductible = min((int) $this->loyalty_points, $points);
        $this->decrement('loyalty_points', $deductible);

        $this->loyaltyTransactions()->create([
            'sale_id' => $saleId,
            'points' => -$deductible,
            'type' => $type,
            'description' => $description ?? "Redeemed {$deductible} loyalty points",
        ]);
    }

    public function syncLoyaltyTier(): void
    {
        $pts = (int) $this->lifetime_points;
        $newTier = match (true) {
            $pts >= 2500 => 'Platinum',
            $pts >= 1000 => 'Gold',
            $pts >= 300 => 'Silver',
            default => 'Bronze',
        };

        if ($this->membership_level !== $newTier) {
            $this->update(['membership_level' => $newTier]);
        }
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

    public ?float $sales_total_spent_cached = null;
    public ?int $sales_completed_count_cached = null;

    public function getTotalSpentAttribute(): float
    {
        if ($this->sales_total_spent_cached !== null) {
            return $this->sales_total_spent_cached;
        }
        if ($this->relationLoaded('sales')) {
            return (float) $this->sales->where('payment_status', 'paid')->sum('final_total');
        }
        $this->ensureSalesAggregatesLoaded();
        return $this->sales_total_spent_cached ?? 0.0;
    }

    public function getCompletedSalesCountAttribute(): int
    {
        if ($this->sales_completed_count_cached !== null) {
            return $this->sales_completed_count_cached;
        }
        if ($this->relationLoaded('sales')) {
            return $this->sales->where('payment_status', 'paid')->count();
        }
        $this->ensureSalesAggregatesLoaded();
        return $this->sales_completed_count_cached ?? 0;
    }

    protected function ensureSalesAggregatesLoaded(): void
    {
        if ($this->sales_total_spent_cached === null || $this->sales_completed_count_cached === null) {
            $agg = $this->sales()
                ->where('payment_status', 'paid')
                ->selectRaw('COALESCE(SUM(final_total), 0) as total_spent, COUNT(*) as completed_count')
                ->first();

            $this->sales_total_spent_cached = (float) ($agg?->total_spent ?? 0.0);
            $this->sales_completed_count_cached = (int) ($agg?->completed_count ?? 0);
        }
    }
}

