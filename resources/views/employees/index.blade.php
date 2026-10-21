@extends('layouts.app')

@section('title', 'Employees & Staff Directory')

@section('breadcrumb')
    <li class="breadcrumb-item active text-dark" aria-current="page">Employees</li>
@endsection

@section('content')
<x-page-header 
    title="Employees & Staff Directory" 
    description="Staff directory, assigned positions, compensation tiers, and contact records."
    :breadcrumbs="['Human Resources' => null, 'Employees' => null]">
    <x-slot:actions>
        <x-action-button roles="admin,manager,hr" icon="bi-person-plus" modalTarget="#addEmployeeModal">
            New Staff Member
        </x-action-button>
    </x-slot:actions>
</x-page-header>

<!-- Search, Filter & Sort Controls -->
<x-search-filter :action="route('employees.index')" :resetUrl="route('employees.index')" placeholder="Search name, phone, or email...">
    <div class="col-12 col-md-3">
        <select name="position_id" class="form-select form-select-sm" onchange="this.form.submit()">
            <option value="">All Job Positions</option>
            @foreach($positions as $pos)
                <option value="{{ $pos->id }}" {{ request('position_id') == $pos->id ? 'selected' : '' }}>
                    {{ $pos->name }}
                </option>
            @endforeach
        </select>
    </div>

    <div class="col-6 col-md-2">
        <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
            <option value="">All Statuses</option>
            <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Active</option>
            <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>Inactive</option>
            <option value="terminated" {{ request('status') === 'terminated' ? 'selected' : '' }}>Terminated</option>
        </select>
    </div>

    <div class="col-6 col-md-2">
        <select name="sort_by" class="form-select form-select-sm" onchange="this.form.submit()">
            <option value="name" {{ request('sort_by') === 'name' ? 'selected' : '' }}>Sort: Name</option>
            <option value="start_date" {{ request('sort_by') === 'start_date' ? 'selected' : '' }}>Sort: Hire Date</option>
            <option value="created_at" {{ request('sort_by') === 'created_at' ? 'selected' : '' }}>Sort: Recently Added</option>
        </select>
    </div>
</x-search-filter>

<x-card :noPadding="true" title="Staff Members" badge="{{ $employees->total() }} employees">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" style="font-size: 0.88rem;">
            <thead class="table-light text-muted" style="font-size: 0.78rem; text-transform: uppercase;">
                <tr>
                    <th class="ps-4">Employee</th>
                    <th>Job Position</th>
                    <th>Base Salary</th>
                    <th>Contact Info</th>
                    <th>Hire Date & Tenure</th>
                    <th>Total Shifts</th>
                    <th>Status</th>
                    <th class="text-end pe-4">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($employees as $employee)
                <tr>
                    <td class="ps-4">
                        <div class="d-flex align-items-center gap-2">
                            <span class="avatar-circle">
                                {{ strtoupper(substr($employee->name, 0, 2)) }}
                            </span>
                            <div>
                                <a href="{{ route('employees.show', $employee) }}" class="fw-semibold text-dark text-decoration-none hover-primary">
                                    {{ $employee->name }}
                                </a>
                                <div class="text-muted" style="font-size: 0.72rem;">ID #EMP-{{ str_pad($employee->id, 4, '0', STR_PAD_LEFT) }}</div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <span class="badge bg-light text-dark border">
                            {{ $employee->position->name ?? 'Unassigned' }}
                        </span>
                    </td>
                    <td>
                        <span class="fw-semibold text-success">
                            ${{ number_format($employee->position->base_salary ?? 0, 2) }}
                        </span>
                    </td>
                    <td>
                        <div><i class="bi bi-telephone text-muted me-1"></i>{{ $employee->phone ?? '—' }}</div>
                        <div class="text-muted small"><i class="bi bi-envelope text-muted me-1"></i>{{ $employee->email ?? '—' }}</div>
                    </td>
                    <td>
                        <div class="text-dark">{{ $employee->start_date ? $employee->start_date->format('M j, Y') : '—' }}</div>
                        <div class="text-muted small">{{ $employee->tenure }}</div>
                    </td>
                    <td>
                        <a href="{{ route('employees.show', $employee) }}" class="badge bg-light text-muted border text-decoration-none">
                            <i class="bi bi-calendar2-check me-1"></i>{{ $employee->attendances_count }} shifts
                        </a>
                    </td>
                    <td>
                        <x-status-badge :status="$employee->status" />
                    </td>
                    <td class="text-end pe-4">
                        <div class="dropdown">
                            <button class="btn btn-sm btn-light border px-2 py-1" type="button" data-bs-toggle="dropdown">
                                <i class="bi bi-three-dots-vertical"></i>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end shadow-sm" style="font-size: 0.85rem;">
                                <li>
                                    <a class="dropdown-item d-flex align-items-center gap-2" href="{{ route('employees.show', $employee) }}">
                                        <i class="bi bi-person-lines-fill text-muted"></i> View Profile & Logs
                                    </a>
                                </li>
                                <li>
                                    <button class="dropdown-item d-flex align-items-center gap-2" type="button" data-bs-toggle="modal" data-bs-target="#editEmployeeModal{{ $employee->id }}">
                                        <i class="bi bi-pencil text-muted"></i> Edit Employee
                                    </button>
                                </li>
                                <li>
                                    <form method="POST" action="{{ route('employees.toggle-status', $employee) }}">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="dropdown-item d-flex align-items-center gap-2">
                                            <i class="bi {{ $employee->status === 'active' ? 'bi-pause-circle text-warning' : 'bi-play-circle text-success' }}"></i>
                                            {{ $employee->status === 'active' ? 'Deactivate Staff' : 'Activate Staff' }}
                                        </button>
                                    </form>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <button class="dropdown-item d-flex align-items-center gap-2 text-danger" type="button" data-bs-toggle="modal" data-bs-target="#deleteEmployeeModal{{ $employee->id }}">
                                        <i class="bi bi-trash3"></i> Delete Staff
                                    </button>
                                </li>
                            </ul>
                        </div>

                        <!-- Edit Employee Modal -->
                        <x-modal id="editEmployeeModal{{ $employee->id }}" title="Edit Employee: {{ $employee->name }}" size="md">
                            <form method="POST" action="{{ route('employees.update', $employee) }}">
                                @csrf
                                @method('PUT')

                                <x-form-input name="name" label="Full Name" :value="$employee->name" required />

                                <x-form-select name="position_id" label="Job Position" required>
                                    @foreach($positions as $pos)
                                        <option value="{{ $pos->id }}" {{ $employee->position_id == $pos->id ? 'selected' : '' }}>
                                            {{ $pos->name }} (${{ number_format($pos->base_salary, 2) }})
                                        </option>
                                    @endforeach
                                </x-form-select>

                                <div class="row g-2">
                                    <div class="col-6">
                                        <x-form-input name="phone" label="Phone Number" :value="$employee->phone" />
                                    </div>
                                    <div class="col-6">
                                        <x-form-input type="email" name="email" label="Email Address" :value="$employee->email" />
                                    </div>
                                </div>

                                <div class="row g-2">
                                    <div class="col-6">
                                        <x-form-input type="date" name="start_date" label="Hire Date" :value="$employee->start_date ? $employee->start_date->format('Y-m-d') : ''" required />
                                    </div>
                                    <div class="col-6">
                                        <x-form-select name="status" label="Status" required>
                                            <option value="active" {{ $employee->status === 'active' ? 'selected' : '' }}>Active</option>
                                            <option value="inactive" {{ $employee->status === 'inactive' ? 'selected' : '' }}>Inactive</option>
                                            <option value="terminated" {{ $employee->status === 'terminated' ? 'selected' : '' }}>Terminated</option>
                                        </x-form-select>
                                    </div>
                                </div>

                                <div class="d-flex justify-content-end gap-2 mt-4">
                                    <button type="button" class="btn btn-sm btn-light border" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-sm btn-skincare-primary">Save Changes</button>
                                </div>
                            </form>
                        </x-modal>

                        <!-- Delete / Deactivate Employee Modal -->
                        <x-modal id="deleteEmployeeModal{{ $employee->id }}" title="Delete Staff Member" size="md">
                            @if($employee->attendances_count > 0)
                                <div class="alert alert-info d-flex align-items-center gap-2 mb-3">
                                    <i class="bi bi-info-circle-fill fs-5"></i>
                                    <div>
                                        <strong>Historical Records Preserved:</strong> This employee has <strong>{{ $employee->attendances_count }} attendance record(s)</strong>.
                                    </div>
                                </div>
                                <p class="text-muted small">
                                    Confirming below will safely deactivate <strong>"{{ $employee->name }}"</strong> to preserve historical shift records and audit logs.
                                </p>
                            @else
                                <p class="text-dark mb-3">
                                    Are you sure you want to permanently delete employee <strong>"{{ $employee->name }}"</strong>?
                                </p>
                            @endif

                            <form method="POST" action="{{ route('employees.destroy', $employee) }}">
                                @csrf
                                @method('DELETE')
                                <div class="d-flex justify-content-end gap-2 mt-4">
                                    <button type="button" class="btn btn-sm btn-light border" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-sm btn-danger">Confirm</button>
                                </div>
                            </form>
                        </x-modal>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8">
                        <x-empty-state icon="bi-people" title="No employees found" description="No staff members match the selected filters." />
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($employees->hasPages())
    <x-slot:footer>
        {{ $employees->links() }}
    </x-slot:footer>
    @endif
</x-card>

<!-- Add Employee Modal -->
<x-modal id="addEmployeeModal" title="Enroll New Staff Member" size="md">
    <form method="POST" action="{{ route('employees.store') }}">
        @csrf

        <x-form-input name="name" label="Full Legal Name" required placeholder="e.g. Eleanor Vance" />

        <x-form-select name="position_id" label="Job Position" required placeholder="Choose position...">
            @foreach($positions as $pos)
                <option value="{{ $pos->id }}">{{ $pos->name }} (${{ number_format($pos->base_salary, 2) }}/mo)</option>
            @endforeach
        </x-form-select>

        <div class="row g-2">
            <div class="col-6">
                <x-form-input name="phone" label="Phone Number" placeholder="+1-555-0199" />
            </div>
            <div class="col-6">
                <x-form-input type="email" name="email" label="Email Address" placeholder="eleanor@skincare.com" />
            </div>
        </div>

        <div class="row g-2">
            <div class="col-6">
                <x-form-input type="date" name="start_date" label="Hire Date" :value="date('Y-m-d')" required />
            </div>
            <div class="col-6">
                <x-form-select name="status" label="Initial Status" required>
                    <option value="active" selected>Active</option>
                    <option value="inactive">Inactive</option>
                </x-form-select>
            </div>
        </div>

        <div class="d-flex justify-content-end gap-2 mt-4">
            <button type="button" class="btn btn-sm btn-light border" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-sm btn-skincare-primary">Enroll Staff</button>
        </div>
    </form>
</x-modal>
@endsection
