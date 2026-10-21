@extends('layouts.app')

@section('title', 'Business Analytics & Consolidated Reports')

@section('breadcrumb')
    <li class="breadcrumb-item active text-dark" aria-current="page">Reports & Analytics</li>
@endsection

@section('content')
<div class="d-print-none">
    <x-page-header 
        title="Business Analytics & Consolidated Reports" 
        description="Comprehensive financial ledgers, inventory valuation, transaction audits, and employee attendance metrics."
        :breadcrumbs="['Analytics' => null, 'Reports' => null]">
        <x-slot:actions>
            <button type="button" class="btn btn-sm btn-light border d-flex align-items-center gap-1" onclick="window.print();">
                <i class="bi bi-printer"></i> Print Report
            </button>
            <div class="dropdown">
                <button class="btn btn-sm btn-skincare-primary dropdown-toggle d-flex align-items-center gap-1" type="button" data-bs-toggle="dropdown">
                    <i class="bi bi-file-earmark-arrow-down"></i> Export CSV
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow-sm" style="font-size: 0.85rem;">
                    <li>
                        <a class="dropdown-item" href="{{ route('reports.export', array_merge(request()->all(), ['type' => 'sales'])) }}">
                            <i class="bi bi-receipt me-2 text-success"></i> Export Sales Ledger (CSV)
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item" href="{{ route('reports.export', array_merge(request()->all(), ['type' => 'inventory'])) }}">
                            <i class="bi bi-boxes me-2 text-primary"></i> Export Inventory Valuation (CSV)
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item" href="{{ route('reports.export', array_merge(request()->all(), ['type' => 'transactions'])) }}">
                            <i class="bi bi-arrow-left-right me-2 text-warning"></i> Export Movement Ledger (CSV)
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item" href="{{ route('reports.export', array_merge(request()->all(), ['type' => 'employees'])) }}">
                            <i class="bi bi-calendar-check me-2 text-info"></i> Export Attendance Report (CSV)
                        </a>
                    </li>
                </ul>
            </div>
        </x-slot:actions>
    </x-page-header>

    <!-- Navigation Tabs -->
    <ul class="nav nav-pills custom-report-tabs mb-4 gap-2 border-bottom pb-3">
        <li class="nav-item">
            <a class="nav-link {{ $activeTab === 'sales' ? 'active' : '' }}" href="{{ route('reports.index', array_merge(request()->except('tab'), ['tab' => 'sales'])) }}">
                <i class="bi bi-graph-up-arrow me-1"></i> Sales & Financials
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ $activeTab === 'inventory' ? 'active' : '' }}" href="{{ route('reports.index', array_merge(request()->except('tab'), ['tab' => 'inventory'])) }}">
                <i class="bi bi-boxes me-1"></i> Inventory Valuation
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ $activeTab === 'transactions' ? 'active' : '' }}" href="{{ route('reports.index', array_merge(request()->except('tab'), ['tab' => 'transactions'])) }}">
                <i class="bi bi-arrow-left-right me-1"></i> Transaction Ledger
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ $activeTab === 'employees' ? 'active' : '' }}" href="{{ route('reports.index', array_merge(request()->except('tab'), ['tab' => 'employees'])) }}">
                <i class="bi bi-people me-1"></i> Employee Attendance
            </a>
        </li>
    </ul>

    <!-- Date Range & Period Filter Bar -->
    <div class="card-skincare p-3 mb-4">
        <form method="GET" action="{{ route('reports.index') }}" class="row g-2 align-items-center">
            <input type="hidden" name="tab" value="{{ $activeTab }}">

            <div class="col-12 col-xl-5 d-flex gap-1 flex-wrap">
                <a href="{{ route('reports.index', ['tab' => $activeTab, 'period' => 'today']) }}" class="btn btn-sm {{ $period === 'today' ? 'btn-skincare-primary' : 'btn-light border' }}">Today</a>
                <a href="{{ route('reports.index', ['tab' => $activeTab, 'period' => 'week']) }}" class="btn btn-sm {{ $period === 'week' ? 'btn-skincare-primary' : 'btn-light border' }}">This Week</a>
                <a href="{{ route('reports.index', ['tab' => $activeTab, 'period' => 'month']) }}" class="btn btn-sm {{ $period === 'month' ? 'btn-skincare-primary' : 'btn-light border' }}">This Month</a>
                <a href="{{ route('reports.index', ['tab' => $activeTab, 'period' => 'year']) }}" class="btn btn-sm {{ $period === 'year' ? 'btn-skincare-primary' : 'btn-light border' }}">This Year</a>
                <a href="{{ route('reports.index', ['tab' => $activeTab, 'period' => 'all']) }}" class="btn btn-sm {{ $period === 'all' ? 'btn-skincare-primary' : 'btn-light border' }}">All Time</a>
            </div>

            <div class="col-12 col-md-3 col-xl-3">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-calendar-event"></i></span>
                    <input type="date" name="start_date" class="form-control border-start-0" value="{{ $startDate }}" placeholder="From">
                </div>
            </div>

            <div class="col-12 col-md-3 col-xl-3">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-calendar-event"></i></span>
                    <input type="date" name="end_date" class="form-control border-start-0" value="{{ $endDate }}" placeholder="To">
                </div>
            </div>

            <div class="col-12 col-md-6 col-xl-1 text-end">
                <button type="submit" class="btn btn-sm btn-dark w-100">Filter</button>
            </div>
        </form>
    </div>
</div>

<!-- Print-Only Letterhead Header -->
<div class="d-none d-print-block mb-4 border-bottom pb-3">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h3 class="fw-bold text-dark mb-0">SkinCare POS Management System</h3>
            <p class="text-muted small mb-0">Executive Audit & Analytics Report &bull; Generated: {{ now()->format('M j, Y h:i A') }}</p>
        </div>
        <div class="text-end">
            <span class="badge bg-dark text-white p-2">Period: {{ $formattedPeriod }}</span>
        </div>
    </div>
</div>

{{-- ═════════════════════════════════════════════════════════════════ --}}
{{-- TAB 1: SALES & FINANCIAL REPORTS                                  --}}
{{-- ═════════════════════════════════════════════════════════════════ --}}
@if($activeTab === 'sales')
<!-- Financial Summary KPI Cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <x-kpi-card 
            title="Gross Invoiced Sales" 
            :value="'$' . number_format($grossSales, 2)" 
            subtitle="Completed order subtotals"
            icon="bi-receipt" 
            color="sage" />
    </div>
    <div class="col-6 col-md-3">
        <x-kpi-card 
            title="Discounts & Promos" 
            :value="'-$' . number_format($totalDiscounts, 2)" 
            subtitle="Promotions & VIP tier perks"
            icon="bi-percent" 
            color="warning" />
    </div>
    <div class="col-6 col-md-3">
        <x-kpi-card 
            title="Net Sales (Excl. Tax)" 
            :value="'$' . number_format($netSales, 2)" 
            subtitle="Gross sales less discounts & refunds"
            icon="bi-cash-coin" 
            color="success" />
    </div>
    <div class="col-6 col-md-3">
        <x-kpi-card 
            title="Cost of Goods (COGS)" 
            :value="'$' . number_format($cogs, 2)" 
            subtitle="Wholesale inventory cost"
            icon="bi-box-seam" 
            color="dark" />
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <x-kpi-card 
            title="Gross Profit" 
            :value="'$' . number_format($grossProfit, 2)" 
            :subtitle="'Margin: ' . $grossMargin . '%'"
            icon="bi-graph-up-arrow" 
            color="success" />
    </div>
    <div class="col-6 col-md-3">
        <x-kpi-card 
            title="Tax Collected" 
            :value="'$' . number_format($totalTax, 2)" 
            subtitle="Government retail sales tax"
            icon="bi-bank" 
            color="info" />
    </div>
    <div class="col-6 col-md-3">
        <x-kpi-card 
            title="Refunds & Returns" 
            :value="'$' . number_format($refundsTotal, 2)" 
            :subtitle="$refundsCount . ' transactions refunded'"
            icon="bi-arrow-counterclockwise" 
            color="dark" />
    </div>
    <div class="col-6 col-md-3">
        <x-kpi-card 
            title="Avg. Order Value (AOV)" 
            :value="'$' . number_format($avgOrderValue, 2)" 
            :subtitle="$completedSalesCount . ' completed orders'"
            icon="bi-cart-check" 
            color="sage" />
    </div>
</div>

<!-- Charts & Breakdowns Row -->
<div class="row g-3 mb-4">
    <!-- Sales by Payment Type -->
    <div class="col-12 col-lg-4">
        <div class="card-skincare p-4 h-100">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <h6 class="fw-bold text-dark mb-0 fs-6">Sales by Payment Method</h6>
                <span class="badge bg-light text-muted border">Distribution</span>
            </div>

            <div style="height: 180px; position: relative;" class="mb-3">
                <canvas id="paymentMethodReportChart"></canvas>
            </div>

            <div class="d-flex flex-column gap-2 border-top pt-3">
                @foreach($paymentSummary as $pKey => $pData)
                <div class="d-flex align-items-center justify-content-between small">
                    <span class="text-dark fw-semibold">
                        <i class="bi bi-circle-fill me-1" style="font-size: 0.6rem; color: {{ match($pKey) { 'cash' => '#5B8A68', 'card' => '#3E5C43', default => '#8DA487' } }};"></i>
                        {{ $pData['label'] }}
                    </span>
                    <div class="text-end">
                        <span class="fw-bold font-monospace text-dark">${{ number_format($pData['total'], 2) }}</span>
                        <span class="badge bg-light text-muted border ms-1">{{ $pData['percentage'] }}%</span>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </div>

    <!-- Sales by Cashier -->
    <div class="col-12 col-lg-4">
        <div class="card-skincare p-4 h-100">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <h6 class="fw-bold text-dark mb-0 fs-6">Sales by Cashier</h6>
                <span class="badge bg-light text-muted border">Team Performance</span>
            </div>

            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0" style="font-size: 0.84rem;">
                    <thead class="table-light text-muted" style="font-size: 0.72rem;">
                        <tr>
                            <th>Cashier</th>
                            <th class="text-center">Orders</th>
                            <th class="text-end">Total Revenue</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($cashierStats as $stat)
                        <tr>
                            <td>
                                <div class="fw-semibold text-dark">{{ $stat['user']->name }}</div>
                                <div class="text-muted" style="font-size: 0.7rem;">{{ ucfirst($stat['user']->role) }}</div>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-light text-dark border">{{ $stat['count'] }}</span>
                            </td>
                            <td class="text-end fw-bold font-monospace text-success">
                                ${{ number_format($stat['total'], 2) }}
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="3" class="text-muted text-center py-3">No sales logged in this period.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Sales by Customer (Top VIPs) -->
    <div class="col-12 col-lg-4">
        <div class="card-skincare p-4 h-100">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <h6 class="fw-bold text-dark mb-0 fs-6">Top Customers / Members</h6>
                <span class="badge bg-light text-muted border">VIP Loyalty</span>
            </div>

            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0" style="font-size: 0.84rem;">
                    <thead class="table-light text-muted" style="font-size: 0.72rem;">
                        <tr>
                            <th>Customer</th>
                            <th class="text-center">Orders</th>
                            <th class="text-end">Total Spend</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($topCustomers as $cust)
                        <tr>
                            <td>
                                <a href="{{ route('customers.show', $cust) }}" class="fw-semibold text-dark text-decoration-none hover-primary">
                                    {{ $cust->name }}
                                </a>
                                <span class="badge bg-secondary badge-role-pill ms-1" style="font-size: 0.65rem;">{{ $cust->membership_level }}</span>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-light text-dark border">{{ $cust->sales_count }}</span>
                            </td>
                            <td class="text-end fw-bold font-monospace text-success">
                                ${{ number_format($cust->sales_sum_final_total ?? 0, 2) }}
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="3" class="text-muted text-center py-2">No customer sales recorded.</td>
                        </tr>
                        @endforelse
                        @if($guestSalesCount > 0)
                        <tr class="table-light">
                            <td><span class="text-muted fst-italic">Walk-in Guests</span></td>
                            <td class="text-center"><span class="badge bg-light text-dark border">{{ $guestSalesCount }}</span></td>
                            <td class="text-end fw-bold font-monospace text-dark">${{ number_format($guestSalesTotal, 2) }}</td>
                        </tr>
                        @endif
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Product Performance: Best-Selling & Slow-Moving Products -->
<div class="row g-3 mb-4">
    <!-- Best-Selling Products -->
    <div class="col-12 col-lg-6">
        <div class="card-skincare p-4 h-100">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <div class="d-flex align-items-center gap-2">
                    <span class="brand-icon-box" style="width: 32px; height: 32px; font-size: 0.95rem; background: rgba(91, 138, 104, 0.12); color: #5B8A68; border-radius: 8px; display: inline-flex; align-items: center; justify-content: center;">
                        <i class="bi bi-star"></i>
                    </span>
                    <h6 class="fw-bold text-dark mb-0 fs-6">Best-Selling Products</h6>
                </div>
                <span class="badge bg-success-subtle text-success border border-success-subtle">Top Volume</span>
            </div>

            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0" style="font-size: 0.84rem;">
                    <thead class="table-light text-muted" style="font-size: 0.72rem;">
                        <tr>
                            <th>Product</th>
                            <th class="text-center">Units Sold</th>
                            <th class="text-end">Revenue</th>
                            <th class="text-end">Gross Profit</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($bestSellingProducts as $item)
                        <tr>
                            <td>
                                <div class="fw-semibold text-dark">{{ $item['product']->name }}</div>
                                <div class="text-muted font-monospace" style="font-size: 0.7rem;">{{ $item['product']->barcode }}</div>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-light text-dark border fw-bold">{{ $item['units_sold'] }} units</span>
                            </td>
                            <td class="text-end fw-bold font-monospace text-dark">
                                ${{ number_format($item['revenue'], 2) }}
                            </td>
                            <td class="text-end fw-bold font-monospace text-success">
                                ${{ number_format($item['revenue'] - $item['cost'], 2) }}
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="4" class="text-muted text-center py-3">No product sales in selected timeframe.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Slow-Moving Products -->
    <div class="col-12 col-lg-6">
        <div class="card-skincare p-4 h-100">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <div class="d-flex align-items-center gap-2">
                    <span class="brand-icon-box" style="width: 32px; height: 32px; font-size: 0.95rem; background: rgba(230, 81, 0, 0.12); color: #E65100; border-radius: 8px; display: inline-flex; align-items: center; justify-content: center;">
                        <i class="bi bi-hourglass-bottom"></i>
                    </span>
                    <h6 class="fw-bold text-dark mb-0 fs-6">Slow-Moving Products</h6>
                </div>
                <span class="badge bg-warning-subtle text-warning border border-warning-subtle">Review Stock</span>
            </div>

            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0" style="font-size: 0.84rem;">
                    <thead class="table-light text-muted" style="font-size: 0.72rem;">
                        <tr>
                            <th>Product</th>
                            <th class="text-center">Units Sold</th>
                            <th class="text-center">Current Stock</th>
                            <th class="text-end">Revenue</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($slowMovingProducts as $item)
                        <tr>
                            <td>
                                <div class="fw-semibold text-dark">{{ $item['product']->name }}</div>
                                <div class="text-muted" style="font-size: 0.7rem;">{{ $item['product']->category->name ?? 'Catalog' }}</div>
                            </td>
                            <td class="text-center">
                                <span class="badge {{ $item['units_sold'] == 0 ? 'bg-danger-subtle text-danger' : 'bg-light text-muted' }} border">
                                    {{ $item['units_sold'] }} sold
                                </span>
                            </td>
                            <td class="text-center font-monospace fw-bold {{ $item['current_stock'] <= 5 ? 'text-danger' : 'text-dark' }}">
                                {{ $item['current_stock'] }}
                            </td>
                            <td class="text-end fw-bold font-monospace text-muted">
                                ${{ number_format($item['revenue'], 2) }}
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="4" class="text-muted text-center py-3">Catalog items are all performing adequately.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Detailed Sales Ledger Table -->
<x-card :noPadding="true" title="Audited Sales Ledger (Period: {{ $formattedPeriod }})" badge="{{ $salesLedger->total() }} transactions">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" style="font-size: 0.88rem;">
            <thead class="table-light text-muted" style="font-size: 0.78rem; text-transform: uppercase;">
                <tr>
                    <th class="ps-4">Invoice #</th>
                    <th>Date & Time</th>
                    <th>Customer</th>
                    <th>Cashier</th>
                    <th>Payment</th>
                    <th>Subtotal</th>
                    <th>Discount</th>
                    <th>Tax</th>
                    <th>Final Total</th>
                    <th class="text-end pe-4">Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($salesLedger as $sale)
                <tr>
                    <td class="ps-4">
                        <a href="{{ route('sales.show', $sale) }}" class="font-monospace fw-bold text-dark text-decoration-none hover-primary">
                            {{ $sale->invoice_number }}
                        </a>
                    </td>
                    <td class="text-muted small">
                        <div>{{ $sale->created_at->format('M j, Y') }}</div>
                        <div style="font-size: 0.72rem;">{{ $sale->created_at->format('h:i A') }}</div>
                    </td>
                    <td>
                        @if($sale->customer)
                            <a href="{{ route('customers.show', $sale->customer) }}" class="fw-semibold text-dark text-decoration-none hover-primary">
                                {{ $sale->customer->name }}
                            </a>
                            <span class="badge bg-secondary badge-role-pill ms-1" style="font-size: 0.65rem;">{{ $sale->customer->membership_level }}</span>
                        @else
                            <span class="text-muted fst-italic small">Walk-in Guest</span>
                        @endif
                    </td>
                    <td class="small text-muted">{{ $sale->user->name ?? 'System' }}</td>
                    <td>
                        <span class="badge bg-light text-dark border text-uppercase" style="font-size: 0.72rem;">
                            {{ $sale->payment_type }}
                        </span>
                    </td>
                    <td class="font-monospace text-muted">${{ number_format($sale->subtotal, 2) }}</td>
                    <td class="font-monospace {{ $sale->discount > 0 ? 'text-danger' : 'text-muted' }}">
                        {{ $sale->discount > 0 ? '-$' . number_format($sale->discount, 2) : '—' }}
                    </td>
                    <td class="font-monospace text-muted">${{ number_format($sale->tax, 2) }}</td>
                    <td class="font-monospace fw-bold text-success">${{ number_format($sale->final_total, 2) }}</td>
                    <td class="text-end pe-4">
                        <x-status-badge :status="$sale->status" />
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="10">
                        <x-empty-state icon="bi-receipt" title="No sales found" description="No sales records match the selected date range." />
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($salesLedger->hasPages())
    <x-slot:footer>
        {{ $salesLedger->links() }}
    </x-slot:footer>
    @endif
</x-card>
@endif

{{-- ═════════════════════════════════════════════════════════════════ --}}
{{-- TAB 2: INVENTORY VALUATION & AUDIT REPORTS                        --}}
{{-- ═════════════════════════════════════════════════════════════════ --}}
@if($activeTab === 'inventory')
<!-- Inventory KPI Summary Cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <x-kpi-card 
            title="Valuation at Cost" 
            :value="'$' . number_format($inventoryCostValuation, 2)" 
            subtitle="Wholesale asset capital"
            icon="bi-box-seam" 
            color="sage" />
    </div>
    <div class="col-6 col-md-3">
        <x-kpi-card 
            title="Potential Retail Value" 
            :value="'$' . number_format($inventoryRetailValuation, 2)" 
            subtitle="Gross realizable revenue"
            icon="bi-cash-coin" 
            color="success" />
    </div>
    <div class="col-6 col-md-3">
        <x-kpi-card 
            title="Potential Margin" 
            :value="'$' . number_format($potentialInventoryProfit, 2)" 
            subtitle="Unrealized markup value"
            icon="bi-graph-up" 
            color="dark" />
    </div>
    <div class="col-6 col-md-3">
        <x-kpi-card 
            title="Total Stock Units" 
            :value="$totalUnitsInStock" 
            subtitle="Units physically present"
            icon="bi-boxes" 
            color="info" />
    </div>
</div>

<!-- Stock Health & Losses in Period -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <x-kpi-card 
            title="Low-Stock Alerts" 
            :value="$lowStockItemsCount" 
            subtitle="Below threshold limit"
            icon="bi-exclamation-triangle" 
            color="warning" />
    </div>
    <div class="col-6 col-md-3">
        <x-kpi-card 
            title="Out of Stock Items" 
            :value="$outOfStockItemsCount" 
            subtitle="Depleted catalog items"
            icon="bi-slash-circle" 
            color="dark" />
    </div>
    <div class="col-6 col-md-3">
        <x-kpi-card 
            title="Damaged / Write-Offs" 
            :value="$damagedUnits . ' units'" 
            :subtitle="'Cost Loss: $' . number_format($damagedLossValuation, 2)"
            icon="bi-trash3" 
            color="dark" />
    </div>
    <div class="col-6 col-md-3">
        <x-kpi-card 
            title="Returns & Transfers" 
            :value="($customerReturnsUnits + $supplierReturnsUnits) . ' units'" 
            :subtitle="$transfersCount . ' branch transfer orders'"
            icon="bi-arrow-left-right" 
            color="sage" />
    </div>
</div>

<!-- Inventory Search & Status Filter -->
<div class="card-skincare p-3 mb-3 d-print-none">
    <form method="GET" action="{{ route('reports.index') }}" class="row g-2 align-items-center">
        <input type="hidden" name="tab" value="inventory">
        <input type="hidden" name="period" value="{{ $period }}">
        <input type="hidden" name="start_date" value="{{ $startDate }}">
        <input type="hidden" name="end_date" value="{{ $endDate }}">

        <div class="col-12 col-md-4">
            <input type="text" name="inv_search" class="form-control form-control-sm" placeholder="Search product name or barcode..." value="{{ request('inv_search') }}">
        </div>

        <div class="col-6 col-md-3">
            <select name="stock_status" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="">All Stock Levels</option>
                <option value="in_stock" {{ request('stock_status') === 'in_stock' ? 'selected' : '' }}>In Stock (Healthy)</option>
                <option value="low_stock" {{ request('stock_status') === 'low_stock' ? 'selected' : '' }}>Low Stock Alerts</option>
                <option value="out_of_stock" {{ request('stock_status') === 'out_of_stock' ? 'selected' : '' }}>Out of Stock</option>
            </select>
        </div>

        <div class="col-6 col-md-3">
            <select name="category_id" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="">All Categories</option>
                @foreach($categories as $cat)
                    <option value="{{ $cat->id }}" {{ request('category_id') == $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
                @endforeach
            </select>
        </div>

        <div class="col-12 col-md-2 d-flex gap-1 justify-content-end">
            <button type="submit" class="btn btn-sm btn-dark">Search</button>
            @if(request()->hasAny(['inv_search', 'stock_status', 'category_id']))
                <a href="{{ route('reports.index', ['tab' => 'inventory', 'period' => $period]) }}" class="btn btn-sm btn-light border">Reset</a>
            @endif
        </div>
    </form>
</div>

<!-- Inventory Valuation Ledger Table -->
<x-card :noPadding="true" title="Catalog Valuation & Quantity Status" badge="{{ $inventoryLedger->total() }} products">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" style="font-size: 0.88rem;">
            <thead class="table-light text-muted" style="font-size: 0.78rem; text-transform: uppercase;">
                <tr>
                    <th class="ps-4">Product Name</th>
                    <th>Barcode</th>
                    <th>Category</th>
                    <th>Supplier</th>
                    <th class="text-center">Available Stock</th>
                    <th class="text-end">Cost Price</th>
                    <th class="text-end">Retail Price</th>
                    <th class="text-end">Cost Valuation</th>
                    <th class="text-end pe-4">Stock Health</th>
                </tr>
            </thead>
            <tbody>
                @forelse($inventoryLedger as $prod)
                @php
                    $isOut = $prod->qty <= 0;
                    $isLow = $prod->qty > 0 && $prod->qty <= $prod->low_stock_threshold;
                @endphp
                <tr>
                    <td class="ps-4 fw-semibold text-dark">
                        <a href="{{ route('products.show', $prod) }}" class="text-dark text-decoration-none hover-primary">
                            {{ $prod->name }}
                        </a>
                    </td>
                    <td class="font-monospace text-muted small">{{ $prod->barcode }}</td>
                    <td><span class="badge bg-light text-dark border">{{ $prod->category->name ?? 'None' }}</span></td>
                    <td class="text-muted small">{{ $prod->supplier->name ?? '—' }}</td>
                    <td class="text-center font-monospace fw-bold {{ $isOut ? 'text-danger' : ($isLow ? 'text-warning' : 'text-dark') }}">
                        {{ $prod->qty }} units
                        <div class="text-muted" style="font-size: 0.68rem;">(Threshold: {{ $prod->low_stock_threshold }})</div>
                    </td>
                    <td class="text-end font-monospace text-muted">${{ number_format($prod->cost_price, 2) }}</td>
                    <td class="text-end font-monospace text-dark">${{ number_format($prod->sale_price, 2) }}</td>
                    <td class="text-end font-monospace fw-bold text-success">${{ number_format($prod->qty * $prod->cost_price, 2) }}</td>
                    <td class="text-end pe-4">
                        @if($isOut)
                            <x-status-badge status="out_of_stock" />
                        @elseif($isLow)
                            <x-status-badge status="low_stock" />
                        @else
                            <x-status-badge status="in_stock" />
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="9">
                        <x-empty-state icon="bi-boxes" title="No inventory items found" description="No products match the selected filters." />
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($inventoryLedger->hasPages())
    <x-slot:footer>
        {{ $inventoryLedger->links() }}
    </x-slot:footer>
    @endif
</x-card>
@endif

{{-- ═════════════════════════════════════════════════════════════════ --}}
{{-- TAB 3: UNIFIED TRANSACTION MOVEMENTS REPORT                       --}}
{{-- ═════════════════════════════════════════════════════════════════ --}}
@if($activeTab === 'transactions')
<!-- Transaction Type Badges KPI Summary -->
<div class="row g-2 mb-4">
    @php
        $typesMeta = [
            'stock-in' => ['label' => 'Stock In', 'icon' => 'bi-box-arrow-in-down', 'color' => 'success'],
            'stock-out' => ['label' => 'Stock Out', 'icon' => 'bi-box-arrow-up', 'color' => 'secondary'],
            'damaged' => ['label' => 'Damaged / Write-Off', 'icon' => 'bi-trash3', 'color' => 'danger'],
            'customer_return' => ['label' => 'Customer Return', 'icon' => 'bi-arrow-counterclockwise', 'color' => 'info'],
            'supplier_return' => ['label' => 'Supplier Return', 'icon' => 'bi-arrow-left', 'color' => 'warning'],
            'transfer' => ['label' => 'Branch Transfer', 'icon' => 'bi-arrow-left-right', 'color' => 'primary'],
            'adjustment' => ['label' => 'Audit Adjustment', 'icon' => 'bi-sliders', 'color' => 'dark'],
        ];
    @endphp

    @foreach($typesMeta as $tKey => $meta)
    @php
        $tData = $txCountsByType->get($tKey);
        $count = $tData ? $tData->count : 0;
        $qty = $tData ? abs($tData->total_qty) : 0;
    @endphp
    <div class="col-6 col-md-3 col-xl">
        <div class="card-skincare p-3 text-center mb-0">
            <span class="text-muted d-block small" style="font-size: 0.72rem;">{{ $meta['label'] }}</span>
            <span class="fw-bold fs-6 text-dark">{{ $count }} logs</span>
            <small class="text-muted d-block" style="font-size: 0.68rem;">{{ $qty }} units</small>
        </div>
    </div>
    @endforeach
</div>

<!-- Transactions Filter Bar -->
<div class="card-skincare p-3 mb-3 d-print-none">
    <form method="GET" action="{{ route('reports.index') }}" class="row g-2 align-items-center">
        <input type="hidden" name="tab" value="transactions">
        <input type="hidden" name="period" value="{{ $period }}">
        <input type="hidden" name="start_date" value="{{ $startDate }}">
        <input type="hidden" name="end_date" value="{{ $endDate }}">

        <div class="col-12 col-md-5">
            <input type="text" name="tx_search" class="form-control form-control-sm" placeholder="Search reference #, product, or notes..." value="{{ request('tx_search') }}">
        </div>

        <div class="col-12 col-md-4">
            <select name="tx_type" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="">All Movement Types</option>
                <option value="stock-in" {{ request('tx_type') === 'stock-in' ? 'selected' : '' }}>Stock In (Receiving)</option>
                <option value="stock-out" {{ request('tx_type') === 'stock-out' ? 'selected' : '' }}>Stock Out (Removals)</option>
                <option value="damaged" {{ request('tx_type') === 'damaged' ? 'selected' : '' }}>Damaged / Broken / Expired</option>
                <option value="customer_return" {{ request('tx_type') === 'customer_return' ? 'selected' : '' }}>Customer Returns</option>
                <option value="supplier_return" {{ request('tx_type') === 'supplier_return' ? 'selected' : '' }}>Supplier Returns</option>
                <option value="transfer" {{ request('tx_type') === 'transfer' ? 'selected' : '' }}>Branch Transfers</option>
                <option value="adjustment" {{ request('tx_type') === 'adjustment' ? 'selected' : '' }}>Authorized Adjustments</option>
            </select>
        </div>

        <div class="col-12 col-md-3 d-flex gap-1 justify-content-end">
            <button type="submit" class="btn btn-sm btn-dark">Filter</button>
            @if(request()->hasAny(['tx_search', 'tx_type']))
                <a href="{{ route('reports.index', ['tab' => 'transactions', 'period' => $period]) }}" class="btn btn-sm btn-light border">Reset</a>
            @endif
        </div>
    </form>
</div>

<!-- Transactions Table -->
<x-card :noPadding="true" title="Unified Stock & Inventory Movements Ledger" badge="{{ $transactionsLedger->total() }} records">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" style="font-size: 0.88rem;">
            <thead class="table-light text-muted" style="font-size: 0.78rem; text-transform: uppercase;">
                <tr>
                    <th class="ps-4">Date</th>
                    <th>Reference #</th>
                    <th>Movement Type</th>
                    <th>Product</th>
                    <th class="text-center">Quantity</th>
                    <th>Operator</th>
                    <th>Supplier / Partner</th>
                    <th class="pe-4 text-end">Notes</th>
                </tr>
            </thead>
            <tbody>
                @forelse($transactionsLedger as $tx)
                @php
                    $isAddition = $tx->qty > 0;
                @endphp
                <tr>
                    <td class="ps-4 text-muted small">
                        {{ $tx->date ? $tx->date->format('M j, Y') : '—' }}
                    </td>
                    <td class="font-monospace text-dark fw-bold small">
                        {{ $tx->reference_number ?? '—' }}
                    </td>
                    <td>
                        <span class="badge {{ match($tx->type) {
                            'stock-in' => 'bg-success-subtle text-success border border-success-subtle',
                            'stock-out' => 'bg-secondary-subtle text-secondary',
                            'damaged' => 'bg-danger-subtle text-danger border border-danger-subtle',
                            'customer_return' => 'bg-info-subtle text-info border border-info-subtle',
                            'supplier_return' => 'bg-warning-subtle text-warning border border-warning-subtle',
                            'transfer' => 'bg-primary-subtle text-primary border border-primary-subtle',
                            default => 'bg-light text-dark border',
                        } }} px-2 py-1 text-uppercase" style="font-size: 0.7rem;">
                            {{ str_replace('_', ' ', $tx->type) }}
                        </span>
                    </td>
                    <td>
                        <div class="fw-semibold text-dark">{{ $tx->product->name ?? 'Unknown Product' }}</div>
                        <div class="text-muted font-monospace" style="font-size: 0.7rem;">{{ $tx->product->barcode ?? '' }}</div>
                    </td>
                    <td class="text-center font-monospace fw-bold {{ $isAddition ? 'text-success' : 'text-danger' }}">
                        {{ $isAddition ? '+' . $tx->qty : $tx->qty }} units
                    </td>
                    <td class="text-muted small">{{ $tx->user->name ?? 'System' }}</td>
                    <td class="text-muted small">{{ $tx->supplier->name ?? '—' }}</td>
                    <td class="pe-4 text-end text-muted small text-truncate" style="max-width: 200px;">
                        {{ $tx->notes ?? '—' }}
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8">
                        <x-empty-state icon="bi-arrow-left-right" title="No transaction records" description="No movements match the selected filters." />
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($transactionsLedger->hasPages())
    <x-slot:footer>
        {{ $transactionsLedger->links() }}
    </x-slot:footer>
    @endif
</x-card>
@endif

{{-- ═════════════════════════════════════════════════════════════════ --}}
{{-- TAB 4: EMPLOYEE ATTENDANCE ANALYTICS                              --}}
{{-- ═════════════════════════════════════════════════════════════════ --}}
@if($activeTab === 'employees')
<!-- Attendance KPI Summary Cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <x-kpi-card 
            title="Total Working Hours" 
            :value="$totalAttHours . ' hrs'" 
            subtitle="Staff on-duty hours in period"
            icon="bi-hourglass-split" 
            color="success" />
    </div>
    <div class="col-6 col-md-3">
        <x-kpi-card 
            title="Present Shifts" 
            :value="$attPresent" 
            subtitle="On-time shift attendance"
            icon="bi-person-check" 
            color="sage" />
    </div>
    <div class="col-6 col-md-3">
        <x-kpi-card 
            title="Late Check-ins" 
            :value="$attLate" 
            subtitle="Past scheduled threshold"
            icon="bi-clock-history" 
            color="warning" />
    </div>
    <div class="col-6 col-md-3">
        <x-kpi-card 
            title="Leaves & Absences" 
            :value="($attPermission + $attAbsent) . ' days'" 
            :subtitle="$attPermission . ' permission, ' . $attAbsent . ' absent'"
            icon="bi-calendar-x" 
            color="dark" />
    </div>
</div>

<!-- Attendance Performance Summary Table -->
<x-card :noPadding="true" title="Staff Attendance & Punctuality Summary (Period: {{ $formattedPeriod }})" badge="{{ $employeeAttendanceSummary->count() }} staff members">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" style="font-size: 0.88rem;">
            <thead class="table-light text-muted" style="font-size: 0.78rem; text-transform: uppercase;">
                <tr>
                    <th class="ps-4">Employee</th>
                    <th>Job Position</th>
                    <th class="text-center">Present</th>
                    <th class="text-center">Late</th>
                    <th class="text-center">Permission</th>
                    <th class="text-center">Absent</th>
                    <th class="text-center">Total Shifts</th>
                    <th class="text-center">Hours Worked</th>
                    <th class="text-end pe-4">Punctuality Score</th>
                </tr>
            </thead>
            <tbody>
                @forelse($employeeAttendanceSummary as $item)
                @php
                    $emp = $item['employee'];
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
                                <div class="text-muted" style="font-size: 0.72rem;">{{ $emp->email }}</div>
                            </div>
                        </div>
                    </td>
                    <td><span class="badge bg-light text-dark border">{{ $emp->position->name ?? 'Staff' }}</span></td>
                    <td class="text-center"><span class="badge bg-success-subtle text-success border border-success-subtle">{{ $item['present'] }} days</span></td>
                    <td class="text-center"><span class="badge {{ $item['late'] > 0 ? 'bg-warning-subtle text-warning border border-warning-subtle' : 'bg-light text-muted' }}">{{ $item['late'] }} days</span></td>
                    <td class="text-center"><span class="badge {{ $item['permission'] > 0 ? 'bg-info-subtle text-info border border-info-subtle' : 'bg-light text-muted' }}">{{ $item['permission'] }} days</span></td>
                    <td class="text-center"><span class="badge {{ $item['absent'] > 0 ? 'bg-danger-subtle text-danger border border-danger-subtle' : 'bg-light text-muted' }}">{{ $item['absent'] }} days</span></td>
                    <td class="text-center fw-bold text-dark">{{ $item['shifts'] }}</td>
                    <td class="text-center font-monospace fw-bold text-success">{{ $item['hours'] }} hrs</td>
                    <td class="text-end pe-4">
                        @if($item['shifts'] > 0)
                            <span class="fw-bold {{ $item['rate'] >= 90 ? 'text-success' : ($item['rate'] >= 75 ? 'text-warning' : 'text-danger') }}">
                                {{ $item['rate'] }}%
                            </span>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="9">
                        <x-empty-state icon="bi-people" title="No staff members registered" description="Employee attendance records will aggregate here." />
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-card>
@endif
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        // Payment Method Doughnut Chart
        const paymentCanvas = document.getElementById('paymentMethodReportChart');
        if (paymentCanvas) {
            const ctx = paymentCanvas.getContext('2d');
            const dataTotals = [
                {{ $paymentSummary['cash']['total'] ?? 0 }},
                {{ $paymentSummary['card']['total'] ?? 0 }},
                {{ $paymentSummary['qr']['total'] ?? 0 }}
            ];

            new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: ['Cash', 'Card', 'QR Payment'],
                    datasets: [{
                        data: dataTotals,
                        backgroundColor: ['#5B8A68', '#3E5C43', '#8DA487'],
                        borderWidth: 2,
                        borderColor: '#FFFFFF'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '68%',
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return ' ' + context.label + ': $' + Number(context.parsed).toLocaleString(undefined, {minimumFractionDigits: 2});
                                }
                            }
                        }
                    }
                }
            });
        }
    });
</script>
@endpush
