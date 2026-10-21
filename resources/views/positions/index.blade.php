@extends('layouts.app')

@section('title', 'Positions & Base Compensation')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('employees.index') }}" class="text-decoration-none text-muted">HR</a></li>
    <li class="breadcrumb-item active text-dark" aria-current="page">Positions</li>
@endsection

@section('content')
<x-page-header 
    title="Positions & Base Compensation" 
    description="Store operational positions, organizational hierarchy, and base salary configurations."
    :breadcrumbs="['Human Resources' => route('employees.index'), 'Positions' => null]">
    <x-slot:actions>
        <x-action-button roles="admin,manager,hr" icon="bi-plus-lg" modalTarget="#addPositionModal">
            Add Position
        </x-action-button>
    </x-slot:actions>
</x-page-header>

@php
    $totalPositions = $positions->count();
    $totalStaff = $positions->sum('employees_count');
    $avgSalary = $totalPositions > 0 ? $positions->avg('base_salary') : 0;
@endphp

<!-- KPI Cards -->
<div class="row g-3 mb-4">
    <div class="col-12 col-sm-4">
        <x-kpi-card 
            title="Position Tiers" 
            :value="$totalPositions" 
            subtitle="Configured career grades"
            icon="bi-briefcase" 
            color="sage" />
    </div>
    <div class="col-12 col-sm-4">
        <x-kpi-card 
            title="Total Assigned Staff" 
            :value="$totalStaff" 
            subtitle="Active team members"
            icon="bi-people" 
            color="success" />
    </div>
    <div class="col-12 col-sm-4">
        <x-kpi-card 
            title="Average Base Salary" 
            value="${{ number_format($avgSalary, 2) }}" 
            subtitle="Standard compensation"
            icon="bi-cash-coin" 
            color="dark" />
    </div>
</div>

<div class="row g-3">
    @forelse($positions as $position)
    <div class="col-12 col-md-6 col-lg-4">
        <div class="card-skincare p-4 h-100 mb-0 d-flex flex-column justify-content-between position-relative">
            <div>
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <span class="brand-icon-box" style="width: 42px; height: 42px; font-size: 1.15rem; background: rgba(113, 133, 107, 0.12); color: #71856B; border-radius: 10px; display: inline-flex; align-items: center; justify-content: center;">
                        <i class="bi bi-briefcase"></i>
                    </span>
                    <div class="d-flex align-items-center gap-1">
                        <button type="button" class="btn btn-sm btn-light border text-muted px-2 py-1" data-bs-toggle="modal" data-bs-target="#editPositionModal{{ $position->id }}" title="Edit Position">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-light border text-danger px-2 py-1" data-bs-toggle="modal" data-bs-target="#deletePositionModal{{ $position->id }}" title="Delete Position">
                            <i class="bi bi-trash3"></i>
                        </button>
                    </div>
                </div>

                <h5 class="fw-bold text-dark mb-1 fs-6">{{ $position->name }}</h5>
                <div class="text-muted small mb-3">Base Compensation Tier:</div>
                <h4 class="fw-bold text-success mb-0">${{ number_format($position->base_salary, 2) }}</h4>
            </div>

            <div class="border-top pt-3 mt-4 d-flex justify-content-between align-items-center small">
                <span class="text-muted">Assigned Employees:</span>
                <span class="badge {{ $position->employees_count > 0 ? 'bg-success-subtle text-success' : 'bg-light text-muted' }} border px-2 py-1">
                    <i class="bi bi-people me-1"></i>{{ $position->employees_count }} staff
                </span>
            </div>
        </div>

        <!-- Edit Position Modal -->
        <x-modal id="editPositionModal{{ $position->id }}" title="Edit Position: {{ $position->name }}" size="md">
            <form method="POST" action="{{ route('positions.update', $position) }}">
                @csrf
                @method('PUT')

                <x-form-input 
                    name="name" 
                    label="Position Title" 
                    :value="$position->name" 
                    required 
                    placeholder="e.g. Master Aesthetician" />

                <x-form-input 
                    type="number" 
                    step="0.01" 
                    min="0" 
                    name="base_salary" 
                    label="Base Salary ($)" 
                    :value="$position->base_salary" 
                    required 
                    placeholder="0.00" />

                <div class="d-flex justify-content-end gap-2 mt-4">
                    <button type="button" class="btn btn-sm btn-light border" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-skincare-primary">Save Changes</button>
                </div>
            </form>
        </x-modal>

        <!-- Delete Position Modal -->
        <x-modal id="deletePositionModal{{ $position->id }}" title="Delete Position" size="md">
            @if($position->employees_count > 0)
                <div class="alert alert-warning d-flex align-items-center gap-2 mb-3">
                    <i class="bi bi-exclamation-triangle-fill fs-5"></i>
                    <div>
                        <strong>Cannot Delete:</strong> This position is currently assigned to <strong>{{ $position->employees_count }} employee(s)</strong>.
                    </div>
                </div>
                <p class="text-muted small">
                    To maintain organizational integrity and employee records, you must reassign these employees to another position before deleting this tier.
                </p>
                <div class="d-flex justify-content-end mt-4">
                    <button type="button" class="btn btn-sm btn-light border" data-bs-dismiss="modal">Close</button>
                </div>
            @else
                <p class="text-dark mb-3">
                    Are you sure you want to permanently delete the position <strong>"{{ $position->name }}"</strong>?
                </p>
                <form method="POST" action="{{ route('positions.destroy', $position) }}">
                    @csrf
                    @method('DELETE')
                    <div class="d-flex justify-content-end gap-2 mt-4">
                        <button type="button" class="btn btn-sm btn-light border" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-sm btn-danger">Confirm Deletion</button>
                    </div>
                </form>
            @endif
        </x-modal>
    </div>
    @empty
    <div class="col-12">
        <x-card>
            <x-empty-state 
                icon="bi-briefcase" 
                title="No positions found" 
                description="No operational job positions have been configured in the system." />
        </x-card>
    </div>
    @endforelse
</div>

<!-- Add Position Modal -->
<x-modal id="addPositionModal" title="Add Job Position" size="md">
    <form method="POST" action="{{ route('positions.store') }}">
        @csrf

        <x-form-input 
            name="name" 
            label="Position Title" 
            required 
            placeholder="e.g. Lead Dermatologist" />

        <x-form-input 
            type="number" 
            step="0.01" 
            min="0" 
            name="base_salary" 
            label="Base Monthly Salary ($)" 
            required 
            placeholder="2500.00" />

        <div class="d-flex justify-content-end gap-2 mt-4">
            <button type="button" class="btn btn-sm btn-light border" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-sm btn-skincare-primary">Create Position</button>
        </div>
    </form>
</x-modal>
@endsection
