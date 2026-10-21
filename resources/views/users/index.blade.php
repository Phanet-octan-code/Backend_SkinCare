@extends('layouts.app')

@section('title', 'User Administration')

@section('content')
<div class="d-flex flex-column flex-md-row align-items-start align-items-md-center justify-content-between gap-3 mb-4">
    <div>
        <h3 class="fw-bold mb-1" style="color: var(--text-main); letter-spacing: -0.5px;">User Administration</h3>
        <p class="text-muted small mb-0">Manage authorized terminal staff, assign system roles, and control account activation status.</p>
    </div>
    <div>
        <button type="button" class="btn btn-skincare-primary d-inline-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#createUserModal">
            <i class="bi bi-person-plus"></i>
            <span>Add New User</span>
        </button>
    </div>
</div>

<!-- Search & Filter Bar -->
<div class="card-skincare p-3 mb-4">
    <form method="GET" action="{{ route('users.index') }}" class="row g-2 align-items-center">
        <div class="col-12 col-md-5">
            <div class="input-group input-group-sm">
                <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
                <input type="text" 
                       name="search" 
                       class="form-control border-start-0" 
                       placeholder="Search by name or email address..." 
                       value="{{ $filters['search'] ?? '' }}">
            </div>
        </div>

        <div class="col-6 col-md-3">
            <select name="role" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="">All Roles</option>
                <option value="admin" {{ ($filters['role'] ?? '') === 'admin' ? 'selected' : '' }}>Administrator</option>
                <option value="manager" {{ ($filters['role'] ?? '') === 'manager' ? 'selected' : '' }}>Store Manager</option>
                <option value="cashier" {{ ($filters['role'] ?? '') === 'cashier' ? 'selected' : '' }}>Cashier</option>
                <option value="inventory" {{ ($filters['role'] ?? '') === 'inventory' ? 'selected' : '' }}>Inventory Officer</option>
                <option value="hr" {{ ($filters['role'] ?? '') === 'hr' ? 'selected' : '' }}>HR Officer</option>
            </select>
        </div>

        <div class="col-6 col-md-2">
            <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="">All Statuses</option>
                <option value="active" {{ ($filters['status'] ?? '') === 'active' ? 'selected' : '' }}>Active</option>
                <option value="inactive" {{ ($filters['status'] ?? '') === 'inactive' ? 'selected' : '' }}>Inactive</option>
            </select>
        </div>

        <div class="col-12 col-md-2 d-flex gap-2">
            <button type="submit" class="btn btn-sm btn-skincare-primary w-100">Filter</button>
            @if(!empty(array_filter($filters)))
                <a href="{{ route('users.index') }}" class="btn btn-sm btn-skincare-outline" title="Reset Filters"><i class="bi bi-x-circle"></i></a>
            @endif
        </div>
    </form>
</div>

<!-- Users Table Card -->
<div class="card-skincare">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" style="font-size: 0.88rem;">
            <thead class="table-light text-muted" style="font-size: 0.78rem; text-transform: uppercase;">
                <tr>
                    <th class="ps-4">User</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Registered</th>
                    <th class="text-end pe-4">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($users as $user)
                <tr>
                    <td class="ps-4">
                        <div class="d-flex align-items-center gap-2">
                            <span class="avatar-circle">
                                {{ strtoupper(substr($user->name, 0, 2)) }}
                            </span>
                            <div>
                                <div class="fw-semibold text-dark">
                                    {{ $user->name }}
                                    @if($user->id === auth()->id())
                                        <span class="badge bg-light text-muted border ms-1" style="font-size: 0.65rem;">You</span>
                                    @endif
                                </div>
                                <div class="text-muted" style="font-size: 0.78rem;">{{ $user->email }}</div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <span class="badge-role-pill badge-{{ $user->role }}">
                            {{ $user->role }}
                        </span>
                    </td>
                    <td>
                        @if($user->status === 'active')
                            <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">
                                <i class="bi bi-check-circle me-1"></i> Active
                            </span>
                        @else
                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1">
                                <i class="bi bi-slash-circle me-1"></i> Deactivated
                            </span>
                        @endif
                    </td>
                    <td class="text-muted" style="font-size: 0.8rem;">
                        {{ $user->created_at->format('M j, Y') }}
                    </td>
                    <td class="text-end pe-4">
                        <div class="d-flex align-items-center justify-content-end gap-2">
                            <!-- Edit Modal Button -->
                            <button type="button" 
                                    class="btn btn-sm btn-skincare-outline p-1 px-2" 
                                    data-bs-toggle="modal" 
                                    data-bs-target="#editUserModal{{ $user->id }}"
                                    title="Edit Details">
                                <i class="bi bi-pencil"></i>
                            </button>

                            <!-- Toggle Status Button -->
                            @if($user->id !== auth()->id())
                            <form method="POST" action="{{ route('users.toggle-status', $user) }}" onsubmit="return confirm('Are you sure you want to change activation status for {{ $user->name }}?');">
                                @csrf
                                @method('PATCH')
                                <button type="submit" 
                                        class="btn btn-sm {{ $user->status === 'active' ? 'btn-outline-danger' : 'btn-outline-success' }} p-1 px-2"
                                        title="{{ $user->status === 'active' ? 'Deactivate Account' : 'Activate Account' }}">
                                    <i class="bi {{ $user->status === 'active' ? 'bi-lock' : 'bi-unlock' }}"></i>
                                </button>
                            </form>
                            @else
                            <span class="badge bg-light text-muted border p-1 px-2" title="You cannot deactivate your own account">
                                <i class="bi bi-shield-lock"></i>
                            </span>
                            @endif
                        </div>

                        <!-- Edit User Modal -->
                        <div class="modal fade text-start" id="editUserModal{{ $user->id }}" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog">
                                <div class="modal-content card-skincare border-0 shadow">
                                    <div class="modal-header border-bottom">
                                        <h6 class="modal-title fw-bold">Edit User: {{ $user->name }}</h6>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <form method="POST" action="{{ route('users.update', $user) }}">
                                        @csrf
                                        @method('PUT')
                                        <div class="modal-body p-4">
                                            <div class="mb-3">
                                                <label class="form-label small fw-semibold">Full Name</label>
                                                <input type="text" name="name" class="form-control" value="{{ $user->name }}" required>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label small fw-semibold">Email Address</label>
                                                <input type="email" name="email" class="form-control" value="{{ $user->email }}" required>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label small fw-semibold">System Role</label>
                                                <select name="role" class="form-select" {{ $user->id === auth()->id() ? 'disabled' : '' }}>
                                                    <option value="admin" {{ $user->role === 'admin' ? 'selected' : '' }}>Administrator</option>
                                                    <option value="manager" {{ $user->role === 'manager' ? 'selected' : '' }}>Store Manager</option>
                                                    <option value="cashier" {{ $user->role === 'cashier' ? 'selected' : '' }}>Lead Cashier</option>
                                                    <option value="inventory" {{ $user->role === 'inventory' ? 'selected' : '' }}>Inventory Officer</option>
                                                    <option value="hr" {{ $user->role === 'hr' ? 'selected' : '' }}>HR Officer</option>
                                                </select>
                                                @if($user->id === auth()->id())
                                                    <input type="hidden" name="role" value="admin">
                                                @endif
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label small fw-semibold">Status</label>
                                                <select name="status" class="form-select" {{ $user->id === auth()->id() ? 'disabled' : '' }}>
                                                    <option value="active" {{ $user->status === 'active' ? 'selected' : '' }}>Active</option>
                                                    <option value="inactive" {{ $user->status === 'inactive' ? 'selected' : '' }}>Inactive / Deactivated</option>
                                                </select>
                                                @if($user->id === auth()->id())
                                                    <input type="hidden" name="status" value="active">
                                                @endif
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label small fw-semibold">New Password (leave blank to keep current)</label>
                                                <input type="password" name="password" class="form-control" placeholder="••••••••">
                                            </div>
                                        </div>
                                        <div class="modal-footer border-top bg-light">
                                            <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                            <button type="submit" class="btn btn-sm btn-skincare-primary">Save Changes</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" class="text-center py-4 text-muted">
                        No users found matching your search criteria.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($users->hasPages())
    <div class="p-3 border-top">
        {{ $users->links() }}
    </div>
    @endif
</div>

<!-- Create User Modal -->
<div class="modal fade" id="createUserModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content card-skincare border-0 shadow">
            <div class="modal-header border-bottom">
                <h6 class="modal-title fw-bold">Create New User Account</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="{{ route('users.store') }}">
                @csrf
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Full Name</label>
                        <input type="text" name="name" class="form-control" placeholder="e.g. Samantha Vance" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Email Address</label>
                        <input type="email" name="email" class="form-control" placeholder="samantha@skincare.com" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Assigned System Role</label>
                        <select name="role" class="form-select" required>
                            <option value="cashier" selected>Cashier (POS & Receipts)</option>
                            <option value="manager">Store Manager (Products, Staff, Reports)</option>
                            <option value="inventory">Inventory Specialist (Stock movements)</option>
                            <option value="hr">HR Officer (Attendances & Staff)</option>
                            <option value="admin">Administrator (Full Access)</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Initial Password</label>
                        <input type="password" name="password" class="form-control" placeholder="Minimum 8 characters" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Account Status</label>
                        <select name="status" class="form-select" required>
                            <option value="active" selected>Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer border-top bg-light">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-skincare-primary">Create Account</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
