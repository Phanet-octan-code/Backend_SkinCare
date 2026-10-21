@extends('layouts.app')

@section('title', 'Damaged & Expired Stock')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none text-muted">Inventory</a></li>
    <li class="breadcrumb-item"><a href="{{ route('inventory.index') }}" class="text-decoration-none text-muted">Stock Overview</a></li>
    <li class="breadcrumb-item active text-dark" aria-current="page">Damaged Stock</li>
@endsection

@section('content')
<x-page-header 
    title="Damaged & Expired Stock Ledger" 
    description="Record broken packaging, dropper damage, oxidized formulas, and expired skincare write-offs."
    :breadcrumbs="['Inventory' => route('inventory.index'), 'Damaged Stock' => null]">
    <x-slot:actions>
        <x-action-button roles="admin,manager,inventory" icon="bi-trash3" modalTarget="#recordDamagedModal">
            Record Damaged Stock
        </x-action-button>
    </x-slot:actions>
</x-page-header>

<!-- Search Filter -->
<x-search-filter :action="route('inventory.damaged')" :resetUrl="route('inventory.damaged')" placeholder="Search damage reference, reason, notes..." />

<!-- Damaged Stock Table -->
<x-table 
    :headers="[
        ['label' => 'Write-Off Ref', 'class' => 'ps-4'],
        'Date',
        'Product / SKU',
        'Written Off By',
        'Quantity Lost',
        ['label' => 'Damage Reason & Audit Details', 'class' => 'pe-4']
    ]">

    <x-slot:cardHeader>
        <div class="d-flex align-items-center justify-content-between w-100">
            <div class="d-flex align-items-center gap-2">
                <h5 class="fw-bold mb-0 fs-6 text-dark">Damaged Stock Write-offs</h5>
                <span class="badge bg-light text-muted border">{{ $transactions->total() }} write-offs</span>
            </div>
            <div class="small text-muted d-none d-md-block">
                Total Written Off: <strong class="text-danger">-{{ number_format($totalDamagedUnits) }} units</strong>
            </div>
        </div>
    </x-slot:cardHeader>

    @forelse($transactions as $tx)
    <tr>
        <td class="ps-4">
            <span class="fw-bold font-monospace text-dark">{{ $tx->reference_number ?? 'DMG-LOG' }}</span>
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
            <span class="badge bg-danger text-white fs-6 font-monospace px-2 py-1">
                {{ $tx->qty }} units
            </span>
        </td>
        <td class="pe-4 text-muted small">
            {{ $tx->notes ?? 'Damaged cosmetic packaging or formulation' }}
        </td>
    </tr>
    @empty
    <tr>
        <td colspan="6" class="p-0">
            <x-empty-state 
                icon="bi-trash3" 
                title="Zero damaged inventory" 
                description="There are no damaged, leaked, or expired product write-offs recorded." />
        </td>
    </tr>
    @endforelse

    @if($transactions->hasPages())
    <x-slot:pagination>
        {{ $transactions->links() }}
    </x-slot:pagination>
    @endif
</x-table>

<!-- Record Damaged Stock Modal -->
<x-modal id="recordDamagedModal" title="Record Damaged or Expired Formulation" size="lg">
    <form method="POST" action="{{ route('inventory.process-damaged') }}">
        @csrf
        <div class="row g-3">
            <div class="col-12 col-md-8">
                <label class="form-label small fw-semibold" for="damaged_product_id">Product Formulation</label>
                <select id="damaged_product_id" name="product_id" class="form-select form-select-sm" style="border-radius: 8px;" required>
                    <option value="">Select product...</option>
                    @foreach($products as $prod)
                        <option value="{{ $prod->id }}">
                            {{ $prod->name }} (Available: {{ $prod->qty }} units) - SKU: {{ $prod->barcode }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-md-4">
                <x-form-input type="number" min="1" name="qty" label="Units Damaged" placeholder="e.g. 2" required />
            </div>

            <div class="col-12 col-md-6">
                <label class="form-label small fw-semibold" for="damaged_reason">Damage Reason Category (Required)</label>
                <select id="damaged_reason" name="reason" class="form-select form-select-sm" style="border-radius: 8px;" required>
                    <option value="">Select reason category...</option>
                    <option value="Formula Expired / Past Period-After-Opening (PAO)">Formula Expired / Past Period-After-Opening (PAO)</option>
                    <option value="Broken Glass Dropper / Cracked Bottle">Broken Glass Dropper / Cracked Bottle</option>
                    <option value="Packaging Seal Broken / Contaminated">Packaging Seal Broken / Contaminated</option>
                    <option value="Pump Dispenser Defect / In-Transit Leakage">Pump Dispenser Defect / In-Transit Leakage</option>
                    <option value="Oxidation / Color or Scent Degradation">Oxidation / Color or Scent Degradation</option>
                </select>
            </div>
            <div class="col-12 col-md-6">
                <x-form-input name="reference_number" label="Inspection Reference #" placeholder="e.g. DMG-20261022-0008" helperText="Leave blank for auto-generated reference." />
            </div>

            <div class="col-12 col-md-6">
                <label class="form-label small fw-semibold" for="damaged_date">Inspection Date</label>
                <input type="date" id="damaged_date" name="date" class="form-control form-control-sm" style="border-radius: 8px;" value="{{ date('Y-m-d') }}" required>
            </div>
            <div class="col-12 col-md-6">
                <x-form-input name="notes" label="Detailed Audit Notes" placeholder="e.g. Dropped during shelf restocking; glass shattered." />
            </div>
        </div>

        <div class="d-flex justify-content-end gap-2 mt-4 pt-2 border-top">
            <button type="button" class="btn btn-sm btn-light border px-3" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-sm btn-danger px-3">
                <i class="bi bi-trash3 me-1"></i> Record Write-Off & Deduct
            </button>
        </div>
    </form>
</x-modal>
@endsection
