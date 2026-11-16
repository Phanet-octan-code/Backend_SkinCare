<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sale extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_SHIPPED = 'shipped';
    public const STATUS_DELIVERED = 'delivered';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_RETURNED = 'returned';
    public const STATUS_COMPLETED = 'completed';

    public const PAYMENT_PENDING = 'pending';
    public const PAYMENT_PAID = 'paid';
    public const PAYMENT_FAILED = 'failed';
    public const PAYMENT_REFUNDED = 'refunded';

    protected static function booted(): void
    {
        static::saved(function () {
            \App\Services\StorefrontCache::bust();
            try {
                \Illuminate\Support\Facades\Cache::increment('dashboard_metrics_version');
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Cache::put('dashboard_metrics_version', time(), 86400 * 30);
            }
        });

        static::deleted(function () {
            \App\Services\StorefrontCache::bust();
            try {
                \Illuminate\Support\Facades\Cache::increment('dashboard_metrics_version');
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Cache::put('dashboard_metrics_version', time(), 86400 * 30);
            }
        });
    }

    public static array $validTransitions = [
        self::STATUS_PENDING => [self::STATUS_CONFIRMED, self::STATUS_PROCESSING, self::STATUS_CANCELLED],
        self::STATUS_CONFIRMED => [self::STATUS_PROCESSING, self::STATUS_CANCELLED],
        self::STATUS_PROCESSING => [self::STATUS_SHIPPED, self::STATUS_CANCELLED],
        self::STATUS_SHIPPED => [self::STATUS_DELIVERED, self::STATUS_RETURNED],
        self::STATUS_DELIVERED => [self::STATUS_RETURNED, self::STATUS_COMPLETED],
        self::STATUS_COMPLETED => [self::STATUS_RETURNED],
        self::STATUS_CANCELLED => [],
        self::STATUS_RETURNED => [],
    ];

    protected $fillable = [
        'user_id',
        'customer_id',
        'subscription_id',
        'invoice_number',
        'subtotal',
        'discount',
        'tax',
        'shipping_fee',
        'shipping_zone_id',
        'shipping_zone_name',
        'final_total',
        'refunded_amount',
        'refund_reason',
        'refunded_at',
        'applied_referral_code',
        'loyalty_points_redeemed',
        'loyalty_points_discount',
        'loyalty_points_earned',
        'shipping_address',
        'tracking_number',
        'order_notes',
        'order_source',
        'payment_type',
        'payment_status',
        'status',
        'currency',
        'exchange_rate',
    ];

    public function canTransitionTo(string $targetStatus): bool
    {
        $allowed = static::$validTransitions[$this->status] ?? [];
        return in_array($targetStatus, $allowed, true);
    }

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'discount' => 'decimal:2',
            'tax' => 'decimal:2',
            'shipping_fee' => 'decimal:2',
            'final_total' => 'decimal:2',
            'refunded_amount' => 'decimal:2',
            'loyalty_points_discount' => 'decimal:2',
            'loyalty_points_redeemed' => 'integer',
            'loyalty_points_earned' => 'integer',
            'refunded_at' => 'datetime',
            'exchange_rate' => 'decimal:4',
        ];
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function details(): HasMany
    {
        return $this->hasMany(SaleDetail::class);
    }

    public function scopeCompleted($query)
    {
        return $this->scopeCompletedRevenue($query);
    }

    public function scopeCompletedRevenue($query)
    {
        return $query->where(function ($q) {
            $q->where('status', self::STATUS_COMPLETED)
              ->orWhere(function ($sub) {
                  $sub->whereIn('status', [
                      self::STATUS_CONFIRMED,
                      self::STATUS_PROCESSING,
                      self::STATUS_SHIPPED,
                      self::STATUS_DELIVERED,
                      self::STATUS_COMPLETED,
                  ])->where('payment_status', self::PAYMENT_PAID);
              });
        })->whereNotIn('status', [self::STATUS_CANCELLED, self::STATUS_RETURNED]);
    }

    public function scopeToday($query)
    {
        return $query->whereDate('created_at', Carbon::today());
    }

    public function shippingZone(): BelongsTo
    {
        return $this->belongsTo(ShippingZone::class);
    }

    /**
     * Convert and format a monetary amount using the sale's explicit recorded currency and historical exchange rate.
     * Guaranteed never to silently mutate or fluctuate if store live exchange rates or currencies are changed later.
     */
    public function formatAmount(float|int|string|null $amount): string
    {
        $amount = (float) ($amount ?? 0);
        $curr = strtoupper($this->currency ?: 'USD');

        if ($curr === 'KHR') {
            $rate = (float) ($this->exchange_rate ?: 4100.0);
            $khrAmount = round($amount * $rate);
            return number_format($khrAmount, 0) . ' ៛';
        }

        return '$' . number_format($amount, 2);
    }

    /**
     * Get the numeric amount converted to the order's historical currency.
     */
    public function getCurrencyAmount(float|int|string|null $amount): float
    {
        $amount = (float) ($amount ?? 0);
        $curr = strtoupper($this->currency ?: 'USD');

        if ($curr === 'KHR') {
            $rate = (float) ($this->exchange_rate ?: 4100.0);
            return (float) round($amount * $rate);
        }

        return round($amount, 2);
    }

    public function getCurrencyFinalTotalAttribute(): float
    {
        return $this->getCurrencyAmount($this->final_total);
    }

    public function getFormattedFinalTotalAttribute(): string
    {
        return $this->formatAmount($this->final_total);
    }

    public function getFormattedSubtotalAttribute(): string
    {
        return $this->formatAmount($this->subtotal);
    }

    public function getFormattedDiscountAttribute(): string
    {
        return $this->formatAmount($this->discount);
    }

    public function getFormattedTaxAttribute(): string
    {
        return $this->formatAmount($this->tax);
    }

    public static function generateInvoiceNumber(): string
    {
        $rawPrefix = Setting::get('invoice_prefix', 'INV-');
        $format = Setting::get('invoice_number_format', 'prefix_date_seq');
        
        $cleanPrefix = rtrim($rawPrefix, '-') . '-';
        $fullPrefix = $format === 'prefix_seq' 
            ? $cleanPrefix 
            : $cleanPrefix . date('Ymd') . '-';

        $latest = static::where('invoice_number', 'LIKE', $fullPrefix . '%')
            ->orderByDesc('id')
            ->value('invoice_number');

        if ($latest) {
            $sequence = (int) substr($latest, strlen($fullPrefix)) + 1;
        } else {
            $sequence = 1;
        }

        return $fullPrefix . str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }
}
