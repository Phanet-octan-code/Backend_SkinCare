@props([
    'title' => null,
    'badge' => null,
    'noPadding' => false
])

<div {{ $attributes->merge(['class' => 'card-skincare']) }}>
    @if($title || isset($header) || isset($actions))
    <div class="card-header-skincare">
        @if(isset($header))
            {{ $header }}
        @else
            <div class="d-flex align-items-center gap-2">
                <h5 class="fw-bold mb-0 fs-6" style="color: var(--text-main);">{{ $title }}</h5>
                @if($badge)
                    <span class="badge bg-light text-muted border" style="font-size: 0.72rem;">{{ $badge }}</span>
                @endif
            </div>
        @endif

        @if(isset($actions))
            <div class="d-flex align-items-center gap-2">
                {{ $actions }}
            </div>
        @endif
    </div>
    @endif

    <div class="{{ $noPadding ? 'p-0' : 'p-3 p-md-4' }}">
        {{ $slot }}
    </div>

    @if(isset($footer))
    <div class="p-3 border-top bg-light" style="border-radius: 0 0 12px 12px; border-color: var(--border-color) !important;">
        {{ $footer }}
    </div>
    @endif
</div>
