@props([
    'action',
    'resetUrl' => null,
    'placeholder' => 'Search by keyword...'
])

<div class="card-skincare p-3 mb-4">
    <form method="GET" action="{{ $action }}" class="row g-2 align-items-center">
        <div class="col-12 col-md-5">
            <div class="input-group input-group-sm">
                <span class="input-group-text bg-white border-end-0 text-muted" style="border-radius: 8px 0 0 8px;">
                    <i class="bi bi-search"></i>
                </span>
                <input type="text" 
                       name="search" 
                       class="form-control border-start-0" 
                       placeholder="{{ $placeholder }}" 
                       value="{{ request('search') }}"
                       style="border-radius: 0 8px 8px 0;">
            </div>
        </div>

        {{ $slot }}

        <div class="col-12 col-md-auto ms-md-auto d-flex gap-2">
            <button type="submit" class="btn btn-sm btn-skincare-primary">
                <i class="bi bi-funnel me-1"></i> Filter
            </button>
            @if($resetUrl && count(request()->except(['page'])) > 0)
                <a href="{{ $resetUrl }}" class="btn btn-sm btn-skincare-outline" title="Reset Filters">
                    <i class="bi bi-x-circle"></i>
                </a>
            @endif
        </div>
    </form>
</div>
