<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReferralCampaign extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'referrer_reward_points',
        'referee_discount_type',
        'referee_discount_value',
        'min_order_spend',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'referrer_reward_points' => 'integer',
            'referee_discount_value' => 'decimal:2',
            'min_order_spend' => 'decimal:2',
        ];
    }

    public static function activeCampaign(): ?self
    {
        return static::where('status', 'active')->first() ?? static::create([
            'name' => 'Skinora Radiance Referral Club',
            'referrer_reward_points' => 200,
            'referee_discount_type' => 'fixed',
            'referee_discount_value' => 10.00,
            'min_order_spend' => 30.00,
            'status' => 'active',
        ]);
    }
}

