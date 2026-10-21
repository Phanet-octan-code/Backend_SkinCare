@props([
    'text' => 'Loading skincare data...',
    'size' => 'md', // sm, md, lg
    'overlay' => false
])

@php
    $spinnerSize = match($size) {
        'sm' => 'width: 1.25rem; height: 1.25rem; border-width: 2px;',
        'lg' => 'width: 2.5rem; height: 2.5rem; border-width: 3px;',
        default => 'width: 1.75rem; height: 1.75rem; border-width: 2.5px;',
    };
@endphp

@if($overlay)
<div class="position-absolute top-0 start-0 w-100 h-100 d-flex flex-column align-items-center justify-content-center bg-white bg-opacity-75" style="z-index: 1050; backdrop-filter: blur(2px);">
    <div class="spinner-border text-success mb-2" style="{{ $spinnerSize }} color: var(--primary) !important;" role="status">
        <span class="visually-hidden">Loading...</span>
    </div>
    @if($text)
        <span class="small fw-semibold text-muted">{{ $text }}</span>
    @endif
</div>
@else
<div class="d-flex flex-column align-items-center justify-content-center py-4 text-center">
    <div class="spinner-border mb-2" style="{{ $spinnerSize }} color: var(--primary) !important;" role="status">
        <span class="visually-hidden">Loading...</span>
    </div>
    @if($text)
        <span class="small fw-semibold text-muted">{{ $text }}</span>
    @endif
</div>
@endif
