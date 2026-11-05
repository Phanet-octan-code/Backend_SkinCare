@extends('layouts.app')

@section('title', 'Categories')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none text-muted">Catalog</a></li>
    <li class="breadcrumb-item active text-dark" aria-current="page">Categories</li>
@endsection

@section('content')
<x-page-header 
    title="Product Categories" 
    description="Organize skincare formulations, toners, serums, and treatments into retail categories."
    :breadcrumbs="['Catalog' => route('products.index'), 'Categories' => null]">
    <x-slot:actions>
        <x-action-button roles="admin,manager" icon="bi-plus-lg" modalTarget="#createCategoryModal">
            Add Category
        </x-action-button>
    </x-slot:actions>
</x-page-header>

<!-- Category KPIs -->
<div class="row g-3 mb-4">
    <div class="col-12 col-sm-4">
        <x-kpi-card 
            title="Total Categories" 
            :value="$totalCategories" 
            subtitle="Defined classifications"
            icon="bi-tags" 
            color="sage" />
    </div>
    <div class="col-12 col-sm-4">
        <x-kpi-card 
            title="Active Categories" 
            :value="$activeCategories" 
            subtitle="Currently visible in POS"
            icon="bi-check-circle" 
            color="success" />
    </div>
    <div class="col-12 col-sm-4">
        <x-kpi-card 
            title="Inactive Categories" 
            :value="$inactiveCategories" 
            subtitle="Hidden from sales catalog"
            icon="bi-pause-circle" 
            color="warning" />
    </div>
</div>

<!-- Search & Filters -->
<x-search-filter :action="route('categories.index')" :resetUrl="route('categories.index')" placeholder="Search category name or description...">
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

<!-- Categories Table -->
<x-table 
    :headers="[
        ['label' => 'Category Name', 'class' => 'ps-4'],
        'Description',
        'Associated Formulations',
        'Status',
        ['label' => 'Actions', 'class' => 'pe-4 text-end']
    ]">

    <x-slot:cardHeader>
        <div class="d-flex align-items-center justify-content-between w-100">
            <div class="d-flex align-items-center gap-2">
                <h5 class="fw-bold mb-0 fs-6 text-dark">Category Directory</h5>
                <span class="badge bg-light text-muted border">{{ $categories->total() }} categories</span>
            </div>
        </div>
    </x-slot:cardHeader>

    @forelse($categories as $category)
    <tr>
        <td class="ps-4">
            <div class="d-flex align-items-center gap-2">
                @if($category->image)
                    <img src="{{ asset($category->image) }}" alt="{{ $category->name }}" class="rounded shadow-2xs" style="width: 38px; height: 38px; object-fit: cover; border: 1px solid #ECE8DE;">
                @else
                    <span class="brand-icon-box" style="width: 38px; height: 38px; font-size: 1.05rem;">
                        <i class="bi bi-tag"></i>
                    </span>
                @endif
                <div>
                    <span class="fw-bold text-dark d-block">{{ $category->name }}</span>
                    <span class="text-muted" style="font-size: 0.72rem;">ID: #{{ $category->id }}</span>
                </div>
            </div>
        </td>
        <td>
            <span class="text-muted small">
                {{ $category->description ? Str::limit($category->description, 60) : 'No description provided.' }}
            </span>
        </td>
        <td>
            <a href="{{ route('products.index', ['category_id' => $category->id]) }}" class="text-decoration-none">
                <span class="badge bg-light text-dark border px-2 py-1">
                    <i class="bi bi-box-seam me-1 text-muted"></i>
                    {{ $category->products_count }} {{ Str::plural('product', $category->products_count) }}
                </span>
            </a>
        </td>
        <td>
            <x-status-badge :status="$category->status" />
        </td>
        <td class="pe-4 text-end">
            <div class="d-flex align-items-center justify-content-end gap-1">
                <!-- Edit button -->
                <button type="button" 
                        class="btn btn-sm btn-light border px-2 py-1" 
                        title="Edit Category"
                        data-bs-toggle="modal" 
                        data-bs-target="#editCategoryModal"
                        data-id="{{ $category->id }}"
                        data-name="{{ $category->name }}"
                        data-description="{{ $category->description }}"
                        data-status="{{ $category->status }}"
                        data-image="{{ $category->image ? asset($category->image) : '' }}"
                        data-action="{{ route('categories.update', $category) }}">
                    <i class="bi bi-pencil small"></i>
                </button>

                <!-- Toggle Status -->
                <form method="POST" action="{{ route('categories.toggle-status', $category) }}" class="d-inline pos-quick-form">
                    @csrf
                    @method('PATCH')
                    <button type="submit" 
                            class="btn btn-sm btn-light border px-2 py-1" 
                            title="{{ $category->status === 'active' ? 'Deactivate Category' : 'Activate Category' }}">
                        <i class="bi {{ $category->status === 'active' ? 'bi-pause-circle text-warning' : 'bi-play-circle text-success' }} small"></i>
                    </button>
                </form>

                <!-- Delete button -->
                @if($category->products_count > 0)
                    <button type="button" 
                            class="btn btn-sm btn-light border px-2 py-1 text-muted" 
                            title="Cannot delete: has associated products"
                            data-bs-toggle="modal" 
                            data-bs-target="#cannotDeleteCategoryModal{{ $category->id }}">
                        <i class="bi bi-trash small text-secondary"></i>
                    </button>
                @else
                    <button type="button" 
                            class="btn btn-sm btn-light border px-2 py-1 text-danger" 
                            title="Delete Category"
                            data-bs-toggle="modal" 
                            data-bs-target="#deleteCategoryModal{{ $category->id }}">
                        <i class="bi bi-trash small"></i>
                    </button>
                @endif
            </div>

            <!-- Cannot Delete Modal (Safety Guard Notice) -->
            @if($category->products_count > 0)
            <div class="modal fade" id="cannotDeleteCategoryModal{{ $category->id }}" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered modal-sm">
                    <div class="modal-content card-skincare border-0 shadow-lg text-center p-3">
                        <div class="modal-body p-3">
                            <div class="brand-icon-box mx-auto mb-3" style="width: 48px; height: 48px; font-size: 1.4rem; background-color: #FEF3C7; color: #D97706;">
                                <i class="bi bi-shield-exclamation"></i>
                            </div>
                            <h6 class="fw-bold mb-2">Category Protected</h6>
                            <p class="text-muted small mb-3">
                                <strong>{{ $category->name }}</strong> is linked to <strong>{{ $category->products_count }} product(s)</strong>.
                                To preserve database integrity, it cannot be deleted. You can safely deactivate it instead.
                            </p>
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-sm btn-light border w-50" data-bs-dismiss="modal">Close</button>
                                <form method="POST" action="{{ route('categories.toggle-status', $category) }}" class="w-50 pos-quick-form">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="btn btn-sm btn-skincare-primary w-100">
                                        {{ $category->status === 'active' ? 'Deactivate' : 'Activate' }}
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
                id="deleteCategoryModal{{ $category->id }}" 
                title="Delete Category" 
                :message="'Are you sure you want to delete category \'' . $category->name . '\'? This action cannot be undone.'"
                confirmText="Delete Category"
                :action="route('categories.destroy', $category)"
                method="DELETE"
                :danger="true" />
            @endif
        </td>
    </tr>
    @empty
    <tr>
        <td colspan="5" class="p-0">
            <x-empty-state 
                icon="bi-tags" 
                title="No categories found" 
                description="No skincare categories matched your criteria. You can create a new category anytime." />
        </td>
    </tr>
    @endforelse

    @if($categories->hasPages())
    <x-slot:pagination>
        {{ $categories->links() }}
    </x-slot:pagination>
    @endif
</x-table>

<!-- Create Category Modal -->
<x-modal id="createCategoryModal" title="Add Skincare Category" size="md">
    <form method="POST" action="{{ route('categories.store') }}" enctype="multipart/form-data">
        @csrf
        <div class="mb-3">
            <x-form-input name="name" label="Category Name" placeholder="e.g. Hydrating Serums & Essences" required />
        </div>
        <div class="mb-3">
            <x-form-textarea name="description" label="Description" placeholder="Brief summary of formulations under this category..." rows="3" />
        </div>
        <div class="mb-3">
            <label class="form-label small fw-semibold" for="create_category_image">Category Image</label>
            <input type="file" id="create_category_image" name="image" class="form-control form-control-sm" accept="image/png,image/jpeg,image/webp" style="border-radius: 8px;">
            <div class="form-text small text-muted">Upload square high-res image (JPG, PNG, WebP up to 2MB). Used for storefront circular category avatars & POS.</div>
            <div id="create_category_image_preview_box" class="mt-2 d-none">
                <img id="create_category_image_preview" src="" alt="Preview" class="rounded border shadow-2xs" style="width: 70px; height: 70px; object-fit: cover;">
            </div>
        </div>
        <div class="mb-3">
            <x-form-select name="status" label="Status" required>
                <option value="active" selected>Active (Available for products and POS)</option>
                <option value="inactive">Inactive (Hidden from POS catalog)</option>
            </x-form-select>
        </div>

        <div class="d-flex justify-content-end gap-2 mt-4 pt-2 border-top">
            <button type="button" class="btn btn-sm btn-light border px-3" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-sm btn-skincare-primary px-3">
                <i class="bi bi-check-lg me-1"></i> Save Category
            </button>
        </div>
    </form>
</x-modal>

<!-- Edit Category Modal (Dynamic JS Binding) -->
<x-modal id="editCategoryModal" title="Edit Skincare Category" size="md">
    <form id="editCategoryForm" method="POST" action="" enctype="multipart/form-data">
        @csrf
        @method('PUT')
        <input type="hidden" id="edit_category_id" name="id" value="">
        <div class="mb-3">
            <x-form-input id="edit_category_name" name="name" label="Category Name" required />
        </div>
        <div class="mb-3">
            <x-form-textarea id="edit_category_description" name="description" label="Description" rows="3" />
        </div>
        <div class="mb-3">
            <label class="form-label small fw-semibold" for="edit_category_image">Category Image</label>
            <div id="edit_current_image_container" class="mb-2 d-none">
                <div class="d-flex align-items-center gap-2 p-2 bg-light rounded border">
                    <img id="edit_current_image_preview" src="" alt="Current Image" class="rounded border shadow-2xs" style="width: 52px; height: 52px; object-fit: cover;">
                    <div class="flex-grow-1 min-w-0">
                        <span class="text-dark fw-semibold small d-block">Current Category Image</span>
                        <span class="text-muted" style="font-size: 0.72rem;">Upload a new image below to replace</span>
                    </div>
                    <div class="form-check form-check-inline m-0">
                        <input class="form-check-input" type="checkbox" name="remove_image" id="remove_image" value="1">
                        <label class="form-check-label small text-danger" for="remove_image">Remove</label>
                    </div>
                </div>
            </div>
            <input type="file" id="edit_category_image" name="image" class="form-control form-control-sm" accept="image/png,image/jpeg,image/webp" style="border-radius: 8px;">
            <div class="form-text small text-muted">Upload a new image (up to 2MB) or leave blank to keep current.</div>
            <div id="edit_category_image_preview_box" class="mt-2 d-none">
                <img id="edit_category_image_preview" src="" alt="New Preview" class="rounded border shadow-2xs" style="width: 70px; height: 70px; object-fit: cover;">
            </div>
        </div>
        <div class="mb-3">
            <label class="form-label small fw-semibold" for="edit_category_status">Status</label>
            <select id="edit_category_status" name="status" class="form-select form-select-sm" style="border-radius: 8px;" required>
                <option value="active">Active (Available for products and POS)</option>
                <option value="inactive">Inactive (Hidden from POS catalog)</option>
            </select>
        </div>

        <div class="d-flex justify-content-end gap-2 mt-4 pt-2 border-top">
            <button type="button" class="btn btn-sm btn-light border px-3" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-sm btn-skincare-primary px-3">
                <i class="bi bi-check-lg me-1"></i> Update Category
            </button>
        </div>
    </form>
</x-modal>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    // Create Category Image Preview
    const createImgInput = document.getElementById('create_category_image');
    const createImgBox = document.getElementById('create_category_image_preview_box');
    const createImgPreview = document.getElementById('create_category_image_preview');
    if (createImgInput) {
        createImgInput.addEventListener('change', function () {
            const file = this.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function (e) {
                    createImgPreview.src = e.target.result;
                    createImgBox.classList.remove('d-none');
                };
                reader.readAsDataURL(file);
            } else {
                createImgBox.classList.add('d-none');
                createImgPreview.src = '';
            }
        });
    }

    // Edit Category Modal & Image Preview
    const editModal = document.getElementById('editCategoryModal');
    const editImgInput = document.getElementById('edit_category_image');
    const editImgBox = document.getElementById('edit_category_image_preview_box');
    const editImgPreview = document.getElementById('edit_category_image_preview');
    const editCurContainer = document.getElementById('edit_current_image_container');
    const editCurPreview = document.getElementById('edit_current_image_preview');
    const removeCheckbox = document.getElementById('remove_image');

    if (editImgInput) {
        editImgInput.addEventListener('change', function () {
            const file = this.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function (e) {
                    editImgPreview.src = e.target.result;
                    editImgBox.classList.remove('d-none');
                };
                reader.readAsDataURL(file);
            } else {
                editImgBox.classList.add('d-none');
                editImgPreview.src = '';
            }
        });
    }

    if (editModal) {
        editModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            if (!button) return;

            const action = button.getAttribute('data-action');
            const id = button.getAttribute('data-id');
            const name = button.getAttribute('data-name');
            const description = button.getAttribute('data-description');
            const status = button.getAttribute('data-status');
            const image = button.getAttribute('data-image');

            document.getElementById('editCategoryForm').action = action;
            document.getElementById('edit_category_id').value = id || '';
            document.getElementById('edit_category_name').value = name || '';
            document.getElementById('edit_category_description').value = description || '';
            document.getElementById('edit_category_status').value = status || 'active';

            if (removeCheckbox) removeCheckbox.checked = false;
            if (editImgInput) editImgInput.value = '';
            if (editImgBox) editImgBox.classList.add('d-none');

            if (image && image.trim() !== '') {
                editCurPreview.src = image;
                editCurContainer.classList.remove('d-none');
            } else {
                editCurPreview.src = '';
                editCurContainer.classList.add('d-none');
            }
        });
    }
});
</script>
@endpush
@endsection
