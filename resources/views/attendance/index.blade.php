@extends('layouts.app')

@section('title', 'Staff Attendance Daily Logs')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('employees.index') }}" class="text-decoration-none text-muted">HR</a></li>
    <li class="breadcrumb-item active text-dark" aria-current="page">Attendance</li>
@endsection

@section('content')
<x-page-header 
    title="Staff Attendance Logs" 
    description="Shift clock-ins, biometric attendance tracking, working hours, and grace period thresholds."
    :breadcrumbs="['Human Resources' => route('employees.index'), 'Attendance' => null]">
    <x-slot:actions>
        <a href="{{ route('attendances.report') }}" class="btn btn-sm btn-light border d-flex align-items-center gap-1">
            <i class="bi bi-file-earmark-bar-graph"></i> Monthly Report
        </a>
        <x-action-button roles="admin,manager,hr" icon="bi-plus-lg" modalTarget="#recordAttendanceModal">
            Log Attendance
        </x-action-button>
    </x-slot:actions>
</x-page-header>

<!-- KPI Cards -->
<div class="row g-3 mb-4">
    <div class="col-12 col-sm-6 col-xl-3">
        <x-kpi-card 
            title="Present Today" 
            :value="$presentCount" 
            subtitle="On-time staff on duty"
            icon="bi-person-check" 
            color="success" />
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <x-kpi-card 
            title="Late Check-ins" 
            :value="$lateCount" 
            subtitle="Past {{ substr($config['late_threshold_time'] ?? '09:15', 0, 5) }} threshold"
            icon="bi-clock-history" 
            color="warning" />
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <x-kpi-card 
            title="Permission / Leave" 
            :value="$permissionCount" 
            subtitle="Excused absence"
            icon="bi-calendar2-check" 
            color="info" />
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <x-kpi-card 
            title="Absent Today" 
            :value="$absentCount" 
            subtitle="Unscheduled absence"
            icon="bi-person-x" 
            color="dark" />
    </div>
</div>

<!-- Date Filter & Search Bar -->
<x-search-filter :action="route('attendances.index')" :resetUrl="route('attendances.index')" placeholder="Search employee or notes...">
    <div class="col-12 col-md-3">
        <div class="input-group input-group-sm">
            <span class="input-group-text bg-white border-end-0 text-muted" style="border-radius: 8px 0 0 8px;">
                <i class="bi bi-calendar3"></i>
            </span>
            <input type="date" 
                   name="date" 
                   class="form-control border-start-0" 
                   value="{{ $selectedDate }}"
                   onchange="this.form.submit()"
                   style="border-radius: 0 8px 8px 0;">
        </div>
    </div>

    <div class="col-6 col-md-2">
        <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
            <option value="">All Statuses</option>
            <option value="present" {{ request('status') === 'present' ? 'selected' : '' }}>Present</option>
            <option value="late" {{ request('status') === 'late' ? 'selected' : '' }}>Late</option>
            <option value="permission" {{ request('status') === 'permission' ? 'selected' : '' }}>Permission</option>
            <option value="absent" {{ request('status') === 'absent' ? 'selected' : '' }}>Absent</option>
        </select>
    </div>

    <div class="col-6 col-md-3">
        <select name="employee_id" class="form-select form-select-sm" onchange="this.form.submit()">
            <option value="">All Employees</option>
            @foreach($employees as $emp)
                <option value="{{ $emp->id }}" {{ request('employee_id') == $emp->id ? 'selected' : '' }}>
                    {{ $emp->name }}
                </option>
            @endforeach
        </select>
    </div>
</x-search-filter>

<!-- Attendance Records Table -->
<x-card :noPadding="true" title="Shift Logs for {{ \Carbon\Carbon::parse($selectedDate)->format('F j, Y') }}" badge="{{ $attendances->total() }} recorded">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" style="font-size: 0.88rem;">
            <thead class="table-light text-muted" style="font-size: 0.78rem; text-transform: uppercase;">
                <tr>
                    <th class="ps-4">Employee</th>
                    <th>Position</th>
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
                    <td class="ps-4">
                        <div class="d-flex align-items-center gap-2">
                            <span class="avatar-circle">
                                {{ strtoupper(substr($record->employee->name ?? 'E', 0, 2)) }}
                            </span>
                            <div>
                                <a href="{{ route('employees.show', $record->employee_id) }}" class="fw-semibold text-dark text-decoration-none hover-primary">
                                    {{ $record->employee->name ?? 'Unknown Employee' }}
                                </a>
                                <div class="text-muted" style="font-size: 0.72rem;">{{ $record->employee->phone ?? '' }}</div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <span class="badge bg-light text-dark border">
                            {{ $record->employee->position->name ?? 'Staff' }}
                        </span>
                    </td>
                    <td>
                        @if($record->check_in)
                            <span class="fw-semibold text-dark font-monospace" style="font-size: 0.84rem;">
                                <i class="bi bi-box-arrow-in-right text-success me-1"></i>{{ \Carbon\Carbon::parse($record->check_in)->format('h:i A') }}
                            </span>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td>
                        @if($record->check_out)
                            <span class="fw-semibold text-dark font-monospace" style="font-size: 0.84rem;">
                                <i class="bi bi-box-arrow-right text-secondary me-1"></i>{{ \Carbon\Carbon::parse($record->check_out)->format('h:i A') }}
                            </span>
                        @elseif($record->status === 'present' || $record->status === 'late')
                            <form method="POST" action="{{ route('attendances.checkout', $record) }}" class="d-inline">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="btn btn-xs btn-outline-success py-0 px-2 fw-semibold" style="font-size: 0.75rem;" title="Clock out now">
                                    <i class="bi bi-clock me-1"></i>Clock Out
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
                    <td class="text-muted small text-truncate" style="max-width: 200px;">
                        {{ $record->notes ?? '—' }}
                    </td>
                    <td class="text-end pe-4">
                        <div class="d-flex align-items-center justify-content-end gap-1">
                            <button type="button" class="btn btn-xs btn-light border text-muted px-2 py-1" data-bs-toggle="modal" data-bs-target="#editAttendanceModal{{ $record->id }}" title="Edit record">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button type="button" class="btn btn-xs btn-light border text-danger px-2 py-1" data-bs-toggle="modal" data-bs-target="#deleteAttendanceModal{{ $record->id }}" title="Delete record">
                                <i class="bi bi-trash3"></i>
                            </button>
                        </div>

                        <!-- Edit Attendance Modal -->
                        <x-modal id="editAttendanceModal{{ $record->id }}" title="Edit Attendance: {{ $record->employee->name }}" size="md">
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
                                    <option value="late" {{ $record->status === 'late' ? 'selected' : '' }}>Late (Past {{ substr($config['late_threshold_time'] ?? '09:15', 0, 5) }})</option>
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

                        <!-- Delete Attendance Modal -->
                        <x-modal id="deleteAttendanceModal{{ $record->id }}" title="Delete Attendance Log" size="md">
                            <p class="text-dark mb-3">
                                Are you sure you want to remove the attendance log for <strong>{{ $record->employee->name }}</strong> on <strong>{{ $record->date->format('M d, Y') }}</strong>?
                            </p>
                            <form method="POST" action="{{ route('attendances.destroy', $record) }}">
                                @csrf
                                @method('DELETE')
                                <div class="d-flex justify-content-end gap-2 mt-4">
                                    <button type="button" class="btn btn-sm btn-light border" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-sm btn-danger">Confirm Delete</button>
                                </div>
                            </form>
                        </x-modal>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8">
                        <x-empty-state 
                            icon="bi-calendar-x" 
                            title="No attendance records for this date" 
                            description="No staff clock-ins or logs have been registered for {{ \Carbon\Carbon::parse($selectedDate)->format('M d, Y') }}." />
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

<!-- Record Attendance Modal -->
<x-modal id="recordAttendanceModal" title="Log Employee Attendance" size="md">
    <form method="POST" action="{{ route('attendances.store') }}">
        @csrf

        <x-form-select name="employee_id" label="Staff Member" required placeholder="Choose employee...">
            @foreach($employees as $emp)
                <option value="{{ $emp->id }}">{{ $emp->name }} ({{ $emp->position->name ?? 'Staff' }})</option>
            @endforeach
        </x-form-select>

        <div class="row g-2">
            <div class="col-6">
                <x-form-input type="date" name="date" label="Date" :value="$selectedDate" required />
            </div>
            <div class="col-6">
                <x-form-select name="status" label="Status" required>
                    <option value="present" selected>Present</option>
                    <option value="late">Late</option>
                    <option value="permission">Permission</option>
                    <option value="absent">Absent</option>
                </x-form-select>
            </div>
        </div>

        <div class="row g-2">
            <div class="col-6">
                <x-form-input type="time" name="check_in" label="Check In Time" :value="date('H:i')" />
            </div>
            <div class="col-6">
                <x-form-input type="time" name="check_out" label="Check Out Time (Optional)" />
            </div>
        </div>

        <div class="text-muted small mb-3">
            <i class="bi bi-info-circle me-1"></i>Shift start is <strong>{{ substr($config['work_start_time'] ?? '09:00', 0, 5) }}</strong>. Check-ins after <strong>{{ substr($config['late_threshold_time'] ?? '09:15', 0, 5) }}</strong> will automatically be classified as Late.
        </div>

        <x-form-textarea name="notes" label="Notes / Reason" placeholder="Shift details, leave rationale, or punctuality reason..." rows="2" />

        <div class="d-flex justify-content-end gap-2 mt-4">
            <button type="button" class="btn btn-sm btn-light border" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-sm btn-skincare-primary">Save Shift Record</button>
        </div>
    </form>
</x-modal>
@endsection
