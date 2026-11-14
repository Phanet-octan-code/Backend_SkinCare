<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Bundle extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'name_km',
        'slug',
        'type', // 'bundle', 'gift_set'
        'description',
        'description_km',
        'image',
        'price',
        'original_price',
        'packaging_details',
        'status', // 'active', 'inactive'
    ];

    public function getLocalizedNameAttribute(): string
    {
        $locale = \Illuminate\Support\Facades\App::getLocale();
        return ($locale === 'km' && !empty($this->name_km)) ? $this->name_km : $this->name;
    }

    public function getLocalizedDescriptionAttribute(): ?string
    {
        $locale = \Illuminate\Support\Facades\App::getLocale();
        return ($locale === 'km' && !empty($this->description_km)) ? $this->description_km : $this->description;
    }

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'original_price' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function ($bundle) {
            if (empty($bundle->slug)) {
                $bundle->slug = Str::slug($bundle->name) . '-' . Str::random(5);
            }
        });

        static::saved(function () {
            \App\Services\StorefrontCache::bust();
        });

        static::deleted(function () {
            \App\Services\StorefrontCache::bust();
        });
    }

    public function items(): HasMany
    {
        return $this->hasMany(BundleItem::class);
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'bundle_items')
            ->withPivot(['quantity', 'is_free_gift'])
            ->withTimestamps();
    }

    public function saleDetails(): HasMany
    {
        return $this->hasMany(SaleDetail::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeGiftSets($query)
    {
        return $query->where('type', 'gift_set');
    }

    public function scopeStandardBundles($query)
    {
        return $query->where('type', 'bundle');
    }

    /**
     * Compute authoritative stock availability based on constituent products.
     */
    public function getAvailableQtyAttribute(): int
    {
        $items = $this->items()->with('product')->get();
        if ($items->isEmpty()) {
            return 0;
        }

        $possibleQuantities = [];
        foreach ($items as $item) {
            $productQty = (int) ($item->product?->qty ?? 0);
            $requiredPerBundle = max(1, (int) $item->quantity);
            $possibleQuantities[] = (int) floor($productQty / $requiredPerBundle);
        }

        return empty($possibleQuantities) ? 0 : max(0, min($possibleQuantities));
    }

    public function getInStockAttribute(): bool
    {
        return $this->available_qty > 0;
    }

    /**
     * Total value of constituent items at individual retail price.
     */
    public function getCalculatedOriginalPriceAttribute(): float
    {
        if ($this->original_price && $this->original_price > 0) {
            return (float) $this->original_price;
        }

        $items = $this->items()->with('product')->get();
        $total = 0.00;
        foreach ($items as $item) {
            $unitPrice = (float) ($item->product?->sale_price ?? 0.0);
            $total += $unitPrice * $item->quantity;
        }

        return round($total, 2);
    }

    public function getSavingsAmountAttribute(): float
    {
        $orig = $this->calculated_original_price;
        return max(0.00, round($orig - (float) $this->price, 2));
    }

    public function getSavingsPercentageAttribute(): int
    {
        $orig = $this->calculated_original_price;
        if ($orig <= 0) {
            return 0;
        }
        return (int) round(($this->savings_amount / $orig) * 100);
    }

    public function getImageUrlAttribute(): string
    {
        if ($this->image) {
            if (filter_var($this->image, FILTER_VALIDATE_URL)) {
                return $this->image;
            }
            return url($this->image);
        }

        // Fallback to first item's image if bundle has no unique photo
        $firstItem = $this->items()->with('product')->first();
        if ($firstItem && $firstItem->product && $firstItem->product->image_url) {
            return $firstItem->product->image_url;
        }

        return url('images/default-bundle.jpg');
    }
}

