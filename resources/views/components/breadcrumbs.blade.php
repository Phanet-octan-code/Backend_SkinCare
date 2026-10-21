@props([
    'items' => [] // Key-value array of ['Label' => 'URL'] or list of items
])

<nav aria-label="breadcrumb">
    <ol class="breadcrumb mb-0 py-1" style="font-size: 0.78rem;">
        <li class="breadcrumb-item">
            <a href="{{ route('dashboard') }}" class="text-decoration-none text-muted d-inline-flex align-items-center gap-1">
                <i class="bi bi-house-door" style="font-size: 0.85rem;"></i>
                <span>Home</span>
            </a>
        </li>
        @foreach($items as $label => $url)
            @if($loop->last || empty($url))
                <li class="breadcrumb-item active text-dark fw-semibold" aria-current="page">
                    {{ $label }}
                </li>
            @else
                <li class="breadcrumb-item">
                    <a href="{{ $url }}" class="text-decoration-none text-muted">{{ $label }}</a>
                </li>
            @endif
        @endforeach
    </ol>
</nav>
