@props([
    'id',
    'title',
    'size' => null // sm, lg, xl
])

<div class="modal fade" id="{{ $id }}" tabindex="-1" aria-labelledby="{{ $id }}Label" aria-hidden="true">
    <div class="modal-dialog {{ $size ? 'modal-' . $size : '' }} modal-dialog-centered">
        <div class="modal-content card-skincare border-0 shadow-lg">
            <div class="modal-header border-bottom py-3 px-4">
                <h6 class="modal-title fw-bold fs-6 mb-0" id="{{ $id }}Label" style="color: var(--text-main);">
                    {{ $title }}
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <div class="modal-body p-4">
                {{ $slot }}
            </div>

            @if(isset($footer))
            <div class="modal-footer border-top bg-light py-2 px-4" style="border-radius: 0 0 12px 12px;">
                {{ $footer }}
            </div>
            @endif
        </div>
    </div>
</div>
