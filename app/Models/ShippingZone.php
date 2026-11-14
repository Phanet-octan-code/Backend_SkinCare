<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\App;

class ShippingZone extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name_en',
        'name_km',
        'standard_fee',
        'free_shipping_threshold',
        'estimated_delivery_en',
        'estimated_delivery_km',
        'provinces',
        'is_active',
    ];

    protected $casts = [
        'standard_fee' => 'decimal:2',
        'free_shipping_threshold' => 'decimal:2',
        'provinces' => 'array',
        'is_active' => 'boolean',
    ];

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function getLocalizedNameAttribute(): string
    {
        $locale = App::getLocale();
        return ($locale === 'km' && !empty($this->name_km)) ? $this->name_km : $this->name_en;
    }

    public function getLocalizedDeliveryAttribute(): string
    {
        $locale = App::getLocale();
        return ($locale === 'km' && !empty($this->estimated_delivery_km)) ? $this->estimated_delivery_km : $this->estimated_delivery_en;
    }

    /**
     * Resolve the appropriate shipping zone from an address or requested code.
     */
    public static function resolveForAddress(?string $address, ?string $preferredCode = null): ?self
    {
        $zones = static::where('is_active', true)->get();
        if ($zones->isEmpty()) {
            return null;
        }

        // 1. If preferred code provided and matches
        if ($preferredCode) {
            $matched = $zones->firstWhere('code', strtolower(trim($preferredCode)));
            if ($matched) {
                return $matched;
            }
        }

        // 2. Address matching heuristics
        if ($address) {
            $addr = mb_strtolower($address);
            if (str_contains($addr, 'phnom penh') || str_contains($addr, 'ភ្នំពេញ')) {
                $pp = $zones->firstWhere('code', 'phnom_penh');
                if ($pp) return $pp;
            }

            // Check provinces list
            foreach ($zones as $zone) {
                if (is_array($zone->provinces)) {
                    foreach ($zone->provinces as $province) {
                        if (str_contains($addr, mb_strtolower($province))) {
                            return $zone;
                        }
                    }
                }
            }
        }

        // 3. Fallback to default provincial or first active zone
        return $zones->firstWhere('code', 'provinces') ?? $zones->first();
    }
}

