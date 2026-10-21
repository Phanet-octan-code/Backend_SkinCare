<?php

namespace App\Http\Controllers;

use App\Models\Position;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PositionController extends Controller
{
    /**
     * Display job positions and base salary tiers.
     */
    public function index(Request $request): View
    {
        $positions = Position::withCount('employees')->orderBy('name')->get();

        return view('positions.index', [
            'positions' => $positions,
        ]);
    }

    /**
     * Store a newly created position in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:positions,name',
            'base_salary' => 'required|numeric|min:0|max:9999999.99',
        ], [
            'name.unique' => 'A position with this title already exists.',
            'base_salary.min' => 'Base compensation cannot be negative.',
        ]);

        $position = Position::create($validated);

        return redirect()->route('positions.index')
            ->with('status', "Position [{$position->name}] added successfully.");
    }

    /**
     * Update the specified position in storage.
     */
    public function update(Request $request, Position $position): RedirectResponse
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('positions', 'name')->ignore($position->id),
            ],
            'base_salary' => 'required|numeric|min:0|max:9999999.99',
        ], [
            'name.unique' => 'A position with this title already exists.',
            'base_salary.min' => 'Base compensation cannot be negative.',
        ]);

        $position->update($validated);

        return redirect()->route('positions.index')
            ->with('status', "Position [{$position->name}] updated successfully.");
    }

    /**
     * Remove the specified position from storage.
     * Guarded: Prevents deletion if currently in use by employees.
     */
    public function destroy(Position $position): RedirectResponse
    {
        $assignedEmployeesCount = $position->employees()->count();

        if ($assignedEmployeesCount > 0) {
            return redirect()->route('positions.index')
                ->with('error', "Cannot delete position [{$position->name}]. It is currently assigned to {$assignedEmployeesCount} staff member(s). Please reassign or update staff before deleting this position.");
        }

        $name = $position->name;
        $position->delete();

        return redirect()->route('positions.index')
            ->with('status', "Position [{$name}] deleted successfully.");
    }
}
