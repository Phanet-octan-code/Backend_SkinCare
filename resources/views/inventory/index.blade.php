@extends('layouts.app')

@section('title', 'Inventory & Stock Balances')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none text-muted">Inventory</a></li>
    <li class="breadcrumb-item active text-dark" aria-current="page">Stock Overview</li>
@endsection

@section('content')
<x-page-header 
    title="Inventory & Stock Balances" 
    description="Single source of truth inventory valuation, warehouse stock balances, and replenishment thresholds."
    :breadcrumbs="['Inventory' => null, 'Stock Overview' => null]">
    <x-slot:actions>
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('inventory.stock-in') }}" class="btn btn-sm btn-skincare-primary d-inline-flex align-items-center gap-1">
                <i class="bi bi-box-arrow-in-down"></i>
                <span>Stock In</span>
            </a>
            <a href="{{ route('inventory.stock-out') }}" class="btn btn-sm btn-light border d-inline-flex align-items-center gap-1">
                <i class="bi bi-box-arrow-up-right"></i>
                <span>Stock Out</span>
            </a>
            <a href="{{ route('inventory.transfers') }}" class="btn btn-sm btn-light border d-inline-flex align-items-center gap-1">
                <i class="bi bi-arrow-left-right"></i>
                <span>Transfer</span>
            </a>
            <a href="{{ route('inventory.history') }}" class="btn btn-sm btn-light border d-inline-flex align-items-center gap-1">
                <i class="bi bi-clock-history"></i>
                <span>Audit History</span>
            </a>
        </div>
    </x-slot:actions>
</x-page-header>

<!-- Inventory Valuation & Status KPI Metrics -->
<div class="row g-3 mb-4">
    <div class="col-12 col-sm-6 col-xl-3">
        <x-kpi-card 
            title="Total Cost Valuation" 
            :value="'$' . number_format($valuation['total_cost_value'], 2)" 
            subtitle="Calculated at wholesale cost"
            icon="bi-cash-coin" 
            color="sage" />
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <x-kpi-card 
            title="Retail Potential" 
            :value="'$' . number_format($valuation['total_retail_value'], 2)" 
            subtitle="'$' . number_format($valuation['potential_profit'], 2) . ' projected gross margin'"
            icon="bi-graph-up-arrow" 
            color="success" />
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <x-kpi-card 
            title="Low Stock Alerts" 
            :value="$valuation['low_stock_count']" 
            subtitle="At or below reorder threshold"
            icon="bi-exclamation-triangle" 
            color="warning" />
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <x-kpi-card 
            title="Depleted / Out of Stock" 
            :value="$valuation['out_of_stock_count']" 
            subtitle="Requires supplier replenishment"
            icon="bi-x-octagon" 
            color="danger" />
    </div>
</div>

<!-- Search & Filtering Controls -->
<x-search-filter :action="route('inventory.index')" :resetUrl="route('inventory.index')" placeholder="Search product name or barcode...">
    <div class="col-12 col-md-3">
        <select name="category_id" class="form-select form-select-sm" style="border-radius: 8px;">
            <option value="">All Categories</option>
            @foreach($categories as $category)
                <option value="{{ $category->id }}" {{ request('category_id') == $category->id ? 'selected' : '' }}>
                    {{ $category->name }}
                </option>
            @endforeach
        </select>
    </div>
    <div class="col-12 col-md-3">
        <select name="stock_status" class="form-select form-select-sm" style="border-radius: 8px;">
            <option value="">All Stock Levels</option>
            <option value="in_stock" {{ request('stock_status') === 'in_stock' ? 'selected' : '' }}>In Stock (Normal)</option>
            <option value="low" {{ request('stock_status') === 'low' ? 'selected' : '' }}>Low Stock Alerts</option>
            <option value="out" {{ request('stock_status') === 'out' ? 'selected' : '' }}>Out of Stock</option>
        </select>
    </div>
    <div class="col-12 col-md-3">
        <select name="sort" class="form-select form-select-sm" style="border-radius: 8px;">
            <option value="latest" {{ request('sort') === 'latest' ? 'selected' : '' }}>Newest Products</option>
            <option value="product_name" {{ request('sort') === 'product_name' ? 'selected' : '' }}>Product Name (A-Z)</option>
            <option value="qty_asc" {{ request('sort') === 'qty_asc' ? 'selected' : '' }}>Stock: Low to High</option>
            <option value="qty_desc" {{ request('sort') === 'qty_desc' ? 'selected' : '' }}>Stock: High to Low</option>
        </select>
    </div>
</x-search-filter>

<!-- Inventory Stock Balances Table -->
<x-table 
    :headers="[
        ['label' => 'Product / SKU', 'class' => 'ps-4'],
        'Category',
        'Barcode',
        'Stock Balance',
        'Cost / Retail Price',
        'Total Valuation',
        'Reorder Threshold',
        ['label' => 'Sync Status', 'class' => 'pe-4 text-end']
    ]">

    <x-slot:cardHeader>
        <div class="d-flex align-items-center justify-content-between w-100">
            <div class="d-flex align-items-center gap-2">
                <h5 class="fw-bold mb-0 fs-6 text-dark">Stock Balances Ledger</h5>
                <span class="badge bg-light text-muted border">{{ $stocks->total() }} tracked SKUs</span>
            </div>
            <div class="small text-muted d-none d-md-block">
                Total Units on Hand: <strong class="text-dark">{{ number_format($valuation['total_units']) }}</strong>
            </div>
        </div>
    </x-slot:cardHeader>

    @forelse($stocks as $stock)
    @php
        $product = $stock->product;
        $isOutOfStock = $stock->qty <= 0;
        $isLowStock = !$isOutOfStock && $stock->qty <= $product->low_stock_threshold;
        $costValuation = $stock->qty * (float) $product->cost_price;
    @endphp
    <tr>
        <td class="ps-4">
            <div class="d-flex align-items-center gap-2">
                @if($product->image && file_exists(public_path($product->image)))
                    <img src="{{ asset($product->image) }}" alt="{{ $product->name }}" class="rounded-2 border shadow-xs" style="width: 42px; height: 42px; object-fit: contain; background-color: #F8F9F7; padding: 2px;">
                @else
                    <span class="brand-icon-box" style="width: 42px; height: 42px; font-size: 1.15rem;">
                        <i class="bi bi-droplet-half"></i>
                    </span>
                @endif
                <div>
                    <span class="fw-bold text-dark d-block">{{ $product->name }}</span>
                    <span class="text-muted" style="font-size: 0.72rem;">Supplier: {{ $product->supplier->name ?? 'Direct' }}</span>
                </div>
            </div>
        </td>
        <td>
            <span class="badge bg-light text-dark border">
                {{ $product->category->name ?? 'General' }}
            </span>
        </td>
        <td>
            <span class="font-monospace text-muted small">{{ $product->barcode }}</span>
        </td>
        <td>
            <div class="d-flex align-items-center gap-2">
                <span class="fw-bold font-monospace fs-6 {{ $isOutOfStock ? 'text-danger' : ($isLowStock ? 'text-warning' : 'text-success') }}">
                    {{ $stock->qty }} units
                </span>
                @if($isOutOfStock)
                    <span class="badge bg-danger text-white" style="font-size: 0.65rem;">Out of Stock</span>
                @elseif($isLowStock)
                    <span class="badge bg-warning-subtle text-warning border border-warning-subtle" style="font-size: 0.65rem;">Low</span>
                @endif
            </div>
        </td>
        <td>
            <div class="small">
                <span class="text-muted">${{ number_format($product->cost_price, 2) }}</span>
                <span class="text-muted">/</span>
                <span class="fw-bold text-dark">${{ number_format($product->sale_price, 2) }}</span>
            </div>
        </td>
        <td>
            <span class="fw-semibold text-dark">${{ number_format($costValuation, 2) }}</span>
        </td>
        <td>
            <div class="d-flex align-items-center gap-2">
                <span class="text-muted small font-monospace">{{ $product->low_stock_threshold }} units</span>
                <button type="button" 
                        class="btn btn-sm btn-link p-0 text-muted" 
                        title="Configure Reorder Alert Threshold"
                        data-bs-toggle="modal" 
                        data-bs-target="#thresholdModal"
                        data-product-id="{{ $product->id }}"
                        data-product-name="{{ $product->name }}"
                        data-threshold="{{ $product->low_stock_threshold }}"
                        data-action="{{ route('inventory.update-threshold', $product) }}">
                    <i class="bi bi-pencil-square" style="font-size: 0.78rem;"></i>
                </button>
            </div>
        </td>
        <td class="pe-4 text-end">
            @if($stock->qty === $product->qty)
                <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1" style="font-size: 0.72rem;">
                    <i class="bi bi-check2-circle me-1"></i> Synchronized
                </span>
            @else
                <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1" style="font-size: 0.72rem;">
                    <i class="bi bi-exclamation-octagon me-1"></i> Mismatch
                </span>
            @endif
        </td>
    </tr>
    @empty
    <tr>
        <td colspan="8" class="p-0">
            <x-empty-state 
                icon="bi-boxes" 
                title="No inventory balances found" 
                description="No products matched your search or stock filter. Try resetting filters." />
        </td>
    </tr>
    @endforelse

    @if($stocks->hasPages())
    <x-slot:pagination>
        {{ $stocks->links() }}
    </x-slot:pagination>
    @endif
</x-table>

<!-- Configure Threshold Modal -->
<x-modal id="thresholdModal" title="Configure Low-Stock Alert Threshold" size="sm">
    <form id="thresholdForm" method="POST" action="">
        @csrf
        <div class="mb-3">
            <label class="form-label small text-muted">Product</label>
            <div id="modal_product_name" class="fw-bold text-dark small mb-2"></div>
            <label class="form-label small fw-semibold" for="modal_threshold">Alert Threshold (units)</label>
            <input type="number" min="1" id="modal_threshold" name="low_stock_threshold" class="form-control form-control-sm" style="border-radius: 8px;" required>
            <div class="form-text" style="font-size: 0.72rem;">When stock drops to or below this quantity, a low-stock alert is raised.</div>
        </div>
        <div class="d-flex justify-content-end gap-2 pt-2 border-top">
            <button type="button" class="btn btn-sm btn-light border px-3" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-sm btn-skincare-primary px-3">Update Alert</button>
        </div>
    </form>
</x-modal>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const thresholdModal = document.getElementById('thresholdModal');
    if (thresholdModal) {
        thresholdModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            if (!button) return;

            document.getElementById('thresholdForm').action = button.getAttribute('data-action');
            document.getElementById('modal_product_name').textContent = button.getAttribute('data-product-name') || '';
            document.getElementById('modal_threshold').value = button.getAttribute('data-threshold') || 10;
        });
    }
});
</script>
@endpush
@endsection
