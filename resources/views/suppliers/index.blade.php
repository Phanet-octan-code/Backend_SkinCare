@extends('layouts.app')

@section('title', 'Suppliers')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none text-muted">Inventory</a></li>
    <li class="breadcrumb-item active text-dark" aria-current="page">Suppliers</li>
@endsection

@section('content')
<x-page-header 
    title="Skincare Suppliers & Distributors" 
    description="Authorized cosmetic distributors, brand supply partners, and contact directories."
    :breadcrumbs="['Inventory' => route('inventory.index'), 'Suppliers' => null]">
    <x-slot:actions>
        <x-action-button roles="admin,manager,inventory" icon="bi-plus-lg" modalTarget="#createSupplierModal">
            Add Supplier
        </x-action-button>
    </x-slot:actions>
</x-page-header>

<!-- Supplier KPIs -->
<div class="row g-3 mb-4">
    <div class="col-12 col-sm-4">
        <x-kpi-card 
            title="Total Suppliers" 
            :value="$totalSuppliers" 
            subtitle="Registered cosmetic distributors"
            icon="bi-truck" 
            color="sage" />
    </div>
    <div class="col-12 col-sm-4">
        <x-kpi-card 
            title="Active Partners" 
            :value="$activeSuppliers" 
            subtitle="Eligible for stock replenishment"
            icon="bi-check-circle" 
            color="success" />
    </div>
    <div class="col-12 col-sm-4">
        <x-kpi-card 
            title="Inactive / Archived" 
            :value="$inactiveSuppliers" 
            subtitle="Historical references preserved"
            icon="bi-pause-circle" 
            color="warning" />
    </div>
</div>

<!-- Search & Filters -->
<x-search-filter :action="route('suppliers.index')" :resetUrl="route('suppliers.index')" placeholder="Search supplier name, email, phone, or address...">
    <div class="col-12 col-md-3">
        <select name="status" class="form-select form-select-sm" style="border-radius: 8px;">
            <option value="">All Statuses</option>
            <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Active</option>
            <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>Inactive</option>
        </select>
    </div>
    <div class="col-12 col-md-3">
        <select name="sort" class="form-select form-select-sm" style="border-radius: 8px;">
            <option value="latest" {{ request('sort') === 'latest' ? 'selected' : '' }}>Newest First</option>
            <option value="name_asc" {{ request('sort') === 'name_asc' ? 'selected' : '' }}>Name (A-Z)</option>
            <option value="name_desc" {{ request('sort') === 'name_desc' ? 'selected' : '' }}>Name (Z-A)</option>
            <option value="products_count" {{ request('sort') === 'products_count' ? 'selected' : '' }}>Most Products</option>
        </select>
    </div>
</x-search-filter>

<!-- Suppliers Table -->
<x-table 
    :headers="[
        ['label' => 'Supplier / Distributor', 'class' => 'ps-4'],
        'Contact Details',
        'Address / Location',
        'Products Supplied',
        'Status',
        ['label' => 'Actions', 'class' => 'pe-4 text-end']
    ]">

    <x-slot:cardHeader>
        <div class="d-flex align-items-center justify-content-between w-100">
            <div class="d-flex align-items-center gap-2">
                <h5 class="fw-bold mb-0 fs-6 text-dark">Supplier Directory</h5>
                <span class="badge bg-light text-muted border">{{ $suppliers->total() }} partners</span>
            </div>
        </div>
    </x-slot:cardHeader>

    @forelse($suppliers as $supplier)
    <tr>
        <td class="ps-4">
            <div class="d-flex align-items-center gap-2">
                <span class="brand-icon-box" style="width: 36px; height: 36px; font-size: 1.05rem;">
                    <i class="bi bi-building"></i>
                </span>
                <div>
                    <span class="fw-bold text-dark d-block">{{ $supplier->name }}</span>
                    <span class="text-muted" style="font-size: 0.72rem;">Partner ID: #{{ $supplier->id }}</span>
                </div>
            </div>
        </td>
        <td>
            <div class="small text-muted d-flex flex-column gap-1" style="font-size: 0.78rem;">
                <div><i class="bi bi-telephone me-1 text-muted"></i> {{ $supplier->phone ?? 'N/A' }}</div>
                <div><i class="bi bi-envelope me-1 text-muted"></i> {{ $supplier->email ?? 'N/A' }}</div>
            </div>
        </td>
        <td>
            <span class="small text-muted text-truncate d-inline-block" style="max-width: 200px;">
                <i class="bi bi-geo-alt me-1 text-muted"></i> {{ $supplier->address ?? 'N/A' }}
            </span>
        </td>
        <td>
            <div class="d-flex flex-column gap-1">
                <a href="{{ route('products.index', ['supplier_id' => $supplier->id]) }}" class="text-decoration-none">
                    <span class="badge bg-light text-dark border px-2 py-1">
                        <i class="bi bi-box-seam me-1 text-muted"></i>
                        {{ $supplier->products_count }} {{ Str::plural('product', $supplier->products_count) }}
                    </span>
                </a>
                <span class="text-muted" style="font-size: 0.7rem;">
                    {{ $supplier->stock_transactions_count }} stock transactions
                </span>
            </div>
        </td>
        <td>
            <x-status-badge :status="$supplier->status" />
        </td>
        <td class="pe-4 text-end">
            <div class="d-flex align-items-center justify-content-end gap-1">
                <!-- Edit button -->
                <button type="button" 
                        class="btn btn-sm btn-light border px-2 py-1" 
                        title="Edit Supplier"
                        data-bs-toggle="modal" 
                        data-bs-target="#editSupplierModal"
                        data-id="{{ $supplier->id }}"
                        data-name="{{ $supplier->name }}"
                        data-phone="{{ $supplier->phone }}"
                        data-email="{{ $supplier->email }}"
                        data-address="{{ $supplier->address }}"
                        data-status="{{ $supplier->status }}"
                        data-action="{{ route('suppliers.update', $supplier) }}">
                    <i class="bi bi-pencil small"></i>
                </button>

                <!-- Toggle Status -->
                <form method="POST" action="{{ route('suppliers.toggle-status', $supplier) }}" class="d-inline">
                    @csrf
                    @method('PATCH')
                    <button type="submit" 
                            class="btn btn-sm btn-light border px-2 py-1" 
                            title="{{ $supplier->status === 'active' ? 'Deactivate Supplier' : 'Activate Supplier' }}">
                        <i class="bi {{ $supplier->status === 'active' ? 'bi-pause-circle text-warning' : 'bi-play-circle text-success' }} small"></i>
                    </button>
                </form>

                <!-- Delete button with historical constraint check -->
                @if($supplier->products_count > 0 || $supplier->stock_transactions_count > 0)
                    <button type="button" 
                            class="btn btn-sm btn-light border px-2 py-1 text-muted" 
                            title="Cannot delete: linked to products or transactions"
                            data-bs-toggle="modal" 
                            data-bs-target="#cannotDeleteSupplierModal{{ $supplier->id }}">
                        <i class="bi bi-trash small text-secondary"></i>
                    </button>
                @else
                    <button type="button" 
                            class="btn btn-sm btn-light border px-2 py-1 text-danger" 
                            title="Delete Supplier"
                            data-bs-toggle="modal" 
                            data-bs-target="#deleteSupplierModal{{ $supplier->id }}">
                        <i class="bi bi-trash small"></i>
                    </button>
                @endif
            </div>

            <!-- Cannot Delete Modal (Historical Reference Guard) -->
            @if($supplier->products_count > 0 || $supplier->stock_transactions_count > 0)
            <div class="modal fade" id="cannotDeleteSupplierModal{{ $supplier->id }}" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered modal-sm">
                    <div class="modal-content card-skincare border-0 shadow-lg text-center p-3">
                        <div class="modal-body p-3">
                            <div class="brand-icon-box mx-auto mb-3" style="width: 48px; height: 48px; font-size: 1.4rem; background-color: #FEF3C7; color: #D97706;">
                                <i class="bi bi-shield-lock"></i>
                            </div>
                            <h6 class="fw-bold mb-2">Historical Records Protected</h6>
                            <p class="text-muted small mb-3">
                                <strong>{{ $supplier->name }}</strong> has
                                @if($supplier->products_count > 0)
                                    <strong>{{ $supplier->products_count }} product(s)</strong>
                                @endif
                                @if($supplier->products_count > 0 && $supplier->stock_transactions_count > 0)
                                    and
                                @endif
                                @if($supplier->stock_transactions_count > 0)
                                    <strong>{{ $supplier->stock_transactions_count }} inventory transaction(s)</strong>
                                @endif
                                linked. To preserve transaction history, it cannot be deleted. Deactivate it instead.
                            </p>
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-sm btn-light border w-50" data-bs-dismiss="modal">Close</button>
                                <form method="POST" action="{{ route('suppliers.toggle-status', $supplier) }}" class="w-50">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="btn btn-sm btn-skincare-primary w-100">
                                        {{ $supplier->status === 'active' ? 'Deactivate' : 'Activate' }}
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            @else
            <!-- Standard Confirm Delete Dialog -->
            <x-confirm-dialog 
                id="deleteSupplierModal{{ $supplier->id }}" 
                title="Delete Supplier" 
                :message="'Are you sure you want to delete supplier \'' . $supplier->name . '\'? This action cannot be undone.'"
                confirmText="Delete Supplier"
                :action="route('suppliers.destroy', $supplier)"
                method="DELETE"
                :danger="true" />
            @endif
        </td>
    </tr>
    @empty
    <tr>
        <td colspan="6" class="p-0">
            <x-empty-state 
                icon="bi-truck" 
                title="No suppliers found" 
                description="No skincare suppliers matched your criteria. Add a new partner to start receiving stock." />
        </td>
    </tr>
    @endforelse

    @if($suppliers->hasPages())
    <x-slot:pagination>
        {{ $suppliers->links() }}
    </x-slot:pagination>
    @endif
</x-table>

<!-- Create Supplier Modal -->
<x-modal id="createSupplierModal" title="Register Skincare Supplier" size="md">
    <form method="POST" action="{{ route('suppliers.store') }}">
        @csrf
        <div class="mb-3">
            <x-form-input name="name" label="Company / Distributor Name" placeholder="e.g. L'Oréal Dermatological Beauty" required />
        </div>
        <div class="row g-2 mb-3">
            <div class="col-12 col-md-6">
                <x-form-input name="phone" label="Phone Number" placeholder="e.g. +1 800-321-4567" />
            </div>
            <div class="col-12 col-md-6">
                <x-form-input type="email" name="email" label="Email Address" placeholder="orders@distributor.com" />
            </div>
        </div>
        <div class="mb-3">
            <x-form-input name="address" label="Warehouse / Facility Address" placeholder="e.g. 10 Hudson Yards, New York, NY" />
        </div>
        <div class="mb-3">
            <x-form-select name="status" label="Status" required>
                <option value="active" selected>Active (Eligible for stock-in & purchases)</option>
                <option value="inactive">Inactive (Archived)</option>
            </x-form-select>
        </div>

        <div class="d-flex justify-content-end gap-2 mt-4 pt-2 border-top">
            <button type="button" class="btn btn-sm btn-light border px-3" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-sm btn-skincare-primary px-3">
                <i class="bi bi-check-lg me-1"></i> Save Supplier
            </button>
        </div>
    </form>
</x-modal>

<!-- Edit Supplier Modal (Dynamic JS Binding) -->
<x-modal id="editSupplierModal" title="Edit Skincare Supplier" size="md">
    <form id="editSupplierForm" method="POST" action="">
        @csrf
        @method('PUT')
        <div class="mb-3">
            <x-form-input id="edit_supplier_name" name="name" label="Company / Distributor Name" required />
        </div>
        <div class="row g-2 mb-3">
            <div class="col-12 col-md-6">
                <x-form-input id="edit_supplier_phone" name="phone" label="Phone Number" />
            </div>
            <div class="col-12 col-md-6">
                <x-form-input id="edit_supplier_email" type="email" name="email" label="Email Address" />
            </div>
        </div>
        <div class="mb-3">
            <x-form-input id="edit_supplier_address" name="address" label="Warehouse / Facility Address" />
        </div>
        <div class="mb-3">
            <label class="form-label small fw-semibold" for="edit_supplier_status">Status</label>
            <select id="edit_supplier_status" name="status" class="form-select form-select-sm" style="border-radius: 8px;" required>
                <option value="active">Active (Eligible for stock-in & purchases)</option>
                <option value="inactive">Inactive (Archived)</option>
            </select>
        </div>

        <div class="d-flex justify-content-end gap-2 mt-4 pt-2 border-top">
            <button type="button" class="btn btn-sm btn-light border px-3" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-sm btn-skincare-primary px-3">
                <i class="bi bi-check-lg me-1"></i> Update Supplier
            </button>
        </div>
    </form>
</x-modal>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const editModal = document.getElementById('editSupplierModal');
    if (editModal) {
        editModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            if (!button) return;

            const action = button.getAttribute('data-action');
            const name = button.getAttribute('data-name');
            const phone = button.getAttribute('data-phone');
            const email = button.getAttribute('data-email');
            const address = button.getAttribute('data-address');
            const status = button.getAttribute('data-status');

            document.getElementById('editSupplierForm').action = action;
            document.getElementById('edit_supplier_name').value = name || '';
            document.getElementById('edit_supplier_phone').value = phone || '';
            document.getElementById('edit_supplier_email').value = email || '';
            document.getElementById('edit_supplier_address').value = address || '';
            document.getElementById('edit_supplier_status').value = status || 'active';
        });
    }
});
</script>
@endpush
@endsection
