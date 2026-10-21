<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Position;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class EmployeeController extends Controller
{
    /**
     * Display employees staff directory with search, filtering, sorting, and pagination.
     */
    public function index(Request $request): View
    {
        $query = Employee::with('position')->withCount('attendances');

        // Search by name, phone, or email
        if ($request->filled('search')) {
            $search = trim($request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('phone', 'LIKE', "%{$search}%")
                  ->orWhere('email', 'LIKE', "%{$search}%");
            });
        }

        // Filter by position
        if ($request->filled('position_id')) {
            $query->where('position_id', $request->input('position_id'));
        }

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        // Sorting
        $allowedSorts = ['name', 'start_date', 'created_at'];
        $sortBy = in_array($request->input('sort_by'), $allowedSorts, true) ? $request->input('sort_by') : 'name';
        $sortDir = strtolower($request->input('sort_dir', 'asc')) === 'desc' ? 'desc' : 'asc';

        $query->orderBy($sortBy, $sortDir);

        $employees = $query->paginate(15)->withQueryString();
        $positions = Position::orderBy('name')->get();

        return view('employees.index', [
            'employees' => $employees,
            'positions' => $positions,
            'filters' => $request->only(['search', 'position_id', 'status', 'sort_by', 'sort_dir']),
        ]);
    }

    /**
     * Store a newly registered staff member.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'position_id' => 'required|exists:positions,id',
            'phone' => 'nullable|string|max:30',
            'email' => 'nullable|email|max:255|unique:employees,email',
            'start_date' => 'required|date',
            'status' => 'required|in:active,inactive,terminated',
        ], [
            'position_id.required' => 'Please select an assigned job position.',
            'position_id.exists' => 'The selected position does not exist.',
            'email.unique' => 'An employee with this email is already registered.',
        ]);

        $employee = Employee::create($validated);

        return redirect()->route('employees.index')
            ->with('status', "Employee [{$employee->name}] enrolled successfully.");
    }

    /**
     * Display detailed employee profile and complete attendance history.
     */
    public function show(Employee $employee, Request $request): View
    {
        $employee->load('position');

        // Statistics
        $attendancesQuery = $employee->attendances();
        $totalLogs = (clone $attendancesQuery)->count();
        $presentCount = (clone $attendancesQuery)->where('status', 'present')->count();
        $lateCount = (clone $attendancesQuery)->where('status', 'late')->count();
        $permissionCount = (clone $attendancesQuery)->where('status', 'permission')->count();
        $absentCount = (clone $attendancesQuery)->where('status', 'absent')->count();

        // Total hours worked
        $workedAttendances = (clone $attendancesQuery)
            ->whereNotNull('check_in')
            ->whereNotNull('check_out')
            ->get();
        $totalHours = round($workedAttendances->sum(fn ($att) => $att->working_hours ?? 0), 2);

        // Paginated attendance history with date filtering
        $historyQuery = $employee->attendances()->latest('date');

        if ($request->filled('month')) {
            $month = $request->input('month'); // YYYY-MM
            $historyQuery->where('date', 'LIKE', "{$month}%");
        }

        if ($request->filled('status')) {
            $historyQuery->where('status', $request->input('status'));
        }

        $attendances = $historyQuery->paginate(15)->withQueryString();
        $positions = Position::orderBy('name')->get();

        return view('employees.show', [
            'employee' => $employee,
            'positions' => $positions,
            'attendances' => $attendances,
            'stats' => [
                'total' => $totalLogs,
                'present' => $presentCount,
                'late' => $lateCount,
                'permission' => $permissionCount,
                'absent' => $absentCount,
                'total_hours' => $totalHours,
            ],
            'filters' => $request->only(['month', 'status']),
        ]);
    }

    /**
     * Update employee profile and assigned position.
     */
    public function update(Request $request, Employee $employee): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'position_id' => 'required|exists:positions,id',
            'phone' => 'nullable|string|max:30',
            'email' => [
                'nullable',
                'email',
                'max:255',
                Rule::unique('employees', 'email')->ignore($employee->id),
            ],
            'start_date' => 'required|date',
            'status' => 'required|in:active,inactive,terminated',
        ], [
            'position_id.required' => 'Please select an assigned job position.',
            'email.unique' => 'An employee with this email is already registered.',
        ]);

        $employee->update($validated);

        return redirect()->back()
            ->with('status', "Employee [{$employee->name}] profile updated successfully.");
    }

    /**
     * Toggle active/inactive status for the employee.
     */
    public function toggleStatus(Employee $employee): RedirectResponse
    {
        $newStatus = $employee->status === 'active' ? 'inactive' : 'active';
        $employee->update(['status' => $newStatus]);

        return redirect()->back()
            ->with('status', "Employee [{$employee->name}] status changed to " . ucfirst($newStatus) . '.');
    }

    /**
     * Remove employee or deactivate if historical records exist.
     */
    public function destroy(Employee $employee): RedirectResponse
    {
        $hasAttendance = $employee->attendances()->exists();

        if ($hasAttendance) {
            $employee->update(['status' => 'inactive']);

            return redirect()->route('employees.index')
                ->with('status', "Employee [{$employee->name}] has historical attendance records. The profile was deactivated instead of deleted to preserve history.");
        }

        $name = $employee->name;
        $employee->delete();

        return redirect()->route('employees.index')
            ->with('status', "Employee [{$name}] deleted successfully.");
    }
}
