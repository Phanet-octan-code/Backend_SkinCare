@props([
    'id',
    'title' => 'Confirm Action',
    'message' => 'Are you sure you want to proceed with this action? This operation cannot be undone.',
    'confirmText' => 'Confirm',
    'action',
    'method' => 'POST',
    'danger' => false
])

<div class="modal fade" id="{{ $id }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content card-skincare border-0 shadow-lg text-center p-3">
            <div class="modal-body p-3">
                <div class="brand-icon-box mx-auto mb-3" style="width: 48px; height: 48px; font-size: 1.4rem; background-color: {{ $danger ? '#FBF0F0' : 'var(--primary-subtle)' }}; color: {{ $danger ? '#C86464' : 'var(--primary)' }};">
                    <i class="bi {{ $danger ? 'bi-exclamation-triangle' : 'bi-question-circle' }}"></i>
                </div>
                <h6 class="fw-bold mb-2">{{ $title }}</h6>
                <p class="text-muted small mb-4">{{ $message }}</p>

                <form method="POST" action="{{ $action }}">
                    @csrf
                    @if(in_array(strtoupper($method), ['PUT', 'PATCH', 'DELETE']))
                        @method($method)
                    @endif

                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-sm btn-light border w-50" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-sm {{ $danger ? 'btn-danger' : 'btn-skincare-primary' }} w-50">
                            {{ $confirmText }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
