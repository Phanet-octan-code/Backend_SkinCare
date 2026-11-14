@extends('layouts.app')

@section('title', 'Edit Promotional Bundle')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none text-muted">Marketing</a></li>
    <li class="breadcrumb-item"><a href="{{ route('bundles.index') }}" class="text-decoration-none text-muted">Bundles & Gift Sets</a></li>
    <li class="breadcrumb-item active text-dark" aria-current="page">Edit {{ $bundle->name }}</li>
@endsection

@section('content')
<x-page-header 
    title="Edit Bundle / Gift Set" 
    description="Update pricing, items, and packaging for {{ $bundle->name }}."
    :breadcrumbs="['Bundles & Gift Sets' => route('bundles.index'), 'Edit' => null]" />

<div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-4">
        <form action="{{ route('bundles.update', $bundle) }}" method="POST" enctype="multipart/form-data">
            @csrf
            @method('PUT')

            <div class="row g-4">
                <div class="col-md-8">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Bundle / Set Title <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" required value="{{ old('name', $bundle->name) }}">
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Offer Type <span class="text-danger">*</span></label>
                            <select name="type" class="form-select" required>
                                <option value="bundle" {{ old('type', $bundle->type) === 'bundle' ? 'selected' : '' }}>Product Bundle</option>
                                <option value="gift_set" {{ old('type', $bundle->type) === 'gift_set' ? 'selected' : '' }}>Gift Set (Luxury Packaging)</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Status <span class="text-danger">*</span></label>
                            <select name="status" class="form-select" required>
                                <option value="active" {{ old('status', $bundle->status) === 'active' ? 'selected' : '' }}>Active</option>
                                <option value="inactive" {{ old('status', $bundle->status) === 'inactive' ? 'selected' : '' }}>Inactive</option>
                            </select>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Special Bundle Price ($) <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" name="price" class="form-control" required value="{{ old('price', $bundle->price) }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Original Comparison Price ($)</label>
                            <input type="number" step="0.01" name="original_price" class="form-control" value="{{ old('original_price', $bundle->original_price) }}">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Description & Skincare Benefits</label>
                        <textarea name="description" class="form-control" rows="3">{{ old('description', $bundle->description) }}</textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Gift Box / Packaging Details</label>
                        <input type="text" name="packaging_details" class="form-control" value="{{ old('packaging_details', $bundle->packaging_details) }}">
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Current Cover Photo</label>
                        <div class="mb-2">
                            <img src="{{ $bundle->image_url }}" alt="{{ $bundle->name }}" class="img-fluid rounded border" style="max-height: 180px;">
                        </div>
                        <label class="form-label fw-semibold small">Replace Photo</label>
                        <input type="file" name="image" class="form-control" accept="image/*">
                    </div>

                    <div class="card bg-light border p-3 small">
                        <div class="fw-bold mb-1">Live Inventory Calculation:</div>
                        <div class="d-flex justify-content-between mb-1">
                            <span>Available Stock:</span>
                            <span class="badge {{ $bundle->available_qty > 0 ? 'bg-success' : 'bg-danger' }}">{{ $bundle->available_qty }} sets</span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span>Calculated Value:</span>
                            <span>${{ number_format($bundle->calculated_original_price, 2) }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <hr class="my-4">

            <!-- Constituent Products Selector -->
            <div class="d-flex align-items-center justify-content-between mb-3">
                <h5 class="fw-bold fs-6 mb-0 text-dark">Constituent Skincare Products</h5>
                <button type="button" id="addItemBtn" class="btn btn-sm btn-outline-primary d-inline-flex align-items-center gap-1">
                    <i class="bi bi-plus-circle"></i> Add Component
                </button>
            </div>

            <div id="bundleItemsContainer" class="d-flex flex-column gap-2">
                @foreach($bundle->items as $idx => $bItem)
                <div class="bundle-item-row row g-2 align-items-center border rounded p-2 bg-light">
                    <div class="col-md-6">
                        <label class="form-label small text-muted mb-1">Product</label>
                        <select name="items[{{ $idx }}][product_id]" class="form-select form-select-sm" required>
                            @foreach($products as $p)
                                <option value="{{ $p->id }}" {{ $p->id === $bItem->product_id ? 'selected' : '' }}>
                                    {{ $p->name }} (${{ number_format($p->sale_price, 2) }} | Stock: {{ $p->qty }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small text-muted mb-1">Quantity</label>
                        <input type="number" name="items[{{ $idx }}][quantity]" class="form-control form-select-sm" value="{{ $bItem->quantity }}" min="1" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small text-muted mb-1">Perk</label>
                        <div class="form-check mt-1">
                            <input type="checkbox" name="items[{{ $idx }}][is_free_gift]" value="1" class="form-check-input" id="gift{{ $idx }}" {{ $bItem->is_free_gift ? 'checked' : '' }}>
                            <label class="form-check-label small" for="gift{{ $idx }}">Free Complimentary Gift</label>
                        </div>
                    </div>
                    <div class="col-md-1 text-end">
                        <button type="button" class="btn btn-sm btn-outline-danger remove-item-btn mt-3"><i class="bi bi-trash"></i></button>
                    </div>
                </div>
                @endforeach
            </div>

            <div class="d-flex justify-content-end gap-2 mt-4">
                <a href="{{ route('bundles.index') }}" class="btn btn-light border">Cancel</a>
                <button type="submit" class="btn btn-skincare-primary px-4">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    let itemIndex = {{ $bundle->items->count() }};
    const container = document.getElementById('bundleItemsContainer');
    const addBtn = document.getElementById('addItemBtn');

    addBtn.addEventListener('click', function () {
        const row = document.createElement('div');
        row.className = 'bundle-item-row row g-2 align-items-center border rounded p-2 bg-light';
        row.innerHTML = `
            <div class="col-md-6">
                <label class="form-label small text-muted mb-1">Product</label>
                <select name="items[${itemIndex}][product_id]" class="form-select form-select-sm" required>
                    <option value="">-- Choose Product --</option>
                    @foreach($products as $p)
                        <option value="{{ $p->id }}">{{ $p->name }} (\${{ number_format($p->sale_price, 2) }} | Stock: {{ $p->qty }})</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted mb-1">Quantity</label>
                <input type="number" name="items[${itemIndex}][quantity]" class="form-control form-select-sm" value="1" min="1" required>
            </div>
            <div class="col-md-3">
                <label class="form-label small text-muted mb-1">Perk</label>
                <div class="form-check mt-1">
                    <input type="checkbox" name="items[${itemIndex}][is_free_gift]" value="1" class="form-check-input" id="gift${itemIndex}">
                    <label class="form-check-label small" for="gift${itemIndex}">Free Complimentary Gift</label>
                </div>
            </div>
            <div class="col-md-1 text-end">
                <button type="button" class="btn btn-sm btn-outline-danger remove-item-btn mt-3"><i class="bi bi-trash"></i></button>
            </div>
        `;
        container.appendChild(row);
        itemIndex++;
    });

    container.addEventListener('click', function (e) {
        if (e.target.closest('.remove-item-btn')) {
            const rows = container.querySelectorAll('.bundle-item-row');
            if (rows.length > 1) {
                e.target.closest('.bundle-item-row').remove();
            } else {
                alert('A bundle must contain at least 1 product.');
            }
        }
    });
});
</script>
@endsection

