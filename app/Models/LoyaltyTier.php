<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LoyaltyTier extends Model
{
    use HasFactory;

    protected $fillable = [
        'tier_name',
        'min_spend',
        'min_points',
        'discount_percentage',
        'points_multiplier',
        'free_shipping',
        'perks_description',
    ];

    protected function casts(): array
    {
        return [
            'min_spend' => 'decimal:2',
            'min_points' => 'integer',
            'discount_percentage' => 'decimal:2',
            'points_multiplier' => 'decimal:2',
            'free_shipping' => 'boolean',
        ];
    }

    /**
     * Determine tier based on points or lifetime spend.
     */
    public static function forCustomer(Customer $customer): self
    {
        $points = (int) $customer->lifetime_points;
        return static::orderByDesc('min_points')
            ->where('min_points', '<=', $points)
            ->first() ?? static::defaultTier();
    }

    public static function defaultTier(): self
    {
        return static::firstOrCreate(
            ['tier_name' => 'Bronze'],
            [
                'min_spend' => 0.00,
                'min_points' => 0,
                'discount_percentage' => 0.00,
                'points_multiplier' => 1.00,
                'free_shipping' => false,
                'perks_description' => 'Earn 1 point per $1 spent',
            ]
        );
    }
}

