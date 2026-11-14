<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LoyaltyTier;
use App\Models\ReferralCampaign;
use App\Services\LoyaltyService;
use App\Services\ReferralService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class CustomerLoyaltyController extends Controller
{
    public function __construct(
        protected LoyaltyService $loyaltyService,
        protected ReferralService $referralService
    ) {}

    public function profile(Request $request): JsonResponse
    {
        $customer = $request->user();
        $lifetimePoints = (int) $customer->lifetime_points;

        // Cache all loyalty tiers for 10 minutes to eliminate repetitive queries
        $allTiers = Cache::remember('loyalty:tiers:all', 600, function () {
            return LoyaltyTier::orderBy('min_points')->get();
        });

        // Determine current tier and next tier from memory collection
        $tier = $allTiers->where('min_points', '<=', $lifetimePoints)->last() ?? $allTiers->first() ?? LoyaltyTier::defaultTier();
        $nextTier = $allTiers->first(fn ($t) => $t->min_points > $lifetimePoints);

        $pointsToNext = $nextTier ? max(0, $nextTier->min_points - $lifetimePoints) : 0;
        $progressPct = 100;
        if ($nextTier && $nextTier->min_points > 0) {
            $currentBase = $tier->min_points;
            $range = $nextTier->min_points - $currentBase;
            $progressPct = $range > 0
                ? min(100, max(0, (int) round((($lifetimePoints - $currentBase) / $range) * 100)))
                : 100;
        }

        // Cache active campaign settings
        $campaign = Cache::remember('referral:active_campaign', 600, function () {
            return ReferralCampaign::activeCampaign();
        });
        $baseUrl = config('app.url', 'https://skinora.com');
        $referralCode = $customer->referral_code;

        // Combined referral count query in single SQL execution
        $referralCounts = $customer->referralsGiven()
            ->selectRaw('COUNT(*) as total, COUNT(CASE WHEN status = ? THEN 1 END) as rewarded', ['rewarded'])
            ->first();

        $recentTransactions = $customer->loyaltyTransactions()
            ->select(['id', 'customer_id', 'points', 'type', 'description', 'created_at'])
            ->latest()
            ->limit(15)
            ->get()
            ->map(function ($tx) {
                return [
                    'id' => $tx->id,
                    'points' => $tx->points,
                    'type' => $tx->type,
                    'description' => $tx->description,
                    'date' => $tx->created_at->format('M d, Y'),
                ];
            });

        return response()->json([
            'success' => true,
            'data' => [
                'current_points' => (int) $customer->loyalty_points,
                'points_dollar_value' => round((int) $customer->loyalty_points * (LoyaltyService::REDEMPTION_VALUE_STEP / LoyaltyService::REDEMPTION_POINTS_STEP), 2),
                'lifetime_points' => $lifetimePoints,
                'tier' => [
                    'name' => $tier->tier_name,
                    'discount_percentage' => (float) $tier->discount_percentage,
                    'points_multiplier' => (float) $tier->points_multiplier,
                    'free_shipping' => (bool) $tier->free_shipping,
                    'perks' => $tier->perks_description,
                ],
                'next_tier' => $nextTier ? [
                    'name' => $nextTier->tier_name,
                    'points_required' => $nextTier->min_points,
                    'points_needed' => $pointsToNext,
                    'progress_percentage' => $progressPct,
                ] : null,
                'referral' => [
                    'code' => $referralCode,
                    'share_url' => "{$baseUrl}/register?ref={$referralCode}",
                    'reward_points' => $campaign?->referrer_reward_points ?? 200,
                    'referee_discount' => $campaign?->referee_discount_value ?? 10.00,
                    'referee_discount_type' => $campaign?->referee_discount_type ?? 'fixed',
                    'total_referrals_made' => (int) ($referralCounts?->total ?? 0),
                    'successful_referrals' => (int) ($referralCounts?->rewarded ?? 0),
                ],
                'recent_history' => $recentTransactions,
            ],
        ]);
    }

    public function validateReferral(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'required|string',
            'subtotal' => 'required|numeric|min:0',
        ]);

        try {
            $res = $this->referralService->validateReferralCode(
                code: $validated['code'],
                referee: $request->user(),
                subtotal: (float) $validated['subtotal']
            );

            return response()->json([
                'success' => true,
                'data' => $res,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}

