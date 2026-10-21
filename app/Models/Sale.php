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

    protected $fillable = [
        'user_id',
        'customer_id',
        'invoice_number',
        'subtotal',
        'discount',
        'tax',
        'final_total',
        'payment_type',
        'payment_status',
        'status',
        'currency',
        'exchange_rate',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'discount' => 'decimal:2',
            'tax' => 'decimal:2',
            'final_total' => 'decimal:2',
            'exchange_rate' => 'decimal:4',
        ];
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
        return $query->where('status', 'completed');
    }

    public function scopeToday($query)
    {
        return $query->whereDate('created_at', Carbon::today());
    }

    /**
     * Format a monetary amount using the sale's explicit recorded currency.
     * Prevents historical amounts from changing if store currency setting is modified later.
     */
    public function formatAmount(float|int|string|null $amount): string
    {
        return Setting::formatMoney($amount, $this->currency ?? 'USD');
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
