<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subscription extends Model
{
    use HasFactory;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_PAUSED = 'paused';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_EXPIRED = 'expired';

    protected $fillable = [
        'customer_id',
        'product_id',
        'bundle_id',
        'status',
        'billing_interval_days',
        'discount_percentage',
        'unit_price',
        'quantity',
        'next_billing_date',
        'last_billing_date',
        'last_sale_id',
        'payment_method',
        'stripe_subscription_id',
        'shipping_address',
        'paused_at',
        'cancelled_at',
        'cancellation_reason',
    ];

    protected function casts(): array
    {
        return [
            'billing_interval_days' => 'integer',
            'discount_percentage' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'quantity' => 'integer',
            'next_billing_date' => 'date',
            'last_billing_date' => 'date',
            'paused_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function bundle(): BelongsTo
    {
        return $this->belongsTo(Bundle::class)->withTrashed();
    }

    public function lastSale(): BelongsTo
    {
        return $this->belongsTo(Sale::class, 'last_sale_id');
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeDueForRenewal($query)
    {
        $today = Carbon::today()->toDateString();
        return $query->where('status', self::STATUS_ACTIVE)
            ->whereDate('next_billing_date', '<=', $today);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isPaused(): bool
    {
        return $this->status === self::STATUS_PAUSED;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function pause(): void
    {
        $this->update([
            'status' => self::STATUS_PAUSED,
            'paused_at' => now(),
        ]);
    }

    public function resume(): void
    {
        $this->update([
            'status' => self::STATUS_ACTIVE,
            'paused_at' => null,
            // If next_billing_date has already passed while paused, push to tomorrow
            'next_billing_date' => $this->next_billing_date->isPast()
                ? Carbon::today()->addDay()
                : $this->next_billing_date,
        ]);
    }

    public function cancel(?string $reason = null): void
    {
        $this->update([
            'status' => self::STATUS_CANCELLED,
            'cancelled_at' => now(),
            'cancellation_reason' => $reason ?? 'Cancelled by customer',
        ]);
    }

    public function getItemNameAttribute(): string
    {
        if ($this->bundle) {
            return $this->bundle->name . ' (Gift Set/Bundle)';
        }
        return $this->product?->name ?? 'Skincare Replenishment';
    }

    public function getItemImageUrlAttribute(): ?string
    {
        return $this->bundle?->image_url ?? $this->product?->image_url;
    }

    public function getRecurringSubtotalAttribute(): float
    {
        $raw = (float) $this->unit_price * $this->quantity;
        $disc = $raw * ((float) $this->discount_percentage / 100);
        return round($raw - $disc, 2);
    }
}

