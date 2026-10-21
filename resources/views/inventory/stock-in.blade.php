@extends('layouts.app')

@section('title', 'Stock In (Receiving)')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none text-muted">Inventory</a></li>
    <li class="breadcrumb-item"><a href="{{ route('inventory.index') }}" class="text-decoration-none text-muted">Stock Overview</a></li>
    <li class="breadcrumb-item active text-dark" aria-current="page">Stock In</li>
@endsection

@section('content')
<x-page-header 
    title="Stock In (Receiving Ledger)" 
    description="Receive wholesale purchase shipments, record batches, and update inventory balances."
    :breadcrumbs="['Inventory' => route('inventory.index'), 'Stock In' => null]">
    <x-slot:actions>
        <x-action-button roles="admin,manager,inventory" icon="bi-plus-lg" modalTarget="#receiveStockModal">
            Receive Stock
        </x-action-button>
    </x-slot:actions>
</x-page-header>

<!-- Search & Quick Filters -->
<x-search-filter :action="route('inventory.stock-in')" :resetUrl="route('inventory.stock-in')" placeholder="Search PO reference, product, notes..." />

<!-- Stock-In Table -->
<x-table 
    :headers="[
        ['label' => 'PO / Receipt Reference', 'class' => 'ps-4'],
        'Date',
        'Product / SKU',
        'Distributor / Supplier',
        'Received By',
        'Qty Received',
        ['label' => 'Notes / Batch', 'class' => 'pe-4']
    ]">

    <x-slot:cardHeader>
        <div class="d-flex align-items-center justify-content-between w-100">
            <div class="d-flex align-items-center gap-2">
                <h5 class="fw-bold mb-0 fs-6 text-dark">Supplier Receipts Ledger</h5>
                <span class="badge bg-light text-muted border">{{ $transactions->total() }} receipts</span>
            </div>
            <div class="small text-muted d-none d-md-block">
                Total Cumulative Received: <strong class="text-success">+{{ number_format($totalReceivedUnits) }} units</strong>
            </div>
        </div>
    </x-slot:cardHeader>

    @forelse($transactions as $tx)
    <tr>
        <td class="ps-4">
            <span class="fw-bold font-monospace text-dark">{{ $tx->reference_number ?? 'PO-DIRECT' }}</span>
        </td>
        <td>
            <span class="text-muted small">{{ $tx->date->format('M j, Y') }}</span>
        </td>
        <td>
            <div class="fw-semibold text-dark">{{ $tx->product->name }}</div>
            <div class="text-muted font-monospace" style="font-size: 0.72rem;">{{ $tx->product->barcode }}</div>
        </td>
        <td>
            <span class="badge bg-light text-dark border">
                <i class="bi bi-truck me-1 text-muted"></i>
                {{ $tx->supplier->name ?? 'General Supplier' }}
            </span>
        </td>
        <td>
            <span class="small text-muted">{{ $tx->user->name ?? 'System' }}</span>
        </td>
        <td>
            <span class="badge bg-success-subtle text-success fs-6 font-monospace px-2 py-1">
                +{{ $tx->qty }} units
            </span>
        </td>
        <td class="pe-4 text-muted small">
            {{ $tx->notes ?? 'Standard shipment receipt' }}
        </td>
    </tr>
    @empty
    <tr>
        <td colspan="7" class="p-0">
            <x-empty-state 
                icon="bi-box-arrow-in-down" 
                title="No Stock-In records found" 
                description="No supplier purchase orders or shipments matched your query." />
        </td>
    </tr>
    @endforelse

    @if($transactions->hasPages())
    <x-slot:pagination>
        {{ $transactions->links() }}
    </x-slot:pagination>
    @endif
</x-table>

<!-- Receive Stock Modal -->
<x-modal id="receiveStockModal" title="Receive Wholesale Stock (Stock In)" size="lg">
    <form method="POST" action="{{ route('inventory.process-stock-in') }}">
        @csrf
        <div class="row g-3">
            <div class="col-12 col-md-8">
                <label class="form-label small fw-semibold" for="stock_in_product_id">Product Formulation</label>
                <select id="stock_in_product_id" name="product_id" class="form-select form-select-sm" style="border-radius: 8px;" required>
                    <option value="">Select product to receive...</option>
                    @foreach($products as $prod)
                        <option value="{{ $prod->id }}" data-supplier-id="{{ $prod->supplier_id }}" data-cost="{{ $prod->cost_price }}">
                            {{ $prod->name }} (Current Stock: {{ $prod->qty }} units) - ${{ number_format($prod->cost_price, 2) }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-md-4">
                <x-form-input type="number" min="1" name="qty" label="Received Quantity" placeholder="e.g. 50" required />
            </div>

            <div class="col-12 col-md-6">
                <label class="form-label small fw-semibold" for="stock_in_supplier_id">Supplier / Distributor</label>
                <select id="stock_in_supplier_id" name="supplier_id" class="form-select form-select-sm" style="border-radius: 8px;">
                    <option value="">Choose supplier (or product default)...</option>
                    @foreach($suppliers as $supplier)
                        <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-md-6">
                <x-form-input type="number" step="0.01" min="0" name="unit_cost" label="Unit Purchase Cost ($)" placeholder="Leave blank to retain current cost" helperText="Optionally updates catalog wholesale cost price." />
            </div>

            <div class="col-12 col-md-6">
                <x-form-input name="reference_number" label="PO / Invoice Reference" placeholder="e.g. PO-20261022-0045" helperText="Leave blank for auto-generated reference." />
            </div>
            <div class="col-12 col-md-6">
                <label class="form-label small fw-semibold" for="stock_in_date">Delivery Date</label>
                <input type="date" id="stock_in_date" name="date" class="form-control form-control-sm" style="border-radius: 8px;" value="{{ date('Y-m-d') }}" required>
            </div>

            <div class="col-12">
                <x-form-textarea name="notes" label="Batch Number & Receiving Notes" placeholder="e.g. Batch #LOT-2026-B. Inspected and verified intact." rows="2" />
            </div>
        </div>

        <div class="d-flex justify-content-end gap-2 mt-4 pt-2 border-top">
            <button type="button" class="btn btn-sm btn-light border px-3" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-sm btn-skincare-primary px-3">
                <i class="bi bi-check-lg me-1"></i> Confirm & Receive Stock
            </button>
        </div>
    </form>
</x-modal>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const prodSelect = document.getElementById('stock_in_product_id');
    const suppSelect = document.getElementById('stock_in_supplier_id');

    if (prodSelect && suppSelect) {
        prodSelect.addEventListener('change', function () {
            const selectedOpt = prodSelect.options[prodSelect.selectedIndex];
            const defaultSuppId = selectedOpt.getAttribute('data-supplier-id');
            if (defaultSuppId && !suppSelect.value) {
                suppSelect.value = defaultSuppId;
            }
        });
    }
});
</script>
@endpush
@endsection
