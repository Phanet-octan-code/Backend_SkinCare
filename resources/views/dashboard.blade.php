@extends('layouts.app')

@section('title', 'Dashboard')

@push('styles')
<style>
    .kpi-row .card-skincare {
        transition: transform 0.18s ease, box-shadow 0.18s ease;
    }
    .kpi-row .card-skincare:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 16px rgba(37, 43, 37, 0.06);
    }
    .quick-action-tile {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px 14px;
        border-radius: var(--radius-md);
        background-color: #FBFDFB;
        border: 1px solid var(--border-color);
        color: var(--text-main);
        text-decoration: none;
        transition: all 0.15s ease;
    }
    .quick-action-tile:hover {
        background-color: var(--primary-subtle);
        border-color: var(--primary);
        color: var(--primary-hover);
        transform: translateX(3px);
    }
    .quick-action-tile.disabled {
        opacity: 0.55;
        cursor: not-allowed;
        pointer-events: none;
    }
    .quick-action-icon {
        width: 38px;
        height: 38px;
        border-radius: 9px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 1.15rem;
        flex-shrink: 0;
    }
    .period-pill {
        font-size: 0.78rem;
        padding: 5px 12px;
        border-radius: 20px;
        font-weight: 600;
        text-decoration: none;
        color: var(--text-muted);
        border: 1px solid var(--border-color);
        background-color: var(--surface);
        transition: all 0.15s ease;
    }
    .period-pill:hover {
        color: var(--primary);
        border-color: var(--primary);
        background-color: var(--primary-subtle);
    }
    .period-pill.active {
        background-color: var(--primary);
        color: #FFFFFF;
        border-color: var(--primary);
    }
    .chart-toggle-btn {
        font-size: 0.75rem;
        padding: 3px 10px;
        font-weight: 600;
        border-radius: 6px;
        border: 1px solid var(--border-color);
        background-color: #FFFFFF;
        color: var(--text-muted);
        transition: all 0.12s ease;
    }
    .chart-toggle-btn.active {
        background-color: var(--primary);
        color: #FFFFFF;
        border-color: var(--primary);
    }
    .product-thumb {
        width: 44px;
        height: 44px;
        border-radius: 8px;
        object-fit: contain;
        background-color: #F8F9F7;
        padding: 2px;
        border: 1px solid var(--border-color);
        flex-shrink: 0;
    }
    .product-thumb-fallback {
        width: 44px;
        height: 44px;
        border-radius: 8px;
        background-color: var(--primary-subtle);
        color: var(--primary);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 1.1rem;
        flex-shrink: 0;
    }
    .health-bar-segment {
        height: 8px;
        transition: width 0.3s ease;
    }
</style>
@endpush

@section('content')
<!-- Page Header with Subtitle, Date-Range Filter, and New Sale Quick Action -->
<div class="d-flex flex-column flex-lg-row align-items-start align-items-lg-center justify-content-between gap-3 mb-4">
    <div>
        <nav aria-label="breadcrumb" class="mb-1">
            <ol class="breadcrumb mb-0" style="font-size: 0.76rem;">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none text-muted"><i class="bi bi-house-door me-1"></i>{{ __('messages.home') }}</a></li>
                <li class="breadcrumb-item active text-dark fw-semibold" aria-current="page">{{ __('messages.dashboard') }}</li>
            </ol>
        </nav>
        <h3 class="fw-bold mb-1" style="color: var(--text-main); letter-spacing: -0.5px;">{{ __('messages.dashboard') }}</h3>
        <p class="text-muted small mb-0">{{ __('messages.dashboard_overview') }}</p>
    </div>

    <div class="d-flex flex-wrap align-items-center gap-2">
        <!-- Date-Range Selector Controls -->
        <div class="d-flex align-items-center gap-1 bg-white p-1 rounded-pill border overflow-x-auto" style="box-shadow: 0 1px 2px rgba(0,0,0,0.03); max-width: 100%; white-space: nowrap; scrollbar-width: none; -webkit-overflow-scrolling: touch;">
            <a href="{{ route('dashboard', ['period' => 'today']) }}" 
               class="period-pill {{ $period === 'today' ? 'active' : '' }}">
                {{ __('messages.today') }}
            </a>
            <a href="{{ route('dashboard', ['period' => 'yesterday']) }}" 
               class="period-pill {{ $period === 'yesterday' ? 'active' : '' }}">
                {{ __('messages.yesterday') }}
            </a>
            <a href="{{ route('dashboard', ['period' => '7days']) }}" 
               class="period-pill {{ $period === '7days' ? 'active' : '' }}">
                {{ __('messages.last_7d') }}
            </a>
            <a href="{{ route('dashboard', ['period' => 'this_month']) }}" 
               class="period-pill {{ $period === 'this_month' ? 'active' : '' }}">
                {{ __('messages.this_month') }}
            </a>
            <a href="{{ route('dashboard', ['period' => 'last_month']) }}" 
               class="period-pill {{ $period === 'last_month' ? 'active' : '' }}">
                {{ __('messages.last_month') }}
            </a>
            <button type="button" 
                    class="period-pill d-inline-flex align-items-center gap-1 {{ $period === 'custom' ? 'active' : '' }}" 
                    data-bs-toggle="modal" 
                    data-bs-target="#customDateModal">
                <i class="bi bi-calendar3"></i>
                <span>{{ __('messages.custom') }}</span>
            </button>
        </div>

        <!-- Quick Action: New Sale (POS Terminal) -->
        <x-action-button roles="admin,manager,cashier" icon="bi-cart-plus-fill" :url="route('pos.index')">
            {{ __('messages.new_sale') }}
        </x-action-button>
    </div>
</div>

<!-- Active Filter Information Banner (when Custom or non-standard filter is active) -->
@if($period === 'custom')
<div class="alert alert-light border d-flex align-items-center justify-content-between py-2 px-3 mb-4 small" style="border-radius: var(--radius-md);">
    <div class="d-flex align-items-center gap-2">
        <i class="bi bi-calendar-range text-primary fs-6"></i>
        <span>{{ __('messages.active_date_range') }}: <strong>{{ $periodLabel }}</strong></span>
    </div>
    <a href="{{ route('dashboard', ['period' => '7days']) }}" class="text-decoration-none text-muted small">
        <i class="bi bi-x-circle me-1"></i>{{ __('messages.reset_to_default') }}
    </a>
</div>
@endif

<!-- KPI Cards Row (6 Metric Cards) -->
<div class="row g-3 mb-4 kpi-row">
    <!-- 1. Today's Sales -->
    <div class="col-12 col-sm-6 col-xl-2">
        <x-kpi-card 
            :title="$isCashier ? __('messages.today_sales') : __('messages.today_sales')" 
            :value="'$' . number_format($todaySalesTotal, 2)" 
            :subtitle="$todaySalesCount . ' ' . __('messages.selected')"
            :trend="$todayTrend"
            :trendUp="$todayTrendUp"
            icon="bi-cash-coin" 
            color="sage" />
    </div>

    <!-- 2. Monthly Revenue -->
    <div class="col-12 col-sm-6 col-xl-2">
        <x-kpi-card 
            :title="$isCashier ? __('messages.monthly_revenue') : __('messages.monthly_revenue')" 
            :value="'$' . number_format($monthlyRevenue, 2)" 
            :subtitle="now()->format('F Y')"
            :trend="$monthlyTrend"
            :trendUp="$monthlyTrendUp"
            icon="bi-graph-up-arrow" 
            color="success" />
    </div>

    <!-- 3. Total Products -->
    <div class="col-12 col-sm-6 col-xl-2">
        <x-kpi-card 
            :title="__('messages.total_products')" 
            :value="number_format($totalProducts)" 
            :subtitle="$activeProductsCount . ' ' . __('messages.active')"
            icon="bi-boxes" 
            color="info" />
    </div>

    <!-- 4. Low Stock Items -->
    <div class="col-12 col-sm-6 col-xl-2">
        <x-kpi-card 
            :title="__('messages.low_stock_items')" 
            :value="number_format($lowStockCount)" 
            :subtitle="$lowStockCount > 0 ? ($outOfStockCount > 0 ? $outOfStockCount . ' ' . __('messages.out_of_stock') : __('messages.low_stock')) : __('messages.in_stock')"
            icon="bi-exclamation-triangle" 
            :color="$lowStockCount > 0 ? 'warning' : 'success'" />
    </div>

    <!-- 5. Total Customers -->
    <div class="col-12 col-sm-6 col-xl-2">
        <x-kpi-card 
            :title="__('messages.total_customers')" 
            :value="number_format($totalCustomers)" 
            :subtitle="$vipCustomersCount . ' VIP'"
            icon="bi-people" 
            color="sage" />
    </div>

    <!-- 6. Total Transactions -->
    <div class="col-12 col-sm-6 col-xl-2">
        <x-kpi-card 
            :title="__('messages.total_transactions')" 
            :value="number_format($periodTransactionsCount)" 
            :subtitle="'$' . number_format($averageOrderValue, 2) . ' ' . __('messages.aov')"
            icon="bi-receipt" 
            color="dark" />
    </div>
</div>

<!-- Main Balanced Two-Column Analytics Layout -->
<div class="row g-4">
    <!-- ── LEFT COLUMN: Sales Overview, Recent Invoices, Best-Selling Formulas (col-lg-8) ── -->
    <div class="col-12 col-xl-8">
        <!-- Section 1: Sales Revenue Overview Chart -->
        <x-card noPadding class="mb-4">
            <x-slot:header>
                <div>
                    <div class="d-flex align-items-center gap-2">
                        <h5 class="fw-bold mb-0 fs-6 text-dark">Sales Revenue Overview</h5>
                        <span class="badge bg-light text-muted border" style="font-size: 0.72rem;">{{ $periodLabel }}</span>
                    </div>
                    <small class="text-muted" style="font-size: 0.76rem;">Completed sales revenue performance over time</small>
                </div>
            </x-slot:header>

            <x-slot:actions>
                <div class="d-flex align-items-center gap-1 bg-light p-1 rounded border">
                    <button type="button" class="chart-toggle-btn active" id="btnChartDaily" onclick="setChartTimeframe('daily')">Daily</button>
                    <button type="button" class="chart-toggle-btn" id="btnChartWeekly" onclick="setChartTimeframe('weekly')">Weekly</button>
                    <button type="button" class="chart-toggle-btn" id="btnChartMonthly" onclick="setChartTimeframe('monthly')">Monthly</button>
                </div>
            </x-slot:actions>

            <div class="p-4">
                <div class="d-flex align-items-baseline gap-2 mb-3">
                    <span class="fs-4 fw-bold text-dark">${{ number_format($periodRevenue, 2) }}</span>
                    <span class="text-muted small">Total completed revenue in this view</span>
                </div>
                <div style="position: relative; height: 280px; width: 100%;">
                    <canvas id="salesOverviewChart"></canvas>
                </div>
            </div>
        </x-card>

        <!-- Section 2: Recent Transactions Table -->
        <x-card noPadding class="mb-4">
            <x-slot:header>
                <div class="d-flex align-items-center gap-2">
                    <h5 class="fw-bold mb-0 fs-6 text-dark">Recent Transactions</h5>
                    <span class="badge bg-success-subtle text-success border border-success-subtle" style="font-size: 0.68rem;">Verified Feed</span>
                </div>
            </x-slot:header>

            <x-slot:actions>
                <a href="{{ route('sales.index') }}" class="btn btn-sm btn-skincare-outline py-1 px-2" style="font-size: 0.78rem;">
                    View Sales History &rarr;
                </a>
            </x-slot:actions>

            <div class="table-responsive">
                <table class="table table-skincare mb-0">
                    <thead>
                        <tr>
                            <th class="ps-4">Invoice #</th>
                            <th>Customer</th>
                            @if(!$isCashier)
                            <th>Cashier</th>
                            @endif
                            <th>Date & Time</th>
                            <th>Payment</th>
                            <th>Total</th>
                            <th class="pe-4 text-end">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($recentSales as $sale)
                        <tr>
                            <td class="ps-4">
                                <span class="fw-bold font-monospace text-dark" style="font-size: 0.84rem;">{{ $sale->invoice_number }}</span>
                            </td>
                            <td>
                                @if($sale->customer)
                                    <div class="fw-semibold text-dark" style="font-size: 0.85rem;">{{ $sale->customer->name }}</div>
                                    <span class="badge bg-secondary badge-role-pill" style="font-size: 0.62rem;">{{ $sale->customer->membership_level ?? 'Standard' }}</span>
                                @else
                                    <span class="text-muted fst-italic small">Walk-in Guest</span>
                                @endif
                            </td>
                            @if(!$isCashier)
                            <td class="small text-muted">
                                {{ $sale->user->name ?? 'System' }}
                            </td>
                            @endif
                            <td class="text-muted small">
                                {{ $sale->created_at->format('M j, Y h:i A') }}
                            </td>
                            <td>
                                @php
                                    $payType = strtolower($sale->payment_type);
                                    $payIcon = match($payType) {
                                        'cash' => 'bi-cash',
                                        'card', 'credit_card', 'debit_card' => 'bi-credit-card',
                                        'qr', 'qris' => 'bi-qr-code',
                                        default => 'bi-wallet2',
                                    };
                                @endphp
                                <span class="badge bg-light text-dark border d-inline-flex align-items-center gap-1" style="font-size: 0.72rem;">
                                    <i class="bi {{ $payIcon }}"></i>
                                    <span class="text-uppercase">{{ $sale->payment_type }}</span>
                                </span>
                            </td>
                            <td>
                                <span class="fw-bold text-dark">${{ number_format($sale->final_total, 2) }}</span>
                            </td>
                            <td class="pe-4 text-end">
                                <x-status-badge :status="$sale->status" />
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="{{ $isCashier ? 6 : 7 }}" class="p-0">
                                <x-empty-state 
                                    icon="bi-receipt" 
                                    title="No transactions recorded yet" 
                                    description="Invoices completed through POS will appear here." />
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-card>

        <!-- Section 3: Best-Selling Products Table -->
        <x-card noPadding class="mb-4">
            <x-slot:header>
                <div>
                    <div class="d-flex align-items-center gap-2">
                        <h5 class="fw-bold mb-0 fs-6 text-dark">Best-Selling Products</h5>
                        <span class="badge bg-light text-muted border" style="font-size: 0.72rem;">Top Performers</span>
                    </div>
                    <small class="text-muted" style="font-size: 0.76rem;">Ranked by units sold across verified sales</small>
                </div>
            </x-slot:header>

            <x-slot:actions>
                @if($isAdmin || $isManager)
                <a href="{{ route('products.index') }}" class="btn btn-sm btn-skincare-outline py-1 px-2" style="font-size: 0.78rem;">
                    Catalog &rarr;
                </a>
                @endif
            </x-slot:actions>

            <div class="table-responsive">
                <table class="table table-skincare mb-0">
                    <thead>
                        <tr>
                            <th class="ps-4">Product Details</th>
                            <th>Category</th>
                            <th class="text-center">Units Sold</th>
                            <th>Total Revenue</th>
                            <th class="pe-4 text-end">Current Stock</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($bestSellers as $item)
                        <tr>
                            <td class="ps-4">
                                <div class="d-flex align-items-center gap-3">
                                    @if($item->product && $item->product->image && file_exists(public_path($item->product->image)))
                                        <img src="{{ asset($item->product->image) }}" alt="{{ $item->product->name }}" class="product-thumb">
                                    @elseif(file_exists(public_path('images/products/skincare_bottle.jpg')))
                                        <img src="{{ asset('images/products/skincare_bottle.jpg') }}" alt="Skincare formula" class="product-thumb">
                                    @else
                                        <div class="product-thumb-fallback">
                                            <i class="bi bi-droplet-half"></i>
                                        </div>
                                    @endif
                                    <div>
                                        <div class="fw-bold text-dark" style="font-size: 0.86rem;">
                                            {{ $item->product->name ?? 'Discontinued Formula' }}
                                        </div>
                                        <div class="text-muted font-monospace small" style="font-size: 0.72rem;">
                                            Barcode: {{ $item->product->barcode ?? 'N/A' }}
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="badge bg-light text-muted border" style="font-size: 0.72rem;">
                                    {{ $item->product->category->name ?? 'Unassigned' }}
                                </span>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-primary-subtle text-primary fw-bold px-2 py-1" style="font-size: 0.8rem;">
                                    {{ number_format($item->total_units_sold) }} pcs
                                </span>
                            </td>
                            <td>
                                <span class="fw-bold text-dark">${{ number_format($item->total_revenue, 2) }}</span>
                            </td>
                            <td class="pe-4 text-end">
                                @if($item->product)
                                    @if($item->product->qty <= 0)
                                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle" style="font-size: 0.72rem;">Out of Stock</span>
                                    @elseif($item->product->isLowStock())
                                        <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle" style="font-size: 0.72rem;">
                                            Low: {{ $item->product->qty }} left
                                        </span>
                                    @else
                                        <span class="badge bg-success-subtle text-success border border-success-subtle" style="font-size: 0.72rem;">
                                            In Stock: {{ $item->product->qty }}
                                        </span>
                                    @endif
                                @else
                                    <span class="text-muted small">—</span>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" class="p-0">
                                <x-empty-state 
                                    icon="bi-box-seam" 
                                    title="No product sales logged yet" 
                                    description="Top-selling skincare formulas will automatically rank here." />
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-card>
    </div>

    <!-- ── RIGHT COLUMN: Quick Actions, Sales by Payment, Low Stock Alerts, Inventory Summary (col-lg-4) ── -->
    <div class="col-12 col-xl-4">
        <!-- 1. Quick Actions Card -->
        <x-card class="mb-4">
            <x-slot:header>
                <div class="d-flex align-items-center gap-2">
                    <h5 class="fw-bold mb-0 fs-6 text-dark">Quick Actions</h5>
                    <span class="badge bg-light text-muted border" style="font-size: 0.72rem;">Shortcuts</span>
                </div>
            </x-slot:header>

            <div class="d-flex flex-column gap-2">
                <!-- Action 1: New Sale (POS) -->
                <a href="{{ route('pos.index') }}" class="quick-action-tile">
                    <span class="quick-action-icon" style="background-color: var(--primary-subtle); color: var(--primary);">
                        <i class="bi bi-cart3"></i>
                    </span>
                    <div class="flex-grow-1">
                        <div class="fw-bold" style="font-size: 0.85rem;">New Sale</div>
                        <small class="text-muted" style="font-size: 0.74rem;">Launch retail POS checkout counter</small>
                    </div>
                    <i class="bi bi-chevron-right text-muted small"></i>
                </a>

                <!-- Action 2: Add Product (Catalog) -->
                @if($isAdmin || $isManager)
                <a href="{{ route('products.index') }}" class="quick-action-tile">
                    <span class="quick-action-icon" style="background-color: #F0F4F8; color: #557288;">
                        <i class="bi bi-plus-square"></i>
                    </span>
                    <div class="flex-grow-1">
                        <div class="fw-bold" style="font-size: 0.85rem;">Add Product</div>
                        <small class="text-muted" style="font-size: 0.74rem;">Add formula, barcode & pricing</small>
                    </div>
                    <i class="bi bi-chevron-right text-muted small"></i>
                </a>
                @else
                <div class="quick-action-tile disabled" data-bs-toggle="tooltip" title="Restricted to Manager and Administrator">
                    <span class="quick-action-icon" style="background-color: #F5F5F5; color: #ADB4A9;">
                        <i class="bi bi-lock"></i>
                    </span>
                    <div class="flex-grow-1">
                        <div class="fw-bold text-muted" style="font-size: 0.85rem;">Add Product</div>
                        <small class="text-muted" style="font-size: 0.74rem;">Restricted to Manager</small>
                    </div>
                </div>
                @endif

                <!-- Action 3: Stock In (Inventory Delivery) -->
                @if($isAdmin || $isManager || auth()->user()->isInventory())
                <a href="{{ route('inventory.stock-in') }}" class="quick-action-tile">
                    <span class="quick-action-icon" style="background-color: #FDF7EE; color: #D4973B;">
                        <i class="bi bi-box-arrow-in-down"></i>
                    </span>
                    <div class="flex-grow-1">
                        <div class="fw-bold" style="font-size: 0.85rem;">Stock In</div>
                        <small class="text-muted" style="font-size: 0.74rem;">Receive supplier deliveries into stock</small>
                    </div>
                    <i class="bi bi-chevron-right text-muted small"></i>
                </a>
                @else
                <div class="quick-action-tile disabled" data-bs-toggle="tooltip" title="Restricted to Inventory Specialists">
                    <span class="quick-action-icon" style="background-color: #F5F5F5; color: #ADB4A9;">
                        <i class="bi bi-lock"></i>
                    </span>
                    <div class="flex-grow-1">
                        <div class="fw-bold text-muted" style="font-size: 0.85rem;">Stock In</div>
                        <small class="text-muted" style="font-size: 0.74rem;">Restricted permission</small>
                    </div>
                </div>
                @endif

                <!-- Action 4: Add Customer -->
                <a href="{{ route('customers.index') }}" class="quick-action-tile">
                    <span class="quick-action-icon" style="background-color: #EFF6F1; color: #5B8A68;">
                        <i class="bi bi-person-plus"></i>
                    </span>
                    <div class="flex-grow-1">
                        <div class="fw-bold" style="font-size: 0.85rem;">Add Customer</div>
                        <small class="text-muted" style="font-size: 0.74rem;">Register client & VIP membership tier</small>
                    </div>
                    <i class="bi bi-chevron-right text-muted small"></i>
                </a>
            </div>
        </x-card>

        <!-- 2. Sales by Payment Method (Doughnut Chart) -->
        <x-card class="mb-4">
            <x-slot:header>
                <div class="d-flex align-items-center gap-2">
                    <h5 class="fw-bold mb-0 fs-6 text-dark">Payment Methods</h5>
                    <span class="badge bg-light text-muted border" style="font-size: 0.72rem;">Distribution</span>
                </div>
            </x-slot:header>

            @if($paymentTotalSum > 0)
            <div style="position: relative; height: 180px; width: 100%;" class="mb-3">
                <canvas id="paymentMethodChart"></canvas>
            </div>

            <!-- Payment Method Breakdown List -->
            <div class="d-flex flex-column gap-2 border-top pt-3">
                @foreach($paymentMap as $key => $item)
                @if($item['count'] > 0 || $paymentTotalSum > 0)
                <div class="d-flex align-items-center justify-content-between small">
                    <div class="d-flex align-items-center gap-2">
                        <span class="d-inline-block rounded-circle" style="width: 10px; height: 10px; background-color: {{ $item['color'] }};"></span>
                        <span class="fw-medium text-dark">{{ $item['label'] }}</span>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <span class="fw-bold text-dark">${{ number_format($item['total'], 2) }}</span>
                        <span class="badge bg-light text-muted border" style="font-size: 0.68rem; min-width: 44px; text-align: right;">
                            {{ $item['percentage'] }}%
                        </span>
                    </div>
                </div>
                @endif
                @endforeach
            </div>
            @else
            <x-empty-state 
                icon="bi-pie-chart" 
                title="No payment records" 
                description="Payment method shares will display once orders are checked out." />
            @endif
        </x-card>

        <!-- 3. Low Stock Alerts Card -->
        <x-card noPadding class="mb-4">
            <x-slot:header>
                <div class="d-flex align-items-center gap-2">
                    <h5 class="fw-bold mb-0 fs-6 text-dark">Low Stock Alerts</h5>
                    @if($lowStockCount > 0)
                        <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle" style="font-size: 0.68rem;">
                            {{ $lowStockCount }} Alert{{ $lowStockCount > 1 ? 's' : '' }}
                        </span>
                    @else
                        <span class="badge bg-success-subtle text-success border border-success-subtle" style="font-size: 0.68rem;">Healthy</span>
                    @endif
                </div>
            </x-slot:header>

            <x-slot:actions>
                @if($isAdmin || $isManager || auth()->user()->isInventory())
                <a href="{{ route('inventory.index') }}" class="btn btn-sm btn-skincare-outline py-1 px-2" style="font-size: 0.78rem;">
                    Manage &rarr;
                </a>
                @endif
            </x-slot:actions>

            @if($lowStockProducts->isNotEmpty())
            <div class="list-group list-group-flush">
                @foreach($lowStockProducts as $prod)
                <div class="list-group-item px-3 py-2 d-flex align-items-center justify-content-between">
                    <div>
                        <div class="fw-semibold text-dark text-truncate" style="font-size: 0.83rem; max-width: 190px;">
                            {{ $prod->name }}
                        </div>
                        <div class="text-muted small" style="font-size: 0.72rem;">
                            Threshold: {{ $prod->low_stock_threshold }} | {{ $prod->category->name ?? 'Uncategorized' }}
                        </div>
                    </div>
                    <div class="text-end d-flex flex-column align-items-end gap-1">
                        @if($prod->qty <= 0)
                            <span class="badge bg-danger text-white" style="font-size: 0.68rem;">0 units</span>
                        @else
                            <span class="badge bg-warning text-dark" style="font-size: 0.68rem;">{{ $prod->qty }} left</span>
                        @endif

                        @if($isAdmin || $isManager || auth()->user()->isInventory())
                        <a href="{{ route('inventory.stock-in') }}" class="btn btn-xs btn-outline-secondary py-0 px-1 text-decoration-none" style="font-size: 0.68rem;">
                            + Restock
                        </a>
                        @endif
                    </div>
                </div>
                @endforeach
            </div>
            @else
            <div class="p-4 text-center">
                <i class="bi bi-shield-check text-success fs-2 d-block mb-2"></i>
                <div class="fw-semibold text-dark small">All items well stocked</div>
                <small class="text-muted" style="font-size: 0.74rem;">No products are currently at or below minimum threshold.</small>
            </div>
            @endif
        </x-card>

        <!-- 4. Inventory Summary Card -->
        <x-card class="mb-4">
            <x-slot:header>
                <div class="d-flex align-items-center gap-2">
                    <h5 class="fw-bold mb-0 fs-6 text-dark">Inventory Summary</h5>
                    <span class="badge bg-light text-muted border" style="font-size: 0.72rem;">Audit</span>
                </div>
            </x-slot:header>

            <!-- Key Metric Boxes -->
            <div class="row g-2 mb-3">
                <div class="col-6">
                    <div class="p-2 border rounded bg-light text-center">
                        <small class="text-muted d-block" style="font-size: 0.72rem;">Total Stock Units</small>
                        <span class="fw-bold fs-6 text-dark">{{ number_format($totalUnitsInStock) }}</span>
                    </div>
                </div>
                <div class="col-6">
                    <div class="p-2 border rounded bg-light text-center">
                        <small class="text-muted d-block" style="font-size: 0.72rem;">Retail Valuation</small>
                        <span class="fw-bold fs-6 text-dark">${{ number_format($inventoryRetailValuation, 2) }}</span>
                    </div>
                </div>
                @if($isAdmin || $isManager)
                <div class="col-6">
                    <div class="p-2 border rounded bg-light text-center">
                        <small class="text-muted d-block" style="font-size: 0.72rem;">Wholesale Cost</small>
                        <span class="fw-bold fs-6 text-dark">${{ number_format($inventoryCostValuation, 2) }}</span>
                    </div>
                </div>
                <div class="col-6">
                    <div class="p-2 border rounded bg-light text-center">
                        <small class="text-muted d-block" style="font-size: 0.72rem;">Potential Margin</small>
                        <span class="fw-bold fs-6 text-success">${{ number_format($inventoryPotentialProfit, 2) }}</span>
                    </div>
                </div>
                @endif
            </div>

            <!-- Inventory Health Multi-Segment Progress Bar -->
            @php
                $denom = max(1, $totalProducts);
                $pctHealthy = round(($inStockHealthyCount / $denom) * 100);
                $pctLow = round(($lowStockWarningCount / $denom) * 100);
                $pctOut = round(($criticallyOutOfStockCount / $denom) * 100);
            @endphp
            <div class="mb-2">
                <div class="d-flex justify-content-between small text-muted mb-1" style="font-size: 0.74rem;">
                    <span>Stock Health Breakdown</span>
                    <span>{{ $totalProducts }} total catalog items</span>
                </div>
                <div class="progress" style="height: 8px; border-radius: 4px; overflow: hidden; background-color: #EAECE9;">
                    <div class="progress-bar bg-success" style="width: {{ $pctHealthy }}%;" title="In Stock: {{ $inStockHealthyCount }}"></div>
                    <div class="progress-bar bg-warning" style="width: {{ $pctLow }}%;" title="Low Stock: {{ $lowStockWarningCount }}"></div>
                    <div class="progress-bar bg-danger" style="width: {{ $pctOut }}%;" title="Out of Stock: {{ $criticallyOutOfStockCount }}"></div>
                </div>
            </div>

            <!-- Health Legend -->
            <div class="d-flex justify-content-between small text-muted pt-1" style="font-size: 0.72rem;">
                <span class="d-flex align-items-center gap-1">
                    <span class="rounded-circle d-inline-block bg-success" style="width: 7px; height: 7px;"></span>
                    {{ $inStockHealthyCount }} In Stock
                </span>
                <span class="d-flex align-items-center gap-1">
                    <span class="rounded-circle d-inline-block bg-warning" style="width: 7px; height: 7px;"></span>
                    {{ $lowStockWarningCount }} Low Stock
                </span>
                <span class="d-flex align-items-center gap-1">
                    <span class="rounded-circle d-inline-block bg-danger" style="width: 7px; height: 7px;"></span>
                    {{ $criticallyOutOfStockCount }} Out of Stock
                </span>
            </div>
        </x-card>
    </div>
</div>

<!-- Custom Date Range Modal -->
<div class="modal fade" id="customDateModal" tabindex="-1" aria-labelledby="customDateModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-bottom py-3">
                <h6 class="modal-title fw-bold text-dark mb-0" id="customDateModalLabel">
                    <i class="bi bi-calendar3 me-1 text-primary"></i>Custom Date Filter
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('dashboard') }}" method="GET">
                <input type="hidden" name="period" value="custom">
                <div class="modal-body py-3">
                    <div class="mb-3">
                        <label for="startDateInput" class="form-label small fw-semibold text-dark">Start Date</label>
                        <input type="date" 
                               id="startDateInput" 
                               name="start_date" 
                               class="form-control form-control-sm" 
                               value="{{ $startDate }}" 
                               required>
                    </div>
                    <div class="mb-2">
                        <label for="endDateInput" class="form-label small fw-semibold text-dark">End Date</label>
                        <input type="date" 
                               id="endDateInput" 
                               name="end_date" 
                               class="form-control form-control-sm" 
                               value="{{ $endDate }}" 
                               required>
                    </div>
                </div>
                <div class="modal-footer border-top py-2 bg-light">
                    <button type="button" class="btn btn-sm btn-skincare-outline" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-skincare-primary">Apply Filter</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<!-- Chart.js 4.4 Library via CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>

<script>
    // ── Chart.js Dataset Storage ──
    const chartSeriesData = {
        daily: {
            labels: @json($chartData['daily']['labels']),
            data: @json($chartData['daily']['data'])
        },
        weekly: {
            labels: @json($chartData['weekly']['labels']),
            data: @json($chartData['weekly']['data'])
        },
        monthly: {
            labels: @json($chartData['monthly']['labels']),
            data: @json($chartData['monthly']['data'])
        }
    };

    let salesOverviewChart = null;
    let paymentMethodChart = null;

    document.addEventListener('DOMContentLoaded', function () {
        // ── 1. Initialize Sales Overview Chart (Line with gradient fill) ──
        const ctxSales = document.getElementById('salesOverviewChart');
        if (ctxSales) {
            const context = ctxSales.getContext('2d');
            const gradient = context.createLinearGradient(0, 0, 0, 260);
            gradient.addColorStop(0, 'rgba(113, 133, 107, 0.28)');
            gradient.addColorStop(1, 'rgba(113, 133, 107, 0.00)');

            salesOverviewChart = new Chart(ctxSales, {
                type: 'line',
                data: {
                    labels: chartSeriesData.daily.labels,
                    datasets: [{
                        label: 'Revenue ($)',
                        data: chartSeriesData.daily.data,
                        borderColor: '#71856B',
                        backgroundColor: gradient,
                        borderWidth: 2.2,
                        pointBackgroundColor: '#FFFFFF',
                        pointBorderColor: '#71856B',
                        pointBorderWidth: 2,
                        pointRadius: 3.5,
                        pointHoverRadius: 6,
                        tension: 0.35,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: '#252B25',
                            titleColor: '#FFFFFF',
                            bodyColor: '#FFFFFF',
                            padding: 10,
                            displayColors: false,
                            callbacks: {
                                label: function(context) {
                                    return 'Revenue: $' + Number(context.parsed.y).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            grid: { display: false },
                            ticks: {
                                font: { family: "'Plus Jakarta Sans', sans-serif", size: 11 },
                                color: '#777F76',
                                maxRotation: 0,
                                autoSkip: true,
                                maxTicksLimit: 12
                            }
                        },
                        y: {
                            border: { dash: [4, 4] },
                            grid: { color: '#EEF1EB' },
                            ticks: {
                                font: { family: "'Plus Jakarta Sans', sans-serif", size: 11 },
                                color: '#777F76',
                                callback: function(value) {
                                    return '$' + value;
                                }
                            },
                            beginAtZero: true
                        }
                    }
                }
            });
        }

        // ── 2. Initialize Payment Methods Doughnut Chart ──
        const ctxPayment = document.getElementById('paymentMethodChart');
        if (ctxPayment) {
            const paymentLabels = [
                'Cash',
                'Card',
                'QR / QRIS'
            ];
            const paymentValues = [
                {{ $paymentMap['cash']['total'] ?? 0 }},
                {{ $paymentMap['card']['total'] ?? 0 }},
                {{ $paymentMap['qr']['total'] ?? 0 }}
            ];
            const paymentColors = [
                '#71856B',
                '#557288',
                '#D4973B'
            ];

            paymentMethodChart = new Chart(ctxPayment, {
                type: 'doughnut',
                data: {
                    labels: paymentLabels,
                    datasets: [{
                        data: paymentValues,
                        backgroundColor: paymentColors,
                        borderWidth: 2,
                        borderColor: '#FFFFFF',
                        hoverOffset: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '70%',
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: '#252B25',
                            titleColor: '#FFFFFF',
                            bodyColor: '#FFFFFF',
                            padding: 10,
                            callbacks: {
                                label: function(context) {
                                    const val = Number(context.parsed).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
                                    return ' ' + context.label + ': $' + val;
                                }
                            }
                        }
                    }
                }
            });
        }
    });

    // ── Timeframe Tab Switching (Daily, Weekly, Monthly) ──
    function setChartTimeframe(type) {
        if (!salesOverviewChart || !chartSeriesData[type]) return;

        // Update button states
        ['daily', 'weekly', 'monthly'].forEach(t => {
            const btn = document.getElementById('btnChart' + t.charAt(0).toUpperCase() + t.slice(1));
            if (btn) btn.classList.toggle('active', t === type);
        });

        // Update chart data & re-render
        salesOverviewChart.data.labels = chartSeriesData[type].labels;
        salesOverviewChart.data.datasets[0].data = chartSeriesData[type].data;
        salesOverviewChart.update();
    }
</script>
@endpush
