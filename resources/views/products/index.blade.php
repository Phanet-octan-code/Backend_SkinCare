@extends('layouts.app')

@section('title', 'Products & Formulations')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none text-muted">Catalog</a></li>
    <li class="breadcrumb-item active text-dark" aria-current="page">Products</li>
@endsection

@section('content')
<x-page-header 
    title="Products & Formulations" 
    description="Manage skincare stock items, barcode pricing, thresholds, and suppliers."
    :breadcrumbs="['Catalog' => route('products.index'), 'Products' => null]">
    <x-slot:actions>
        <x-action-button roles="admin,manager" icon="bi-plus-lg" modalTarget="#createProductModal">
            Add New Product
        </x-action-button>
    </x-slot:actions>
</x-page-header>

<!-- Catalog KPI Metrics -->
<div class="row g-3 mb-4">
    <div class="col-12 col-sm-6 col-xl-3">
        <x-kpi-card 
            title="Total Formulations" 
            :value="$totalProducts" 
            subtitle="Catalog SKUs"
            icon="bi-box-seam" 
            color="sage" />
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <x-kpi-card 
            title="Active in Store" 
            :value="$activeCatalogCount" 
            subtitle="Available for POS checkout"
            icon="bi-check-circle" 
            color="success" />
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <x-kpi-card 
            title="Low Stock Alerts" 
            :value="$lowStockCount" 
            subtitle="At or below threshold"
            icon="bi-exclamation-triangle" 
            color="warning" />
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <x-kpi-card 
            title="Out of Stock" 
            :value="$outOfStockCount" 
            subtitle="Requires replenishment"
            icon="bi-x-octagon" 
            color="danger" />
    </div>
</div>

<!-- Search & Filters -->
<x-search-filter :action="route('products.index')" :resetUrl="route('products.index')" placeholder="Search product name or barcode...">
    <div class="col-12 col-md-2">
        <select name="category_id" class="form-select form-select-sm" style="border-radius: 8px;">
            <option value="">All Categories</option>
            @foreach($categories as $category)
                <option value="{{ $category->id }}" {{ request('category_id') == $category->id ? 'selected' : '' }}>
                    {{ $category->name }}
                </option>
            @endforeach
        </select>
    </div>
    <div class="col-12 col-md-2">
        <select name="supplier_id" class="form-select form-select-sm" style="border-radius: 8px;">
            <option value="">All Suppliers</option>
            @foreach($suppliers as $supplier)
                <option value="{{ $supplier->id }}" {{ request('supplier_id') == $supplier->id ? 'selected' : '' }}>
                    {{ $supplier->name }}
                </option>
            @endforeach
        </select>
    </div>
    <div class="col-12 col-md-2">
        <select name="stock_status" class="form-select form-select-sm" style="border-radius: 8px;">
            <option value="">All Stock Levels</option>
            <option value="in_stock" {{ request('stock_status') === 'in_stock' ? 'selected' : '' }}>Adequate Stock</option>
            <option value="low" {{ request('stock_status') === 'low' ? 'selected' : '' }}>Low Stock Alerts</option>
            <option value="out" {{ request('stock_status') === 'out' ? 'selected' : '' }}>Out of Stock</option>
        </select>
    </div>
    <div class="col-12 col-md-2">
        <select name="sort" class="form-select form-select-sm" style="border-radius: 8px;">
            <option value="latest" {{ request('sort') === 'latest' ? 'selected' : '' }}>Newest First</option>
            <option value="name_asc" {{ request('sort') === 'name_asc' ? 'selected' : '' }}>Name (A-Z)</option>
            <option value="name_desc" {{ request('sort') === 'name_desc' ? 'selected' : '' }}>Name (Z-A)</option>
            <option value="price_asc" {{ request('sort') === 'price_asc' ? 'selected' : '' }}>Price: Low to High</option>
            <option value="price_desc" {{ request('sort') === 'price_desc' ? 'selected' : '' }}>Price: High to Low</option>
            <option value="qty_asc" {{ request('sort') === 'qty_asc' ? 'selected' : '' }}>Stock: Low to High</option>
            <option value="qty_desc" {{ request('sort') === 'qty_desc' ? 'selected' : '' }}>Stock: High to Low</option>
        </select>
    </div>
</x-search-filter>

<!-- Products Table -->
<x-table 
    :headers="[
        ['label' => 'Product / SKU', 'class' => 'ps-4'],
        'Category',
        'Barcode',
        'Cost Price',
        'Retail Price',
        'Stock Balance',
        'Status',
        ['label' => 'Actions', 'class' => 'pe-4 text-end']
    ]">

    <x-slot:cardHeader>
        <div class="d-flex align-items-center justify-content-between w-100">
            <div class="d-flex align-items-center gap-2">
                <h5 class="fw-bold mb-0 fs-6 text-dark">Formulation Catalog</h5>
                <span class="badge bg-light text-muted border">{{ $products->total() }} items</span>
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
    <tr>
        <td class="ps-4">
            <div class="d-flex align-items-center gap-2">
                @if($product->image && file_exists(public_path($product->image)))
                    <img src="{{ asset($product->image) }}" alt="{{ $product->name }}" class="rounded-2 border object-fit-cover shadow-xs" style="width: 38px; height: 38px;">
                @else
                    <span class="brand-icon-box" style="width: 38px; height: 38px; font-size: 1.1rem;">
                        <i class="bi bi-droplet-half"></i>
                    </span>
                @endif
                <div>
                    <div class="d-flex align-items-center gap-1">
                        <span class="fw-bold text-dark">{{ $product->name }}</span>
                        @if($hasActivePromo)
                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle" style="font-size: 0.65rem;">
                                <i class="bi bi-tag-fill me-1"></i>{{ $activePromo->discount_type === 'percentage' ? number_format($activePromo->discount_value, 0) . '% OFF' : '$' . number_format($activePromo->discount_value, 2) . ' OFF' }}
                            </span>
                        @endif
                    </div>
                    <div class="text-muted" style="font-size: 0.72rem;">
                        Supplier: <span class="fw-semibold text-secondary">{{ $product->supplier->name ?? 'Direct' }}</span>
                    </div>
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
            <span class="text-muted small">${{ number_format($product->cost_price, 2) }}</span>
        </td>
        <td>
            <div>
                <span class="fw-bold text-dark">${{ number_format($product->sale_price, 2) }}</span>
                @if($product->cost_price > 0 && $product->sale_price > $product->cost_price)
                    <div class="text-success" style="font-size: 0.68rem;">
                        +{{ round((($product->sale_price - $product->cost_price) / $product->cost_price) * 100) }}% margin
                    </div>
                @endif
            </div>
        </td>
        <td>
            <div class="d-flex align-items-center gap-2">
                <span class="fw-bold font-monospace {{ $isOutOfStock ? 'text-danger' : ($isLowStock ? 'text-warning' : 'text-success') }}">
                    {{ $product->qty }} units
                </span>
                @if($isOutOfStock)
                    <span class="badge bg-danger text-white" style="font-size: 0.65rem;">Out of Stock</span>
                @elseif($isLowStock)
                    <span class="badge bg-warning-subtle text-warning border border-warning-subtle" style="font-size: 0.65rem;">Low (≤{{ $product->low_stock_threshold }})</span>
                @endif
            </div>
        </td>
        <td>
            <x-status-badge :status="$product->status" />
        </td>
        <td class="pe-4 text-end">
            <div class="dropdown">
                <button class="btn btn-sm btn-light border p-1 px-2" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-three-dots-vertical"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow-sm border" style="border-radius: 8px;">
                    <li>
                        <button type="button" class="dropdown-item py-1 small" 
                                data-bs-toggle="modal" 
                                data-bs-target="#viewProductModal"
                                data-product="{{ json_encode([
                                    'id' => $product->id,
                                    'name' => $product->name,
                                    'barcode' => $product->barcode,
                                    'category' => $product->category->name ?? 'General',
                                    'supplier' => $product->supplier->name ?? 'Direct',
                                    'cost_price' => number_format($product->cost_price, 2),
                                    'sale_price' => number_format($product->sale_price, 2),
                                    'qty' => $product->qty,
                                    'low_stock_threshold' => $product->low_stock_threshold,
                                    'status' => $product->status,
                                    'description' => $product->description,
                                    'image' => $product->image && file_exists(public_path($product->image)) ? asset($product->image) : null,
                                    'promo' => $hasActivePromo ? ($activePromo->name . ' (' . ($activePromo->discount_type === 'percentage' ? number_format($activePromo->discount_value, 0) . '% OFF' : '$' . number_format($activePromo->discount_value, 2) . ' OFF') . ')') : 'None'
                                ]) }}">
                            <i class="bi bi-eye me-2 text-primary"></i> View Details
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
                            <i class="bi bi-pencil me-2 text-secondary"></i> Edit Product
                        </button>
                    </li>
                    <li>
                        <form method="POST" action="{{ route('products.toggle-status', $product) }}">
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="dropdown-item py-1 small">
                                <i class="bi {{ $product->status === 'active' ? 'bi-pause-circle text-warning' : 'bi-play-circle text-success' }} me-2"></i>
                                {{ $product->status === 'active' ? 'Deactivate Product' : 'Activate Product' }}
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
                        @php
                            $isReferenced = $product->saleDetails()->exists() || $product->stockTransactions()->exists();
                        @endphp
                        @if($isReferenced)
                            <button type="button" class="dropdown-item py-1 small text-muted" 
                                    data-bs-toggle="modal" 
                                    data-bs-target="#cannotDeleteProductModal{{ $product->id }}">
                                <i class="bi bi-trash me-2 text-secondary"></i> Delete (Protected)
                            </button>
                        @else
                            <button type="button" class="dropdown-item py-1 small text-danger" 
                                    data-bs-toggle="modal" 
                                    data-bs-target="#deleteProductModal{{ $product->id }}">
                                <i class="bi bi-trash me-2"></i> Delete Product
                            </button>
                        @endif
                    </li>
                </ul>
            </div>

            <!-- Cannot Delete Modal (Safety Guard) -->
            @if($isReferenced)
            <div class="modal fade" id="cannotDeleteProductModal{{ $product->id }}" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered modal-sm">
                    <div class="modal-content card-skincare border-0 shadow-lg text-center p-3">
                        <div class="modal-body p-3">
                            <div class="brand-icon-box mx-auto mb-3" style="width: 48px; height: 48px; font-size: 1.4rem; background-color: #FEF3C7; color: #D97706;">
                                <i class="bi bi-shield-lock"></i>
                            </div>
                            <h6 class="fw-bold mb-2">Product Has History</h6>
                            <p class="text-muted small mb-3">
                                <strong>{{ $product->name }}</strong> has sales invoices or inventory audit records linked.
                                To protect historical financial data, it cannot be deleted. Deactivate it instead.
                            </p>
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-sm btn-light border w-50" data-bs-dismiss="modal">Close</button>
                                <form method="POST" action="{{ route('products.toggle-status', $product) }}" class="w-50">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="btn btn-sm btn-skincare-primary w-100">
                                        {{ $product->status === 'active' ? 'Deactivate' : 'Activate' }}
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            @else
            <!-- Standard Delete Dialog -->
            <x-confirm-dialog 
                id="deleteProductModal{{ $product->id }}" 
                title="Delete Product" 
                :message="'Are you sure you want to permanently delete \'' . $product->name . '\'?'"
                confirmText="Delete Product"
                :action="route('products.destroy', $product)"
                method="DELETE"
                :danger="true" />
            @endif
        </td>
    </tr>
    @empty
    <tr>
        <td colspan="8" class="p-0">
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
                <x-form-input name="barcode" label="Barcode / SKU" placeholder="e.g. 3337875597180" required />
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
                <div class="form-text" style="font-size: 0.72rem;">Supported formats: JPG, PNG, WEBP. Maximum file size: 2MB.</div>
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
                <x-form-input id="edit_prod_barcode" name="barcode" label="Barcode / SKU" required />
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
                <div class="form-text" style="font-size: 0.72rem;">Leave empty to keep existing image. Supported: JPG, PNG, WEBP (Max 2MB).</div>
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
            <div id="viewProdImageContainer" class="p-2 border rounded-3 bg-light mb-2">
                <img id="viewProdImage" src="" alt="Product Image" class="img-fluid rounded-2 object-fit-cover" style="max-height: 220px;">
                <div id="viewProdPlaceholder" class="brand-icon-box mx-auto my-4" style="width: 72px; height: 72px; font-size: 2.2rem;">
                    <i class="bi bi-droplet-half"></i>
                </div>
            </div>
            <div id="viewProdStatusBadge" class="mb-2"></div>
            <div class="small text-muted font-monospace" id="viewProdBarcode"></div>
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

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    // Edit Product Modal population
    const editModal = document.getElementById('editProductModal');
    if (editModal) {
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
    const viewModal = document.getElementById('viewProductModal');
    if (viewModal) {
        viewModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            if (!button) return;

            let prod = {};
            try {
                prod = JSON.parse(button.getAttribute('data-product') || '{}');
            } catch (e) {
                prod = {};
            }

            document.getElementById('viewProdName').textContent = prod.name || '';
            document.getElementById('viewProdBarcode').textContent = 'SKU / Barcode: ' + (prod.barcode || 'N/A');
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
});
</script>
@endpush
@endsection
