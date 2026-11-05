<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'category_id',
        'supplier_id',
        'name',
        'barcode',
        'cost_price',
        'sale_price',
        'qty',
        'image',
        'description',
        'low_stock_threshold',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'cost_price' => 'decimal:2',
            'sale_price' => 'decimal:2',
            'qty' => 'integer',
            'low_stock_threshold' => 'integer',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function stock(): HasOne
    {
        return $this->hasOne(Stock::class);
    }

    public function saleDetails(): HasMany
    {
        return $this->hasMany(SaleDetail::class);
    }

    public function stockTransactions(): HasMany
    {
        return $this->hasMany(StockTransaction::class);
    }

    public function promotions(): BelongsToMany
    {
        return $this->belongsToMany(Promotion::class, 'product_promotion')
            ->withTimestamps();
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(ProductReview::class);
    }

    public function wishlists(): HasMany
    {
        return $this->hasMany(CustomerWishlist::class);
    }

    public function getImageUrlAttribute(): string
    {
        if ($this->image) {
            if (filter_var($this->image, FILTER_VALIDATE_URL)) {
                return $this->image;
            }
            $baseUrl = rtrim(env('RENDER_EXTERNAL_URL', config('app.url')), '/');
            if (request()->hasHeader('Host') || request()->getHttpHost()) {
                $scheme = request()->isSecure() || request()->header('X-Forwarded-Proto') === 'https' ? 'https' : 'http';
                $host = request()->getHttpHost();
                if (!str_contains($host, 'localhost') && !str_contains($host, '127.0.0.1')) {
                    $baseUrl = $scheme . '://' . $host;
                }
            }
            if (str_contains($baseUrl, 'localhost') || str_contains($baseUrl, '127.0.0.1')) {
                $baseUrl = 'https://backend-skincare-ng5o.onrender.com';
            }
            return $baseUrl . '/' . ltrim($this->image, '/');
        }

        // Return curated reliable skincare imagery based on product ID/category
        return "https://images.unsplash.com/photo-1556228720-195a672e8a03?auto=format&fit=crop&w=600&q=80";
    }

    protected static function booted(): void
    {
        static::saved(function () {
            \App\Services\StorefrontCache::bust();
        });

        static::deleted(function () {
            \App\Services\StorefrontCache::bust();
        });
    }

    public function getAverageRatingAttribute(): float
    {
        if (array_key_exists('average_rating', $this->attributes)) {
            return $this->attributes['average_rating'] !== null
                ? (float) round((float) $this->attributes['average_rating'], 1)
                : 5.0;
        }
        if ($this->relationLoaded('reviews')) {
            $approved = $this->reviews->where('status', 'approved');
            return $approved->count() > 0 ? (float) round($approved->avg('rating'), 1) : 5.0;
        }
        return 5.0;
    }

    public function getReviewsCountAttribute(): int
    {
        if (array_key_exists('reviews_count', $this->attributes)) {
            return (int) ($this->attributes['reviews_count'] ?? 0);
        }
        if ($this->relationLoaded('reviews')) {
            return $this->reviews->where('status', 'approved')->count();
        }
        return 0;
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeLowStock($query)
    {
        return $query->whereColumn('qty', '<=', 'low_stock_threshold');
    }

    public function isLowStock(): bool
    {
        return $this->qty <= $this->low_stock_threshold;
    }
}

