@props([
    'name',
    'label' => null,
    'required' => false,
    'disabled' => false,
    'hint' => null,
    'id' => null,
    'placeholder' => null
])

@php
    $inputId = $id ?? 'select_' . str_replace(['[', ']', '.'], '_', $name);
    $errorName = str_replace(['[', ']'], ['.', ''], $name);
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

    <select name="{{ $name }}" 
            id="{{ $inputId }}" 
            @if($required) required @endif
            @if($disabled) disabled @endif
            class="form-select @error($errorName) is-invalid @enderror"
            style="border-radius: 8px; border-color: var(--border-color); font-size: 0.88rem;">
        @if($placeholder)
            <option value="">{{ $placeholder }}</option>
        @endif
        {{ $slot }}
    </select>

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
