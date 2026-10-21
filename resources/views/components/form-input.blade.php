@props([
    'name',
    'label' => null,
    'type' => 'text',
    'value' => null,
    'placeholder' => null,
    'required' => false,
    'disabled' => false,
    'readonly' => false,
    'icon' => null,
    'hint' => null,
    'id' => null,
    'step' => null,
    'min' => null,
    'max' => null,
    'autocomplete' => null
])

@php
    $inputId = $id ?? 'input_' . str_replace(['[', ']', '.'], '_', $name);
    $errorName = str_replace(['[', ']'], ['.', ''], $name);
    $inputValue = old($errorName, $value);
@endphp

<div {{ $attributes->merge(['class' => 'mb-3']) }}>
    @if($label)
    <label for="{{ $inputId }}" class="form-label small fw-semibold text-dark mb-1">
        {{ $label }}
        @if($required)
            <span class="text-danger">*</span>
        @endif
    </label>
    @endif

    @if($icon)
    <div class="input-group">
        <span class="input-group-text bg-light border-end-0 text-muted" style="border-radius: 8px 0 0 8px; border-color: var(--border-color);">
            <i class="bi {{ $icon }}"></i>
        </span>
        <input type="{{ $type }}" 
               name="{{ $name }}" 
               id="{{ $inputId }}" 
               value="{{ $inputValue }}" 
               placeholder="{{ $placeholder }}"
               @if($required) required @endif
               @if($disabled) disabled @endif
               @if($readonly) readonly @endif
               @if($step) step="{{ $step }}" @endif
               @if($min !== null) min="{{ $min }}" @endif
               @if($max !== null) max="{{ $max }}" @endif
               @if($autocomplete) autocomplete="{{ $autocomplete }}" @endif
               class="form-control border-start-0 @error($errorName) is-invalid @enderror"
               style="border-radius: 0 8px 8px 0; border-color: var(--border-color); font-size: 0.88rem;">
    </div>
    @else
    <input type="{{ $type }}" 
           name="{{ $name }}" 
           id="{{ $inputId }}" 
           value="{{ $inputValue }}" 
           placeholder="{{ $placeholder }}"
           @if($required) required @endif
           @if($disabled) disabled @endif
           @if($readonly) readonly @endif
           @if($step) step="{{ $step }}" @endif
           @if($min !== null) min="{{ $min }}" @endif
           @if($max !== null) max="{{ $max }}" @endif
           @if($autocomplete) autocomplete="{{ $autocomplete }}" @endif
           class="form-control @error($errorName) is-invalid @enderror"
           style="border-radius: 8px; border-color: var(--border-color); font-size: 0.88rem;">
    @endif

    @error($errorName)
    <div class="invalid-feedback d-block small mt-1">
        <i class="bi bi-exclamation-circle me-1"></i>{{ $message }}
    </div>
    @enderror

    @if($hint && !$errors->has($errorName))
    <div class="form-text small text-muted mt-1" style="font-size: 0.75rem;">
        {{ $hint }}
    </div>
    @endif
</div>
