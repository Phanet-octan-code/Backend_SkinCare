@props([
    'title',
    'value',
    'subtitle' => null,
    'icon' => 'bi-graph-up',
    'color' => 'sage', // sage, success, warning, info, dark
    'trend' => null,
    'trendUp' => true
])

@php
    $colorMap = [
        'sage' => ['bg' => 'var(--primary-subtle)', 'text' => 'var(--primary)'],
        'success' => ['bg' => '#EFF6F1', 'text' => '#5B8A68'],
        'warning' => ['bg' => '#FDF7EE', 'text' => '#D4973B'],
        'info' => ['bg' => '#F0F4F8', 'text' => '#557288'],
        'dark' => ['bg' => '#EAECE9', 'text' => '#252B25'],
    ];
    $theme = $colorMap[$color] ?? $colorMap['sage'];
@endphp

<div class="card-skincare p-3 mb-0 h-100">
    <div class="d-flex align-items-center justify-content-between mb-2">
        <span class="text-muted small fw-semibold">{{ $title }}</span>
        <span class="brand-icon-box" style="width: 34px; height: 34px; font-size: 1.05rem; background-color: {{ $theme['bg'] }}; color: {{ $theme['text'] }};">
            <i class="bi {{ $icon }}"></i>
        </span>
    </div>
    <h3 class="fw-bold mb-1" style="color: var(--text-main);">{{ $value }}</h3>
    @if($subtitle || $trend)
    <div class="d-flex align-items-center gap-1" style="font-size: 0.76rem;">
        @if($trend)
            <span class="{{ $trendUp ? 'text-success' : 'text-danger' }} fw-semibold me-1">
                <i class="bi {{ $trendUp ? 'bi-arrow-up-right' : 'bi-arrow-down-right' }}"></i> {{ $trend }}
            </span>
        @endif
        @if($subtitle)
            <span class="text-muted">{{ $subtitle }}</span>
        @endif
    </div>
    @endif
</div>
