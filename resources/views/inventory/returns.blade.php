@extends('layouts.app')

@section('title', 'Returns & Restock')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none text-muted">Inventory</a></li>
    <li class="breadcrumb-item"><a href="{{ route('inventory.index') }}" class="text-decoration-none text-muted">Stock Overview</a></li>
    <li class="breadcrumb-item active text-dark" aria-current="page">Returns</li>
@endsection

@section('content')
<x-page-header 
    title="Returns & Restocking Management" 
    description="Process customer invoice returns with condition checks, and manage return-to-vendor supplier shipments."
    :breadcrumbs="['Inventory' => route('inventory.index'), 'Returns' => null]">
    <x-slot:actions>
        <div class="d-flex gap-2">
            <x-action-button roles="admin,manager,inventory" icon="bi-arrow-counterclockwise" modalTarget="#customerReturnModal">
                Customer Return
            </x-action-button>
            <button type="button" class="btn btn-sm btn-light border d-inline-flex align-items-center gap-1" data-bs-toggle="modal" data-bs-target="#supplierReturnModal">
                <i class="bi bi-box-arrow-right"></i>
                <span>Return to Supplier</span>
            </button>
        </div>
    </x-slot:actions>
</x-page-header>

<!-- Search Filter -->
<x-search-filter :action="route('inventory.returns')" :resetUrl="route('inventory.returns')" placeholder="Search return reference, invoice, product, notes..." />

<!-- Returns Table -->
<x-table 
    :headers="[
        ['label' => 'Reference / Invoice', 'class' => 'ps-4'],
        'Date',
        'Product / SKU',
        'Return Type & Condition',
        'Processed By',
        'Inventory Delta',
        ['label' => 'Reason & Audit Notes', 'class' => 'pe-4']
    ]">

    <x-slot:cardHeader>
        <div class="d-flex align-items-center justify-content-between w-100">
            <div class="d-flex align-items-center gap-2">
                <h5 class="fw-bold mb-0 fs-6 text-dark">Returns Ledger</h5>
                <span class="badge bg-light text-muted border">{{ $transactions->total() }} return events</span>
            </div>
        </div>
    </x-slot:cardHeader>

    @forelse($transactions as $tx)
    @php
        $isCustomerReturn = str_contains($tx->notes ?? '', 'Customer return');
        $isResellable = str_contains($tx->notes ?? '', 'Resellable');
        $isVendorReturn = str_contains($tx->notes ?? '', 'Return to Vendor');
    @endphp
    <tr>
        <td class="ps-4">
            <span class="fw-bold font-monospace text-dark">{{ $tx->reference_number ?? 'RET-EVENT' }}</span>
        </td>
        <td>
            <span class="text-muted small">{{ $tx->date->format('M j, Y') }}</span>
        </td>
        <td>
            <div class="fw-semibold text-dark">{{ $tx->product->name }}</div>
            <div class="text-muted font-monospace" style="font-size: 0.72rem;">{{ $tx->product->barcode }}</div>
        </td>
        <td>
            @if($isCustomerReturn)
                @if($isResellable)
                    <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">
                        <i class="bi bi-arrow-repeat me-1"></i> Customer (Resellable)
                    </span>
                @else
                    <span class="badge bg-warning-subtle text-warning border border-warning-subtle px-2 py-1">
                        <i class="bi bi-exclamation-triangle me-1"></i> Customer (Damaged/Defective)
                    </span>
                @endif
            @elseif($isVendorReturn)
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1">
                    <i class="bi bi-truck me-1"></i> Return to Vendor
                </span>
            @else
                <span class="badge bg-light text-dark border px-2 py-1">Standard Return</span>
            @endif
        </td>
        <td>
            <span class="small text-muted">{{ $tx->user->name ?? 'System' }}</span>
        </td>
        <td>
            @if($tx->qty > 0)
                <span class="badge bg-success-subtle text-success fs-6 font-monospace px-2 py-1">
                    +{{ $tx->qty }} restocked
                </span>
            @else
                <span class="badge bg-danger-subtle text-danger fs-6 font-monospace px-2 py-1">
                    {{ $tx->qty }} units
                </span>
            @endif
        </td>
        <td class="pe-4 text-muted small">
            {{ $tx->notes ?? '—' }}
        </td>
    </tr>
    @empty
    <tr>
        <td colspan="7" class="p-0">
            <x-empty-state 
                icon="bi-arrow-counterclockwise" 
                title="No returns recorded" 
                description="No customer refund restocking or supplier return transactions matched your query." />
        </td>
    </tr>
    @endforelse

    @if($transactions->hasPages())
    <x-slot:pagination>
        {{ $transactions->links() }}
    </x-slot:pagination>
    @endif
</x-table>

<!-- Customer Return Modal (Linked to Sale Invoice) -->
<x-modal id="customerReturnModal" title="Process Customer Return Against Invoice" size="lg">
    <form method="POST" action="{{ route('inventory.process-customer-return') }}">
        @csrf
        <div class="row g-3">
            <div class="col-12 col-md-6">
                <label class="form-label small fw-semibold" for="ret_sale_id">Original Sales Invoice</label>
                <select id="ret_sale_id" name="sale_id" class="form-select form-select-sm" style="border-radius: 8px;" required>
                    <option value="">Select sales invoice...</option>
                    @foreach($recentSales as $sale)
                        <option value="{{ $sale->id }}" data-details="{{ json_encode($sale->details->map(fn($d) => ['product_id' => $d->product_id, 'product_name' => $d->product->name ?? 'Product #' . $d->product_id, 'qty' => $d->qty])) }}">
                            #{{ $sale->invoice_number }} — {{ $sale->created_at->format('M d, Y') }} (${{ number_format($sale->final_total, 2) }})
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-md-6">
                <label class="form-label small fw-semibold" for="ret_product_id">Formulation Returned</label>
                <select id="ret_product_id" name="product_id" class="form-select form-select-sm" style="border-radius: 8px;" required>
                    <option value="">Select invoice first...</option>
                </select>
            </div>

            <div class="col-12 col-md-4">
                <x-form-input type="number" min="1" id="ret_qty" name="qty" label="Return Quantity" placeholder="e.g. 1" required />
            </div>
            <div class="col-12 col-md-8">
                <label class="form-label small fw-semibold" for="ret_condition">Item Condition Assessment</label>
                <select id="ret_condition" name="condition" class="form-select form-select-sm" style="border-radius: 8px;" required>
                    <option value="resellable" selected>Resellable (Unopened / Seal Intact - Restock to Inventory)</option>
                    <option value="damaged">Damaged / Defective (Opened / Formula Spoiled - Do NOT restock)</option>
                </select>
                <div class="form-text" style="font-size: 0.72rem;">Damaged items are refunded to the customer but excluded from available stock.</div>
            </div>

            <div class="col-12">
                <x-form-textarea name="reason" label="Customer Return Reason & Verification" placeholder="e.g. Customer purchased incorrect formulation; returned unopened in original box." rows="2" required />
            </div>
        </div>

        <div class="d-flex justify-content-end gap-2 mt-4 pt-2 border-top">
            <button type="button" class="btn btn-sm btn-light border px-3" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-sm btn-skincare-primary px-3">
                <i class="bi bi-check2-circle me-1"></i> Process Customer Return
            </button>
        </div>
    </form>
</x-modal>

<!-- Supplier Return Modal (Return to Vendor) -->
<x-modal id="supplierReturnModal" title="Return Stock to Supplier (RTV)" size="lg">
    <form method="POST" action="{{ route('inventory.process-supplier-return') }}">
        @csrf
        <div class="row g-3">
            <div class="col-12 col-md-6">
                <label class="form-label small fw-semibold" for="rtv_product_id">Product Formulation</label>
                <select id="rtv_product_id" name="product_id" class="form-select form-select-sm" style="border-radius: 8px;" required>
                    <option value="">Select product...</option>
                    @foreach($products as $prod)
                        <option value="{{ $prod->id }}" data-supplier-id="{{ $prod->supplier_id }}" data-qty="{{ $prod->qty }}">
                            {{ $prod->name }} (Available: {{ $prod->qty }} units)
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-md-6">
                <label class="form-label small fw-semibold" for="rtv_supplier_id">Distributor / Supplier</label>
                <select id="rtv_supplier_id" name="supplier_id" class="form-select form-select-sm" style="border-radius: 8px;" required>
                    <option value="">Select supplier...</option>
                    @foreach($suppliers as $supplier)
                        <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="col-12 col-md-4">
                <x-form-input type="number" min="1" name="qty" label="Units to Return" placeholder="e.g. 10" required />
            </div>
            <div class="col-12 col-md-4">
                <x-form-input name="reference_number" label="Supplier RMA / Credit Ref" placeholder="e.g. RMA-20261022-001" helperText="Supplier return authorization reference." />
            </div>
            <div class="col-12 col-md-4">
                <label class="form-label small fw-semibold" for="rtv_date">Shipment Date</label>
                <input type="date" id="rtv_date" name="date" class="form-control form-control-sm" style="border-radius: 8px;" value="{{ date('Y-m-d') }}" required>
            </div>

            <div class="col-12">
                <x-form-textarea name="reason" label="Return Reason (Vendor Credit Request)" placeholder="e.g. Near expiry batch replacement under supplier warranty agreement." rows="2" required />
            </div>
        </div>

        <div class="d-flex justify-content-end gap-2 mt-4 pt-2 border-top">
            <button type="button" class="btn btn-sm btn-light border px-3" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-sm btn-danger px-3">
                <i class="bi bi-box-arrow-right me-1"></i> Dispatch Supplier Return
            </button>
        </div>
    </form>
</x-modal>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const saleSelect = document.getElementById('ret_sale_id');
    const prodSelect = document.getElementById('ret_product_id');
    const qtyInput = document.getElementById('ret_qty');

    if (saleSelect && prodSelect) {
        saleSelect.addEventListener('change', function () {
            const selectedOpt = saleSelect.options[saleSelect.selectedIndex];
            prodSelect.innerHTML = '<option value="">Select product from invoice...</option>';

            if (!selectedOpt || !selectedOpt.value) return;

            let details = [];
            try {
                details = JSON.parse(selectedOpt.getAttribute('data-details') || '[]');
            } catch (e) {
                details = [];
            }

            details.forEach(d => {
                const opt = document.createElement('option');
                opt.value = d.product_id;
                opt.textContent = `${d.product_name} (Sold: ${d.qty} units)`;
                opt.setAttribute('data-sold-qty', d.qty);
                prodSelect.appendChild(opt);
            });
        });

        prodSelect.addEventListener('change', function () {
            const selectedOpt = prodSelect.options[prodSelect.selectedIndex];
            if (selectedOpt) {
                const soldQty = selectedOpt.getAttribute('data-sold-qty');
                if (soldQty && qtyInput) {
                    qtyInput.max = soldQty;
                    qtyInput.value = 1;
                }
            }
        });
    }

    // Auto-select supplier on RTV product select
    const rtvProd = document.getElementById('rtv_product_id');
    const rtvSupp = document.getElementById('rtv_supplier_id');
    if (rtvProd && rtvSupp) {
        rtvProd.addEventListener('change', function () {
            const selectedOpt = rtvProd.options[rtvProd.selectedIndex];
            const suppId = selectedOpt.getAttribute('data-supplier-id');
            if (suppId) {
                rtvSupp.value = suppId;
            }
        });
    }
});
</script>
@endpush
@endsection
