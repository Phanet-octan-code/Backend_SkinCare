@props([
    'icon' => 'bi-inbox',
    'title' => 'No records found',
    'description' => 'There are currently no items matching your criteria in the system.'
])

<div class="text-center py-5 px-3">
    <div class="brand-icon-box mx-auto mb-3" style="width: 56px; height: 56px; font-size: 1.6rem; background-color: var(--primary-subtle); color: var(--primary);">
        <i class="bi {{ $icon }}"></i>
    </div>
    <h5 class="fw-bold text-dark mb-1 fs-6">{{ $title }}</h5>
    <p class="text-muted small mb-3 mx-auto" style="max-width: 360px;">{{ $description }}</p>

    @if(isset($action))
        <div class="mt-2">
            {{ $action }}
        </div>
    @endif
</div>
