@extends('layouts.app')

@section('title', 'Subscription #' . $subscription->id)

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none text-muted">Sales & POS</a></li>
    <li class="breadcrumb-item"><a href="{{ route('subscriptions.index') }}" class="text-decoration-none text-muted">Subscriptions</a></li>
    <li class="breadcrumb-item active text-dark" aria-current="page">#SUB-{{ str_pad($subscription->id, 5, '0', STR_PAD_LEFT) }}</li>
@endsection

@section('content')
<x-page-header 
    title="Subscription #SUB-{{ str_pad($subscription->id, 5, '0', STR_PAD_LEFT) }}" 
    description="Recurring replenishment details, delivery cadence, and renewal order history."
    :breadcrumbs="['Subscriptions' => route('subscriptions.index'), '#SUB-' . $subscription->id => null]">
    <x-slot:actions>
        @if($subscription->isActive())
            <form action="{{ route('subscriptions.pause', $subscription) }}" method="POST" class="d-inline">
                @csrf
                <button type="submit" class="btn btn-outline-warning btn-sm"><i class="bi bi-pause"></i> Pause Plan</button>
            </form>
        @elseif($subscription->isPaused())
            <form action="{{ route('subscriptions.resume', $subscription) }}" method="POST" class="d-inline">
                @csrf
                <button type="submit" class="btn btn-outline-success btn-sm"><i class="bi bi-play"></i> Resume Plan</button>
            </form>
        @endif
    </x-slot:actions>
</x-page-header>

<div class="row g-4">
    <div class="col-md-8">
        <div class="card border-0 shadow-sm rounded-4 mb-4">
            <div class="card-body p-4">
                <h5 class="fw-bold fs-6 mb-3 text-dark">Replenishment Plan Summary</h5>
                <div class="d-flex align-items-center gap-3 p-3 bg-light rounded-3 mb-4">
                    @if($subscription->item_image_url)
                        <img src="{{ $subscription->item_image_url }}" alt="{{ $subscription->item_name }}" class="rounded border object-fit-cover" style="width: 60px; height: 60px;">
                    @endif
                    <div>
                        <h6 class="fw-bold mb-1 text-dark">{{ $subscription->item_name }}</h6>
                        <div class="text-muted small">
                            Quantity: <strong>{{ $subscription->quantity }}</strong> | 
                            Unit Price: <strong>${{ number_format($subscription->unit_price, 2) }}</strong> | 
                            Subscribe & Save Discount: <strong class="text-success">{{ (int) $subscription->discount_percentage }}%</strong>
                        </div>
                        <div class="fw-bold text-dark mt-1">
                            Recurring Total: ${{ number_format($subscription->recurring_subtotal, 2) }} / delivery
                        </div>
                    </div>
                </div>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="text-muted small">Delivery Interval</label>
                        <div class="fw-semibold text-dark">Every {{ $subscription->billing_interval_days }} Days</div>
                    </div>
                    <div class="col-md-6">
                        <label class="text-muted small">Payment Method</label>
                        <div class="fw-semibold text-dark text-uppercase">{{ $subscription->payment_method }}</div>
                    </div>
                    <div class="col-md-6">
                        <label class="text-muted small">Next Scheduled Delivery</label>
                        <div class="fw-semibold text-dark">{{ $subscription->next_billing_date ? $subscription->next_billing_date->format('F d, Y') : 'N/A' }}</div>
                    </div>
                    <div class="col-md-6">
                        <label class="text-muted small">Last Billing Date</label>
                        <div class="fw-semibold text-dark">{{ $subscription->last_billing_date ? $subscription->last_billing_date->format('F d, Y') : 'N/A' }}</div>
                    </div>
                    <div class="col-12">
                        <label class="text-muted small">Shipping Destination</label>
                        <div class="fw-semibold text-dark">{{ $subscription->shipping_address }}</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Renewal Orders Table -->
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-header bg-white py-3 border-0">
                <h5 class="fw-bold fs-6 mb-0 text-dark">Generated Renewal Orders</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-4">Invoice #</th>
                                <th>Date</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th class="pe-4 text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($subscription->sales as $sale)
                            <tr>
                                <td class="ps-4 fw-bold">
                                    <a href="{{ route('sales.show', $sale) }}" class="text-decoration-none text-dark">{{ $sale->invoice_number }}</a>
                                </td>
                                <td>{{ $sale->created_at->format('M d, Y') }}</td>
                                <td class="fw-bold">${{ number_format($sale->final_total, 2) }}</td>
                                <td>
                                    <span class="badge {{ $sale->payment_status === 'paid' ? 'bg-success' : 'bg-warning text-dark' }}">
                                        {{ ucfirst($sale->payment_status) }}
                                    </span>
                                </td>
                                <td class="pe-4 text-end">
                                    <a href="{{ route('sales.show', $sale) }}" class="btn btn-sm btn-light"><i class="bi bi-eye"></i> View Order</a>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="5" class="text-center py-4 text-muted">No renewal orders generated yet.</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card border-0 shadow-sm rounded-4 mb-4">
            <div class="card-body p-4">
                <h5 class="fw-bold fs-6 mb-3 text-dark">Customer Information</h5>
                <div class="mb-3">
                    <label class="text-muted small">Customer Name</label>
                    <div class="fw-semibold text-dark">{{ $subscription->customer?->name ?? 'Guest' }}</div>
                </div>
                <div class="mb-3">
                    <label class="text-muted small">Email Address</label>
                    <div class="fw-semibold text-dark">{{ $subscription->customer?->email ?? '-' }}</div>
                </div>
                <div class="mb-3">
                    <label class="text-muted small">Phone</label>
                    <div class="fw-semibold text-dark">{{ $subscription->customer?->phone ?? '-' }}</div>
                </div>
                <div>
                    <label class="text-muted small">Loyalty Tier</label>
                    <div><span class="badge bg-primary-subtle text-primary">{{ $subscription->customer?->membership_level ?? 'Bronze' }}</span></div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

