@extends('layouts.app')

@section('title', 'Monthly Attendance Report - ' . $monthLabel)

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('employees.index') }}" class="text-decoration-none text-muted">HR</a></li>
    <li class="breadcrumb-item"><a href="{{ route('attendances.index') }}" class="text-decoration-none text-muted">Attendance</a></li>
    <li class="breadcrumb-item active text-dark" aria-current="page">Monthly Report</li>
@endsection

@section('content')
<x-page-header 
    title="Monthly Staff Attendance Report" 
    description="Cumulative attendance statistics, shift volume, punctuality breakdown, and total payroll hours."
    :breadcrumbs="['Human Resources' => route('employees.index'), 'Attendance' => route('attendances.index'), 'Monthly Report' => null]">
    <x-slot:actions>
        <button type="button" class="btn btn-sm btn-light border d-flex align-items-center gap-1" onclick="window.print()">
            <i class="bi bi-printer"></i> Print Report
        </button>
        <a href="{{ route('attendances.index') }}" class="btn btn-sm btn-skincare-primary d-flex align-items-center gap-1">
            <i class="bi bi-calendar-day"></i> Daily Log View
        </a>
    </x-slot:actions>
</x-page-header>

<!-- Month Selector Bar -->
<div class="card-skincare p-3 mb-4">
    <form method="GET" action="{{ route('attendances.report') }}" class="row g-2 align-items-center">
        <div class="col-12 col-md-4">
            <div class="input-group input-group-sm">
                <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-calendar3"></i></span>
                <input type="month" name="month" class="form-control border-start-0" value="{{ $selectedMonth }}" onchange="this.form.submit()">
            </div>
        </div>
        <div class="col-12 col-md-8 text-md-end text-muted small">
            Displaying attendance records for <strong class="text-dark">{{ $monthLabel }}</strong>
        </div>
    </form>
</div>

<!-- KPI Cards -->
<div class="row g-3 mb-4">
    <div class="col-12 col-sm-6 col-xl-3">
        <x-kpi-card 
            title="Total Working Hours" 
            :value="$grandTotalHours . ' hrs'" 
            subtitle="Across all staff in {{ $monthLabel }}"
            icon="bi-hourglass-split" 
            color="success" />
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <x-kpi-card 
            title="Present Shifts" 
            :value="$grandTotalPresent" 
            subtitle="On-time shifts logged"
            icon="bi-person-check" 
            color="sage" />
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <x-kpi-card 
            title="Late Shifts" 
            :value="$grandTotalLate" 
            subtitle="Past grace threshold"
            icon="bi-clock-history" 
            color="warning" />
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <x-kpi-card 
            title="Leaves / Permissions" 
            :value="$grandTotalPermission" 
            subtitle="Authorized staff leaves"
            icon="bi-calendar2-check" 
            color="info" />
    </div>
</div>

<!-- Report Summary Table -->
<x-card :noPadding="true" title="Employee Monthly Shift Breakdown: {{ $monthLabel }}" badge="{{ $reportData->count() }} employees">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" style="font-size: 0.88rem;">
            <thead class="table-light text-muted" style="font-size: 0.78rem; text-transform: uppercase;">
                <tr>
                    <th class="ps-4">Staff Member</th>
                    <th>Job Position</th>
                    <th class="text-center">Present</th>
                    <th class="text-center">Late</th>
                    <th class="text-center">Permission</th>
                    <th class="text-center">Absent</th>
                    <th class="text-center">Total Shifts</th>
                    <th class="text-center">Hours Worked</th>
                    <th class="text-end pe-4">Punctuality Rate</th>
                </tr>
            </thead>
            <tbody>
                @forelse($reportData as $row)
                @php
                    $emp = $row['employee'];
                    $totalShifts = $row['total_shifts'];
                    $onTimeRate = $totalShifts > 0 ? round(($row['present'] / $totalShifts) * 100, 1) : 0;
                @endphp
                <tr>
                    <td class="ps-4">
                        <div class="d-flex align-items-center gap-2">
                            <span class="avatar-circle">
                                {{ strtoupper(substr($emp->name, 0, 2)) }}
                            </span>
                            <div>
                                <a href="{{ route('employees.show', $emp) }}" class="fw-semibold text-dark text-decoration-none hover-primary">
                                    {{ $emp->name }}
                                </a>
                                <div class="text-muted" style="font-size: 0.72rem;">{{ $emp->phone ?? $emp->email }}</div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <span class="badge bg-light text-dark border">
                            {{ $emp->position->name ?? 'Staff' }}
                        </span>
                    </td>
                    <td class="text-center">
                        <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">
                            {{ $row['present'] }} days
                        </span>
                    </td>
                    <td class="text-center">
                        <span class="badge {{ $row['late'] > 0 ? 'bg-warning-subtle text-warning border border-warning-subtle' : 'bg-light text-muted' }} px-2 py-1">
                            {{ $row['late'] }} days
                        </span>
                    </td>
                    <td class="text-center">
                        <span class="badge {{ $row['permission'] > 0 ? 'bg-info-subtle text-info border border-info-subtle' : 'bg-light text-muted' }} px-2 py-1">
                            {{ $row['permission'] }} days
                        </span>
                    </td>
                    <td class="text-center">
                        <span class="badge {{ $row['absent'] > 0 ? 'bg-danger-subtle text-danger border border-danger-subtle' : 'bg-light text-muted' }} px-2 py-1">
                            {{ $row['absent'] }} days
                        </span>
                    </td>
                    <td class="text-center fw-bold text-dark">
                        {{ $totalShifts }}
                    </td>
                    <td class="text-center font-monospace fw-bold text-success">
                        {{ $row['total_hours'] }} hrs
                    </td>
                    <td class="text-end pe-4">
                        @if($totalShifts > 0)
                            <div class="d-inline-flex align-items-center gap-1">
                                <span class="fw-bold {{ $onTimeRate >= 90 ? 'text-success' : ($onTimeRate >= 75 ? 'text-warning' : 'text-danger') }}">
                                    {{ $onTimeRate }}%
                                </span>
                            </div>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="9">
                        <x-empty-state 
                            icon="bi-calendar-x" 
                            title="No monthly data" 
                            description="No attendance shifts found for {{ $monthLabel }}." />
                    </td>
                </tr>
                @endforelse
            </tbody>
            <tfoot class="table-light fw-bold">
                <tr>
                    <td class="ps-4" colspan="2">Boutique Grand Totals</td>
                    <td class="text-center text-success">{{ $grandTotalPresent }}</td>
                    <td class="text-center text-warning">{{ $grandTotalLate }}</td>
                    <td class="text-center text-info">{{ $grandTotalPermission }}</td>
                    <td class="text-center text-danger">{{ $grandTotalAbsent }}</td>
                    <td class="text-center">{{ $grandTotalPresent + $grandTotalLate }}</td>
                    <td class="text-center text-success font-monospace">{{ $grandTotalHours }} hrs</td>
                    <td class="text-end pe-4 text-muted small">Month Complete</td>
                </tr>
            </tfoot>
        </table>
    </div>
</x-card>
@endsection
