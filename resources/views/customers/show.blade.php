@extends('layouts.app')

@section('title', 'Customer CRM: ' . $customer->name)

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('customers.index') }}" class="text-decoration-none text-muted">Customers</a></li>
    <li class="breadcrumb-item active text-dark" aria-current="page">{{ $customer->name }}</li>
@endsection

@section('content')
<x-page-header 
    :title="$customer->name" 
    description="Customer CRM profile, VIP loyalty tier privileges, contact details, and complete purchase invoice history."
    :breadcrumbs="['Customers' => route('customers.index'), $customer->name => null]">
    <x-slot:actions>
        <button type="button" class="btn btn-sm btn-light border d-flex align-items-center gap-1" data-bs-toggle="modal" data-bs-target="#editCustomerModal">
            <i class="bi bi-pencil"></i> Edit Profile
        </button>
        <a href="{{ route('pos.index', ['customer_id' => $customer->id]) }}" class="btn btn-sm btn-skincare-primary d-flex align-items-center gap-1">
            <i class="bi bi-cart-plus"></i> New Sale in POS
        </a>
    </x-slot:actions>
</x-page-header>

<!-- VIP Member Card & Details Header -->
<div class="card-skincare p-4 mb-4">
    <div class="row align-items-center g-4">
        <div class="col-12 col-lg-8">
            <div class="d-flex align-items-start gap-3 flex-wrap flex-sm-nowrap">
                <span class="avatar-circle" style="width: 68px; height: 68px; font-size: 1.8rem; background: #71856B; color: white;">
                    {{ strtoupper(substr($customer->name, 0, 2)) }}
                </span>
                <div>
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <h4 class="fw-bold text-dark mb-0 fs-5">{{ $customer->name }}</h4>
                        <span class="badge {{ match(strtolower($customer->membership_level ?? '')) {
                            'platinum' => 'bg-dark text-white border-dark',
                            'gold' => 'bg-warning-subtle text-dark border-warning',
                            'silver' => 'bg-secondary-subtle text-secondary border-secondary-subtle',
                            default => 'bg-light text-dark border',
                        } }} px-2 py-1">
                            <i class="bi bi-award me-1"></i>{{ $customer->membership_level }} VIP
                        </span>
                        <x-status-badge :status="$customer->status" />
                    </div>

                    <div class="text-muted small mt-1">
                        Membership Number: <strong class="font-monospace text-dark">{{ $customer->membership_number ?? 'REGULAR' }}</strong> &bull; 
                        Enjoys <strong class="text-success">{{ $customer->membership_discount_percentage }}% VIP discount</strong> on all store purchases.
                    </div>

                    <div class="d-flex align-items-center gap-4 mt-3 text-muted small flex-wrap">
                        <span><i class="bi bi-envelope me-1"></i>{{ $customer->email ?? 'No email' }}</span>
                        <span><i class="bi bi-telephone me-1"></i>{{ $customer->phone ?? 'No phone' }}</span>
                        <span><i class="bi bi-geo-alt me-1"></i>{{ $customer->address ?? 'No physical address' }}</span>
                        <span><i class="bi bi-calendar3 me-1"></i>Client Since: {{ $customer->created_at->format('M Y') }}</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-4 border-start-lg">
            <div class="p-3 rounded-3 bg-light border">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted small fw-semibold">VIP Privilege Tier:</span>
                    <span class="fw-bold text-dark">{{ $customer->membership_level }}</span>
                </div>
                <div class="progress mb-2" style="height: 6px;">
                    @php
                        $progressWidth = match(strtolower($customer->membership_level ?? '')) {
                            'platinum' => 100,
                            'gold' => 75,
                            'silver' => 50,
                            default => 25,
                        };
                    @endphp
                    <div class="progress-bar bg-success" role="progressbar" style="width: {{ $progressWidth }}%"></div>
                </div>
                <div class="d-flex justify-content-between text-muted" style="font-size: 0.72rem;">
                    <span>Bronze (0%)</span>
                    <span>Silver (5%)</span>
                    <span>Gold (10%)</span>
                    <span>Platinum (15%)</span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 4 KPI Cards -->
<div class="row g-3 mb-4">
    <div class="col-12 col-sm-6 col-xl-3">
        <x-kpi-card 
            title="Lifetime Spend" 
            value="${{ number_format($stats['total_spent'], 2) }}" 
            subtitle="Completed purchase total"
            icon="bi-cash-coin" 
            color="success" />
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <x-kpi-card 
            title="Completed Orders" 
            :value="$stats['completed_orders']" 
            subtitle="Total fulfilled invoices"
            icon="bi-bag-check" 
            color="sage" />
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <x-kpi-card 
            title="Average Order Value" 
            value="${{ number_format($stats['average_order'], 2) }}" 
            subtitle="Mean basket size"
            icon="bi-graph-up" 
            color="dark" />
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <x-kpi-card 
            title="Latest Purchase" 
            :value="$stats['latest_order_date']" 
            subtitle="Most recent transaction"
            icon="bi-clock-history" 
            color="info" />
    </div>
</div>

<!-- Filter Bar for Purchase History -->
<div class="card-skincare p-3 mb-3">
    <form method="GET" action="{{ route('customers.show', $customer) }}" class="row g-2 align-items-center">
        <div class="col-6 col-md-3">
            <select name="payment_status" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="">All Payment Statuses</option>
                <option value="paid" {{ request('payment_status') === 'paid' ? 'selected' : '' }}>Paid</option>
                <option value="refunded" {{ request('payment_status') === 'refunded' ? 'selected' : '' }}>Refunded</option>
                <option value="pending" {{ request('payment_status') === 'pending' ? 'selected' : '' }}>Pending</option>
            </select>
        </div>

        <div class="col-6 col-md-3">
            <select name="payment_type" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="">All Payment Methods</option>
                <option value="cash" {{ request('payment_type') === 'cash' ? 'selected' : '' }}>Cash</option>
                <option value="card" {{ request('payment_type') === 'card' ? 'selected' : '' }}>Card</option>
                <option value="qr" {{ request('payment_type') === 'qr' ? 'selected' : '' }}>QR Payment</option>
            </select>
        </div>

        <div class="col-12 col-md-6 d-flex justify-content-md-end gap-2">
            @if(request()->hasAny(['payment_status', 'payment_type']))
                <a href="{{ route('customers.show', $customer) }}" class="btn btn-sm btn-light border">Reset Filters</a>
            @endif
        </div>
    </form>
</div>

<!-- Purchase History Table -->
<x-card :noPadding="true" title="Purchase & Invoice History" badge="{{ $sales->total() }} transactions">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" style="font-size: 0.88rem;">
            <thead class="table-light text-muted" style="font-size: 0.78rem; text-transform: uppercase;">
                <tr>
                    <th class="ps-4">Invoice #</th>
                    <th>Date & Time</th>
                    <th>Cashier</th>
                    <th>Payment Method</th>
                    <th>Subtotal</th>
                    <th>VIP Discount</th>
                    <th>Final Total</th>
                    <th>Payment Status</th>
                    <th class="text-end pe-4">Receipt</th>
                </tr>
            </thead>
            <tbody>
                @forelse($sales as $sale)
                <tr>
                    <td class="ps-4">
                        <a href="{{ route('sales.show', $sale) }}" class="font-monospace fw-bold text-dark text-decoration-none hover-primary">
                            {{ $sale->invoice_number }}
                        </a>
                    </td>
                    <td class="text-muted small">
                        <div>{{ $sale->created_at->format('M j, Y') }}</div>
                        <div style="font-size: 0.72rem;">{{ $sale->created_at->format('h:i A') }}</div>
                    </td>
                    <td>
                        <span class="badge bg-light text-muted border">
                            <i class="bi bi-person me-1"></i>{{ $sale->user->name ?? 'Cashier' }}
                        </span>
                    </td>
                    <td>
                        <span class="badge {{ match($sale->payment_type) {
                            'cash' => 'bg-success-subtle text-success',
                            'card' => 'bg-primary-subtle text-primary',
                            'qr' => 'bg-info-subtle text-info',
                            default => 'bg-light text-dark',
                        } }} border px-2 py-1 text-uppercase" style="font-size: 0.72rem;">
                            {{ $sale->payment_type }}
                        </span>
                    </td>
                    <td class="text-muted font-monospace">${{ number_format($sale->subtotal, 2) }}</td>
                    <td class="font-monospace {{ $sale->discount > 0 ? 'text-danger' : 'text-muted' }}">
                        {{ $sale->discount > 0 ? '-$' . number_format($sale->discount, 2) : '—' }}
                    </td>
                    <td class="font-monospace fw-bold text-success">${{ number_format($sale->final_total, 2) }}</td>
                    <td>
                        <x-status-badge :status="$sale->payment_status" />
                    </td>
                    <td class="text-end pe-4">
                        <div class="d-flex align-items-center justify-content-end gap-1">
                            <a href="{{ route('sales.show', $sale) }}" class="btn btn-xs btn-light border px-2 py-1" title="View Sale Details">
                                <i class="bi bi-eye"></i>
                            </a>
                            <a href="{{ route('pos.receipt', $sale) }}" target="_blank" class="btn btn-xs btn-light border text-muted px-2 py-1" title="Print Receipt">
                                <i class="bi bi-printer"></i>
                            </a>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="9">
                        <x-empty-state 
                            icon="bi-receipt" 
                            title="No purchases found" 
                            description="This customer does not have any recorded purchase transactions matching the filter." />
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($sales->hasPages())
    <x-slot:footer>
        {{ $sales->links() }}
    </x-slot:footer>
    @endif
</x-card>

<!-- Edit Customer Modal -->
<x-modal id="editCustomerModal" title="Edit Customer Profile" size="md">
    <form method="POST" action="{{ route('customers.update', $customer) }}">
        @csrf
        @method('PUT')

        <x-form-input name="name" label="Customer Name" :value="$customer->name" required />

        <div class="row g-2">
            <div class="col-6">
                <x-form-input name="phone" label="Phone" :value="$customer->phone" />
            </div>
            <div class="col-6">
                <x-form-input type="email" name="email" label="Email" :value="$customer->email" />
            </div>
        </div>

        <div class="row g-2">
            <div class="col-6">
                <x-form-select name="membership_level" label="VIP Loyalty Tier" required>
                    <option value="Bronze" {{ $customer->membership_level === 'Bronze' ? 'selected' : '' }}>Bronze (0% discount)</option>
                    <option value="Silver" {{ $customer->membership_level === 'Silver' ? 'selected' : '' }}>Silver (5% discount)</option>
                    <option value="Gold" {{ $customer->membership_level === 'Gold' ? 'selected' : '' }}>Gold (10% discount)</option>
                    <option value="Platinum" {{ $customer->membership_level === 'Platinum' ? 'selected' : '' }}>Platinum (15% discount)</option>
                </x-form-select>
            </div>
            <div class="col-6">
                <x-form-select name="status" label="Account Status" required>
                    <option value="active" {{ $customer->status === 'active' ? 'selected' : '' }}>Active</option>
                    <option value="inactive" {{ $customer->status === 'inactive' ? 'selected' : '' }}>Inactive</option>
                </x-form-select>
            </div>
        </div>

        <x-form-input name="membership_number" label="Membership Number" :value="$customer->membership_number" />

        <x-form-textarea name="address" label="Address" :value="$customer->address" rows="2" />

        <div class="d-flex justify-content-end gap-2 mt-4">
            <button type="button" class="btn btn-sm btn-light border" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-sm btn-skincare-primary">Save Changes</button>
        </div>
    </form>
</x-modal>
@endsection
