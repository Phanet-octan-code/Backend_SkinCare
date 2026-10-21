@extends('layouts.app')

@section('title', 'Stock Movement History')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none text-muted">Inventory</a></li>
    <li class="breadcrumb-item"><a href="{{ route('inventory.index') }}" class="text-decoration-none text-muted">Stock Overview</a></li>
    <li class="breadcrumb-item active text-dark" aria-current="page">Movement History</li>
@endsection

@section('content')
<x-page-header 
    title="Stock Movement History & Audit Trail" 
    description="Comprehensive immutable ledger of all inventory transactions, POS deductions, receipts, and write-offs."
    :breadcrumbs="['Inventory' => route('inventory.index'), 'Audit History' => null]">
    <x-slot:actions>
        <a href="{{ route('inventory.index') }}" class="btn btn-sm btn-light border">
            <i class="bi bi-arrow-left me-1"></i> Stock Balances
        </a>
    </x-slot:actions>
</x-page-header>

<!-- Filter Controls -->
<x-card class="mb-4">
    <form method="GET" action="{{ route('inventory.history') }}">
        <div class="row g-2 align-items-center">
            <div class="col-12 col-md-3">
                <input type="text" name="search" class="form-control form-control-sm" placeholder="Search ref or notes..." value="{{ request('search') }}" style="border-radius: 8px;">
            </div>
            <div class="col-12 col-md-2">
                <select name="type" class="form-select form-select-sm" style="border-radius: 8px;">
                    <option value="all">All Movement Types</option>
                    @foreach($types as $key => $label)
                        <option value="{{ $key }}" {{ request('type') === $key ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-md-3">
                <select name="product_id" class="form-select form-select-sm" style="border-radius: 8px;">
                    <option value="">All Products</option>
                    @foreach($products as $prod)
                        <option value="{{ $prod->id }}" {{ request('product_id') == $prod->id ? 'selected' : '' }}>
                            {{ $prod->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-2">
                <input type="date" name="start_date" class="form-control form-control-sm" placeholder="From" value="{{ request('start_date') }}" style="border-radius: 8px;" title="Start Date">
            </div>
            <div class="col-6 col-md-2">
                <div class="d-flex gap-1">
                    <input type="date" name="end_date" class="form-control form-control-sm" placeholder="To" value="{{ request('end_date') }}" style="border-radius: 8px;" title="End Date">
                    <button type="submit" class="btn btn-sm btn-skincare-primary px-3">Filter</button>
                    @if(request()->hasAny(['search', 'type', 'product_id', 'start_date', 'end_date']))
                        <a href="{{ route('inventory.history') }}" class="btn btn-sm btn-light border" title="Reset Filters"><i class="bi bi-x-lg"></i></a>
                    @endif
                </div>
            </div>
        </div>
    </form>
</x-card>

<!-- History Table -->
<x-table 
    :headers="[
        ['label' => 'Date & Time', 'class' => 'ps-4'],
        'Movement Type',
        'Product / SKU',
        'Quantity Delta',
        'Reference Voucher',
        'Responsible User',
        ['label' => 'Audit Justification & Notes', 'class' => 'pe-4']
    ]">

    <x-slot:cardHeader>
        <div class="d-flex align-items-center justify-content-between w-100">
            <div class="d-flex align-items-center gap-2">
                <h5 class="fw-bold mb-0 fs-6 text-dark">Transaction Ledger</h5>
                <span class="badge bg-light text-muted border">{{ $transactions->total() }} movements</span>
            </div>
        </div>
    </x-slot:cardHeader>

    @forelse($transactions as $tx)
    @php
        $badgeClass = match($tx->type) {
            'stock-in' => 'bg-success-subtle text-success border border-success-subtle',
            'return' => 'bg-info-subtle text-info border border-info-subtle',
            'sale' => 'bg-primary-subtle text-primary border border-primary-subtle',
            'damaged' => 'bg-danger-subtle text-danger border border-danger-subtle',
            'transfer' => 'bg-secondary-subtle text-secondary border border-secondary-subtle',
            'stock-out' => 'bg-warning-subtle text-warning border border-warning-subtle',
            default => 'bg-light text-dark border',
        };

        $typeLabel = match($tx->type) {
            'stock-in' => 'Stock In',
            'return' => 'Return',
            'sale' => 'POS Sale',
            'damaged' => 'Damaged',
            'transfer' => 'Transfer',
            'stock-out' => 'Stock Out',
            default => ucfirst($tx->type),
        };
    @endphp
    <tr>
        <td class="ps-4">
            <span class="fw-semibold text-dark d-block">{{ $tx->date->format('M j, Y') }}</span>
            <span class="text-muted" style="font-size: 0.72rem;">{{ $tx->created_at->format('H:i:s') }}</span>
        </td>
        <td>
            <span class="badge {{ $badgeClass }} px-2 py-1 font-monospace" style="font-size: 0.75rem;">
                {{ $typeLabel }}
            </span>
        </td>
        <td>
            <div class="fw-semibold text-dark">{{ $tx->product->name }}</div>
            <div class="text-muted font-monospace" style="font-size: 0.72rem;">{{ $tx->product->barcode }}</div>
        </td>
        <td>
            @if($tx->qty > 0)
                <span class="badge bg-success-subtle text-success fs-6 font-monospace px-2 py-1">
                    +{{ $tx->qty }}
                </span>
            @else
                <span class="badge bg-danger-subtle text-danger fs-6 font-monospace px-2 py-1">
                    {{ $tx->qty }}
                </span>
            @endif
        </td>
        <td>
            <span class="fw-bold font-monospace text-dark">{{ $tx->reference_number ?? '—' }}</span>
        </td>
        <td>
            <span class="small text-muted">{{ $tx->user->name ?? 'System' }}</span>
        </td>
        <td class="pe-4 text-muted small">
            {{ $tx->notes ?? '—' }}
        </td>
    </tr>
    @empty
    <tr>
        <td colspan="7" class="p-0">
            <x-empty-state 
                icon="bi-clock-history" 
                title="No stock transactions found" 
                description="No inventory movement records match your search or date filter." />
        </td>
    </tr>
    @endforelse

    @if($transactions->hasPages())
    <x-slot:pagination>
        {{ $transactions->links() }}
    </x-slot:pagination>
    @endif
</x-table>
@endsection
