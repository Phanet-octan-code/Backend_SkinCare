<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Promotion extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'discount_type',
        'discount_value',
        'is_flash_sale',
        'start_date',
        'end_date',
        'starts_at',
        'ends_at',
        'total_usage_limit',
        'current_usage_count',
        'min_spend',
        'badge_text',
        'status',
    ];

    protected static function booted(): void
    {
        static::saved(function () {
            \App\Services\StorefrontCache::bust();
        });

        static::deleted(function () {
            \App\Services\StorefrontCache::bust();
        });
    }

    protected function casts(): array
    {
        return [
            'discount_value' => 'decimal:2',
            'is_flash_sale' => 'boolean',
            'start_date' => 'date',
            'end_date' => 'date',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'total_usage_limit' => 'integer',
            'current_usage_count' => 'integer',
            'min_spend' => 'decimal:2',
        ];
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_promotion')
            ->withTimestamps();
    }

    public function scopeActive($query)
    {
        $today = Carbon::today()->toDateString();
        $now = Carbon::now();

        return $query->where('status', 'active')
            ->where(function ($q) use ($today, $now) {
                // If starts_at / ends_at are specified, enforce timestamp precision
                $q->where(function ($sub) use ($now) {
                    $sub->whereNotNull('starts_at')
                        ->where('starts_at', '<=', $now)
                        ->where(function ($subEnd) use ($now) {
                            $subEnd->whereNull('ends_at')->orWhere('ends_at', '>=', $now);
                        });
                })->orWhere(function ($sub) use ($today) {
                    // Fallback to legacy date range
                    $sub->whereNull('starts_at')
                        ->whereDate('start_date', '<=', $today)
                        ->whereDate('end_date', '>=', $today);
                });
            })
            ->where(function ($q) {
                // If total_usage_limit is set, ensure quota remains
                $q->whereNull('total_usage_limit')
                    ->orWhereColumn('current_usage_count', '<', 'total_usage_limit');
            });
    }

    public function scopeFlashSale($query)
    {
        return $query->where('is_flash_sale', true);
    }

    public function scopeGeneral($query)
    {
        return $query->whereDoesntHave('products');
    }

    public function isGeneral(): bool
    {
        return $this->products()->count() === 0;
    }

    public function appliesToProduct(int $productId): bool
    {
        if ($this->isGeneral()) {
            return true;
        }

        return $this->products()->where('products.id', $productId)->exists();
    }

    public function isActive(): bool
    {
        $today = Carbon::today()->toDateString();

        return $this->status === 'active'
            && ($this->start_date === null || $this->start_date->toDateString() <= $today)
            && ($this->end_date === null || $this->end_date->toDateString() >= $today);
    }
}
