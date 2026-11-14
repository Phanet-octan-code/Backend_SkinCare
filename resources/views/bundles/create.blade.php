@extends('layouts.app')

@section('title', 'Create Promotional Bundle or Gift Set')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none text-muted">Marketing</a></li>
    <li class="breadcrumb-item"><a href="{{ route('bundles.index') }}" class="text-decoration-none text-muted">Bundles & Gift Sets</a></li>
    <li class="breadcrumb-item active text-dark" aria-current="page">Create</li>
@endsection

@section('content')
<x-page-header 
    title="Create Bundle or Gift Set" 
    description="Combine multiple skincare items into a single curated offer."
    :breadcrumbs="['Bundles & Gift Sets' => route('bundles.index'), 'Create' => null]" />

<div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-4">
        <form action="{{ route('bundles.store') }}" method="POST" enctype="multipart/form-data">
            @csrf

            <div class="row g-4">
                <div class="col-md-8">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Bundle / Set Title <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" placeholder="e.g. Daily Hydration 3-Step Routine" required value="{{ old('name') }}">
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Offer Type <span class="text-danger">*</span></label>
                            <select name="type" class="form-select" required>
                                <option value="bundle" {{ old('type') === 'bundle' ? 'selected' : '' }}>Product Bundle</option>
                                <option value="gift_set" {{ old('type') === 'gift_set' ? 'selected' : '' }}>Gift Set (Luxury Packaging)</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Status <span class="text-danger">*</span></label>
                            <select name="status" class="form-select" required>
                                <option value="active" {{ old('status', 'active') === 'active' ? 'selected' : '' }}>Active</option>
                                <option value="inactive" {{ old('status') === 'inactive' ? 'selected' : '' }}>Inactive</option>
                            </select>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Special Bundle Price ($) <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" name="price" class="form-control" placeholder="e.g. 75.00" required value="{{ old('price') }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Original Comparison Price ($)</label>
                            <input type="number" step="0.01" name="original_price" class="form-control" placeholder="e.g. 98.00 (leave blank to auto-calculate)" value="{{ old('original_price') }}">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Description & Skincare Benefits</label>
                        <textarea name="description" class="form-control" rows="3" placeholder="Describe routine steps, skin concerns addressed, and instructions...">{{ old('description') }}</textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Gift Box / Packaging Details</label>
                        <input type="text" name="packaging_details" class="form-control" placeholder="e.g. Includes silk ribbon luxury gift box & botanical card" value="{{ old('packaging_details') }}">
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Bundle Cover Image</label>
                        <input type="file" name="image" class="form-control" accept="image/*">
                        <div class="form-text small">Recommended: 800x800px square photo.</div>
                    </div>

                    <div class="alert alert-light border small text-muted">
                        <i class="bi bi-info-circle text-primary me-1"></i>
                        <strong>Dynamic Inventory Handling:</strong> Bundle stock is not a static number. Skinora automatically calculates real-time available sets based on the stock of all selected constituent products.
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
                <div class="bundle-item-row row g-2 align-items-center border rounded p-2 bg-light">
                    <div class="col-md-6">
                        <label class="form-label small text-muted mb-1">Product</label>
                        <select name="items[0][product_id]" class="form-select form-select-sm" required>
                            <option value="">-- Choose Product --</option>
                            @foreach($products as $p)
                                <option value="{{ $p->id }}">{{ $p->name }} (${{ number_format($p->sale_price, 2) }} | Stock: {{ $p->qty }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small text-muted mb-1">Quantity</label>
                        <input type="number" name="items[0][quantity]" class="form-control form-select-sm" value="1" min="1" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small text-muted mb-1">Perk</label>
                        <div class="form-check mt-1">
                            <input type="checkbox" name="items[0][is_free_gift]" value="1" class="form-check-input" id="gift0">
                            <label class="form-check-label small" for="gift0">Free Complimentary Gift</label>
                        </div>
                    </div>
                    <div class="col-md-1 text-end">
                        <button type="button" class="btn btn-sm btn-outline-danger remove-item-btn mt-3"><i class="bi bi-trash"></i></button>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2 mt-4">
                <a href="{{ route('bundles.index') }}" class="btn btn-light border">Cancel</a>
                <button type="submit" class="btn btn-skincare-primary px-4">Save Bundle</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    let itemIndex = 1;
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

