<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AttendanceController extends Controller
{
    /**
     * Display staff attendance daily logs with filters and KPI cards.
     */
    public function index(Request $request): View
    {
        $selectedDate = $request->input('date', Carbon::today()->toDateString());
        $statusFilter = $request->input('status');
        $employeeFilter = $request->input('employee_id');
        $search = trim((string) $request->input('search'));

        $query = Attendance::with(['employee.position'])
            ->whereDate('date', $selectedDate);

        if ($statusFilter) {
            $query->where('status', $statusFilter);
        }

        if ($employeeFilter) {
            $query->where('employee_id', $employeeFilter);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->whereHas('employee', function ($empQ) use ($search) {
                    $empQ->where('name', 'LIKE', "%{$search}%")
                         ->orWhere('phone', 'LIKE', "%{$search}%");
                })->orWhere('notes', 'LIKE', "%{$search}%");
            });
        }

        $attendances = $query->latest('check_in')->paginate(20)->withQueryString();

        // Calculate daily stats for the selected date in 1 query
        $stats = Attendance::whereDate('date', $selectedDate)
            ->selectRaw("
                COUNT(CASE WHEN status = 'present' THEN 1 END) as present_count,
                COUNT(CASE WHEN status = 'late' THEN 1 END) as late_count,
                COUNT(CASE WHEN status = 'permission' THEN 1 END) as permission_count,
                COUNT(CASE WHEN status = 'absent' THEN 1 END) as absent_count
            ")->first();

        $presentCount = (int) ($stats->present_count ?? 0);
        $lateCount = (int) ($stats->late_count ?? 0);
        $permissionCount = (int) ($stats->permission_count ?? 0);
        $absentCount = (int) ($stats->absent_count ?? 0);

        $activeEmployees = Employee::with('position')->where('status', 'active')->orderBy('name')->get();

        return view('attendance.index', [
            'attendances' => $attendances,
            'selectedDate' => $selectedDate,
            'employees' => $activeEmployees,
            'presentCount' => $presentCount,
            'lateCount' => $lateCount,
            'permissionCount' => $permissionCount,
            'absentCount' => $absentCount,
            'totalActiveStaff' => $activeEmployees->count(),
            'filters' => $request->only(['date', 'status', 'employee_id', 'search']),
            'config' => array_merge(config('attendance', []), [
                'work_start_time' => Setting::get('work_start_time', '09:00'),
                'work_end_time' => Setting::get('work_end_time', '18:00'),
                'late_threshold_time' => Setting::get('late_threshold_time', '09:15'),
                'standard_daily_hours' => (float) Setting::get('standard_daily_hours', 8.0),
            ]),
        ]);
    }

    /**
     * Record a new attendance log or clock-in.
     */
    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'date' => 'required|date',
            'check_in' => 'nullable|string',
            'check_out' => 'nullable|string',
            'status' => 'required|in:present,late,permission,absent',
            'notes' => 'nullable|string|max:1000',
        ]);

        // Prevent duplicate attendance for the same employee and date
        $exists = Attendance::where('employee_id', $validated['employee_id'])
            ->whereDate('date', $validated['date'])
            ->exists();

        if ($exists) {
            $errMsg = 'An attendance record already exists for this staff member on the selected date.';
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => $errMsg, 'errors' => ['employee_id' => [$errMsg]]], 422);
            }
            return redirect()->back()
                ->withInput()
                ->withErrors(['employee_id' => $errMsg]);
        }

        // Validate check_in and check_out chronological order
        if (!empty($validated['check_in']) && !empty($validated['check_out'])) {
            $in = Carbon::parse($validated['check_in']);
            $out = Carbon::parse($validated['check_out']);
            if ($out->lt($in)) {
                $errMsg = 'Check-out time must be after check-in time.';
                if ($request->wantsJson() || $request->ajax()) {
                    return response()->json(['success' => false, 'message' => $errMsg, 'errors' => ['check_out' => [$errMsg]]], 422);
                }
                return redirect()->back()
                    ->withInput()
                    ->withErrors(['check_out' => $errMsg]);
            }
        }

        // Configurable late threshold determination
        $lateThreshold = Setting::get('late_threshold_time', config('attendance.late_threshold_time', '09:15:00'));
        if (strlen($lateThreshold) === 5) {
            $lateThreshold .= ':00';
        }
        if (!empty($validated['check_in']) && $validated['status'] === 'present') {
            $inTime = Carbon::parse($validated['check_in'])->format('H:i:s');
            if ($inTime > $lateThreshold) {
                $validated['status'] = 'late';
            }
        }

        $attendance = Attendance::create($validated);
        $employee = Employee::find($validated['employee_id']);
        $msg = "Attendance logged for [{$employee->name}] ({$attendance->status}).";

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => $msg,
                'attendance' => $attendance,
            ]);
        }

        return redirect()->route('attendances.index', ['date' => $validated['date']])
            ->with('status', $msg);
    }

    /**
     * Quick check-out action for an active shift.
     */
    public function checkOut(Request $request, Attendance $attendance): RedirectResponse|JsonResponse
    {
        if ($attendance->check_out) {
            $errMsg = "Employee [{$attendance->employee->name}] has already checked out.";
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => $errMsg], 422);
            }
            return redirect()->back()->with('error', $errMsg);
        }

        $checkOutTime = $request->input('check_out', now()->format('H:i:s'));

        // Validate check-out is after check-in
        if ($attendance->check_in) {
            $in = Carbon::parse($attendance->check_in);
            $out = Carbon::parse($checkOutTime);
            if ($out->lt($in)) {
                $errMsg = "Check-out time ({$checkOutTime}) cannot be earlier than check-in time ({$attendance->check_in}).";
                if ($request->wantsJson() || $request->ajax()) {
                    return response()->json(['success' => false, 'message' => $errMsg], 422);
                }
                return redirect()->back()->with('error', $errMsg);
            }
        }

        $attendance->update([
            'check_out' => $checkOutTime,
        ]);

        $hours = $attendance->working_hours;
        $hoursNotice = $hours ? " ({$hours} hours worked)" : '';
        $msg = "Employee [{$attendance->employee->name}] clocked out at {$checkOutTime}{$hoursNotice}.";

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => $msg,
                'attendance' => $attendance,
            ]);
        }

        return redirect()->back()
            ->with('status', $msg);
    }

    /**
     * Update an existing attendance record.
     */
    public function update(Request $request, Attendance $attendance): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'date' => 'required|date',
            'check_in' => 'nullable|string',
            'check_out' => 'nullable|string',
            'status' => 'required|in:present,late,permission,absent',
            'notes' => 'nullable|string|max:1000',
        ]);

        // Prevent duplicate if date is changed
        if ($validated['date'] !== $attendance->date->toDateString()) {
            $exists = Attendance::where('employee_id', $attendance->employee_id)
                ->whereDate('date', $validated['date'])
                ->where('id', '!=', $attendance->id)
                ->exists();

            if ($exists) {
                $errMsg = 'An attendance record already exists for this employee on the specified new date.';
                if ($request->wantsJson() || $request->ajax()) {
                    return response()->json(['success' => false, 'message' => $errMsg, 'errors' => ['date' => [$errMsg]]], 422);
                }
                return redirect()->back()
                    ->withInput()
                    ->withErrors(['date' => $errMsg]);
            }
        }

        // Validate check_in and check_out
        if (!empty($validated['check_in']) && !empty($validated['check_out'])) {
            $in = Carbon::parse($validated['check_in']);
            $out = Carbon::parse($validated['check_out']);
            if ($out->lt($in)) {
                $errMsg = 'Check-out time must be after check-in time.';
                if ($request->wantsJson() || $request->ajax()) {
                    return response()->json(['success' => false, 'message' => $errMsg, 'errors' => ['check_out' => [$errMsg]]], 422);
                }
                return redirect()->back()
                    ->withInput()
                    ->withErrors(['check_out' => $errMsg]);
            }
        }

        // Configurable late threshold determination
        $lateThreshold = config('attendance.late_threshold_time', '09:15:00');
        if (!empty($validated['check_in']) && $validated['status'] === 'present') {
            $inTime = Carbon::parse($validated['check_in'])->format('H:i:s');
            if ($inTime > $lateThreshold) {
                $validated['status'] = 'late';
            }
        }

        $attendance->update($validated);
        $msg = "Attendance record updated for [{$attendance->employee->name}].";

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => $msg,
                'attendance' => $attendance,
            ]);
        }

        return redirect()->back()
            ->with('status', $msg);
    }

    /**
     * Delete an attendance record.
     */
    public function destroy(Request $request, Attendance $attendance): RedirectResponse|JsonResponse
    {
        $employeeName = $attendance->employee->name ?? 'Staff';
        $dateStr = $attendance->date ? $attendance->date->format('M j, Y') : '';
        $attendance->delete();

        $msg = "Attendance log for [{$employeeName}] on {$dateStr} removed.";

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => $msg,
            ]);
        }

        return redirect()->back()
            ->with('status', $msg);
    }

    /**
     * Provide monthly attendance summary reports.
     */
    public function monthlyReport(Request $request): View
    {
        $selectedMonth = $request->input('month', Carbon::now()->format('Y-m')); // e.g. 2026-10
        $monthCarbon = Carbon::createFromFormat('Y-m', $selectedMonth)->startOfMonth();
        $monthEnd = $monthCarbon->copy()->endOfMonth();

        // Get all active employees or employees with records in that month
        $employees = Employee::with(['position', 'attendances' => function ($q) use ($monthCarbon, $monthEnd) {
            $q->whereBetween('date', [$monthCarbon->toDateString(), $monthEnd->toDateString()]);
        }])->orderBy('name')->get();

        $reportData = $employees->map(function ($emp) {
            $records = $emp->attendances;
            $present = $records->where('status', 'present')->count();
            $late = $records->where('status', 'late')->count();
            $permission = $records->where('status', 'permission')->count();
            $absent = $records->where('status', 'absent')->count();

            $totalHours = round($records->sum(fn ($a) => $a->working_hours ?? 0), 2);
            $totalShifts = $present + $late;

            return [
                'employee' => $emp,
                'present' => $present,
                'late' => $late,
                'permission' => $permission,
                'absent' => $absent,
                'total_shifts' => $totalShifts,
                'total_hours' => $totalHours,
            ];
        });

        // Totals across the boutique
        $grandTotalHours = round($reportData->sum('total_hours'), 2);
        $grandTotalPresent = $reportData->sum('present');
        $grandTotalLate = $reportData->sum('late');
        $grandTotalPermission = $reportData->sum('permission');
        $grandTotalAbsent = $reportData->sum('absent');

        return view('attendance.report', [
            'selectedMonth' => $selectedMonth,
            'monthLabel' => $monthCarbon->format('F Y'),
            'reportData' => $reportData,
            'grandTotalHours' => $grandTotalHours,
            'grandTotalPresent' => $grandTotalPresent,
            'grandTotalLate' => $grandTotalLate,
            'grandTotalPermission' => $grandTotalPermission,
            'grandTotalAbsent' => $grandTotalAbsent,
            'config' => config('attendance'),
        ]);
    }
}
