@extends('layouts.app')

@section('title', 'Stock Transfers')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none text-muted">Inventory</a></li>
    <li class="breadcrumb-item"><a href="{{ route('inventory.index') }}" class="text-decoration-none text-muted">Stock Overview</a></li>
    <li class="breadcrumb-item active text-dark" aria-current="page">Stock Transfers</li>
@endsection

@section('content')
<x-page-header 
    title="Stock Transfers & Branch Logistics" 
    description="Track inter-branch stock movements between configured warehouses, spa suites, and retail boutiques."
    :breadcrumbs="['Inventory' => route('inventory.index'), 'Stock Transfers' => null]">
    <x-slot:actions>
        <x-action-button roles="admin,manager,inventory" icon="bi-arrow-left-right" modalTarget="#createTransferModal">
            Create Transfer
        </x-action-button>
    </x-slot:actions>
</x-page-header>

<!-- Configured Branches Banner -->
<div class="card bg-light border p-3 mb-4" style="border-radius: 10px;">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div class="d-flex align-items-center gap-2">
            <span class="brand-icon-box" style="width: 34px; height: 34px; font-size: 1rem;">
                <i class="bi bi-geo-alt"></i>
            </span>
            <div>
                <span class="fw-bold text-dark small d-block">Configured Transfer Locations</span>
                <span class="text-muted" style="font-size: 0.75rem;">Defined in <code>config/inventory.php</code>:</span>
            </div>
        </div>
        <div class="d-flex flex-wrap gap-2">
            @foreach($branches as $key => $name)
                <span class="badge bg-white text-dark border px-2 py-1 shadow-xs" style="font-size: 0.72rem;">
                    <i class="bi bi-building me-1 text-muted"></i>{{ $name }}
                </span>
            @endforeach
        </div>
    </div>
</div>

<!-- Search & Status Filter -->
<x-search-filter :action="route('inventory.transfers')" :resetUrl="route('inventory.transfers')" placeholder="Search transfer reference, location...">
    <div class="col-12 col-md-3">
        <select name="status" class="form-select form-select-sm" style="border-radius: 8px;">
            <option value="">All Transfer Statuses</option>
            <option value="in_transit" {{ request('status') === 'in_transit' ? 'selected' : '' }}>In Transit</option>
            <option value="completed" {{ request('status') === 'completed' ? 'selected' : '' }}>Completed</option>
            <option value="cancelled" {{ request('status') === 'cancelled' ? 'selected' : '' }}>Cancelled</option>
        </select>
    </div>
</x-search-filter>

<!-- Transfers Table -->
<x-table 
    :headers="[
        ['label' => 'Transfer Ref', 'class' => 'ps-4'],
        'Source Location',
        'Destination Location',
        'Items Transferred',
        'Dispatched By',
        'Date',
        'Status',
        ['label' => 'Actions', 'class' => 'pe-4 text-end']
    ]">

    <x-slot:cardHeader>
        <div class="d-flex align-items-center justify-content-between w-100">
            <div class="d-flex align-items-center gap-2">
                <h5 class="fw-bold mb-0 fs-6 text-dark">Inter-Location Transfer Ledger</h5>
                <span class="badge bg-light text-muted border">{{ $transfers->total() }} shipments</span>
            </div>
        </div>
    </x-slot:cardHeader>

    @forelse($transfers as $trf)
    <tr>
        <td class="ps-4">
            <span class="fw-bold font-monospace text-dark">{{ $trf->reference_number }}</span>
            @if($trf->notes)
                <div class="text-muted text-truncate" style="font-size: 0.72rem; max-width: 160px;">{{ $trf->notes }}</div>
            @endif
        </td>
        <td>
            <span class="badge bg-light text-dark border">
                <i class="bi bi-box-arrow-up-right text-muted me-1"></i>{{ $trf->source_location }}
            </span>
        </td>
        <td>
            <span class="badge bg-light text-dark border">
                <i class="bi bi-geo-alt-fill text-success me-1"></i>{{ $trf->destination_location }}
            </span>
        </td>
        <td>
            <div>
                <span class="fw-bold font-monospace text-dark">{{ $trf->details->sum('qty') }} units</span>
                <span class="text-muted" style="font-size: 0.72rem;">({{ $trf->details->count() }} formulas)</span>
            </div>
            <ul class="list-unstyled mb-0 text-muted" style="font-size: 0.7rem;">
                @foreach($trf->details->take(2) as $det)
                    <li class="text-truncate" style="max-width: 220px;">
                        • {{ $det->qty }}x {{ $det->product->name ?? 'SKU #' . $det->product_id }}
                    </li>
                @endforeach
                @if($trf->details->count() > 2)
                    <li class="fst-italic">+{{ $trf->details->count() - 2 }} more...</li>
                @endif
            </ul>
        </td>
        <td>
            <span class="small text-muted">{{ $trf->user->name ?? 'System' }}</span>
        </td>
        <td>
            <span class="text-muted small">{{ $trf->created_at->format('M j, Y') }}</span>
        </td>
        <td>
            <x-status-badge :status="$trf->status" />
        </td>
        <td class="pe-4 text-end">
            @if($trf->status === 'in_transit')
                <div class="d-flex align-items-center justify-content-end gap-1">
                    <!-- Complete Transfer Button -->
                    <form method="POST" action="{{ route('inventory.update-transfer-status', $trf) }}" class="d-inline">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="status" value="completed">
                        <button type="submit" class="btn btn-sm btn-skincare-primary py-0 px-2" style="font-size: 0.75rem;" title="Confirm Delivery at Destination">
                            <i class="bi bi-check2-circle me-1"></i> Receive
                        </button>
                    </form>

                    <!-- Cancel Transfer Button -->
                    <form method="POST" action="{{ route('inventory.update-transfer-status', $trf) }}" class="d-inline" onsubmit="return confirm('Cancel this transfer and return items to source stock?');">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="status" value="cancelled">
                        <button type="submit" class="btn btn-sm btn-light border py-0 px-2 text-danger" style="font-size: 0.75rem;" title="Cancel & Return Stock">
                            <i class="bi bi-x-circle"></i>
                        </button>
                    </form>
                </div>
            @else
                <span class="text-muted small">Closed</span>
            @endif
        </td>
    </tr>
    @empty
    <tr>
        <td colspan="8" class="p-0">
            <x-empty-state 
                icon="bi-arrow-left-right" 
                title="No transfers found" 
                description="No inter-branch shipments found for the current query." />
        </td>
    </tr>
    @endforelse

    @if($transfers->hasPages())
    <x-slot:pagination>
        {{ $transfers->links() }}
    </x-slot:pagination>
    @endif
</x-table>

<!-- Create Transfer Modal -->
<x-modal id="createTransferModal" title="Dispatch Inter-Branch Stock Transfer" size="lg">
    <form method="POST" action="{{ route('inventory.process-transfer') }}" id="transferForm">
        @csrf
        <div class="row g-3 mb-3">
            <div class="col-12 col-md-6">
                <label class="form-label small fw-semibold" for="source_location">Origin / Source Location</label>
                <select id="source_location" name="source_location" class="form-select form-select-sm" style="border-radius: 8px;" required>
                    @foreach($branches as $name)
                        <option value="{{ $name }}" {{ $loop->first ? 'selected' : '' }}>{{ $name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-md-6">
                <label class="form-label small fw-semibold" for="destination_location">Destination Location</label>
                <select id="destination_location" name="destination_location" class="form-select form-select-sm" style="border-radius: 8px;" required>
                    @foreach($branches as $name)
                        <option value="{{ $name }}" {{ $loop->iteration === 2 ? 'selected' : '' }}>{{ $name }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <!-- Transfer Line Items -->
        <div class="card bg-light border p-3 mb-3" style="border-radius: 10px;">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="small fw-bold text-dark">Transfer Formulation Lines</span>
                <button type="button" class="btn btn-sm btn-outline-secondary py-0" style="font-size: 0.75rem;" onclick="addTransferLine()">
                    <i class="bi bi-plus-lg me-1"></i> Add Formula Line
                </button>
            </div>

            <div id="transferLinesContainer">
                <div class="row g-2 align-items-center transfer-line mb-2">
                    <div class="col-8">
                        <select name="items[0][product_id]" class="form-select form-select-sm" style="border-radius: 8px;" required>
                            <option value="">Select product to transfer...</option>
                            @foreach($products as $prod)
                                <option value="{{ $prod->id }}">
                                    {{ $prod->name }} (Available: {{ $prod->qty }} units)
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-3">
                        <input type="number" min="1" name="items[0][qty]" class="form-control form-control-sm" placeholder="Qty" style="border-radius: 8px;" required>
                    </div>
                    <div class="col-1 text-center">
                        <button type="button" class="btn btn-sm btn-link text-muted p-0" onclick="this.closest('.transfer-line').remove()" title="Remove line">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="mb-3">
            <x-form-textarea name="notes" label="Dispatch Notes & Courier Reference" placeholder="e.g. Courier van dispatch #CV-09. Temperature controlled skincare cargo." rows="2" />
        </div>

        <div class="d-flex justify-content-end gap-2 mt-4 pt-2 border-top">
            <button type="button" class="btn btn-sm btn-light border px-3" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-sm btn-skincare-primary px-3">
                <i class="bi bi-send me-1"></i> Dispatch Transfer
            </button>
        </div>
    </form>
</x-modal>

@push('scripts')
<script>
let lineIndex = 1;
function addTransferLine() {
    const container = document.getElementById('transferLinesContainer');
    const firstSelect = container.querySelector('select');
    if (!firstSelect) return;

    const row = document.createElement('div');
    row.className = 'row g-2 align-items-center transfer-line mb-2';
    row.innerHTML = `
        <div class="col-8">
            <select name="items[${lineIndex}][product_id]" class="form-select form-select-sm" style="border-radius: 8px;" required>
                ${firstSelect.innerHTML}
            </select>
        </div>
        <div class="col-3">
            <input type="number" min="1" name="items[${lineIndex}][qty]" class="form-control form-control-sm" placeholder="Qty" style="border-radius: 8px;" required>
        </div>
        <div class="col-1 text-center">
            <button type="button" class="btn btn-sm btn-link text-muted p-0" onclick="this.closest('.transfer-line').remove()" title="Remove line">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
    `;
    container.appendChild(row);
    lineIndex++;
}
</script>
@endpush
@endsection
