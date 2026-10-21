@extends('layouts.app')

@section('title', 'Promotions & Discounts')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none text-muted">Marketing</a></li>
    <li class="breadcrumb-item active text-dark" aria-current="page">Promotions</li>
@endsection

@section('content')
<x-page-header 
    title="Promotions & Discounts" 
    description="Configure storewide specials, seasonal discounts, and product-specific retail deals."
    :breadcrumbs="['Marketing' => route('promotions.index'), 'Promotions' => null]">
    <x-slot:actions>
        <x-action-button roles="admin,manager" icon="bi-plus-lg" modalTarget="#createPromotionModal">
            Create Promotion
        </x-action-button>
    </x-slot:actions>
</x-page-header>

<!-- Filter Navigation Tabs -->
<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4 border-bottom pb-2">
    <ul class="nav nav-pills gap-1">
        <li class="nav-item">
            <a class="nav-link py-1 px-3 small {{ $activeTab === 'all' ? 'active bg-skincare-primary' : 'text-muted' }}" 
               href="{{ route('promotions.index', ['tab' => 'all']) }}">
                All Campaigns <span class="badge bg-light text-dark ms-1">{{ $counts['all'] }}</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link py-1 px-3 small {{ $activeTab === 'active' ? 'active bg-skincare-primary' : 'text-muted' }}" 
               href="{{ route('promotions.index', ['tab' => 'active']) }}">
                <i class="bi bi-circle-fill text-success me-1" style="font-size: 0.5rem;"></i> Active Now 
                <span class="badge bg-light text-dark ms-1">{{ $counts['active'] }}</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link py-1 px-3 small {{ $activeTab === 'scheduled' ? 'active bg-skincare-primary' : 'text-muted' }}" 
               href="{{ route('promotions.index', ['tab' => 'scheduled']) }}">
                <i class="bi bi-clock-history text-primary me-1"></i> Scheduled 
                <span class="badge bg-light text-dark ms-1">{{ $counts['scheduled'] }}</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link py-1 px-3 small {{ $activeTab === 'expired' ? 'active bg-skincare-primary' : 'text-muted' }}" 
               href="{{ route('promotions.index', ['tab' => 'expired']) }}">
                <i class="bi bi-slash-circle text-muted me-1"></i> Expired 
                <span class="badge bg-light text-dark ms-1">{{ $counts['expired'] }}</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link py-1 px-3 small {{ $activeTab === 'inactive' ? 'active bg-skincare-primary' : 'text-muted' }}" 
               href="{{ route('promotions.index', ['tab' => 'inactive']) }}">
                <i class="bi bi-pause-circle text-warning me-1"></i> Inactive 
                <span class="badge bg-light text-dark ms-1">{{ $counts['inactive'] }}</span>
            </a>
        </li>
    </ul>

    <!-- Quick Search -->
    <form method="GET" action="{{ route('promotions.index') }}" class="d-flex align-items-center gap-2">
        <input type="hidden" name="tab" value="{{ $activeTab }}">
        <div class="input-group input-group-sm">
            <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
            <input type="text" name="search" class="form-control border-start-0" placeholder="Search campaign..." value="{{ request('search') }}" style="border-radius: 0 8px 8px 0; max-width: 200px;">
        </div>
        @if(request('search'))
            <a href="{{ route('promotions.index', ['tab' => $activeTab]) }}" class="btn btn-sm btn-light border text-muted">Reset</a>
        @endif
    </form>
</div>

<!-- Promotions Table -->
<x-table 
    :headers="[
        ['label' => 'Promotion Campaign', 'class' => 'ps-4'],
        'Discount Rate',
        'Application Scope',
        'Validity Period',
        'Schedule Status',
        ['label' => 'Actions', 'class' => 'pe-4 text-end']
    ]">

    <x-slot:cardHeader>
        <div class="d-flex align-items-center justify-content-between w-100">
            <div class="d-flex align-items-center gap-2">
                <h5 class="fw-bold mb-0 fs-6 text-dark">Promotions Catalog</h5>
                <span class="badge bg-light text-muted border">{{ $promotions->total() }} campaigns</span>
            </div>
        </div>
    </x-slot:cardHeader>

    @php
        $todayStr = \Carbon\Carbon::today()->toDateString();
    @endphp

    @forelse($promotions as $promo)
    @php
        $startDateStr = $promo->start_date->toDateString();
        $endDateStr = $promo->end_date->toDateString();
        $isScheduled = $startDateStr > $todayStr;
        $isExpired = $endDateStr < $todayStr;
        $isCurrentlyActive = $promo->status === 'active' && !$isScheduled && !$isExpired;
    @endphp
    <tr>
        <td class="ps-4">
            <div class="d-flex align-items-center gap-2">
                <span class="brand-icon-box" style="width: 36px; height: 36px; font-size: 1.05rem;">
                    <i class="bi bi-percent"></i>
                </span>
                <div>
                    <span class="fw-bold text-dark d-block">{{ $promo->name }}</span>
                    <span class="text-muted" style="font-size: 0.72rem;">Promo ID: #{{ $promo->id }}</span>
                </div>
            </div>
        </td>
        <td>
            <span class="badge bg-light text-dark border px-2 py-1 font-monospace fw-bold" style="font-size: 0.8rem;">
                {{ $promo->discount_type === 'percentage' ? number_format($promo->discount_value, 0) . '% OFF' : '$' . number_format($promo->discount_value, 2) . ' OFF' }}
            </span>
        </td>
        <td>
            @if($promo->isGeneral())
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1">
                    <i class="bi bi-globe me-1"></i> Storewide (All Formulas)
                </span>
            @else
                <div>
                    <span class="badge bg-info-subtle text-info border border-info-subtle px-2 py-1">
                        <i class="bi bi-bag-check me-1"></i> {{ $promo->products->count() }} Specific Products
                    </span>
                    @if($promo->products->count() > 0)
                        <div class="text-muted mt-1" style="font-size: 0.72rem;">
                            {{ $promo->products->take(2)->pluck('name')->implode(', ') }}
                            @if($promo->products->count() > 2)
                                <span class="text-muted fst-italic">+{{ $promo->products->count() - 2 }} more</span>
                            @endif
                        </div>
                    @endif
                </div>
            @endif
        </td>
        <td>
            <div class="small text-muted d-flex flex-column gap-1" style="font-size: 0.78rem;">
                <div><i class="bi bi-calendar-event me-1 text-muted"></i> {{ $promo->start_date->format('M d, Y') }}</div>
                <div><i class="bi bi-calendar-check me-1 text-muted"></i> {{ $promo->end_date->format('M d, Y') }}</div>
            </div>
        </td>
        <td>
            @if($promo->status === 'inactive')
                <span class="badge bg-secondary-subtle text-secondary border px-2 py-1">Inactive</span>
            @elseif($isScheduled)
                <span class="badge bg-info-subtle text-info border border-info-subtle px-2 py-1">
                    <i class="bi bi-clock me-1"></i> Starts {{ $promo->start_date->diffForHumans() }}
                </span>
            @elseif($isExpired)
                <span class="badge bg-light text-muted border px-2 py-1">
                    <i class="bi bi-calendar-x me-1"></i> Expired
                </span>
            @else
                <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">
                    <i class="bi bi-lightning-charge-fill me-1"></i> Active Now
                </span>
            @endif
        </td>
        <td class="pe-4 text-end">
            <div class="d-flex align-items-center justify-content-end gap-1">
                <!-- Edit Button -->
                <button type="button" 
                        class="btn btn-sm btn-light border px-2 py-1" 
                        title="Edit Promotion"
                        data-bs-toggle="modal" 
                        data-bs-target="#editPromotionModal"
                        data-id="{{ $promo->id }}"
                        data-name="{{ $promo->name }}"
                        data-type="{{ $promo->discount_type }}"
                        data-value="{{ $promo->discount_value }}"
                        data-start="{{ $startDateStr }}"
                        data-end="{{ $endDateStr }}"
                        data-status="{{ $promo->status }}"
                        data-scope="{{ $promo->isGeneral() ? 'general' : 'specific' }}"
                        data-product-ids="{{ json_encode($promo->products->pluck('id')->toArray()) }}"
                        data-action="{{ route('promotions.update', $promo) }}">
                    <i class="bi bi-pencil small"></i>
                </button>

                <!-- Toggle Status Button -->
                <form method="POST" action="{{ route('promotions.toggle-status', $promo) }}" class="d-inline">
                    @csrf
                    @method('PATCH')
                    <button type="submit" 
                            class="btn btn-sm btn-light border px-2 py-1" 
                            title="{{ $promo->status === 'active' ? 'Deactivate Promotion' : 'Activate Promotion' }}">
                        <i class="bi {{ $promo->status === 'active' ? 'bi-pause-circle text-warning' : 'bi-play-circle text-success' }} small"></i>
                    </button>
                </form>

                <!-- Delete Button -->
                <button type="button" 
                        class="btn btn-sm btn-light border px-2 py-1 text-danger" 
                        title="Delete Promotion"
                        data-bs-toggle="modal" 
                        data-bs-target="#deletePromotionModal{{ $promo->id }}">
                    <i class="bi bi-trash small"></i>
                </button>
            </div>

            <!-- Confirm Delete Dialog -->
            <x-confirm-dialog 
                id="deletePromotionModal{{ $promo->id }}" 
                title="Delete Promotion" 
                :message="'Are you sure you want to delete campaign \'' . $promo->name . '\'? This will remove promotional pricing for linked items.'"
                confirmText="Delete Promotion"
                :action="route('promotions.destroy', $promo)"
                method="DELETE"
                :danger="true" />
        </td>
    </tr>
    @empty
    <tr>
        <td colspan="6" class="p-0">
            <x-empty-state 
                icon="bi-percent" 
                title="No promotions found" 
                description="No promotional deals found for this filter tab. Create a new campaign to boost sales." />
        </td>
    </tr>
    @endforelse

    @if($promotions->hasPages())
    <x-slot:pagination>
        {{ $promotions->links() }}
    </x-slot:pagination>
    @endif
</x-table>

<!-- Create Promotion Modal -->
<x-modal id="createPromotionModal" title="Create Skincare Promotion" size="lg">
    <form method="POST" action="{{ route('promotions.store') }}">
        @csrf
        <div class="row g-3 mb-3">
            <div class="col-12 col-md-8">
                <x-form-input name="name" label="Promotion / Campaign Name" placeholder="e.g. Summer Hydration Glow 20% OFF" required />
            </div>
            <div class="col-12 col-md-4">
                <x-form-select name="status" label="Status" required>
                    <option value="active" selected>Active</option>
                    <option value="inactive">Inactive</option>
                </x-form-select>
            </div>

            <div class="col-12 col-md-6">
                <label class="form-label small fw-semibold" for="create_discount_type">Discount Type</label>
                <select id="create_discount_type" name="discount_type" class="form-select form-select-sm" style="border-radius: 8px;" required>
                    <option value="percentage">Percentage Discount (% OFF)</option>
                    <option value="fixed">Fixed Dollar Amount ($ OFF)</option>
                </select>
            </div>
            <div class="col-12 col-md-6">
                <x-form-input type="number" step="0.01" min="0.01" id="create_discount_value" name="discount_value" label="Discount Value (% or $)" placeholder="e.g. 15 or 5.00" required />
            </div>

            <div class="col-12 col-md-6">
                <label class="form-label small fw-semibold" for="create_start_date">Start Date</label>
                <input type="date" id="create_start_date" name="start_date" class="form-control form-control-sm" style="border-radius: 8px;" value="{{ date('Y-m-d') }}" required>
            </div>
            <div class="col-12 col-md-6">
                <label class="form-label small fw-semibold" for="create_end_date">End Date</label>
                <input type="date" id="create_end_date" name="end_date" class="form-control form-control-sm" style="border-radius: 8px;" value="{{ date('Y-m-d', strtotime('+14 days')) }}" required>
            </div>
        </div>

        <!-- Scope Selection: General vs Product-Specific -->
        <div class="card bg-light border p-3 mb-3" style="border-radius: 10px;">
            <label class="form-label small fw-bold text-dark mb-2">Promotion Scope</label>
            <div class="d-flex gap-4 mb-3">
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="scope_type" id="createScopeGeneral" value="general" checked onchange="toggleScope('create', 'general')">
                    <label class="form-check-label small fw-semibold" for="createScopeGeneral">
                        Storewide Discount (Applies to all products)
                    </label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="scope_type" id="createScopeSpecific" value="specific" onchange="toggleScope('create', 'specific')">
                    <label class="form-check-label small fw-semibold" for="createScopeSpecific">
                        Specific Products (Select formulas below)
                    </label>
                </div>
            </div>

            <!-- Specific Products Checklist -->
            <div id="createProductsContainer" style="display: none;">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="small fw-semibold text-muted">Select Eligible Products:</span>
                    <input type="text" id="createSearchProd" class="form-control form-control-sm w-50" placeholder="Filter products..." onkeyup="filterProductCheckboxes('create')">
                </div>
                <div class="border rounded-2 p-2 bg-white" style="max-height: 180px; overflow-y: auto;">
                    @foreach($products as $prod)
                    <div class="form-check py-1 border-bottom border-light create-prod-item">
                        <input class="form-check-input" type="checkbox" name="product_ids[]" value="{{ $prod->id }}" id="create_prod_{{ $prod->id }}">
                        <label class="form-check-label small text-dark d-flex justify-content-between w-100" for="create_prod_{{ $prod->id }}">
                            <span class="text-truncate" style="max-width: 320px;">{{ $prod->name }}</span>
                            <span class="text-muted font-monospace">${{ number_format($prod->sale_price, 2) }}</span>
                        </label>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-end gap-2 pt-2 border-top">
            <button type="button" class="btn btn-sm btn-light border px-3" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-sm btn-skincare-primary px-3">
                <i class="bi bi-check-lg me-1"></i> Save Promotion
            </button>
        </div>
    </form>
</x-modal>

<!-- Edit Promotion Modal -->
<x-modal id="editPromotionModal" title="Edit Skincare Promotion" size="lg">
    <form id="editPromotionForm" method="POST" action="">
        @csrf
        @method('PUT')
        <div class="row g-3 mb-3">
            <div class="col-12 col-md-8">
                <x-form-input id="edit_promo_name" name="name" label="Promotion / Campaign Name" required />
            </div>
            <div class="col-12 col-md-4">
                <label class="form-label small fw-semibold" for="edit_promo_status">Status</label>
                <select id="edit_promo_status" name="status" class="form-select form-select-sm" style="border-radius: 8px;" required>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>

            <div class="col-12 col-md-6">
                <label class="form-label small fw-semibold" for="edit_promo_discount_type">Discount Type</label>
                <select id="edit_promo_discount_type" name="discount_type" class="form-select form-select-sm" style="border-radius: 8px;" required>
                    <option value="percentage">Percentage Discount (% OFF)</option>
                    <option value="fixed">Fixed Dollar Amount ($ OFF)</option>
                </select>
            </div>
            <div class="col-12 col-md-6">
                <x-form-input type="number" step="0.01" min="0.01" id="edit_promo_discount_value" name="discount_value" label="Discount Value (% or $)" required />
            </div>

            <div class="col-12 col-md-6">
                <label class="form-label small fw-semibold" for="edit_promo_start_date">Start Date</label>
                <input type="date" id="edit_promo_start_date" name="start_date" class="form-control form-control-sm" style="border-radius: 8px;" required>
            </div>
            <div class="col-12 col-md-6">
                <label class="form-label small fw-semibold" for="edit_promo_end_date">End Date</label>
                <input type="date" id="edit_promo_end_date" name="end_date" class="form-control form-control-sm" style="border-radius: 8px;" required>
            </div>
        </div>

        <!-- Scope Selection in Edit Modal -->
        <div class="card bg-light border p-3 mb-3" style="border-radius: 10px;">
            <label class="form-label small fw-bold text-dark mb-2">Promotion Scope</label>
            <div class="d-flex gap-4 mb-3">
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="scope_type" id="editScopeGeneral" value="general" onchange="toggleScope('edit', 'general')">
                    <label class="form-check-label small fw-semibold" for="editScopeGeneral">
                        Storewide Discount (Applies to all products)
                    </label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="scope_type" id="editScopeSpecific" value="specific" onchange="toggleScope('edit', 'specific')">
                    <label class="form-check-label small fw-semibold" for="editScopeSpecific">
                        Specific Products (Select formulas below)
                    </label>
                </div>
            </div>

            <!-- Specific Products Checklist -->
            <div id="editProductsContainer" style="display: none;">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="small fw-semibold text-muted">Select Eligible Products:</span>
                    <input type="text" id="editSearchProd" class="form-control form-control-sm w-50" placeholder="Filter products..." onkeyup="filterProductCheckboxes('edit')">
                </div>
                <div class="border rounded-2 p-2 bg-white" style="max-height: 180px; overflow-y: auto;">
                    @foreach($products as $prod)
                    <div class="form-check py-1 border-bottom border-light edit-prod-item">
                        <input class="form-check-input edit-prod-checkbox" type="checkbox" name="product_ids[]" value="{{ $prod->id }}" id="edit_prod_{{ $prod->id }}">
                        <label class="form-check-label small text-dark d-flex justify-content-between w-100" for="edit_prod_{{ $prod->id }}">
                            <span class="text-truncate" style="max-width: 320px;">{{ $prod->name }}</span>
                            <span class="text-muted font-monospace">${{ number_format($prod->sale_price, 2) }}</span>
                        </label>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-end gap-2 pt-2 border-top">
            <button type="button" class="btn btn-sm btn-light border px-3" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-sm btn-skincare-primary px-3">
                <i class="bi bi-check-lg me-1"></i> Update Promotion
            </button>
        </div>
    </form>
</x-modal>

@push('scripts')
<script>
function toggleScope(prefix, type) {
    const container = document.getElementById(prefix + 'ProductsContainer');
    if (container) {
        container.style.display = (type === 'specific') ? 'block' : 'none';
    }
}

function filterProductCheckboxes(prefix) {
    const search = document.getElementById(prefix + 'SearchProd').value.toLowerCase();
    const items = document.querySelectorAll('.' + prefix + '-prod-item');
    items.forEach(item => {
        const text = item.textContent.toLowerCase();
        item.style.display = text.includes(search) ? '' : 'none';
    });
}

document.addEventListener('DOMContentLoaded', function () {
    const editModal = document.getElementById('editPromotionModal');
    if (editModal) {
        editModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            if (!button) return;

            const action = button.getAttribute('data-action');
            const name = button.getAttribute('data-name');
            const type = button.getAttribute('data-type');
            const value = button.getAttribute('data-value');
            const start = button.getAttribute('data-start');
            const end = button.getAttribute('data-end');
            const status = button.getAttribute('data-status');
            const scope = button.getAttribute('data-scope');
            let productIds = [];
            try {
                productIds = JSON.parse(button.getAttribute('data-product-ids') || '[]');
            } catch (e) {
                productIds = [];
            }

            document.getElementById('editPromotionForm').action = action;
            document.getElementById('edit_promo_name').value = name || '';
            document.getElementById('edit_promo_discount_type').value = type || 'percentage';
            document.getElementById('edit_promo_discount_value').value = value || '';
            document.getElementById('edit_promo_start_date').value = start || '';
            document.getElementById('edit_promo_end_date').value = end || '';
            document.getElementById('edit_promo_status').value = status || 'active';

            if (scope === 'specific') {
                document.getElementById('editScopeSpecific').checked = true;
                toggleScope('edit', 'specific');
            } else {
                document.getElementById('editScopeGeneral').checked = true;
                toggleScope('edit', 'general');
            }

            // Check the appropriate checkboxes
            const checkboxes = document.querySelectorAll('.edit-prod-checkbox');
            checkboxes.forEach(cb => {
                cb.checked = productIds.includes(parseInt(cb.value));
            });
        });
    }
});
</script>
@endpush
@endsection
