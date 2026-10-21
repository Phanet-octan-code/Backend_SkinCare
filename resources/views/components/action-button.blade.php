@props([
    'roles' => [], // e.g. ['admin', 'manager'] or string 'admin'
    'variant' => 'primary', // primary, outline, danger, success, light
    'icon' => null,
    'size' => 'sm',
    'url' => null,
    'type' => 'button',
    'disabled' => false,
    'hideUnauthorized' => false,
    'tooltip' => null,
    'modalTarget' => null
])

@php
    $rolesList = is_string($roles) ? explode(',', $roles) : (array) $roles;
    $rolesList = array_map('trim', $rolesList);

    $user = auth()->user();
    $isAuthorized = empty($rolesList) || ($user && in_array($user->role, $rolesList));

    // Button style class
    $btnClasses = match($variant) {
        'primary' => 'btn btn-' . $size . ' btn-skincare-primary',
        'outline' => 'btn btn-' . $size . ' btn-skincare-outline',
        'danger' => 'btn btn-' . $size . ' btn-outline-danger',
        'success' => 'btn btn-' . $size . ' btn-outline-success',
        'light' => 'btn btn-' . $size . ' btn-light border',
        default => 'btn btn-' . $size . ' btn-skincare-primary',
    };

    $effectiveTooltip = $tooltip;
    if (!$isAuthorized) {
        $requiredText = implode(' or ', array_map('ucfirst', $rolesList));
        $effectiveTooltip = "Restricted: Requires {$requiredText} authorization";
    }
@endphp

@if(!$isAuthorized && $hideUnauthorized)
    {{-- Hidden for unauthorized users --}}
@else
    @if($url && $isAuthorized && !$disabled)
        <a href="{{ $url }}" 
           {{ $attributes->merge(['class' => $btnClasses . ' d-inline-flex align-items-center gap-1']) }}
           @if($effectiveTooltip) data-bs-toggle="tooltip" data-bs-placement="top" title="{{ $effectiveTooltip }}" @endif>
            @if($icon) <i class="bi {{ $icon }}"></i> @endif
            <span>{{ $slot }}</span>
        </a>
    @else
        <button type="{{ $type }}" 
                {{ $attributes->merge(['class' => $btnClasses . ' d-inline-flex align-items-center gap-1']) }}
                @if(!$isAuthorized || $disabled) disabled @endif
                @if($modalTarget && $isAuthorized) data-bs-toggle="modal" data-bs-target="{{ $modalTarget }}" @endif
                @if($effectiveTooltip) data-bs-toggle="tooltip" data-bs-placement="top" title="{{ $effectiveTooltip }}" @endif>
            @if($icon) <i class="bi {{ $icon }}"></i> @endif
            <span>{{ $slot }}</span>
        </button>
    @endif
@endif
