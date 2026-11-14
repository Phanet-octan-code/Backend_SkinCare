@extends('layouts.app')

@section('title', 'Loyalty Tiers & Referral Campaigns')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none text-muted">Marketing</a></li>
    <li class="breadcrumb-item active text-dark" aria-current="page">Loyalty & Referrals</li>
@endsection

@section('content')
<x-page-header 
    title="Loyalty Tiers & Referral Program" 
    description="Configure VIP reward tiers, spend-to-point accrual rates, and client invitation rewards."
    :breadcrumbs="['Marketing' => route('promotions.index'), 'Loyalty & Referrals' => null]" />

<!-- Program Analytics -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card border-0 shadow-sm rounded-4 p-3 bg-white">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <div class="text-muted small">Total Points Issued</div>
                    <div class="fs-4 fw-bold text-dark">{{ number_format($stats['total_points_issued']) }}</div>
                </div>
                <div class="p-3 bg-primary-subtle text-primary rounded-circle"><i class="bi bi-star-fill"></i></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm rounded-4 p-3 bg-white">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <div class="text-muted small">Points Redeemed</div>
                    <div class="fs-4 fw-bold text-success">{{ number_format($stats['total_points_redeemed']) }}</div>
                </div>
                <div class="p-3 bg-success-subtle text-success rounded-circle"><i class="bi bi-gift-fill"></i></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm rounded-4 p-3 bg-white">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <div class="text-muted small">Referral Invitations</div>
                    <div class="fs-4 fw-bold text-dark">{{ number_format($stats['total_referrals']) }}</div>
                </div>
                <div class="p-3 bg-info-subtle text-info rounded-circle"><i class="bi bi-people-fill"></i></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm rounded-4 p-3 bg-white">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <div class="text-muted small">Completed Conversions</div>
                    <div class="fs-4 fw-bold text-warning">{{ number_format($stats['rewarded_referrals']) }}</div>
                </div>
                <div class="p-3 bg-warning-subtle text-warning rounded-circle"><i class="bi bi-check-circle-fill"></i></div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <!-- Loyalty Tiers Configuration -->
    <div class="col-md-7">
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-header bg-white py-3 border-0">
                <h5 class="fw-bold fs-6 mb-0 text-dark">VIP Membership Tiers & Point Multipliers</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-4">Tier</th>
                                <th>Min Points</th>
                                <th>Store Discount</th>
                                <th>Earn Multiplier</th>
                                <th>Free Shipping</th>
                                <th class="pe-4 text-end">Save</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($tiers as $tier)
                            <tr>
                                <td class="ps-4 fw-bold">
                                    <span class="badge {{ match(strtolower($tier->tier_name)) {
                                        'platinum' => 'bg-dark text-white',
                                        'gold' => 'bg-warning text-dark',
                                        'silver' => 'bg-secondary text-white',
                                        default => 'bg-light text-dark border'
                                    } }}">{{ $tier->tier_name }}</span>
                                </td>
                                <form action="{{ route('loyalty.tier.update', $tier) }}" method="POST">
                                    @csrf
                                    @method('PUT')
                                    <td>
                                        <input type="number" name="min_points" class="form-control form-control-sm" value="{{ $tier->min_points }}" style="width: 90px;" required>
                                    </td>
                                    <td>
                                        <div class="input-group input-group-sm" style="width: 90px;">
                                            <input type="number" step="0.5" name="discount_percentage" class="form-control" value="{{ $tier->discount_percentage }}" required>
                                            <span class="input-group-text">%</span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="input-group input-group-sm" style="width: 80px;">
                                            <input type="number" step="0.25" name="points_multiplier" class="form-control" value="{{ $tier->points_multiplier }}" required>
                                            <span class="input-group-text">x</span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="form-check">
                                            <input type="checkbox" name="free_shipping" value="1" class="form-check-input" {{ $tier->free_shipping ? 'checked' : '' }}>
                                        </div>
                                    </td>
                                    <td class="pe-4 text-end">
                                        <button type="submit" class="btn btn-sm btn-outline-primary"><i class="bi bi-check-lg"></i></button>
                                    </td>
                                </form>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Active Referral Campaign Settings -->
    <div class="col-md-5">
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-header bg-white py-3 border-0">
                <h5 class="fw-bold fs-6 mb-0 text-dark">Referral Campaign Configuration</h5>
            </div>
            <div class="card-body p-4">
                <form action="{{ route('loyalty.campaign.update') }}" method="POST">
                    @csrf
                    @method('PUT')

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Campaign Name</label>
                        <input type="text" name="name" class="form-control form-control-sm" value="{{ $campaign->name }}" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Referrer Reward (Points)</label>
                        <input type="number" name="referrer_reward_points" class="form-control form-control-sm" value="{{ $campaign->referrer_reward_points }}" required>
                        <div class="form-text small">e.g. 200 points ($10 store credit).</div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Referee Discount Type</label>
                            <select name="referee_discount_type" class="form-select form-select-sm" required>
                                <option value="fixed" {{ $campaign->referee_discount_type === 'fixed' ? 'selected' : '' }}>Fixed ($)</option>
                                <option value="percentage" {{ $campaign->referee_discount_type === 'percentage' ? 'selected' : '' }}>Percentage (%)</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Discount Amount</label>
                            <input type="number" step="0.5" name="referee_discount_value" class="form-control form-control-sm" value="{{ $campaign->referee_discount_value }}" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Minimum Qualifying Order Subtotal ($)</label>
                        <input type="number" step="1.0" name="min_order_spend" class="form-control form-control-sm" value="{{ $campaign->min_order_spend }}" required>
                    </div>

                    <div class="d-flex justify-content-end">
                        <button type="submit" class="btn btn-sm btn-skincare-primary px-3">Update Campaign</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

