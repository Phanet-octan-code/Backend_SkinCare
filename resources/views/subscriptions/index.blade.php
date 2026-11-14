@extends('layouts.app')

@section('title', 'Skincare Subscriptions')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none text-muted">Sales & POS</a></li>
    <li class="breadcrumb-item active text-dark" aria-current="page">Subscriptions</li>
@endsection

@section('content')
<x-page-header 
    title="Recurring Skincare Subscriptions" 
    description="Manage customer replenishment plans, scheduled deliveries, and automated recurring billing."
    :breadcrumbs="['Sales & POS' => route('sales.index'), 'Subscriptions' => null]">
    <x-slot:actions>
        <form action="{{ route('subscriptions.renew') }}" method="POST" class="d-inline">
            @csrf
            <button type="submit" class="btn btn-outline-primary d-inline-flex align-items-center gap-2">
                <i class="bi bi-arrow-repeat"></i> Process Due Renewals Now
            </button>
        </form>
    </x-slot:actions>
</x-page-header>

<!-- Filter Tabs -->
<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4 border-bottom pb-2">
    <ul class="nav nav-pills gap-1">
        <li class="nav-item">
            <a class="nav-link py-1 px-3 small {{ request('status', 'all') === 'all' ? 'active bg-skincare-primary' : 'text-muted' }}" 
               href="{{ route('subscriptions.index', ['status' => 'all']) }}">
                All Plans <span class="badge bg-light text-dark ms-1">{{ $counts['all'] }}</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link py-1 px-3 small {{ request('status') === 'active' ? 'active bg-skincare-primary' : 'text-muted' }}" 
               href="{{ route('subscriptions.index', ['status' => 'active']) }}">
                <i class="bi bi-check-circle-fill text-success me-1"></i> Active 
                <span class="badge bg-light text-dark ms-1">{{ $counts['active'] }}</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link py-1 px-3 small {{ request('status') === 'paused' ? 'active bg-skincare-primary' : 'text-muted' }}" 
               href="{{ route('subscriptions.index', ['status' => 'paused']) }}">
                <i class="bi bi-pause-circle text-warning me-1"></i> Paused 
                <span class="badge bg-light text-dark ms-1">{{ $counts['paused'] }}</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link py-1 px-3 small {{ request('status') === 'cancelled' ? 'active bg-skincare-primary' : 'text-muted' }}" 
               href="{{ route('subscriptions.index', ['status' => 'cancelled']) }}">
                <i class="bi bi-x-circle text-muted me-1"></i> Cancelled 
                <span class="badge bg-light text-dark ms-1">{{ $counts['cancelled'] }}</span>
            </a>
        </li>
    </ul>

    <!-- Quick Search -->
    <form method="GET" action="{{ route('subscriptions.index') }}" class="d-flex align-items-center gap-2">
        <input type="hidden" name="status" value="{{ request('status', 'all') }}">
        <div class="input-group input-group-sm">
            <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
            <input type="text" name="search" class="form-control border-start-0" placeholder="Search customer..." value="{{ request('search') }}" style="border-radius: 0 8px 8px 0; max-width: 220px;">
        </div>
        @if(request('search'))
            <a href="{{ route('subscriptions.index', ['status' => request('status', 'all')]) }}" class="btn btn-sm btn-light border text-muted">Reset</a>
        @endif
    </form>
</div>

<!-- Subscriptions Table -->
<x-table 
    :headers="[
        ['label' => 'Subscription ID', 'class' => 'ps-4'],
        'Customer',
        'Replenishment Item',
        'Cadence',
        'Billing Amount',
        'Next Delivery',
        'Status',
        ['label' => 'Actions', 'class' => 'pe-4 text-end']
    ]">

    <x-slot:cardHeader>
        <div class="d-flex align-items-center justify-content-between w-100">
            <h5 class="fw-bold mb-0 fs-6 text-dark">Active Replenishment Cadences</h5>
            <span class="badge bg-light text-muted border">{{ $subscriptions->total() }} subscribers</span>
        </div>
    </x-slot:cardHeader>

    @forelse($subscriptions as $sub)
    <tr>
        <td class="ps-4 fw-bold text-dark">
            <a href="{{ route('subscriptions.show', $sub) }}" class="text-decoration-none text-dark">#SUB-{{ str_pad($sub->id, 5, '0', STR_PAD_LEFT) }}</a>
        </td>
        <td>
            <div>
                <span class="fw-semibold text-dark">{{ $sub->customer?->name ?? 'Guest/Unknown' }}</span>
                <div class="text-muted small">{{ $sub->customer?->email }}</div>
            </div>
        </td>
        <td>
            <div class="d-flex align-items-center gap-2">
                @if($sub->item_image_url)
                    <img src="{{ $sub->item_image_url }}" alt="{{ $sub->item_name }}" class="rounded border object-fit-cover" style="width: 32px; height: 32px;">
                @endif
                <div>
                    <span class="fw-medium text-dark">{{ $sub->item_name }}</span>
                    <span class="text-muted small d-block">Qty: {{ $sub->quantity }}</span>
                </div>
            </div>
        </td>
        <td>
            <span class="badge bg-light text-dark border">Every {{ $sub->billing_interval_days }} Days</span>
        </td>
        <td>
            <span class="fw-bold text-dark">${{ number_format($sub->recurring_subtotal, 2) }}</span>
            <span class="text-success small d-block">({{ (int) $sub->discount_percentage }}% sub & save)</span>
        </td>
        <td>
            <span class="fw-semibold {{ $sub->next_billing_date && $sub->next_billing_date->isPast() ? 'text-danger' : 'text-dark' }}">
                {{ $sub->next_billing_date ? $sub->next_billing_date->format('M d, Y') : '-' }}
            </span>
            @if($sub->next_billing_date && $sub->next_billing_date->isPast() && $sub->isActive())
                <span class="badge bg-danger-subtle text-danger small d-block">Due for Renewal</span>
            @endif
        </td>
        <td>
            @if($sub->status === 'active')
                <span class="badge bg-success-subtle text-success">Active</span>
            @elseif($sub->status === 'paused')
                <span class="badge bg-warning-subtle text-warning">Paused</span>
            @else
                <span class="badge bg-secondary">Cancelled</span>
            @endif
        </td>
        <td class="pe-4 text-end">
            <div class="dropdown">
                <button class="btn btn-sm btn-light border-0" data-bs-toggle="dropdown">
                    <i class="bi bi-three-dots-vertical"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                    <li><a class="dropdown-item small" href="{{ route('subscriptions.show', $sub) }}"><i class="bi bi-eye me-2 text-muted"></i> View Details</a></li>
                    @if($sub->isActive())
                        <li>
                            <form action="{{ route('subscriptions.pause', $sub) }}" method="POST">
                                @csrf
                                <button type="submit" class="dropdown-item small text-warning"><i class="bi bi-pause me-2"></i> Pause Plan</button>
                            </form>
                        </li>
                    @elseif($sub->isPaused())
                        <li>
                            <form action="{{ route('subscriptions.resume', $sub) }}" method="POST">
                                @csrf
                                <button type="submit" class="dropdown-item small text-success"><i class="bi bi-play me-2"></i> Resume Plan</button>
                            </form>
                        </li>
                    @endif
                    @if(!$sub->isCancelled())
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <button type="button" class="dropdown-item small text-danger" data-bs-toggle="modal" data-bs-target="#cancelModal{{ $sub->id }}">
                                <i class="bi bi-x-circle me-2"></i> Cancel Plan
                            </button>
                        </li>
                    @endif
                </ul>
            </div>

            <!-- Cancel Modal -->
            <div class="modal fade" id="cancelModal{{ $sub->id }}" tabindex="-1">
                <div class="modal-dialog modal-dialog-centered text-start">
                    <div class="modal-content">
                        <form action="{{ route('subscriptions.cancel', $sub) }}" method="POST">
                            @csrf
                            <div class="modal-header">
                                <h5 class="modal-title fs-6 fw-bold">Cancel Subscription #{{ $sub->id }}</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <p class="text-muted small mb-3">Please specify the cancellation reason for customer records:</p>
                                <div class="mb-3">
                                    <label class="form-label small fw-semibold">Reason</label>
                                    <select name="reason" class="form-select form-select-sm" required>
                                        <option value="Customer request via support">Customer request via support</option>
                                        <option value="Discontinued product">Discontinued product</option>
                                        <option value="Payment failure">Repeated payment failure</option>
                                        <option value="Administrative intervention">Administrative intervention</option>
                                    </select>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-sm btn-light" data-bs-dismiss="modal">Close</button>
                                <button type="submit" class="btn btn-sm btn-danger">Confirm Cancellation</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </td>
    </tr>
    @empty
    <tr>
        <td colspan="8" class="text-center py-5 text-muted">
            <i class="bi bi-arrow-repeat fs-2 d-block mb-2 text-muted opacity-50"></i>
            No skincare subscriptions found.
        </td>
    </tr>
    @endforelse
</x-table>

<div class="mt-4">
    {{ $subscriptions->links() }}
</div>
@endsection

