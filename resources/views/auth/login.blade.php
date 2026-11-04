@extends('layouts.auth')

@section('title', 'Sign In')

@section('content')
<div class="auth-card p-4 p-md-5">
    <div class="text-center mb-4">
        <h3 class="mb-1" style="font-family: var(--font-serif); font-size: 1.7rem; font-weight: 600; letter-spacing: 0.04em; color: var(--text-main);">
            ÉLIXIR <span style="color: var(--primary); font-weight: 400;">BOTANIQUE</span>
        </h3>
        <p class="text-muted small mb-0" style="font-size: 0.65rem; font-weight: 600; letter-spacing: 0.14em; text-transform: uppercase;">
            {{ __('messages.app_subtitle') }}
        </p>
    </div>

    @if (session('status'))
        <div class="alert alert-success d-flex align-items-center py-2 px-3 mb-3 small" role="alert">
            <i class="bi bi-check-circle-fill me-2 fs-5"></i>
            <div>{{ session('status') }}</div>
        </div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger py-2 px-3 mb-3 small" role="alert">
            <ul class="mb-0 ps-3">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('login') }}" novalidate>
        @csrf

        <div class="mb-3">
            <label for="email" class="form-label">Email Address</label>
            <div class="input-group">
                <span class="input-group-text bg-transparent border-end-0 text-muted" style="border-radius: 9px 0 0 9px;">
                    <i class="bi bi-envelope"></i>
                </span>
                <input type="email" 
                       class="form-control border-start-0 ps-0 @error('email') is-invalid @enderror" 
                       id="email" 
                       name="email" 
                       value="{{ old('email', 'admin@skincare.com') }}" 
                       required 
                       autocomplete="email" 
                       autofocus 
                       placeholder="name@skincare.com"
                       style="border-radius: 0 9px 9px 0;">
            </div>
        </div>

        <div class="mb-3">
            <label for="password" class="form-label">Password</label>
            <div class="input-group">
                <span class="input-group-text bg-transparent border-end-0 text-muted" style="border-radius: 9px 0 0 9px;">
                    <i class="bi bi-lock"></i>
                </span>
                <input type="password" 
                       class="form-control border-start-0 border-end-0 px-0 @error('password') is-invalid @enderror" 
                       id="password" 
                       name="password" 
                       required 
                       autocomplete="current-password" 
                       placeholder="••••••••"
                       value="Admin@SkinCare2026">
                <span class="input-group-text" id="togglePasswordBtn" title="Show/Hide Password" style="border-radius: 0 9px 9px 0;">
                    <i class="bi bi-eye" id="togglePasswordIcon"></i>
                </span>
            </div>
        </div>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="remember" id="remember" {{ old('remember') ? 'checked' : '' }}>
                <label class="form-check-label small text-muted" for="remember">
                    Remember this terminal
                </label>
            </div>
            <span class="small text-muted"><i class="bi bi-shield-check text-success me-1"></i>Secure SSL</span>
        </div>

        <button type="submit" class="btn btn-primary-skincare w-100 shadow-sm mb-4">
            <i class="bi bi-box-arrow-in-right me-1"></i> Sign In to POS
        </button>
    </form>

    <!-- Quick Role Switcher for Testing & Demonstration -->
    <div class="border-top pt-3 mt-2" style="border-color: var(--border-color) !important;">
        <p class="small fw-semibold text-muted mb-2 text-center" style="font-size: 0.76rem; letter-spacing: 0.4px; text-transform: uppercase;">
            Demo Quick Login Presets
        </p>
        <div class="d-flex flex-column gap-2">
            <button type="button" class="role-btn d-flex justify-content-between align-items-center" onclick="fillCredentials('admin@skincare.com', 'Admin@SkinCare2026')">
                <div>
                    <span class="fw-bold" style="color: var(--primary);">Administrator</span>
                    <div class="text-muted" style="font-size: 0.72rem;">admin@skincare.com &bull; Full Access</div>
                </div>
                <span class="badge bg-dark badge-role">Admin</span>
            </button>

            <button type="button" class="role-btn d-flex justify-content-between align-items-center" onclick="fillCredentials('manager@skincare.com', 'Admin@SkinCare2026')">
                <div>
                    <span class="fw-bold text-dark">Store Manager</span>
                    <div class="text-muted" style="font-size: 0.72rem;">manager@skincare.com &bull; Sales, Inventory & Staff</div>
                </div>
                <span class="badge bg-secondary badge-role">Manager</span>
            </button>

            <button type="button" class="role-btn d-flex justify-content-between align-items-center" onclick="fillCredentials('cashier@skincare.com', 'Admin@SkinCare2026')">
                <div>
                    <span class="fw-bold text-dark">Lead Cashier</span>
                    <div class="text-muted" style="font-size: 0.72rem;">cashier@skincare.com &bull; POS & Checkout Only</div>
                </div>
                <span class="badge bg-success badge-role">Cashier</span>
            </button>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    // Password visibility toggle
    const toggleBtn = document.getElementById('togglePasswordBtn');
    const passwordInput = document.getElementById('password');
    const toggleIcon = document.getElementById('togglePasswordIcon');

    if (toggleBtn && passwordInput && toggleIcon) {
        toggleBtn.addEventListener('click', function () {
            const isPassword = passwordInput.getAttribute('type') === 'password';
            passwordInput.setAttribute('type', isPassword ? 'text' : 'password');
            toggleIcon.classList.toggle('bi-eye', !isPassword);
            toggleIcon.classList.toggle('bi-eye-slash', isPassword);
        });
    }

    // Quick role filler helper
    function fillCredentials(email, password) {
        document.getElementById('email').value = email;
        document.getElementById('password').value = password;
    }
</script>
@endpush
