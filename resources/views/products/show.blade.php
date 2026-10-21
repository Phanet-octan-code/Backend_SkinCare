@extends('layouts.app')

@section('title', $product->name)

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none text-muted">Catalog</a></li>
    <li class="breadcrumb-item"><a href="{{ route('products.index') }}" class="text-decoration-none text-muted">Products</a></li>
    <li class="breadcrumb-item active text-dark" aria-current="page">{{ $product->name }}</li>
@endsection

@section('content')
<x-page-header 
    :title="$product->name" 
    description="Detailed formulation specifications, inventory balance, and supplier attribution."
    :breadcrumbs="['Catalog' => route('products.index'), 'Products' => route('products.index'), $product->name => null]">
    <x-slot:actions>
        <a href="{{ route('products.index') }}" class="btn btn-sm btn-light border">
            <i class="bi bi-arrow-left me-1"></i> Back to Catalog
        </a>
    </x-slot:actions>
</x-page-header>

<div class="row g-4">
    <div class="col-12 col-lg-4">
        <div class="card-skincare p-4 text-center">
            @if($product->image && file_exists(public_path($product->image)))
                <img src="{{ asset($product->image) }}" alt="{{ $product->name }}" class="img-fluid rounded-3 border mb-3 object-fit-cover shadow-xs" style="max-height: 280px;">
            @else
                <div class="brand-icon-box mx-auto my-4" style="width: 80px; height: 80px; font-size: 2.5rem;">
                    <i class="bi bi-droplet-half"></i>
                </div>
            @endif

            <h5 class="fw-bold text-dark mb-1">{{ $product->name }}</h5>
            <div class="font-monospace text-muted small mb-3">Barcode: {{ $product->barcode }}</div>
            <x-status-badge :status="$product->status" />
        </div>
    </div>

    <div class="col-12 col-lg-8">
        <div class="card-skincare p-4">
            <h6 class="fw-bold text-dark border-bottom pb-2 mb-3">Formulation Specifications</h6>

            <div class="row g-3 mb-4">
                <div class="col-6 col-sm-3">
                    <div class="p-3 border rounded-2 bg-light">
                        <span class="d-block text-muted small" style="font-size: 0.72rem;">Cost Price</span>
                        <span class="fs-5 fw-bold text-dark">${{ number_format($product->cost_price, 2) }}</span>
                    </div>
                </div>
                <div class="col-6 col-sm-3">
                    <div class="p-3 border rounded-2 bg-light">
                        <span class="d-block text-muted small" style="font-size: 0.72rem;">Retail Selling Price</span>
                        <span class="fs-5 fw-bold text-dark">${{ number_format($product->sale_price, 2) }}</span>
                    </div>
                </div>
                <div class="col-6 col-sm-3">
                    <div class="p-3 border rounded-2 bg-light">
                        <span class="d-block text-muted small" style="font-size: 0.72rem;">Current Stock</span>
                        <span class="fs-5 fw-bold font-monospace {{ $product->qty <= 0 ? 'text-danger' : ($product->isLowStock() ? 'text-warning' : 'text-success') }}">
                            {{ $product->qty }} units
                        </span>
                    </div>
                </div>
                <div class="col-6 col-sm-3">
                    <div class="p-3 border rounded-2 bg-light">
                        <span class="d-block text-muted small" style="font-size: 0.72rem;">Reorder Threshold</span>
                        <span class="fs-5 fw-bold font-monospace text-muted">{{ $product->low_stock_threshold }} units</span>
                    </div>
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-12 col-md-6">
                    <div class="text-muted small">Category</div>
                    <div class="fw-semibold text-dark">{{ $product->category->name ?? 'N/A' }}</div>
                </div>
                <div class="col-12 col-md-6">
                    <div class="text-muted small">Supplier / Distributor</div>
                    <div class="fw-semibold text-dark">{{ $product->supplier->name ?? 'N/A' }}</div>
                </div>
            </div>

            <div class="mb-4">
                <div class="text-muted small mb-1">Description & Ingredients</div>
                <p class="text-dark small mb-0" style="line-height: 1.6;">
                    {{ $product->description ?: 'No formulation description provided.' }}
                </p>
            </div>

            <div class="border-top pt-3 d-flex gap-2">
                <a href="{{ route('inventory.index', ['search' => $product->barcode]) }}" class="btn btn-sm btn-light border">
                    <i class="bi bi-boxes me-1"></i> View Inventory Movements
                </a>
                <form method="POST" action="{{ route('products.toggle-status', $product) }}">
                    @csrf
                    @method('PATCH')
                    <button type="submit" class="btn btn-sm btn-outline-secondary">
                        <i class="bi {{ $product->status === 'active' ? 'bi-pause-circle' : 'bi-play-circle' }} me-1"></i>
                        {{ $product->status === 'active' ? 'Deactivate Product' : 'Activate Product' }}
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
