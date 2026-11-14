<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerReferral;
use App\Models\ReferralCampaign;
use App\Models\Sale;
use InvalidArgumentException;

class ReferralService
{
    /**
     * Validate a referral code for a customer purchase.
     */
    public function validateReferralCode(string $code, ?Customer $referee, float $subtotal): array
    {
        $code = strtoupper(trim($code));
        $campaign = ReferralCampaign::activeCampaign();
        if (!$campaign) {
            throw new InvalidArgumentException('No active referral campaign is running.');
        }

        $referrer = Customer::where('referral_code', $code)->first();
        if (!$referrer) {
            throw new InvalidArgumentException("Referral code '{$code}' is invalid.");
        }

        // Anti-Fraud 1: Disallow self-referral
        if ($referee) {
            if ($referrer->id === $referee->id) {
                throw new InvalidArgumentException('You cannot use your own referral code.');
            }

            if (!empty($referee->email) && strtolower($referrer->email ?? '') === strtolower($referee->email)) {
                throw new InvalidArgumentException('Self-referrals are not permitted.');
            }

            // Anti-Fraud 2: Only valid on first purchase
            $existingOrders = Sale::where('customer_id', $referee->id)
                ->where('status', '!=', Sale::STATUS_CANCELLED)
                ->count();

            if ($existingOrders > 0) {
                throw new InvalidArgumentException('Referral codes are only valid on your first Skinora order.');
            }

            // Anti-Fraud 3: Ensure not already referred by someone else
            if (CustomerReferral::where('referee_id', $referee->id)->exists()) {
                throw new InvalidArgumentException('You have already redeemed a referral invitation.');
            }
        }

        // Anti-Fraud 4: Minimum order spend
        $minSpend = (float) $campaign->min_order_spend;
        if ($subtotal < $minSpend) {
            throw new InvalidArgumentException("Referral discount requires a minimum order subtotal of \${$minSpend}.");
        }

        $discount = $campaign->referee_discount_type === 'percentage'
            ? round($subtotal * ($campaign->referee_discount_value / 100), 2)
            : min($subtotal, (float) $campaign->referee_discount_value);

        return [
            'valid' => true,
            'code' => $code,
            'referrer_id' => $referrer->id,
            'referrer_name' => $referrer->name,
            'discount' => $discount,
            'reward_points' => $campaign->referrer_reward_points,
            'description' => "Friend Referral (\${$discount} Off)",
        ];
    }

    /**
     * Record referral usage during checkout.
     */
    public function recordReferralUsage(Sale $sale, Customer $referrer, Customer $referee, string $code): CustomerReferral
    {
        // Link referee to referrer
        if (!$referee->referred_by_id) {
            $referee->update(['referred_by_id' => $referrer->id]);
        }

        return CustomerReferral::create([
            'referrer_id' => $referrer->id,
            'referee_id' => $referee->id,
            'referral_code' => $code,
            'sale_id' => $sale->id,
            'status' => 'pending',
        ]);
    }

    /**
     * Reward referrer when referee's order is confirmed or paid.
     */
    public function rewardReferrer(Sale $sale): void
    {
        $referral = CustomerReferral::where('sale_id', $sale->id)
            ->where('status', 'pending')
            ->first();

        if (!$referral) {
            return;
        }

        $campaign = ReferralCampaign::activeCampaign();
        $points = $campaign ? $campaign->referrer_reward_points : 200;

        $referrer = $referral->referrer;
        if ($referrer && $points > 0) {
            $referrer->addLoyaltyPoints(
                points: $points,
                type: 'referral_bonus',
                saleId: $sale->id,
                description: "Referral reward: Friend completed order #{$sale->invoice_number}"
            );

            $referral->update([
                'status' => 'rewarded',
                'rewarded_at' => now(),
            ]);
        }
    }
}

