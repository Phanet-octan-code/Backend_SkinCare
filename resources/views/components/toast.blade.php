@props([
    'position' => 'top-right' // top-right, bottom-right, top-center
])

@php
    $posClasses = match($position) {
        'bottom-right' => 'position-fixed bottom-0 end-0 p-3',
        'top-center' => 'position-fixed top-0 start-50 translate-middle-x p-3',
        default => 'position-fixed top-0 end-0 p-3',
    };
@endphp

<!-- Toast Container -->
<div class="toast-container {{ $posClasses }}" style="z-index: 1090;">
    <!-- Server-Side Flash Success Toast -->
    @if(session('success') || session('status'))
    <div class="toast align-items-center text-bg-light border-0 shadow-lg show mb-2" role="alert" aria-live="assertive" aria-atomic="true" data-bs-delay="4000">
        <div class="d-flex align-items-center border-start border-4 border-success p-2">
            <span class="brand-icon-box me-2" style="width: 28px; height: 28px; background-color: #EFF6F1; color: #5B8A68; font-size: 0.95rem;">
                <i class="bi bi-check2"></i>
            </span>
            <div class="toast-body p-1 small fw-semibold text-dark flex-grow-1">
                {{ session('success') ?? session('status') }}
            </div>
            <button type="button" class="btn-close me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
    </div>
    @endif

    <!-- Server-Side Flash Error Toast -->
    @if(session('error'))
    <div class="toast align-items-center text-bg-light border-0 shadow-lg show mb-2" role="alert" aria-live="assertive" aria-atomic="true" data-bs-delay="5000">
        <div class="d-flex align-items-center border-start border-4 border-danger p-2">
            <span class="brand-icon-box me-2" style="width: 28px; height: 28px; background-color: #FBF0F0; color: #C86464; font-size: 0.95rem;">
                <i class="bi bi-exclamation-octagon"></i>
            </span>
            <div class="toast-body p-1 small fw-semibold text-dark flex-grow-1">
                {{ session('error') }}
            </div>
            <button type="button" class="btn-close me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
    </div>
    @endif
</div>

<script>
    window.SkinCareToast = {
        show: function(message, type = 'success', duration = 3500) {
            let container = document.querySelector('.toast-container');
            if (!container) {
                container = document.createElement('div');
                container.className = 'toast-container position-fixed top-0 end-0 p-3';
                container.style.zIndex = '1090';
                document.body.appendChild(container);
            }

            const borderMap = {
                'success': { border: 'border-success', bg: '#EFF6F1', text: '#5B8A68', icon: 'bi-check2' },
                'error': { border: 'border-danger', bg: '#FBF0F0', text: '#C86464', icon: 'bi-exclamation-octagon' },
                'warning': { border: 'border-warning', bg: '#FDF7EE', text: '#D4973B', icon: 'bi-exclamation-triangle' },
                'info': { border: 'border-info', bg: '#F0F4F8', text: '#557288', icon: 'bi-info-circle' }
            };

            const conf = borderMap[type] || borderMap['info'];

            const toastEl = document.createElement('div');
            toastEl.className = 'toast align-items-center text-bg-light border-0 shadow-lg mb-2';
            toastEl.setAttribute('role', 'alert');
            toastEl.setAttribute('aria-live', 'assertive');
            toastEl.setAttribute('aria-atomic', 'true');

            toastEl.innerHTML = `
                <div class="d-flex align-items-center border-start border-4 ${conf.border} p-2">
                    <span class="brand-icon-box me-2" style="width: 28px; height: 28px; background-color: ${conf.bg}; color: ${conf.text}; font-size: 0.95rem;">
                        <i class="bi ${conf.icon}"></i>
                    </span>
                    <div class="toast-body p-1 small fw-semibold text-dark flex-grow-1">
                        ${message}
                    </div>
                    <button type="button" class="btn-close me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
            `;

            container.appendChild(toastEl);
            const bsToast = new bootstrap.Toast(toastEl, { delay: duration });
            bsToast.show();

            toastEl.addEventListener('hidden.bs.toast', () => {
                toastEl.remove();
            });
        },
        success: function(msg, dur) { this.show(msg, 'success', dur); },
        error: function(msg, dur) { this.show(msg, 'error', dur); },
        warning: function(msg, dur) { this.show(msg, 'warning', dur); },
        info: function(msg, dur) { this.show(msg, 'info', dur); }
    };
</script>
