<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerReferral;
use App\Models\LoyaltyTier;
use App\Models\LoyaltyTransaction;
use App\Models\ReferralCampaign;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LoyaltyAdminController extends Controller
{
    public function index(): View
    {
        $tiers = LoyaltyTier::orderBy('min_points')->get();
        if ($tiers->isEmpty()) {
            LoyaltyTier::defaultTier();
            $tiers = LoyaltyTier::orderBy('min_points')->get();
        }

        $campaign = ReferralCampaign::activeCampaign();

        $topCustomers = Customer::orderByDesc('loyalty_points')->limit(10)->get();

        $stats = [
            'total_points_issued' => LoyaltyTransaction::where('points', '>', 0)->sum('points'),
            'total_points_redeemed' => abs(LoyaltyTransaction::where('points', '<', 0)->sum('points')),
            'total_referrals' => CustomerReferral::count(),
            'rewarded_referrals' => CustomerReferral::where('status', 'rewarded')->count(),
        ];

        return view('loyalty.index', compact('tiers', 'campaign', 'topCustomers', 'stats'));
    }

    public function updateTier(Request $request, LoyaltyTier $tier): RedirectResponse
    {
        $validated = $request->validate([
            'discount_percentage' => 'required|numeric|min:0|max:100',
            'points_multiplier' => 'required|numeric|min:1|max:10',
            'min_points' => 'required|integer|min:0',
            'free_shipping' => 'nullable|boolean',
            'perks_description' => 'nullable|string|max:255',
        ]);

        $validated['free_shipping'] = (bool) ($request->free_shipping ?? false);
        $tier->update($validated);

        return back()->with('success', "Tier '{$tier->tier_name}' updated successfully.");
    }

    public function updateCampaign(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'referrer_reward_points' => 'required|integer|min:0',
            'referee_discount_type' => 'required|in:fixed,percentage',
            'referee_discount_value' => 'required|numeric|min:0',
            'min_order_spend' => 'required|numeric|min:0',
        ]);

        $campaign = ReferralCampaign::activeCampaign();
        $campaign->update($validated);

        return back()->with('success', 'Referral campaign updated successfully.');
    }
}

