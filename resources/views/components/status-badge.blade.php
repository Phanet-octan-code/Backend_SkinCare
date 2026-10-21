@props([
    'status'
])

@php
    $normalized = strtolower(trim((string) $status));
    
    $badgeConfig = match($normalized) {
        'active', 'completed', 'paid', 'present', 'in_stock' => [
            'class' => 'bg-success-subtle text-success border border-success-subtle',
            'icon' => 'bi-check-circle',
            'label' => ucfirst($status),
        ],
        'inactive', 'cancelled', 'deactivated', 'absent', 'out_of_stock' => [
            'class' => 'bg-danger-subtle text-danger border border-danger-subtle',
            'icon' => 'bi-slash-circle',
            'label' => ucfirst($status),
        ],
        'pending', 'late', 'partial', 'in_transit' => [
            'class' => 'bg-warning-subtle text-warning border border-warning-subtle',
            'icon' => 'bi-clock-history',
            'label' => ucfirst($status),
        ],
        'low', 'low_stock' => [
            'class' => 'bg-danger-subtle text-danger border border-danger-subtle',
            'icon' => 'bi-exclamation-triangle',
            'label' => 'Low Stock',
        ],
        'refunded', 'return', 'returned' => [
            'class' => 'bg-info-subtle text-info border border-info-subtle',
            'icon' => 'bi-arrow-counterclockwise',
            'label' => ucfirst($status),
        ],
        'permission', 'on_leave' => [
            'class' => 'bg-primary-subtle text-primary border border-primary-subtle',
            'icon' => 'bi-calendar2-check',
            'label' => ucfirst($status),
        ],
        'terminated' => [
            'class' => 'bg-secondary-subtle text-secondary border border-secondary-subtle',
            'icon' => 'bi-person-x',
            'label' => 'Terminated',
        ],
        default => [
            'class' => 'bg-light text-muted border',
            'icon' => 'bi-circle',
            'label' => ucfirst($status),
        ]
    };
@endphp

<span class="badge {{ $badgeConfig['class'] }} px-2 py-1 d-inline-flex align-items-center gap-1" style="font-size: 0.75rem; font-weight: 600;">
    <i class="bi {{ $badgeConfig['icon'] }}"></i>
    <span>{{ $badgeConfig['label'] }}</span>
</span>
