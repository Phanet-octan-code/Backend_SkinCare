@extends('layouts.app')

@section('title', __('messages.products_formulations'))

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none text-muted">{{ __('messages.catalog') }}</a></li>
    <li class="breadcrumb-item active text-dark" aria-current="page">{{ __('messages.products') }}</li>
@endsection

@section('content')
<x-page-header 
    :title="__('messages.products_formulations')" 
    :description="__('messages.products_formulations_desc')"
    :breadcrumbs="[__('messages.catalog') => route('products.index'), __('messages.products') => null]">
    <x-slot:actions>
        <x-action-button roles="admin,manager" icon="bi-plus-lg" modalTarget="#createProductModal">
            {{ __('messages.add_product') }}
        </x-action-button>
    </x-slot:actions>
</x-page-header>

<!-- Catalog KPI Metrics -->
<div class="row g-3 mb-4">
    <div class="col-12 col-sm-6 col-xl-3">
        <x-kpi-card 
            :title="__('messages.total_formulations')" 
            :value="$totalProducts" 
            :subtitle="__('messages.catalog_skus')"
            icon="bi-box-seam" 
            color="sage" />
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <x-kpi-card 
            :title="__('messages.active_in_store')" 
            :value="$activeCatalogCount" 
            :subtitle="__('messages.pos_checkout_ready')"
            icon="bi-check-circle" 
            color="success" />
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <x-kpi-card 
            :title="__('messages.low_stock_alerts')" 
            :value="$lowStockCount" 
            :subtitle="__('messages.at_or_below_threshold')"
            icon="bi-exclamation-triangle" 
            color="warning" />
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <x-kpi-card 
            :title="__('messages.out_of_stock_title')" 
            :value="$outOfStockCount" 
            :subtitle="__('messages.requires_replenishment')"
            icon="bi-x-octagon" 
            color="danger" />
    </div>
</div>

<!-- Search & Filters -->
<x-search-filter :action="route('products.index')" :resetUrl="route('products.index')" :placeholder="__('messages.search_placeholder')">
    <div class="col-6 col-md-2">
        <select name="category_id" class="form-select form-select-sm" style="border-radius: 8px;">
            <option value="">{{ __('messages.all_categories') }}</option>
            @foreach($categories as $category)
                <option value="{{ $category->id }}" {{ request('category_id') == $category->id ? 'selected' : '' }}>
                    {{ $category->localized_name }}
                </option>
            @endforeach
        </select>
    </div>
    <div class="col-6 col-md-2">
        <select name="supplier_id" class="form-select form-select-sm" style="border-radius: 8px;">
            <option value="">{{ __('messages.all_suppliers') }}</option>
            @foreach($suppliers as $supplier)
                <option value="{{ $supplier->id }}" {{ request('supplier_id') == $supplier->id ? 'selected' : '' }}>
                    {{ $supplier->name }}
                </option>
            @endforeach
        </select>
    </div>
    <div class="col-6 col-md-2">
        <select name="status" class="form-select form-select-sm" style="border-radius: 8px;">
            <option value="">{{ __('messages.all_statuses') }}</option>
            <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>{{ __('messages.active') }}</option>
            <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>{{ __('messages.inactive') }}</option>
            <option value="trashed" {{ request('status') === 'trashed' ? 'selected' : '' }}>{{ __('messages.deleted_archive') }}</option>
        </select>
    </div>
    <div class="col-6 col-md-2">
        <select name="stock_status" class="form-select form-select-sm" style="border-radius: 8px;">
            <option value="">{{ __('messages.all_stock_levels') }}</option>
            <option value="in_stock" {{ request('stock_status') === 'in_stock' ? 'selected' : '' }}>{{ __('messages.adequate_stock') }}</option>
            <option value="low" {{ request('stock_status') === 'low' ? 'selected' : '' }}>{{ __('messages.low_stock') }}</option>
            <option value="out" {{ request('stock_status') === 'out' ? 'selected' : '' }}>{{ __('messages.out_of_stock') }}</option>
        </select>
    </div>
    <div class="col-12 col-md-2">
        <select name="sort" class="form-select form-select-sm" style="border-radius: 8px;">
            <option value="latest" {{ request('sort') === 'latest' ? 'selected' : '' }}>{{ __('messages.newest_first') }}</option>
            <option value="name_asc" {{ request('sort') === 'name_asc' ? 'selected' : '' }}>{{ __('messages.name_asc') }}</option>
            <option value="name_desc" {{ request('sort') === 'name_desc' ? 'selected' : '' }}>{{ __('messages.name_desc') }}</option>
            <option value="price_asc" {{ request('sort') === 'price_asc' ? 'selected' : '' }}>{{ __('messages.price_asc') }}</option>
            <option value="price_desc" {{ request('sort') === 'price_desc' ? 'selected' : '' }}>{{ __('messages.price_desc') }}</option>
            <option value="qty_asc" {{ request('sort') === 'qty_asc' ? 'selected' : '' }}>{{ __('messages.qty_asc') }}</option>
            <option value="qty_desc" {{ request('sort') === 'qty_desc' ? 'selected' : '' }}>{{ __('messages.qty_desc') }}</option>
        </select>
    </div>
</x-search-filter>

<!-- Products Table -->
<x-table>
    <x-slot:thead>
        <tr>
            <th class="ps-3 py-3" style="width: 44px;">
                <div class="form-check mb-0">
                    <input class="form-check-input" type="checkbox" id="selectAllProducts" title="Select all products on this page">
                </div>
            </th>
            <th class="py-3">{{ __('messages.product_sku') }}</th>
            <th class="py-3">{{ __('messages.categories') }}</th>
            <th class="py-3">{{ __('messages.barcode') }}</th>
            <th class="py-3">{{ __('messages.cost_price') }}</th>
            <th class="py-3">{{ __('messages.retail_price') }}</th>
            <th class="py-3">{{ __('messages.stock_balance') }}</th>
            <th class="py-3">{{ __('messages.status') }}</th>
            <th class="pe-4 text-end py-3">{{ __('messages.actions') }}</th>
        </tr>
    </x-slot:thead>

    <x-slot:cardHeader>
        <div class="d-flex align-items-center justify-content-between w-100 flex-wrap gap-2">
            <div class="d-flex align-items-center gap-2">
                <h5 class="fw-bold mb-0 fs-6 text-dark">{{ __('messages.formulation_catalog') }}</h5>
                <span class="badge bg-light text-muted border">{{ __('messages.items_count', ['count' => $products->total()]) }}</span>
            </div>

            <!-- Bulk Selection Action Bar (appears when 1 or more items selected) -->
            <div id="bulkActionsToolbar" class="d-none align-items-center gap-2 bg-white border rounded-pill px-3 py-1 shadow-sm">
                <span class="small fw-semibold text-dark">
                    <span id="selectedCountBadge" class="badge bg-primary text-white rounded-pill me-1">0</span> {{ __('messages.selected') }}
                </span>
                <div class="vr my-1 text-muted" style="height: 16px;"></div>
                <button type="button" class="btn btn-sm btn-danger py-1 px-3 d-inline-flex align-items-center gap-1 rounded-pill fw-semibold" id="bulkDeleteBtn" data-bs-toggle="modal" data-bs-target="#bulkDeleteModal" style="font-size: 0.78rem;">
                    <i class="bi bi-trash3"></i>
                    <span>{{ __('messages.delete_selected') }}</span>
                </button>
                <button type="button" class="btn btn-sm btn-link text-muted p-0 text-decoration-none small" id="clearSelectionBtn" style="font-size: 0.76rem;">
                    {{ __('messages.clear_selection') }}
                </button>
            </div>
        </div>
    </x-slot:cardHeader>

    @forelse($products as $product)
    @php
        // Determine active promotion indicators
        $activePromo = $product->promotions->first() ?? $storewidePromotions->first();
        $hasActivePromo = (bool) $activePromo;
        $isOutOfStock = $product->qty <= 0;
        $isLowStock = !$isOutOfStock && $product->qty <= $product->low_stock_threshold;
    @endphp
    <tr class="product-row" id="product-row-{{ $product->id }}">
        <td class="ps-3" style="width: 44px;">
            <div class="form-check mb-0">
                <input class="form-check-input product-select-checkbox" 
                       type="checkbox" 
                       value="{{ $product->id }}" 
                       data-id="{{ $product->id }}"
                       data-name="{{ $product->name }}"
                       data-barcode="{{ $product->barcode }}"
                       data-price="${{ number_format($product->sale_price, 2) }}"
                       data-stock="{{ $product->qty }}"
                       {{ $product->trashed() ? 'disabled title="Archived product"' : '' }}>
            </div>
        </td>
        <td>
            <div class="d-flex align-items-center gap-2">
                @if($product->image && file_exists(public_path($product->image)))
                    <img src="{{ asset($product->image) }}" alt="{{ $product->name }}" class="rounded-2 border shadow-xs" style="width: 44px; height: 44px; object-fit: contain; background-color: #F8F9F7; padding: 2px;">
                @else
                    <span class="brand-icon-box" style="width: 44px; height: 44px; font-size: 1.15rem;">
                        <i class="bi bi-droplet-half"></i>
                    </span>
                @endif
                <div>
                    <div class="d-flex align-items-center gap-1">
                        <span class="fw-bold text-dark">{{ $product->name }}</span>
                        @if($hasActivePromo)
                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle" style="font-size: 0.65rem;">
                                <i class="bi bi-tag-fill me-1"></i>{{ $activePromo->discount_type === 'percentage' ? number_format($activePromo->discount_value, 0) . '% ' . __('messages.off') : '$' . number_format($activePromo->discount_value, 2) . ' ' . __('messages.off') }}
                            </span>
                        @endif
                    </div>
                    <div class="text-muted" style="font-size: 0.72rem;">
                        {{ __('messages.suppliers') }}: <span class="fw-semibold text-secondary">{{ $product->supplier->name ?? __('messages.direct') }}</span>
                    </div>
                </div>
            </div>
        </td>
        <td>
            <span class="badge bg-light text-dark border">
                {{ $product->category->localized_name ?? __('messages.general') }}
            </span>
        </td>
        <td>
            <span class="font-monospace text-muted small">{{ $product->barcode }}</span>
        </td>
        <td>
            <span class="text-muted small">${{ number_format($product->cost_price, 2) }}</span>
        </td>
        <td>
            <div>
                <span class="fw-bold text-dark">${{ number_format($product->sale_price, 2) }}</span>
                @if($product->cost_price > 0 && $product->sale_price > $product->cost_price)
                    <div class="text-success" style="font-size: 0.68rem;">
                        +{{ round((($product->sale_price - $product->cost_price) / $product->cost_price) * 100) }}% {{ __('messages.margin') }}
                    </div>
                @endif
            </div>
        </td>
        <td>
            <div class="d-flex align-items-center gap-2">
                <span class="fw-bold font-monospace {{ $isOutOfStock ? 'text-danger' : ($isLowStock ? 'text-warning' : 'text-success') }}">
                    {{ $product->qty }} {{ __('messages.units') }}
                </span>
                @if($isOutOfStock)
                    <span class="badge bg-danger text-white" style="font-size: 0.65rem;">{{ __('messages.out_of_stock') }}</span>
                @elseif($isLowStock)
                    <span class="badge bg-warning-subtle text-warning border border-warning-subtle" style="font-size: 0.65rem;">{{ __('messages.low') }} (≤{{ $product->low_stock_threshold }})</span>
                @endif
            </div>
        </td>
        <td>
            @if($product->trashed())
                <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">
                    <i class="bi bi-trash me-1"></i> {{ __('messages.deleted_archive') }}
                </span>
            @else
                <x-status-badge :status="$product->status" />
            @endif
        </td>
        <td class="pe-4 text-end">
            <div class="dropdown">
                <button class="btn btn-sm btn-light border p-1 px-2" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-three-dots-vertical"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow-sm border" style="border-radius: 8px;">
                    @if($product->trashed())
                        <li>
                            <form method="POST" action="{{ route('products.restore', $product->id) }}">
                                @csrf
                                <button type="submit" class="dropdown-item py-1 small text-success">
                                    <i class="bi bi-arrow-counterclockwise me-2"></i> {{ __('messages.restore_product') }}
                                </button>
                            </form>
                        </li>
                    @else
                        <li>
                            <button type="button" class="dropdown-item py-1 small" 
                                    data-bs-toggle="modal" 
                                    data-bs-target="#viewProductModal"
                                    data-product="{{ json_encode([
                                        'id' => $product->id,
                                        'name' => $product->name,
                                        'barcode' => $product->barcode,
                                        'category' => $product->category->localized_name ?? __('messages.general'),
                                        'supplier' => $product->supplier->name ?? __('messages.direct'),
                                        'cost_price' => number_format($product->cost_price, 2),
                                        'sale_price' => number_format($product->sale_price, 2),
                                        'qty' => $product->qty,
                                        'low_stock_threshold' => $product->low_stock_threshold,
                                        'status' => $product->status,
                                        'description' => $product->description,
                                        'image' => $product->image && file_exists(public_path($product->image)) ? asset($product->image) : null,
                                        'promo' => $hasActivePromo ? ($activePromo->name . ' (' . ($activePromo->discount_type === 'percentage' ? number_format($activePromo->discount_value, 0) . '% ' . __('messages.off') : '$' . number_format($activePromo->discount_value, 2) . ' ' . __('messages.off')) . ')') : 'None'
                                    ]) }}">
                                <i class="bi bi-eye me-2 text-primary"></i> {{ __('messages.view_details') }}
                            </button>
                        </li>
                        <li>
                            <button type="button" class="dropdown-item py-1 small"
                                    data-bs-toggle="modal"
                                    data-bs-target="#editProductModal"
                                    data-id="{{ $product->id }}"
                                    data-name="{{ $product->name }}"
                                    data-barcode="{{ $product->barcode }}"
                                    data-category-id="{{ $product->category_id }}"
                                    data-supplier-id="{{ $product->supplier_id }}"
                                    data-cost-price="{{ $product->cost_price }}"
                                    data-sale-price="{{ $product->sale_price }}"
                                    data-qty="{{ $product->qty }}"
                                    data-threshold="{{ $product->low_stock_threshold }}"
                                    data-status="{{ $product->status }}"
                                    data-description="{{ $product->description }}"
                                    data-action="{{ route('products.update', $product) }}">
                                <i class="bi bi-pencil me-2 text-secondary"></i> {{ __('messages.edit_product') }}
                            </button>
                        </li>
                        <li>
                            <form method="POST" action="{{ route('products.toggle-status', $product) }}" class="pos-quick-form">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="dropdown-item py-1 small">
                                    <i class="bi {{ $product->status === 'active' ? 'bi-pause-circle text-warning' : 'bi-play-circle text-success' }} me-2"></i>
                                    {{ $product->status === 'active' ? __('messages.deactivate') : __('messages.activate') }}
                                </button>
                            </form>
                        </li>
                        <li>
                            <a class="dropdown-item py-1 small" href="{{ route('inventory.index', ['search' => $product->barcode]) }}">
                                <i class="bi bi-boxes me-2 text-info"></i> Stock Audit Log
                            </a>
                        </li>
                        <li><hr class="dropdown-divider my-1"></li>
                        <li>
                            <button type="button" class="dropdown-item py-1 small text-danger" 
                                    data-bs-toggle="modal" 
                                    data-bs-target="#deleteProductModal{{ $product->id }}">
                                <i class="bi bi-trash me-2"></i> Delete Product
                            </button>
                        </li>
                    @endif
                </ul>
            </div>

            @if(!$product->trashed())
            <!-- Standard Delete Dialog -->
            <x-confirm-dialog 
                id="deleteProductModal{{ $product->id }}" 
                title="Delete Product" 
                :message="'Are you sure you want to delete \'' . $product->name . '\'? It will be removed from catalog and storefront while preserving historical audit and sales records.'"
                confirmText="Delete Product"
                :action="route('products.destroy', $product)"
                method="DELETE"
                :danger="true" />
            @endif
        </td>
    </tr>
    @empty
    <tr>
        <td colspan="9" class="p-0">
            <x-empty-state 
                icon="bi-box-seam" 
                title="No products found" 
                description="No products matched your search or filter criteria. Try resetting filters." />
        </td>
    </tr>
    @endforelse

    @if($products->hasPages())
    <x-slot:pagination>
        {{ $products->links() }}
    </x-slot:pagination>
    @endif
</x-table>

<!-- Create Product Modal -->
<x-modal id="createProductModal" title="Add Skincare Product" size="lg">
    <form method="POST" action="{{ route('products.store') }}" enctype="multipart/form-data">
        @csrf
        <div class="row g-3">
            <div class="col-12 col-md-8">
                <x-form-input name="name" label="Product / Formulation Name" placeholder="e.g. CeraVe Hydrating Cleanser 473ml" required />
            </div>
            <div class="col-12 col-md-4">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <label for="create_prod_barcode" class="form-label small fw-semibold text-dark mb-0">
                        {{ __('messages.barcode') }} / SKU <span class="text-danger">*</span>
                    </label>
                    <button type="button" 
                            class="btn btn-link btn-sm p-0 text-decoration-none text-success" 
                            id="btnQuickGenBarcode" 
                            style="font-size: 0.74rem; font-weight: 600;"
                            title="Generate a valid, unique 13-digit EAN barcode">
                        <i class="bi bi-magic me-1"></i> Auto-Generate
                    </button>
                </div>
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0 text-muted" style="border-radius: 8px 0 0 8px; border-color: var(--border-color);">
                        <i class="bi bi-upc-scan"></i>
                    </span>
                    <input type="text" 
                           name="barcode" 
                           id="create_prod_barcode" 
                           class="form-control font-monospace border-start-0 border-end-0" 
                           placeholder="e.g. 3337875597180" 
                           required
                           style="border-color: var(--border-color); font-size: 0.88rem;">
                    <button type="button" 
                            class="btn btn-skincare-outline d-inline-flex align-items-center gap-1" 
                            id="btnGenerateBarcode"
                            style="border-radius: 0 8px 8px 0; border-color: var(--border-color); font-size: 0.82rem; font-weight: 600; padding: 0 12px;" 
                            title="Generate valid unique EAN-13 barcode">
                        <i class="bi bi-arrow-repeat"></i>
                        <span>Generate</span>
                    </button>
                </div>
                <div class="form-text text-muted" style="font-size: 0.72rem;">Enter existing manufacturer barcode or click Generate.</div>
            </div>

            <div class="col-12 col-md-6">
                <label class="form-label small fw-semibold" for="create_category_id">Category</label>
                <select id="create_category_id" name="category_id" class="form-select form-select-sm" style="border-radius: 8px;" required>
                    <option value="">Select category...</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-md-6">
                <label class="form-label small fw-semibold" for="create_supplier_id">Supplier / Distributor</label>
                <select id="create_supplier_id" name="supplier_id" class="form-select form-select-sm" style="border-radius: 8px;" required>
                    <option value="">Select supplier...</option>
                    @foreach($suppliers as $supplier)
                        <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="col-12 col-md-4">
                <x-form-input type="number" step="0.01" min="0" name="cost_price" label="Cost Price ($)" placeholder="0.00" required />
            </div>
            <div class="col-12 col-md-4">
                <x-form-input type="number" step="0.01" min="0" name="sale_price" label="Retail Selling Price ($)" placeholder="0.00" required />
            </div>
            <div class="col-12 col-md-4">
                <x-form-input type="number" min="1" name="low_stock_threshold" label="Reorder Threshold" value="10" required />
            </div>

            <div class="col-12 col-md-6">
                <x-form-input type="number" min="0" name="qty" label="Starting Stock Quantity" value="0" helperText="Initial inventory will be logged into the audit transaction ledger." />
            </div>
            <div class="col-12 col-md-6">
                <label class="form-label small fw-semibold" for="create_product_status">Status</label>
                <select id="create_product_status" name="status" class="form-select form-select-sm" style="border-radius: 8px;" required>
                    <option value="active" selected>Active (Visible for POS checkout)</option>
                    <option value="inactive">Inactive (Draft / Hidden)</option>
                </select>
            </div>

            <div class="col-12">
                <label class="form-label small fw-semibold" for="create_image">Product Thumbnail / Image</label>
                <input type="file" id="create_image" name="image" class="form-control form-control-sm" accept="image/png,image/jpeg,image/jpg,image/webp" style="border-radius: 8px;">
                <div class="form-text" style="font-size: 0.72rem;">Supported formats: JPG, PNG, WEBP. Maximum file size: 10MB.</div>
            </div>

            <div class="col-12">
                <x-form-textarea name="description" label="Formula Description & Active Ingredients" placeholder="e.g. Formulated with 3 essential ceramides, hyaluronic acid, and niacinamide to cleanse without disrupting the skin barrier..." rows="2" />
            </div>
        </div>

        <div class="d-flex justify-content-end gap-2 mt-4 pt-2 border-top">
            <button type="button" class="btn btn-sm btn-light border px-3" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-sm btn-skincare-primary px-3">
                <i class="bi bi-check-lg me-1"></i> Save Product
            </button>
        </div>
    </form>
</x-modal>

<!-- Edit Product Modal -->
<x-modal id="editProductModal" title="Edit Skincare Product" size="lg">
    <form id="editProductForm" method="POST" action="" enctype="multipart/form-data">
        @csrf
        @method('PUT')
        <div class="row g-3">
            <div class="col-12 col-md-8">
                <x-form-input id="edit_prod_name" name="name" label="Product / Formulation Name" required />
            </div>
            <div class="col-12 col-md-4">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <label for="edit_prod_barcode" class="form-label small fw-semibold text-dark mb-0">
                        {{ __('messages.barcode') }} / SKU <span class="text-danger">*</span>
                    </label>
                    <button type="button" 
                            class="btn btn-link btn-sm p-0 text-decoration-none text-success" 
                            id="btnQuickGenEditBarcode" 
                            style="font-size: 0.74rem; font-weight: 600;"
                            title="Generate a valid, unique 13-digit EAN barcode">
                        <i class="bi bi-magic me-1"></i> New Barcode
                    </button>
                </div>
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0 text-muted" style="border-radius: 8px 0 0 8px; border-color: var(--border-color);">
                        <i class="bi bi-upc-scan"></i>
                    </span>
                    <input type="text" 
                           id="edit_prod_barcode" 
                           name="barcode" 
                           class="form-control font-monospace border-start-0 border-end-0" 
                           required
                           style="border-color: var(--border-color); font-size: 0.88rem;">
                    <button type="button" 
                            class="btn btn-skincare-outline d-inline-flex align-items-center gap-1" 
                            id="btnGenerateEditBarcode"
                            style="border-radius: 0 8px 8px 0; border-color: var(--border-color); font-size: 0.82rem; font-weight: 600; padding: 0 12px;" 
                            title="Generate valid unique EAN-13 barcode">
                        <i class="bi bi-arrow-repeat"></i>
                        <span>Generate</span>
                    </button>
                </div>
            </div>

            <div class="col-12 col-md-6">
                <label class="form-label small fw-semibold" for="edit_prod_category_id">Category</label>
                <select id="edit_prod_category_id" name="category_id" class="form-select form-select-sm" style="border-radius: 8px;" required>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-md-6">
                <label class="form-label small fw-semibold" for="edit_prod_supplier_id">Supplier / Distributor</label>
                <select id="edit_prod_supplier_id" name="supplier_id" class="form-select form-select-sm" style="border-radius: 8px;" required>
                    @foreach($suppliers as $supplier)
                        <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="col-12 col-md-4">
                <x-form-input type="number" step="0.01" min="0" id="edit_prod_cost_price" name="cost_price" label="Cost Price ($)" required />
            </div>
            <div class="col-12 col-md-4">
                <x-form-input type="number" step="0.01" min="0" id="edit_prod_sale_price" name="sale_price" label="Retail Selling Price ($)" required />
            </div>
            <div class="col-12 col-md-4">
                <x-form-input type="number" min="1" id="edit_prod_threshold" name="low_stock_threshold" label="Reorder Threshold" required />
            </div>

            <!-- Current Stock Display (Audited Read-Only) -->
            <div class="col-12 col-md-6">
                <label class="form-label small fw-semibold">Current Stock Balance</label>
                <div class="p-2 border rounded-2 bg-light d-flex align-items-center justify-content-between">
                    <span id="edit_prod_qty_badge" class="fw-bold font-monospace text-dark fs-6">0 units</span>
                    <a href="{{ route('inventory.stock-in') }}" class="btn btn-sm btn-outline-secondary py-0" style="font-size: 0.72rem;">
                        <i class="bi bi-box-arrow-in-down me-1"></i> Stock-In / Adjust
                    </a>
                </div>
                <div class="form-text" style="font-size: 0.7rem;">Stock quantity changes require an audited inventory transaction.</div>
            </div>

            <div class="col-12 col-md-6">
                <label class="form-label small fw-semibold" for="edit_prod_status">Status</label>
                <select id="edit_prod_status" name="status" class="form-select form-select-sm" style="border-radius: 8px;" required>
                    <option value="active">Active (Visible for POS checkout)</option>
                    <option value="inactive">Inactive (Hidden)</option>
                </select>
            </div>

            <div class="col-12">
                <label class="form-label small fw-semibold" for="edit_image">Replace Product Image (Optional)</label>
                <input type="file" id="edit_image" name="image" class="form-control form-control-sm" accept="image/png,image/jpeg,image/jpg,image/webp" style="border-radius: 8px;">
                <div class="form-text" style="font-size: 0.72rem;">Leave empty to keep existing image. Supported: JPG, PNG, WEBP (Max 10MB).</div>
            </div>

            <div class="col-12">
                <x-form-textarea id="edit_prod_description" name="description" label="Formula Description & Active Ingredients" rows="2" />
            </div>
        </div>

        <div class="d-flex justify-content-end gap-2 mt-4 pt-2 border-top">
            <button type="button" class="btn btn-sm btn-light border px-3" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-sm btn-skincare-primary px-3">
                <i class="bi bi-check-lg me-1"></i> Update Product
            </button>
        </div>
    </form>
</x-modal>

<!-- View Product Details Modal -->
<x-modal id="viewProductModal" title="Skincare Product Specification" size="lg">
    <div class="row g-4">
        <div class="col-12 col-md-4 text-center">
            <div id="viewProdImageContainer" class="p-2 border rounded-3 mb-2" style="background-color: #F8F9F7; min-height: 220px; display: flex; align-items: center; justify-content: center;">
                <img id="viewProdImage" src="" alt="Product Image" class="img-fluid rounded-2" style="max-height: 240px; width: auto; max-width: 100%; object-fit: contain; padding: 6px;">
                <div id="viewProdPlaceholder" class="brand-icon-box mx-auto my-4" style="width: 72px; height: 72px; font-size: 2.2rem;">
                    <i class="bi bi-droplet-half"></i>
                </div>
            </div>
            <div id="viewProdStatusBadge" class="mb-2"></div>
            <div class="p-2 border rounded-2 bg-white d-inline-block text-center shadow-xs mb-2 w-100" style="max-width: 220px;">
                <svg id="viewProdBarcodeSvg" style="max-width: 100%; height: 46px; display: block; margin: 0 auto;"></svg>
                <div class="small text-dark font-monospace fw-bold mt-1" id="viewProdBarcode" style="letter-spacing: 1px;"></div>
            </div>
            <div>
                <button type="button" class="btn btn-sm btn-outline-secondary d-inline-flex align-items-center gap-1" id="btnPrintBarcodeLabel" title="Print Barcode Label Sticker">
                    <i class="bi bi-printer"></i>
                    <span>Print Label</span>
                </button>
            </div>
        </div>
        <div class="col-12 col-md-8">
            <h5 id="viewProdName" class="fw-bold text-dark mb-1"></h5>
            <div class="d-flex align-items-center gap-2 mb-3">
                <span class="badge bg-light text-dark border" id="viewProdCategory"></span>
                <span class="text-muted small">|</span>
                <span class="text-muted small">Distributor: <strong class="text-dark" id="viewProdSupplier"></strong></span>
            </div>

            <div class="row g-2 mb-3">
                <div class="col-6 col-sm-3">
                    <div class="p-2 border rounded-2 bg-light">
                        <div class="text-muted" style="font-size: 0.7rem; text-transform: uppercase;">Cost Price</div>
                        <div class="fw-bold text-dark" id="viewProdCostPrice">$0.00</div>
                    </div>
                </div>
                <div class="col-6 col-sm-3">
                    <div class="p-2 border rounded-2 bg-light">
                        <div class="text-muted" style="font-size: 0.7rem; text-transform: uppercase;">Retail Price</div>
                        <div class="fw-bold text-dark" id="viewProdSalePrice">$0.00</div>
                    </div>
                </div>
                <div class="col-6 col-sm-3">
                    <div class="p-2 border rounded-2 bg-light">
                        <div class="text-muted" style="font-size: 0.7rem; text-transform: uppercase;">Current Stock</div>
                        <div class="fw-bold font-monospace" id="viewProdQty">0 units</div>
                    </div>
                </div>
                <div class="col-6 col-sm-3">
                    <div class="p-2 border rounded-2 bg-light">
                        <div class="text-muted" style="font-size: 0.7rem; text-transform: uppercase;">Threshold</div>
                        <div class="fw-bold font-monospace text-muted" id="viewProdThreshold">10 units</div>
                    </div>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label small fw-bold text-dark mb-1">Active Promotion</label>
                <div class="p-2 border rounded-2 bg-light small" id="viewProdPromo"></div>
            </div>

            <div class="mb-2">
                <label class="form-label small fw-bold text-dark mb-1">Formula & Ingredients</label>
                <p class="small text-muted mb-0" id="viewProdDescription" style="line-height: 1.6;"></p>
            </div>
        </div>
    </div>
    <div class="d-flex justify-content-end gap-2 mt-4 pt-2 border-top">
        <button type="button" class="btn btn-sm btn-light border px-3" data-bs-dismiss="modal">Close</button>
    </div>
</x-modal>

<!-- Bulk Delete Confirmation Modal -->
<div class="modal fade" id="bulkDeleteModal" tabindex="-1" aria-labelledby="bulkDeleteModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-bottom py-3 bg-danger-subtle">
                <h6 class="modal-title fw-bold text-danger mb-0 d-flex align-items-center gap-2" id="bulkDeleteModalLabel">
                    <i class="bi bi-exclamation-triangle-fill fs-5"></i>
                    <span>Delete Selected Products</span>
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="bulkDeleteForm" method="POST" action="{{ route('products.bulk-delete') }}">
                @csrf
                @method('DELETE')
                <div class="modal-body p-4">
                    <p class="text-dark mb-2">
                        Are you sure you want to delete <strong id="modalBulkCountText">0 products</strong>?
                    </p>
                    <p class="text-muted small mb-3">
                        These formulations will be safely removed from the active store catalog and POS while preserving historical inventory audits and sales ledger records.
                    </p>

                    <!-- Preview of selected products -->
                    <label class="form-label small fw-semibold text-dark mb-1">Products to be deleted:</label>
                    <div class="border rounded-3 p-2 bg-light mb-3" style="max-height: 200px; overflow-y: auto;">
                        <ul class="list-group list-group-flush small" id="bulkDeleteItemsList">
                            <!-- Populated dynamically via JS -->
                        </ul>
                    </div>

                    <!-- Hidden inputs container for form submission fallback -->
                    <div id="bulkDeleteHiddenInputs"></div>

                    <div class="alert alert-warning py-2 px-3 small d-flex align-items-center gap-2 mb-0" style="border-radius: 8px;">
                        <i class="bi bi-info-circle-fill text-warning flex-shrink-0"></i>
                        <span>You can restore deleted products anytime from the <strong>Deleted Archive</strong> filter.</span>
                    </div>
                </div>
                <div class="modal-footer border-top py-2 bg-light">
                    <button type="button" class="btn btn-sm btn-skincare-outline" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-danger px-4 fw-bold" id="confirmBulkDeleteBtn">
                        <span class="spinner-border spinner-border-sm me-1 d-none" id="bulkDeleteSpinner" role="status"></span>
                        <i class="bi bi-trash3 me-1" id="bulkDeleteBtnIcon"></i>
                        <span id="confirmBulkDeleteText">Delete Products</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
(function () {
    function initProductManagement() {
        // Edit Product Modal population
        const editModal = document.getElementById('editProductModal');
        if (editModal && !editModal.dataset.listenerAttached) {
            editModal.dataset.listenerAttached = 'true';
            editModal.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                if (!button) return;

                document.getElementById('editProductForm').action = button.getAttribute('data-action');
                document.getElementById('edit_prod_name').value = button.getAttribute('data-name') || '';
                document.getElementById('edit_prod_barcode').value = button.getAttribute('data-barcode') || '';
                document.getElementById('edit_prod_category_id').value = button.getAttribute('data-category-id') || '';
                document.getElementById('edit_prod_supplier_id').value = button.getAttribute('data-supplier-id') || '';
                document.getElementById('edit_prod_cost_price').value = button.getAttribute('data-cost-price') || '';
                document.getElementById('edit_prod_sale_price').value = button.getAttribute('data-sale-price') || '';
                document.getElementById('edit_prod_threshold').value = button.getAttribute('data-threshold') || '';
                document.getElementById('edit_prod_status').value = button.getAttribute('data-status') || 'active';
                document.getElementById('edit_prod_description').value = button.getAttribute('data-description') || '';
                document.getElementById('edit_prod_qty_badge').textContent = (button.getAttribute('data-qty') || '0') + ' units in stock';
            });
        }

        // View Product Modal population
        let currentViewingProduct = null;
        const viewModal = document.getElementById('viewProductModal');
        if (viewModal && !viewModal.dataset.listenerAttached) {
            viewModal.dataset.listenerAttached = 'true';
            viewModal.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                if (!button) return;

                let prod = {};
                try {
                    prod = JSON.parse(button.getAttribute('data-product') || '{}');
                } catch (e) {
                    prod = {};
                }
                currentViewingProduct = prod;

                document.getElementById('viewProdName').textContent = prod.name || '';
                document.getElementById('viewProdBarcode').textContent = prod.barcode || 'N/A';
                renderBarcodeSvg(prod.barcode, document.getElementById('viewProdBarcodeSvg'));
                document.getElementById('viewProdCategory').textContent = prod.category || 'General';
                document.getElementById('viewProdSupplier').textContent = prod.supplier || 'Direct';
                document.getElementById('viewProdCostPrice').textContent = '$' + (prod.cost_price || '0.00');
                document.getElementById('viewProdSalePrice').textContent = '$' + (prod.sale_price || '0.00');
                document.getElementById('viewProdQty').textContent = (prod.qty ?? 0) + ' units';
                document.getElementById('viewProdThreshold').textContent = (prod.low_stock_threshold ?? 10) + ' units';
                document.getElementById('viewProdPromo').textContent = prod.promo || 'No active promotion currently applied';
                document.getElementById('viewProdDescription').textContent = prod.description || 'No formula description provided.';

                const imgEl = document.getElementById('viewProdImage');
                const placeholderEl = document.getElementById('viewProdPlaceholder');
                if (prod.image) {
                    imgEl.src = prod.image;
                    imgEl.style.display = 'block';
                    placeholderEl.style.display = 'none';
                } else {
                    imgEl.style.display = 'none';
                    placeholderEl.style.display = 'flex';
                }

                const statusContainer = document.getElementById('viewProdStatusBadge');
                if (prod.status === 'active') {
                    statusContainer.innerHTML = '<span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">Active</span>';
                } else {
                    statusContainer.innerHTML = '<span class="badge bg-secondary-subtle text-secondary border px-2 py-1">Inactive</span>';
                }
            });
        }

        // Print Barcode Label
        const btnPrint = document.getElementById('btnPrintBarcodeLabel');
        if (btnPrint && !btnPrint.dataset.listenerAttached) {
            btnPrint.dataset.listenerAttached = 'true';
            btnPrint.addEventListener('click', function () {
                if (!currentViewingProduct) return;
                const barcode = currentViewingProduct.barcode || '';
                const name = currentViewingProduct.name || 'Skincare Formulation';
                const price = currentViewingProduct.sale_price ? '$' + currentViewingProduct.sale_price : '';
                const svgEl = document.getElementById('viewProdBarcodeSvg');
                const printSvg = svgEl ? svgEl.outerHTML : '';

                const printWindow = window.open('', '_blank', 'width=460,height=380');
                if (!printWindow) {
                    alert('Please allow popups to print barcode labels.');
                    return;
                }
                printWindow.document.write(`<!DOCTYPE html><html><head><title>Barcode Label - ${barcode}</title><style>@page{size:auto;margin:8mm;}body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;text-align:center;margin:0;padding:12px;}.label-card{border:1.5px dashed #71856B;border-radius:8px;padding:14px 18px;display:inline-block;max-width:300px;}.brand{font-size:0.72rem;letter-spacing:2px;text-transform:uppercase;color:#71856B;font-weight:bold;margin-bottom:4px;}.prod-name{font-size:0.88rem;font-weight:600;color:#252B25;margin-bottom:8px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:260px;}.barcode-box{margin:6px 0;}.barcode-box svg{max-width:220px;height:50px;}.code-text{font-family:monospace;font-size:0.95rem;font-weight:bold;letter-spacing:2px;margin-top:4px;}.price{font-size:1.1rem;font-weight:bold;color:#252B25;margin-top:6px;}</style></head><body><div class="label-card"><div class="brand">ÉLIXIR BOTANIQUE</div><div class="prod-name">${name}</div><div class="barcode-box">${printSvg}</div><div class="code-text">${barcode}</div><div class="price">${price}</div></div><script>window.onload=function(){window.print();};<\/script></body></html>`);
                printWindow.document.close();
            });
        }

        // ── Barcode Generator & Visualizer System ──
        function generateClientEan13() {
            const base = '200' + String(Math.floor(Math.random() * 900000000) + 100000000);
            let sum = 0;
            for (let i = 0; i < 12; i++) {
                const digit = parseInt(base[i], 10);
                sum += (i % 2 === 0) ? digit : digit * 3;
            }
            const checkDigit = (10 - (sum % 10)) % 10;
            return base + checkDigit;
        }

        async function requestServerBarcode(targetInputId, btnElement) {
            const input = document.getElementById(targetInputId);
            if (!input) return;

            let originalContent = '';
            if (btnElement) {
                originalContent = btnElement.innerHTML;
                btnElement.disabled = true;
                btnElement.innerHTML = '<span class="spinner-border spinner-border-sm" role="status"></span>';
            }

            try {
                const res = await fetch("{{ route('products.generate-barcode') }}", {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                });
                const data = await res.json();
                if (data.success && data.barcode) {
                    input.value = data.barcode;
                } else {
                    input.value = generateClientEan13();
                }
            } catch (e) {
                input.value = generateClientEan13();
            } finally {
                if (btnElement) {
                    btnElement.disabled = false;
                    btnElement.innerHTML = originalContent;
                }
                input.focus();
                input.classList.add('is-valid');
                setTimeout(() => input.classList.remove('is-valid'), 1500);
            }
        }

        // Auto-generate on Create Modal open if empty, or on button click
        const createModal = document.getElementById('createProductModal');
        if (createModal && !createModal.dataset.barcodeListenerAttached) {
            createModal.dataset.barcodeListenerAttached = 'true';
            createModal.addEventListener('show.bs.modal', function () {
                const barcodeInput = document.getElementById('create_prod_barcode');
                if (barcodeInput && !barcodeInput.value.trim()) {
                    requestServerBarcode('create_prod_barcode');
                }
            });
        }

        const btnGen = document.getElementById('btnGenerateBarcode');
        if (btnGen && !btnGen.dataset.listenerAttached) {
            btnGen.dataset.listenerAttached = 'true';
            btnGen.addEventListener('click', function () {
                requestServerBarcode('create_prod_barcode', this);
            });
        }

        const btnQuickGen = document.getElementById('btnQuickGenBarcode');
        if (btnQuickGen && !btnQuickGen.dataset.listenerAttached) {
            btnQuickGen.dataset.listenerAttached = 'true';
            btnQuickGen.addEventListener('click', function () {
                requestServerBarcode('create_prod_barcode');
            });
        }

        const btnGenEdit = document.getElementById('btnGenerateEditBarcode');
        if (btnGenEdit && !btnGenEdit.dataset.listenerAttached) {
            btnGenEdit.dataset.listenerAttached = 'true';
            btnGenEdit.addEventListener('click', function () {
                requestServerBarcode('edit_prod_barcode', this);
            });
        }

        const btnQuickGenEdit = document.getElementById('btnQuickGenEditBarcode');
        if (btnQuickGenEdit && !btnQuickGenEdit.dataset.listenerAttached) {
            btnQuickGenEdit.dataset.listenerAttached = 'true';
            btnQuickGenEdit.addEventListener('click', function () {
                requestServerBarcode('edit_prod_barcode');
            });
        }

        // Vector Barcode Renderer (Universal Code128 pattern generator)
        function renderBarcodeSvg(code, svgElement) {
            if (!svgElement) return;
            const cleanCode = String(code || '').trim();
            if (!cleanCode) {
                svgElement.innerHTML = '';
                return;
            }

            const patterns = [
                "212222", "222122", "222221", "121223", "121322", "131222", "122213", "122312", "132212", "221213",
                "221312", "231212", "112232", "122132", "122231", "113222", "123122", "123221", "223211", "221132",
                "221231", "213212", "223112", "312131", "311222", "321122", "321221", "312212", "322112", "322211",
                "212123", "212321", "232121", "111323", "131123", "131321", "112313", "132113", "132311", "211313",
                "231113", "231311", "112133", "112331", "132131", "113123", "113321", "133121", "313121", "211331",
                "231131", "213113", "213311", "213131", "311123", "311321", "331121", "312113", "312311", "332111",
                "314111", "221411", "431111", "111224", "111422", "121124", "121421", "141122", "141221", "112214",
                "112412", "122114", "122411", "142112", "142211", "241211", "221114", "413111", "241112", "134111",
                "111242", "121142", "121241", "114212", "124112", "124211", "411212", "421112", "421211", "212141",
                "214121", "412121", "111143", "111341", "131141", "114113", "114311", "411113", "411311", "113141",
                "114131", "311141", "411131", "211412", "211214", "211232", "2331112"
            ];

            let codes = [104];
            let checksum = 104;
            for (let i = 0; i < cleanCode.length; i++) {
                const val = cleanCode.charCodeAt(i) - 32;
                if (val >= 0 && val <= 95) {
                    codes.push(val);
                    checksum += val * (i + 1);
                }
            }
            codes.push(checksum % 103);
            codes.push(106);

            let modules = "";
            for (let c of codes) {
                const p = patterns[c] || patterns[0];
                let bar = true;
                for (let ch of p) {
                    modules += (bar ? "1" : "0").repeat(parseInt(ch, 10));
                    bar = !bar;
                }
            }

            const barWidth = 1.6;
            const height = 44;
            const totalWidth = modules.length * barWidth;
            let rects = "";
            for (let i = 0; i < modules.length; i++) {
                if (modules[i] === "1") {
                    rects += `<rect x="${(i * barWidth).toFixed(1)}" y="0" width="${barWidth.toFixed(1)}" height="${height}" fill="#252B25"/>`;
                }
            }

            svgElement.setAttribute("viewBox", `0 0 ${totalWidth} ${height}`);
            svgElement.innerHTML = rects;
        }

        // ── Bulk Selection & Batch Deletion Logic ──
        const selectAllCheckbox = document.getElementById('selectAllProducts');
        const rowCheckboxes = document.querySelectorAll('.product-select-checkbox:not(:disabled)');
        const bulkToolbar = document.getElementById('bulkActionsToolbar');
        const selectedCountBadge = document.getElementById('selectedCountBadge');
        const clearSelectionBtn = document.getElementById('clearSelectionBtn');
        const bulkDeleteModal = document.getElementById('bulkDeleteModal');
        const bulkDeleteForm = document.getElementById('bulkDeleteForm');

        function updateSelectionState() {
            const allBoxes = Array.from(document.querySelectorAll('.product-select-checkbox:not(:disabled)'));
            const checkedBoxes = allBoxes.filter(cb => cb.checked);
            const count = checkedBoxes.length;

            if (selectedCountBadge) selectedCountBadge.textContent = count;

            if (bulkToolbar) {
                if (count > 0) {
                    bulkToolbar.classList.remove('d-none');
                    bulkToolbar.classList.add('d-inline-flex');
                } else {
                    bulkToolbar.classList.add('d-none');
                    bulkToolbar.classList.remove('d-inline-flex');
                }
            }

            if (selectAllCheckbox) {
                if (count === 0) {
                    selectAllCheckbox.checked = false;
                    selectAllCheckbox.indeterminate = false;
                } else if (count === allBoxes.length && allBoxes.length > 0) {
                    selectAllCheckbox.checked = true;
                    selectAllCheckbox.indeterminate = false;
                } else {
                    selectAllCheckbox.checked = false;
                    selectAllCheckbox.indeterminate = true;
                }
            }

            // Highlight selected table rows
            allBoxes.forEach(cb => {
                const tr = cb.closest('tr');
                if (tr) tr.classList.toggle('table-active', cb.checked);
            });
        }

        if (selectAllCheckbox && !selectAllCheckbox.dataset.listenerAttached) {
            selectAllCheckbox.dataset.listenerAttached = 'true';
            selectAllCheckbox.addEventListener('change', function () {
                const isChecked = this.checked;
                document.querySelectorAll('.product-select-checkbox:not(:disabled)').forEach(cb => {
                    cb.checked = isChecked;
                });
                updateSelectionState();
            });
        }

        rowCheckboxes.forEach(cb => {
            if (!cb.dataset.listenerAttached) {
                cb.dataset.listenerAttached = 'true';
                cb.addEventListener('change', updateSelectionState);
            }
        });

        if (clearSelectionBtn && !clearSelectionBtn.dataset.listenerAttached) {
            clearSelectionBtn.dataset.listenerAttached = 'true';
            clearSelectionBtn.addEventListener('click', function () {
                document.querySelectorAll('.product-select-checkbox').forEach(cb => {
                    cb.checked = false;
                });
                updateSelectionState();
            });
        }

        // Populate Bulk Delete Modal when opened
        if (bulkDeleteModal && !bulkDeleteModal.dataset.listenerAttached) {
            bulkDeleteModal.dataset.listenerAttached = 'true';
            bulkDeleteModal.addEventListener('show.bs.modal', function () {
                const checkedBoxes = Array.from(document.querySelectorAll('.product-select-checkbox:checked'));
                const count = checkedBoxes.length;

                const countText = document.getElementById('modalBulkCountText');
                const btnText = document.getElementById('confirmBulkDeleteText');
                const itemsList = document.getElementById('bulkDeleteItemsList');
                const hiddenInputs = document.getElementById('bulkDeleteHiddenInputs');

                if (countText) countText.textContent = `${count} ${count === 1 ? 'formulation' : 'formulations'}`;
                if (btnText) btnText.textContent = `Delete ${count} ${count === 1 ? 'Product' : 'Products'}`;

                if (itemsList) {
                    itemsList.innerHTML = checkedBoxes.map(cb => {
                        const name = cb.getAttribute('data-name') || 'Unnamed Product';
                        const barcode = cb.getAttribute('data-barcode') || 'N/A';
                        const price = cb.getAttribute('data-price') || '$0.00';
                        const qty = cb.getAttribute('data-stock') || '0';
                        return `
                            <li class="list-group-item d-flex justify-content-between align-items-center py-2 px-1 bg-transparent border-0 border-bottom">
                                <div>
                                    <div class="fw-semibold text-dark">${name}</div>
                                    <div class="text-muted font-monospace" style="font-size: 0.72rem;">SKU: ${barcode} &bull; ${price}</div>
                                </div>
                                <span class="badge bg-light text-muted border">${qty} units</span>
                            </li>
                        `;
                    }).join('');
                }

                if (hiddenInputs) {
                    hiddenInputs.innerHTML = checkedBoxes.map(cb => `
                        <input type="hidden" name="ids[]" value="${cb.value}">
                    `).join('');
                }
            });
        }

        // Handle AJAX Bulk Delete Form submission
        if (bulkDeleteForm && !bulkDeleteForm.dataset.listenerAttached) {
            bulkDeleteForm.dataset.listenerAttached = 'true';
            bulkDeleteForm.addEventListener('submit', async function (e) {
                e.preventDefault();

                const checkedBoxes = Array.from(document.querySelectorAll('.product-select-checkbox:checked'));
                if (checkedBoxes.length === 0) {
                    alert('Please select at least one product to delete.');
                    return;
                }

                const ids = checkedBoxes.map(cb => parseInt(cb.value));
                const submitBtn = document.getElementById('confirmBulkDeleteBtn');
                const spinner = document.getElementById('bulkDeleteSpinner');
                const icon = document.getElementById('bulkDeleteBtnIcon');

                if (submitBtn) submitBtn.disabled = true;
                if (spinner) spinner.classList.remove('d-none');
                if (icon) icon.classList.add('d-none');

                try {
                    const response = await fetch(bulkDeleteForm.action, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                        },
                        body: JSON.stringify({
                            _method: 'DELETE',
                            ids: ids
                        })
                    });

                    const result = await response.json();
                    if (!response.ok || !result.success) {
                        throw new Error(result.message || 'Bulk deletion failed. Please try again.');
                    }

                    // Hide modal
                    const bsModal = bootstrap.Modal.getInstance(bulkDeleteModal);
                    if (bsModal) bsModal.hide();

                    if (window.SkinCareToast) {
                        window.SkinCareToast.success(result.message || 'Products deleted successfully.');
                    } else if (window.posToast) {
                        window.posToast('success', result.message || 'Products deleted successfully.');
                    }

                    // Soft refresh or reload
                    if (window.posSoftRefresh) {
                        window.posSoftRefresh(window.location.href);
                    } else {
                        window.location.reload();
                    }
                } catch (err) {
                    alert('Error: ' + err.message);
                } finally {
                    if (submitBtn) submitBtn.disabled = false;
                    if (spinner) spinner.classList.add('d-none');
                    if (icon) icon.classList.remove('d-none');
                }
            });
        }

        // Client-side image validation (Max 10MB limit guard)
        ['create_image', 'edit_image'].forEach(id => {
            const input = document.getElementById(id);
            if (input && !input.dataset.sizeChecked) {
                input.dataset.sizeChecked = '1';
                input.addEventListener('change', function () {
                    const file = this.files && this.files[0];
                    if (file && file.size > 10 * 1024 * 1024) {
                        const sizeMb = (file.size / (1024 * 1024)).toFixed(1);
                        if (window.SkinCareToast) {
                            window.SkinCareToast.error(`Selected image (${sizeMb} MB) exceeds the 10MB limit. Please choose a smaller photo.`);
                        } else {
                            alert(`Selected image (${sizeMb} MB) exceeds the 10MB limit. Please choose a smaller photo.`);
                        }
                        this.value = '';
                    }
                });
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initProductManagement);
    } else {
        initProductManagement();
    }
    document.addEventListener('app:page-loaded', initProductManagement);
})();
</script>
@endpush
@endsection
