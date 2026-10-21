@extends('layouts.app')

@section('title', 'Stock Out (Adjustments)')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none text-muted">Inventory</a></li>
    <li class="breadcrumb-item"><a href="{{ route('inventory.index') }}" class="text-decoration-none text-muted">Stock Overview</a></li>
    <li class="breadcrumb-item active text-dark" aria-current="page">Stock Out</li>
@endsection

@section('content')
<x-page-header 
    title="Stock Out (Non-Sale Deductions)" 
    description="Record authorized manual deductions, tester samples, internal clinic usage, and shrinkage."
    :breadcrumbs="['Inventory' => route('inventory.index'), 'Stock Out' => null]">
    <x-slot:actions>
        <x-action-button roles="admin,manager,inventory" icon="bi-dash-lg" modalTarget="#stockOutModal">
            Record Stock Out
        </x-action-button>
    </x-slot:actions>
</x-page-header>

<!-- Search Filter -->
<x-search-filter :action="route('inventory.stock-out')" :resetUrl="route('inventory.stock-out')" placeholder="Search stock-out reference, reason, notes..." />

<!-- Stock-Out Table -->
<x-table 
    :headers="[
        ['label' => 'Reference Voucher', 'class' => 'ps-4'],
        'Date',
        'Product / SKU',
        'Authorized By',
        'Quantity Removed',
        ['label' => 'Reason & Justification', 'class' => 'pe-4']
    ]">

    <x-slot:cardHeader>
        <div class="d-flex align-items-center justify-content-between w-100">
            <div class="d-flex align-items-center gap-2">
                <h5 class="fw-bold mb-0 fs-6 text-dark">Stock-Out Adjustments Ledger</h5>
                <span class="badge bg-light text-muted border">{{ $transactions->total() }} records</span>
            </div>
            <div class="small text-muted d-none d-md-block">
                Total Deducted Units: <strong class="text-danger">-{{ number_format($totalDeductedUnits) }} units</strong>
            </div>
        </div>
    </x-slot:cardHeader>

    @forelse($transactions as $tx)
    <tr>
        <td class="ps-4">
            <span class="fw-bold font-monospace text-dark">{{ $tx->reference_number ?? 'SO-ADJUST' }}</span>
        </td>
        <td>
            <span class="text-muted small">{{ $tx->date->format('M j, Y') }}</span>
        </td>
        <td>
            <div class="fw-semibold text-dark">{{ $tx->product->name }}</div>
            <div class="text-muted font-monospace" style="font-size: 0.72rem;">{{ $tx->product->barcode }}</div>
        </td>
        <td>
            <span class="small text-muted">{{ $tx->user->name ?? 'System' }}</span>
        </td>
        <td>
            <span class="badge bg-danger-subtle text-danger fs-6 font-monospace px-2 py-1">
                {{ $tx->qty }} units
            </span>
        </td>
        <td class="pe-4 text-muted small">
            {{ $tx->notes ?? 'Non-sale stock deduction' }}
        </td>
    </tr>
    @empty
    <tr>
        <td colspan="6" class="p-0">
            <x-empty-state 
                icon="bi-box-arrow-up-right" 
                title="No Stock-Out records" 
                description="No manual stock deductions or tester write-offs have been recorded." />
        </td>
    </tr>
    @endforelse

    @if($transactions->hasPages())
    <x-slot:pagination>
        {{ $transactions->links() }}
    </x-slot:pagination>
    @endif
</x-table>

<!-- Stock Out Modal -->
<x-modal id="stockOutModal" title="Authorize Stock Out Deduction" size="lg">
    <form method="POST" action="{{ route('inventory.process-stock-out') }}">
        @csrf
        <div class="row g-3">
            <div class="col-12 col-md-8">
                <label class="form-label small fw-semibold" for="stock_out_product_id">Product Formulation</label>
                <select id="stock_out_product_id" name="product_id" class="form-select form-select-sm" style="border-radius: 8px;" required>
                    <option value="">Select product to deduct...</option>
                    @foreach($products as $prod)
                        <option value="{{ $prod->id }}" data-qty="{{ $prod->qty }}">
                            {{ $prod->name }} (Available: {{ $prod->qty }} units) - SKU: {{ $prod->barcode }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-md-4">
                <x-form-input type="number" min="1" id="stock_out_qty" name="qty" label="Deduction Quantity" placeholder="e.g. 5" required />
            </div>

            <div class="col-12 col-md-6">
                <label class="form-label small fw-semibold" for="stock_out_reason">Deduction Reason (Required)</label>
                <select id="stock_out_reason" name="reason" class="form-select form-select-sm" style="border-radius: 8px;" required>
                    <option value="">Select removal reason...</option>
                    <option value="Store Tester / Counter Marketing Display">Store Tester / Counter Marketing Display</option>
                    <option value="Staff Training & Facial Demo Sample">Staff Training & Facial Demo Sample</option>
                    <option value="Internal Esthetician / Spa Suite Usage">Internal Esthetician / Spa Suite Usage</option>
                    <option value="VIP Loyalty Gift / PR Giveaway">VIP Loyalty Gift / PR Giveaway</option>
                    <option value="Inventory Reconciliation / Shrinkage">Inventory Reconciliation / Shrinkage</option>
                </select>
            </div>
            <div class="col-12 col-md-6">
                <x-form-input name="reference_number" label="Voucher / Authorization Ref" placeholder="e.g. SO-20261022-0012" helperText="Leave blank for auto-generated reference." />
            </div>

            <div class="col-12 col-md-6">
                <label class="form-label small fw-semibold" for="stock_out_date">Adjustment Date</label>
                <input type="date" id="stock_out_date" name="date" class="form-control form-control-sm" style="border-radius: 8px;" value="{{ date('Y-m-d') }}" required>
            </div>
            <div class="col-12 col-md-6">
                <x-form-input name="notes" label="Additional Details / Recipient" placeholder="e.g. Issued to Spa Suite Room 3 for esthetician treatment..." />
            </div>
        </div>

        <div class="d-flex justify-content-end gap-2 mt-4 pt-2 border-top">
            <button type="button" class="btn btn-sm btn-light border px-3" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-sm btn-danger px-3">
                <i class="bi bi-dash-circle me-1"></i> Authorize & Deduct Stock
            </button>
        </div>
    </form>
</x-modal>
@endsection
