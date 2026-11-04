@props([
    'title',
    'description' => null,
    'breadcrumbs' => []
])

<div class="d-flex flex-column flex-md-row align-items-start align-items-md-center justify-content-between gap-3 mb-4">
    <div>
        @if(!empty($breadcrumbs))
        <nav aria-label="breadcrumb" class="mb-1">
            <ol class="breadcrumb mb-0" style="font-size: 0.76rem;">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none text-muted"><i class="bi bi-house-door me-1"></i>Home</a></li>
                @foreach($breadcrumbs as $label => $url)
                    @if($loop->last || empty($url))
                        <li class="breadcrumb-item active text-dark fw-semibold" aria-current="page">{{ $label }}</li>
                    @else
                        <li class="breadcrumb-item"><a href="{{ $url }}" class="text-decoration-none text-muted">{{ $label }}</a></li>
                    @endif
                @endforeach
            </ol>
        </nav>
        @endif
        <h3 class="fw-bold mb-1" style="color: var(--text-main); letter-spacing: -0.5px;">{{ $title }}</h3>
        @if($description)
            <p class="text-muted small mb-0">{{ $description }}</p>
        @endif
    </div>

    @if(isset($actions))
    <div class="d-flex align-items-center gap-2 flex-wrap">
        {{ $actions }}
    </div>
    @endif
</div>
