@extends('layouts.app')

@section('title', 'Profile & Password')

@section('content')
<div class="mb-4">
    <h3 class="fw-bold mb-1" style="color: var(--text-main); letter-spacing: -0.5px;">Account Profile</h3>
    <p class="text-muted small mb-0">Manage your terminal user details and secure password credentials.</p>
</div>

<div class="row g-4">
    <!-- Profile Information Card -->
    <div class="col-12 col-lg-6">
        <div class="card-skincare h-100">
            <div class="card-header-skincare">
                <h5 class="fw-bold mb-0 fs-6">Personal Details</h5>
                <span class="badge-role-pill badge-{{ $user->role }}">{{ strtoupper($user->role) }}</span>
            </div>
            <div class="p-4">
                <form method="POST" action="{{ route('profile.update') }}">
                    @csrf
                    @method('PATCH')

                    <div class="mb-3">
                        <label for="name" class="form-label small fw-semibold">Full Name</label>
                        <input type="text" 
                               class="form-control @error('name') is-invalid @enderror" 
                               id="name" 
                               name="name" 
                               value="{{ old('name', $user->name) }}" 
                               required>
                        @error('name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="email" class="form-label small fw-semibold">Email Address</label>
                        <input type="email" 
                               class="form-control @error('email') is-invalid @enderror" 
                               id="email" 
                               name="email" 
                               value="{{ old('email', $user->email) }}" 
                               required>
                        @error('email')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-4">
                        <label class="form-label small fw-semibold text-muted">Assigned System Role</label>
                        <input type="text" class="form-control bg-light" value="{{ ucfirst($user->role) }}" disabled readonly>
                        <span class="form-text" style="font-size: 0.75rem;">Role assignments are governed by the Administrator.</span>
                    </div>

                    <button type="submit" class="btn btn-skincare-primary">
                        <i class="bi bi-check2 me-1"></i> Save Profile
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Password Change Card -->
    <div class="col-12 col-lg-6">
        <div class="card-skincare h-100">
            <div class="card-header-skincare">
                <h5 class="fw-bold mb-0 fs-6">Change Password</h5>
                <span class="badge bg-light text-muted border">Bcrypt Encrypted</span>
            </div>
            <div class="p-4">
                @if (session('password_status'))
                    <div class="alert alert-success py-2 px-3 mb-3 small d-flex align-items-center" role="alert">
                        <i class="bi bi-shield-check me-2 fs-5"></i>
                        <div>{{ session('password_status') }}</div>
                    </div>
                @endif

                <form method="POST" action="{{ route('password.update') }}">
                    @csrf
                    @method('PUT')

                    <div class="mb-3">
                        <label for="current_password" class="form-label small fw-semibold">Current Password</label>
                        <input type="password" 
                               class="form-control @error('current_password') is-invalid @enderror" 
                               id="current_password" 
                               name="current_password" 
                               required 
                               placeholder="••••••••">
                        @error('current_password')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="password" class="form-label small fw-semibold">New Password</label>
                        <input type="password" 
                               class="form-control @error('password') is-invalid @enderror" 
                               id="password" 
                               name="password" 
                               required 
                               placeholder="Minimum 8 characters">
                        @error('password')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-4">
                        <label for="password_confirmation" class="form-label small fw-semibold">Confirm New Password</label>
                        <input type="password" 
                               class="form-control" 
                               id="password_confirmation" 
                               name="password_confirmation" 
                               required 
                               placeholder="Re-type new password">
                    </div>

                    <button type="submit" class="btn btn-skincare-primary">
                        <i class="bi bi-key me-1"></i> Update Password
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
