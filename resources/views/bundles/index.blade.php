@extends('layouts.app')

@section('title', 'Product Bundles & Gift Sets')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none text-muted">Marketing</a></li>
    <li class="breadcrumb-item active text-dark" aria-current="page">Bundles & Gift Sets</li>
@endsection

@section('content')
<x-page-header 
    title="Bundles & Gift Sets" 
    description="Curate skincare routines, seasonal gift sets, and discounted multi-product sets."
    :breadcrumbs="['Marketing' => route('promotions.index'), 'Bundles & Gift Sets' => null]">
    <x-slot:actions>
        <a href="{{ route('bundles.create') }}" class="btn btn-skincare-primary d-inline-flex align-items-center gap-2">
            <i class="bi bi-gift"></i> Create Bundle or Gift Set
        </a>
    </x-slot:actions>
</x-page-header>

<!-- Filter Tabs -->
<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4 border-bottom pb-2">
    <ul class="nav nav-pills gap-1">
        <li class="nav-item">
            <a class="nav-link py-1 px-3 small {{ request('type', 'all') === 'all' ? 'active bg-skincare-primary' : 'text-muted' }}" 
               href="{{ route('bundles.index', ['type' => 'all']) }}">
                All Offers <span class="badge bg-light text-dark ms-1">{{ $counts['all'] }}</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link py-1 px-3 small {{ request('type') === 'bundle' ? 'active bg-skincare-primary' : 'text-muted' }}" 
               href="{{ route('bundles.index', ['type' => 'bundle']) }}">
                <i class="bi bi-box me-1"></i> Product Bundles 
                <span class="badge bg-light text-dark ms-1">{{ $counts['bundle'] }}</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link py-1 px-3 small {{ request('type') === 'gift_set' ? 'active bg-skincare-primary' : 'text-muted' }}" 
               href="{{ route('bundles.index', ['type' => 'gift_set']) }}">
                <i class="bi bi-gift me-1"></i> Gift Sets 
                <span class="badge bg-light text-dark ms-1">{{ $counts['gift_set'] }}</span>
            </a>
        </li>
    </ul>

    <!-- Quick Search -->
    <form method="GET" action="{{ route('bundles.index') }}" class="d-flex align-items-center gap-2">
        <input type="hidden" name="type" value="{{ request('type', 'all') }}">
        <div class="input-group input-group-sm">
            <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
            <input type="text" name="search" class="form-control border-start-0" placeholder="Search bundles..." value="{{ request('search') }}" style="border-radius: 0 8px 8px 0; max-width: 200px;">
        </div>
        @if(request('search'))
            <a href="{{ route('bundles.index', ['type' => request('type', 'all')]) }}" class="btn btn-sm btn-light border text-muted">Reset</a>
        @endif
    </form>
</div>

<!-- Bundles Table -->
<x-table 
    :headers="[
        ['label' => 'Bundle / Gift Set', 'class' => 'ps-4'],
        'Type',
        'Constituent Products',
        'Bundle Price',
        'Savings',
        'Available Stock',
        'Status',
        ['label' => 'Actions', 'class' => 'pe-4 text-end']
    ]">

    <x-slot:cardHeader>
        <div class="d-flex align-items-center justify-content-between w-100">
            <h5 class="fw-bold mb-0 fs-6 text-dark">Promotional Bundles Catalog</h5>
            <span class="badge bg-light text-muted border">{{ $bundles->total() }} records</span>
        </div>
    </x-slot:cardHeader>

    @forelse($bundles as $b)
    <tr>
        <td class="ps-4">
            <div class="d-flex align-items-center gap-3">
                <img src="{{ $b->image_url }}" alt="{{ $b->name }}" class="rounded border object-fit-cover" style="width: 44px; height: 44px;">
                <div>
                    <span class="fw-semibold text-dark">{{ $b->name }}</span>
                    <div class="text-muted small text-truncate" style="max-width: 250px;">{{ $b->description ?: 'No description' }}</div>
                </div>
            </div>
        </td>
        <td>
            @if($b->type === 'gift_set')
                <span class="badge bg-warning text-dark"><i class="bi bi-gift me-1"></i> Gift Set</span>
            @else
                <span class="badge bg-info text-dark"><i class="bi bi-box me-1"></i> Routine Bundle</span>
            @endif
        </td>
        <td>
            <div class="small">
                @foreach($b->items as $item)
                    <div>
                        <span class="fw-medium">{{ $item->quantity }}x</span> {{ $item->product?->name ?? 'Product' }}
                        @if($item->is_free_gift)
                            <span class="badge bg-success-subtle text-success ms-1">Free Gift</span>
                        @endif
                    </div>
                @endforeach
            </div>
        </td>
        <td>
            <span class="fw-bold text-dark">${{ number_format($b->price, 2) }}</span>
            @if($b->calculated_original_price > $b->price)
                <div class="text-muted small text-decoration-line-through">${{ number_format($b->calculated_original_price, 2) }}</div>
            @endif
        </td>
        <td>
            @if($b->savings_amount > 0)
                <span class="badge bg-success-subtle text-success">
                    Save ${{ number_format($b->savings_amount, 2) }} ({{ $b->savings_percentage }}%)
                </span>
            @else
                <span class="text-muted small">-</span>
            @endif
        </td>
        <td>
            @if($b->available_qty > 5)
                <span class="badge bg-success">{{ $b->available_qty }} sets</span>
            @elseif($b->available_qty > 0)
                <span class="badge bg-warning text-dark">Low: {{ $b->available_qty }} sets</span>
            @else
                <span class="badge bg-danger">Sold Out</span>
            @endif
        </td>
        <td>
            <span class="badge {{ $b->status === 'active' ? 'bg-success-subtle text-success' : 'bg-secondary' }}">
                {{ ucfirst($b->status) }}
            </span>
        </td>
        <td class="pe-4 text-end">
            <div class="dropdown">
                <button class="btn btn-sm btn-light border-0" data-bs-toggle="dropdown">
                    <i class="bi bi-three-dots-vertical"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                    <li><a class="dropdown-item small" href="{{ route('bundles.edit', $b) }}"><i class="bi bi-pencil me-2 text-muted"></i> Edit Bundle</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <form action="{{ route('bundles.destroy', $b) }}" method="POST" onsubmit="return confirm('Archive this bundle?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="dropdown-item small text-danger"><i class="bi bi-trash me-2"></i> Archive</button>
                        </form>
                    </li>
                </ul>
            </div>
        </td>
    </tr>
    @empty
    <tr>
        <td colspan="8" class="text-center py-5 text-muted">
            <i class="bi bi-gift fs-2 d-block mb-2 text-muted opacity-50"></i>
            No promotional bundles or gift sets found.
        </td>
    </tr>
    @endforelse
</x-table>

<div class="mt-4">
    {{ $bundles->links() }}
</div>
@endsection

