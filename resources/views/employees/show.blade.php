@extends('layouts.app')

@section('title', 'Employee Profile: ' . $employee->name)

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('employees.index') }}" class="text-decoration-none text-muted">Employees</a></li>
    <li class="breadcrumb-item active text-dark" aria-current="page">{{ $employee->name }}</li>
@endsection

@section('content')
<x-page-header 
    :title="$employee->name" 
    description="Staff member profile, operational compensation tier, and historical attendance logs."
    :breadcrumbs="['Employees' => route('employees.index'), $employee->name => null]">
    <x-slot:actions>
        <button type="button" class="btn btn-sm btn-light border d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#editEmployeeModal">
            <i class="bi bi-pencil"></i> Edit Profile
        </button>
        <x-action-button roles="admin,manager,hr" icon="bi-plus-lg" modalTarget="#logAttendanceModal">
            Clock Shift
        </x-action-button>
    </x-slot:actions>
</x-page-header>

<!-- Employee Header Card -->
<div class="card-skincare p-4 mb-4">
    <div class="row align-items-center g-3">
        <div class="col-auto">
            <span class="avatar-circle" style="width: 64px; height: 64px; font-size: 1.6rem; background: #71856B; color: white;">
                {{ strtoupper(substr($employee->name, 0, 2)) }}
            </span>
        </div>
        <div class="col">
            <div class="d-flex align-items-center gap-3 flex-wrap">
                <h4 class="fw-bold text-dark mb-0 fs-5">{{ $employee->name }}</h4>
                <x-status-badge :status="$employee->status" />
                <span class="badge bg-light text-dark border">
                    <i class="bi bi-briefcase me-1"></i>{{ $employee->position->name ?? 'Unassigned' }}
                </span>
            </div>
            <div class="d-flex align-items-center gap-4 mt-2 text-muted small flex-wrap">
                <span><i class="bi bi-envelope me-1"></i>{{ $employee->email ?? 'No email' }}</span>
                <span><i class="bi bi-telephone me-1"></i>{{ $employee->phone ?? 'No phone' }}</span>
                <span><i class="bi bi-calendar-event me-1"></i>Hired: {{ $employee->start_date ? $employee->start_date->format('M j, Y') : '—' }} ({{ $employee->tenure }})</span>
                <span class="fw-semibold text-success"><i class="bi bi-cash me-1"></i>${{ number_format($employee->position->base_salary ?? 0, 2) }}/mo</span>
            </div>
        </div>
        <div class="col-auto">
            <form method="POST" action="{{ route('employees.toggle-status', $employee) }}">
                @csrf
                @method('PATCH')
                <button type="submit" class="btn btn-sm {{ $employee->status === 'active' ? 'btn-outline-warning' : 'btn-outline-success' }}">
                    <i class="bi {{ $employee->status === 'active' ? 'bi-pause-circle' : 'bi-play-circle' }} me-1"></i>
                    {{ $employee->status === 'active' ? 'Deactivate' : 'Activate' }}
                </button>
            </form>
        </div>
    </div>
</div>

<!-- KPI Cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-4 col-xl-2">
        <x-kpi-card 
            title="Total Shifts" 
            :value="$stats['total']" 
            subtitle="Recorded days"
            icon="bi-calendar-check" 
            color="sage" />
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <x-kpi-card 
            title="Days Present" 
            :value="$stats['present']" 
            subtitle="On-time shifts"
            icon="bi-person-check" 
            color="success" />
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <x-kpi-card 
            title="Late Check-ins" 
            :value="$stats['late']" 
            subtitle="After shift start"
            icon="bi-clock-history" 
            color="warning" />
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <x-kpi-card 
            title="Permission / Leave" 
            :value="$stats['permission']" 
            subtitle="Authorized leave"
            icon="bi-calendar2-check" 
            color="info" />
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <x-kpi-card 
            title="Absent Days" 
            :value="$stats['absent']" 
            subtitle="Unexcused"
            icon="bi-person-x" 
            color="dark" />
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <x-kpi-card 
            title="Hours Worked" 
            :value="$stats['total_hours'] . ' hrs'" 
            subtitle="Cumulative duty"
            icon="bi-hourglass-split" 
            color="success" />
    </div>
</div>

<!-- Attendance History Filters -->
<div class="card-skincare p-3 mb-3">
    <form method="GET" action="{{ route('employees.show', $employee) }}" class="row g-2 align-items-center">
        <div class="col-12 col-md-4">
            <div class="input-group input-group-sm">
                <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-calendar3"></i></span>
                <input type="month" name="month" class="form-control border-start-0" value="{{ request('month') }}" onchange="this.form.submit()">
            </div>
        </div>

        <div class="col-12 col-md-4">
            <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="">All Shift Statuses</option>
                <option value="present" {{ request('status') === 'present' ? 'selected' : '' }}>Present</option>
                <option value="late" {{ request('status') === 'late' ? 'selected' : '' }}>Late</option>
                <option value="permission" {{ request('status') === 'permission' ? 'selected' : '' }}>Permission</option>
                <option value="absent" {{ request('status') === 'absent' ? 'selected' : '' }}>Absent</option>
            </select>
        </div>

        <div class="col-12 col-md-4 d-flex gap-2">
            @if(request()->hasAny(['month', 'status']))
                <a href="{{ route('employees.show', $employee) }}" class="btn btn-sm btn-light border">Reset Filter</a>
            @endif
        </div>
    </form>
</div>

<!-- Attendance Table -->
<x-card :noPadding="true" title="Shift & Attendance History" badge="{{ $attendances->total() }} records">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" style="font-size: 0.88rem;">
            <thead class="table-light text-muted" style="font-size: 0.78rem; text-transform: uppercase;">
                <tr>
                    <th class="ps-4">Date</th>
                    <th>Check In</th>
                    <th>Check Out</th>
                    <th>Working Hours</th>
                    <th>Status</th>
                    <th>Notes & Remarks</th>
                    <th class="text-end pe-4">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($attendances as $record)
                <tr>
                    <td class="ps-4 fw-semibold text-dark">
                        {{ \Carbon\Carbon::parse($record->date)->format('M d, Y (D)') }}
                    </td>
                    <td>
                        @if($record->check_in)
                            <span class="font-monospace fw-semibold text-dark">{{ \Carbon\Carbon::parse($record->check_in)->format('h:i A') }}</span>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td>
                        @if($record->check_out)
                            <span class="font-monospace fw-semibold text-dark">{{ \Carbon\Carbon::parse($record->check_out)->format('h:i A') }}</span>
                        @elseif($record->status === 'present' || $record->status === 'late')
                            <form method="POST" action="{{ route('attendances.checkout', $record) }}" class="d-inline">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="btn btn-xs btn-outline-success py-0 px-2" style="font-size: 0.75rem;">
                                    Clock Out
                                </button>
                            </form>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td>
                        @if($record->working_hours !== null)
                            <span class="fw-bold text-dark font-monospace">{{ $record->working_hours }} hrs</span>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td>
                        <x-status-badge :status="$record->status" />
                    </td>
                    <td class="text-muted small text-truncate" style="max-width: 250px;">
                        {{ $record->notes ?? '—' }}
                    </td>
                    <td class="text-end pe-4">
                        <button type="button" class="btn btn-xs btn-light border text-muted px-2 py-1" data-bs-toggle="modal" data-bs-target="#editLogModal{{ $record->id }}">
                            <i class="bi bi-pencil"></i>
                        </button>

                        <!-- Edit Attendance Modal -->
                        <x-modal id="editLogModal{{ $record->id }}" title="Edit Attendance: {{ \Carbon\Carbon::parse($record->date)->format('M d, Y') }}" size="md">
                            <form method="POST" action="{{ route('attendances.update', $record) }}">
                                @csrf
                                @method('PUT')

                                <x-form-input type="date" name="date" label="Date" :value="$record->date->toDateString()" required />

                                <div class="row g-2">
                                    <div class="col-6">
                                        <x-form-input type="time" name="check_in" label="Check In Time" :value="$record->check_in ? substr($record->check_in, 0, 5) : ''" />
                                    </div>
                                    <div class="col-6">
                                        <x-form-input type="time" name="check_out" label="Check Out Time" :value="$record->check_out ? substr($record->check_out, 0, 5) : ''" />
                                    </div>
                                </div>

                                <x-form-select name="status" label="Status" required>
                                    <option value="present" {{ $record->status === 'present' ? 'selected' : '' }}>Present</option>
                                    <option value="late" {{ $record->status === 'late' ? 'selected' : '' }}>Late</option>
                                    <option value="permission" {{ $record->status === 'permission' ? 'selected' : '' }}>Permission</option>
                                    <option value="absent" {{ $record->status === 'absent' ? 'selected' : '' }}>Absent</option>
                                </x-form-select>

                                <x-form-textarea name="notes" label="Notes" :value="$record->notes" rows="2" />

                                <div class="d-flex justify-content-end gap-2 mt-4">
                                    <button type="button" class="btn btn-sm btn-light border" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-sm btn-skincare-primary">Save Changes</button>
                                </div>
                            </form>
                        </x-modal>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7">
                        <x-empty-state icon="bi-calendar-x" title="No attendance logs found" description="No shift records match the specified filters for this employee." />
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($attendances->hasPages())
    <x-slot:footer>
        {{ $attendances->links() }}
    </x-slot:footer>
    @endif
</x-card>

<!-- Edit Employee Profile Modal -->
<x-modal id="editEmployeeModal" title="Edit Employee Profile" size="md">
    <form method="POST" action="{{ route('employees.update', $employee) }}">
        @csrf
        @method('PUT')

        <x-form-input name="name" label="Full Legal Name" :value="$employee->name" required />

        <x-form-select name="position_id" label="Job Position" required>
            @foreach($positions as $pos)
                <option value="{{ $pos->id }}" {{ $employee->position_id == $pos->id ? 'selected' : '' }}>
                    {{ $pos->name }} (${{ number_format($pos->base_salary, 2) }}/mo)
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
            <button type="submit" class="btn btn-sm btn-skincare-primary">Save Profile</button>
        </div>
    </form>
</x-modal>

<!-- Log Attendance Modal for this Employee -->
<x-modal id="logAttendanceModal" title="Log Attendance for {{ $employee->name }}" size="md">
    <form method="POST" action="{{ route('attendances.store') }}">
        @csrf
        <input type="hidden" name="employee_id" value="{{ $employee->id }}">

        <x-form-input type="date" name="date" label="Date" :value="date('Y-m-d')" required />

        <div class="row g-2">
            <div class="col-6">
                <x-form-input type="time" name="check_in" label="Check In Time" :value="date('H:i')" />
            </div>
            <div class="col-6">
                <x-form-input type="time" name="check_out" label="Check Out Time (Optional)" />
            </div>
        </div>

        <x-form-select name="status" label="Status" required>
            <option value="present" selected>Present</option>
            <option value="late">Late</option>
            <option value="permission">Permission</option>
            <option value="absent">Absent</option>
        </x-form-select>

        <x-form-textarea name="notes" label="Notes" placeholder="Shift remarks, leave explanation..." rows="2" />

        <div class="d-flex justify-content-end gap-2 mt-4">
            <button type="button" class="btn btn-sm btn-light border" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-sm btn-skincare-primary">Submit Record</button>
        </div>
    </form>
</x-modal>
@endsection
