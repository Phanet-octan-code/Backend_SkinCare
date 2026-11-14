<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\LoyaltyTier;
use App\Models\LoyaltyTransaction;
use App\Models\Sale;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class LoyaltyService
{
    public const REDEMPTION_POINTS_STEP = 100;
    public const REDEMPTION_VALUE_STEP = 5.00; // 100 points = $5.00 ($0.05/pt)

    /**
     * Calculate points that would be earned for a given net spend.
     */
    public function calculatePointsEarned(Customer $customer, float $netSpend): int
    {
        if ($netSpend <= 0) {
            return 0;
        }

        $tier = LoyaltyTier::forCustomer($customer);
        $multiplier = (float) $tier->points_multiplier;

        return (int) floor($netSpend * $multiplier);
    }

    /**
     * Calculate discount value for a requested number of points to redeem.
     */
    public function calculateRedemptionDiscount(Customer $customer, int $pointsRequested, float $subtotal): array
    {
        if ($pointsRequested <= 0) {
            return [
                'points_to_redeem' => 0,
                'discount' => 0.00,
            ];
        }

        $available = (int) $customer->loyalty_points;
        $pointsToUse = min($available, $pointsRequested);

        // Normalize to increments of 100 (or round to point value $0.05)
        $discount = round($pointsToUse * (self::REDEMPTION_VALUE_STEP / self::REDEMPTION_POINTS_STEP), 2);

        // Maximum discount cannot exceed 50% of subtotal or total subtotal
        $maxDiscount = round($subtotal * 0.50, 2);
        if ($discount > $maxDiscount) {
            $discount = $maxDiscount;
            $pointsToUse = (int) ceil($discount / (self::REDEMPTION_VALUE_STEP / self::REDEMPTION_POINTS_STEP));
        }

        return [
            'points_to_redeem' => $pointsToUse,
            'discount' => $discount,
        ];
    }

    /**
     * Award loyalty points for an order.
     */
    public function awardPointsForSale(Sale $sale): void
    {
        if (!$sale->customer_id) {
            return;
        }

        $customer = $sale->customer;
        if (!$customer) {
            return;
        }

        // Avoid duplicate awards for the same sale
        $alreadyAwarded = LoyaltyTransaction::where('customer_id', $customer->id)
            ->where('sale_id', $sale->id)
            ->where('type', 'earned_purchase')
            ->exists();

        if ($alreadyAwarded) {
            return;
        }

        $netSpend = (float) $sale->final_total;
        $points = $this->calculatePointsEarned($customer, $netSpend);

        if ($points > 0) {
            $customer->addLoyaltyPoints(
                points: $points,
                type: 'earned_purchase',
                saleId: $sale->id,
                description: "Earned for order #{$sale->invoice_number}"
            );

            $sale->update(['loyalty_points_earned' => $points]);
        }
    }

    /**
     * Reverse loyalty points upon order cancellation or refund.
     */
    public function reversePointsForRefund(Sale $sale, float $refundAmount): void
    {
        if (!$sale->customer_id) {
            return;
        }

        $customer = $sale->customer;
        if (!$customer) {
            return;
        }

        // Calculate points to reverse proportionally
        $totalSale = (float) $sale->final_total;
        if ($totalSale <= 0) {
            return;
        }

        $pointsEarned = (int) $sale->loyalty_points_earned;
        if ($pointsEarned <= 0) {
            return;
        }

        $ratio = min(1.0, $refundAmount / $totalSale);
        $pointsToReverse = (int) round($pointsEarned * $ratio);

        if ($pointsToReverse > 0) {
            $customer->deductLoyaltyPoints(
                points: $pointsToReverse,
                type: 'refund_reversal',
                saleId: $sale->id,
                description: "Points reversed for refund on #{$sale->invoice_number}"
            );
        }
    }
}

