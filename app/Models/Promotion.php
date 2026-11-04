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
        'discount_type',
        'discount_value',
        'start_date',
        'end_date',
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
            'start_date' => 'date',
            'end_date' => 'date',
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

        return $query->where('status', 'active')
            ->whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today);
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
