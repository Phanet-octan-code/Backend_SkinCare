<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleDetail;
use App\Models\StockTransaction;
use App\Models\StockTransfer;
use App\Models\Supplier;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    /**
     * Display consolidated business analytics, inventory valuation, transactions, and employee reports.
     * Authorized for: Admin, Manager.
     */
    public function index(Request $request): View
    {
        $activeTab = $request->input('tab', 'sales');
        [$start, $end, $period] = $this->resolveDateRange($request);
        $startDateStr = $start->toDateString();
        $endDateStr = $end->toDateString();

        // ─────────────────────────────────────────────────────────────
        // 1. SALES & FINANCIAL DATA
        // ─────────────────────────────────────────────────────────────
        $completedSalesQuery = Sale::where('status', 'completed')
            ->whereBetween('created_at', [$start, $end]);

        if ($request->filled('cashier_id')) {
            $completedSalesQuery->where('user_id', $request->input('cashier_id'));
        }
        if ($request->filled('payment_type')) {
            $completedSalesQuery->where('payment_type', $request->input('payment_type'));
        }

        $grossSales = (float) (clone $completedSalesQuery)->sum('subtotal');
        $totalDiscounts = (float) (clone $completedSalesQuery)->sum('discount');
        $totalTax = (float) (clone $completedSalesQuery)->sum('tax');
        $totalRevenue = (float) (clone $completedSalesQuery)->sum('final_total');
        $completedSalesCount = (clone $completedSalesQuery)->count();

        // Refunds / Cancellations in the period
        $refundsQuery = Sale::where(function ($q) {
            $q->where('status', 'cancelled')->orWhere('payment_status', 'refunded');
        })->whereBetween('created_at', [$start, $end]);

        $refundsTotal = (float) $refundsQuery->sum('final_total');
        $refundsCount = $refundsQuery->count();

        // Net Sales = Gross Sales minus Discounts minus Refunds
        $netSales = max(0.0, $grossSales - $totalDiscounts - $refundsTotal);

        // Cost of Goods Sold (COGS) for completed sales
        $completedSaleIds = (clone $completedSalesQuery)->pluck('id');
        $saleDetails = SaleDetail::whereIn('sale_id', $completedSaleIds)->with('product')->get();
        $cogs = (float) $saleDetails->sum(fn ($d) => $d->qty * ($d->product->cost_price ?? 0.0));

        // Gross Profit = Net Sales - COGS
        $grossProfit = $netSales - $cogs;
        $grossMargin = $netSales > 0 ? round(($grossProfit / $netSales) * 100, 2) : 0.0;
        $avgOrderValue = $completedSalesCount > 0 ? round($totalRevenue / $completedSalesCount, 2) : 0.0;

        // Sales by Payment Method
        $paymentSummary = [];
        foreach (['cash' => 'Cash', 'card' => 'Card', 'qr' => 'QR Payment'] as $pKey => $pLabel) {
            $pQuery = Sale::where('status', 'completed')
                ->where('payment_type', $pKey)
                ->whereBetween('created_at', [$start, $end]);
            $pTotal = (float) (clone $pQuery)->sum('final_total');
            $pCount = (clone $pQuery)->count();
            $paymentSummary[$pKey] = [
                'label' => $pLabel,
                'count' => $pCount,
                'total' => $pTotal,
                'percentage' => $totalRevenue > 0 ? round(($pTotal / $totalRevenue) * 100, 1) : 0.0,
            ];
        }

        // Sales by Cashier
        $cashierStats = User::whereIn('role', ['cashier', 'admin', 'manager'])
            ->get()
            ->map(function ($u) use ($start, $end) {
                $q = Sale::where('user_id', $u->id)
                    ->where('status', 'completed')
                    ->whereBetween('created_at', [$start, $end]);
                return [
                    'user' => $u,
                    'count' => (clone $q)->count(),
                    'total' => (float) (clone $q)->sum('final_total'),
                ];
            })
            ->sortByDesc('total')
            ->values();

        // Sales by Customer (Top VIPs)
        $topCustomers = Customer::whereHas('sales', fn ($q) => $q->where('status', 'completed')->whereBetween('created_at', [$start, $end]))
            ->withCount(['sales' => fn ($q) => $q->where('status', 'completed')->whereBetween('created_at', [$start, $end])])
            ->withSum(['sales' => fn ($q) => $q->where('status', 'completed')->whereBetween('created_at', [$start, $end])], 'final_total')
            ->orderByDesc('sales_sum_final_total')
            ->limit(10)
            ->get();

        $guestSalesCount = Sale::whereNull('customer_id')
            ->where('status', 'completed')
            ->whereBetween('created_at', [$start, $end])
            ->count();
        $guestSalesTotal = (float) Sale::whereNull('customer_id')
            ->where('status', 'completed')
            ->whereBetween('created_at', [$start, $end])
            ->sum('final_total');

        // Best-Selling & Slow-Moving Products
        $productSalesMap = [];
        foreach ($saleDetails as $detail) {
            $pid = $detail->product_id;
            if (!$detail->product) {
                continue;
            }
            if (!isset($productSalesMap[$pid])) {
                $productSalesMap[$pid] = [
                    'product' => $detail->product,
                    'units_sold' => 0,
                    'revenue' => 0.0,
                    'cost' => 0.0,
                ];
            }
            $productSalesMap[$pid]['units_sold'] += $detail->qty;
            $productSalesMap[$pid]['revenue'] += (float) $detail->final_total;
            $productSalesMap[$pid]['cost'] += $detail->qty * ($detail->product->cost_price ?? 0);
        }

        $bestSellingProducts = collect($productSalesMap)->sortByDesc('units_sold')->take(10)->values();

        // Slow-moving: Active catalog products with lowest or 0 units sold in period
        $activeCatalog = Product::where('status', 'active')->with(['category', 'supplier'])->get();
        $slowMovingProducts = $activeCatalog->map(function ($prod) use ($productSalesMap) {
            $sold = $productSalesMap[$prod->id] ?? null;
            return [
                'product' => $prod,
                'units_sold' => $sold ? $sold['units_sold'] : 0,
                'revenue' => $sold ? $sold['revenue'] : 0.0,
                'current_stock' => $prod->qty,
            ];
        })->sortBy('units_sold')->take(10)->values();

        // Detailed Sales Ledger Table
        $ledgerQuery = Sale::with(['user', 'customer'])
            ->whereBetween('created_at', [$start, $end]);

        if ($request->filled('search')) {
            $search = trim($request->input('search'));
            $ledgerQuery->where(function ($q) use ($search) {
                $q->where('invoice_number', 'LIKE', "%{$search}%")
                  ->orWhereHas('customer', fn ($c) => $c->where('name', 'LIKE', "%{$search}%"))
                  ->orWhereHas('user', fn ($u) => $u->where('name', 'LIKE', "%{$search}%"));
            });
        }
        if ($request->filled('cashier_id')) {
            $ledgerQuery->where('user_id', $request->input('cashier_id'));
        }
        if ($request->filled('payment_type')) {
            $ledgerQuery->where('payment_type', $request->input('payment_type'));
        }
        if ($request->filled('sale_status')) {
            $ledgerQuery->where('status', $request->input('sale_status'));
        }

        $salesLedger = $ledgerQuery->latest()->paginate(15, ['*'], 'sales_page')->withQueryString();

        // ─────────────────────────────────────────────────────────────
        // 2. INVENTORY VALUATION & AUDIT DATA
        // ─────────────────────────────────────────────────────────────
        $catalogProducts = Product::with(['category', 'supplier'])->orderBy('name')->get();
        $inventoryCostValuation = (float) $catalogProducts->sum(fn ($p) => $p->qty * $p->cost_price);
        $inventoryRetailValuation = (float) $catalogProducts->sum(fn ($p) => $p->qty * $p->sale_price);
        $totalUnitsInStock = (int) $catalogProducts->sum('qty');
        $potentialInventoryProfit = $inventoryRetailValuation - $inventoryCostValuation;

        $lowStockItemsCount = $catalogProducts->filter(fn ($p) => $p->qty > 0 && $p->qty <= $p->low_stock_threshold)->count();
        $outOfStockItemsCount = $catalogProducts->filter(fn ($p) => $p->qty <= 0)->count();
        $healthyStockItemsCount = $catalogProducts->filter(fn ($p) => $p->qty > $p->low_stock_threshold)->count();

        // Damaged, Returns, and Transfers in period
        $damagedTx = StockTransaction::where('type', StockTransaction::TYPE_DAMAGED)
            ->whereDate('date', '>=', $startDateStr)
            ->whereDate('date', '<=', $endDateStr)
            ->with('product')
            ->get();
        $damagedUnits = abs($damagedTx->sum('qty'));
        $damagedLossValuation = (float) $damagedTx->sum(fn ($t) => abs($t->qty) * ($t->product->cost_price ?? 0));

        $customerReturnsTx = StockTransaction::where('type', 'customer_return')
            ->whereDate('date', '>=', $startDateStr)
            ->whereDate('date', '<=', $endDateStr)
            ->with('product')
            ->get();
        $customerReturnsUnits = abs($customerReturnsTx->sum('qty'));

        $supplierReturnsTx = StockTransaction::where('type', 'supplier_return')
            ->whereDate('date', '>=', $startDateStr)
            ->whereDate('date', '<=', $endDateStr)
            ->with(['product', 'supplier'])
            ->get();
        $supplierReturnsUnits = abs($supplierReturnsTx->sum('qty'));

        $stockInTx = StockTransaction::where('type', StockTransaction::TYPE_STOCK_IN)
            ->whereDate('date', '>=', $startDateStr)
            ->whereDate('date', '<=', $endDateStr)
            ->get();
        $stockInUnits = $stockInTx->sum('qty');

        $stockOutTx = StockTransaction::where('type', StockTransaction::TYPE_STOCK_OUT)
            ->whereDate('date', '>=', $startDateStr)
            ->whereDate('date', '<=', $endDateStr)
            ->get();
        $stockOutUnits = abs($stockOutTx->sum('qty'));

        $transfersCount = StockTransfer::whereBetween('created_at', [$start, $end])->count();

        // Paginated Inventory Status Table
        $invQuery = Product::with(['category', 'supplier']);
        if ($request->filled('stock_status')) {
            $st = $request->input('stock_status');
            if ($st === 'out_of_stock') {
                $invQuery->where('qty', '<=', 0);
            } elseif ($st === 'low_stock') {
                $invQuery->where('qty', '>', 0)->whereColumn('qty', '<=', 'low_stock_threshold');
            } elseif ($st === 'in_stock') {
                $invQuery->whereColumn('qty', '>', 'low_stock_threshold');
            }
        }
        if ($request->filled('category_id')) {
            $invQuery->where('category_id', $request->input('category_id'));
        }
        if ($request->filled('inv_search')) {
            $s = trim($request->input('inv_search'));
            $invQuery->where(function ($q) use ($s) {
                $q->where('name', 'LIKE', "%{$s}%")->orWhere('barcode', 'LIKE', "%{$s}%");
            });
        }
        $inventoryLedger = $invQuery->paginate(15, ['*'], 'inv_page')->withQueryString();

        // ─────────────────────────────────────────────────────────────
        // 3. UNIFIED TRANSACTION MOVEMENTS REPORT
        // ─────────────────────────────────────────────────────────────
        $txQuery = StockTransaction::with(['product.category', 'user', 'supplier'])
            ->whereDate('date', '>=', $startDateStr)
            ->whereDate('date', '<=', $endDateStr);

        if ($request->filled('tx_type')) {
            $txQuery->where('type', $request->input('tx_type'));
        }
        if ($request->filled('tx_search')) {
            $txS = trim($request->input('tx_search'));
            $txQuery->where(function ($q) use ($txS) {
                $q->where('reference_number', 'LIKE', "%{$txS}%")
                  ->orWhere('notes', 'LIKE', "%{$txS}%")
                  ->orWhereHas('product', fn ($p) => $p->where('name', 'LIKE', "%{$txS}%"));
            });
        }

        $txCountsByType = StockTransaction::whereDate('date', '>=', $startDateStr)
            ->whereDate('date', '<=', $endDateStr)
            ->selectRaw('type, count(*) as count, sum(qty) as total_qty')
            ->groupBy('type')
            ->get()
            ->keyBy('type');

        $transactionsLedger = $txQuery->latest('date')->latest('id')->paginate(20, ['*'], 'tx_page')->withQueryString();

        // ─────────────────────────────────────────────────────────────
        // 4. EMPLOYEE ATTENDANCE ANALYTICS
        // ─────────────────────────────────────────────────────────────
        $attendances = Attendance::whereDate('date', '>=', $startDateStr)
            ->whereDate('date', '<=', $endDateStr)
            ->with(['employee.position'])
            ->get();

        $attPresent = $attendances->where('status', 'present')->count();
        $attLate = $attendances->where('status', 'late')->count();
        $attPermission = $attendances->where('status', 'permission')->count();
        $attAbsent = $attendances->where('status', 'absent')->count();
        $totalAttHours = round($attendances->sum(fn ($a) => $a->working_hours ?? 0), 2);

        $employeeAttendanceSummary = Employee::with('position')->orderBy('name')->get()->map(function ($emp) use ($attendances) {
            $records = $attendances->where('employee_id', $emp->id);
            $present = $records->where('status', 'present')->count();
            $late = $records->where('status', 'late')->count();
            $perm = $records->where('status', 'permission')->count();
            $absent = $records->where('status', 'absent')->count();
            $hours = round($records->sum(fn ($a) => $a->working_hours ?? 0), 2);
            $shifts = $present + $late;
            $rate = $shifts > 0 ? round(($present / $shifts) * 100, 1) : 0.0;

            return [
                'employee' => $emp,
                'present' => $present,
                'late' => $late,
                'permission' => $perm,
                'absent' => $absent,
                'shifts' => $shifts,
                'hours' => $hours,
                'rate' => $rate,
            ];
        });

        return view('reports.index', [
            'activeTab' => $activeTab,
            'period' => $period,
            'startDate' => $startDateStr,
            'endDate' => $endDateStr,
            'formattedPeriod' => $start->format('M j, Y') . ' — ' . $end->format('M j, Y'),

            // Financial & Sales Metrics
            'grossSales' => $grossSales,
            'totalDiscounts' => $totalDiscounts,
            'totalTax' => $totalTax,
            'totalRevenue' => $totalRevenue,
            'completedSalesCount' => $completedSalesCount,
            'refundsTotal' => $refundsTotal,
            'refundsCount' => $refundsCount,
            'netSales' => $netSales,
            'cogs' => $cogs,
            'grossProfit' => $grossProfit,
            'grossMargin' => $grossMargin,
            'avgOrderValue' => $avgOrderValue,
            'paymentSummary' => $paymentSummary,
            'cashierStats' => $cashierStats,
            'topCustomers' => $topCustomers,
            'guestSalesCount' => $guestSalesCount,
            'guestSalesTotal' => $guestSalesTotal,
            'bestSellingProducts' => $bestSellingProducts,
            'slowMovingProducts' => $slowMovingProducts,
            'salesLedger' => $salesLedger,

            // Inventory Metrics
            'inventoryCostValuation' => $inventoryCostValuation,
            'inventoryRetailValuation' => $inventoryRetailValuation,
            'totalUnitsInStock' => $totalUnitsInStock,
            'potentialInventoryProfit' => $potentialInventoryProfit,
            'lowStockItemsCount' => $lowStockItemsCount,
            'outOfStockItemsCount' => $outOfStockItemsCount,
            'healthyStockItemsCount' => $healthyStockItemsCount,
            'damagedUnits' => $damagedUnits,
            'damagedLossValuation' => $damagedLossValuation,
            'customerReturnsUnits' => $customerReturnsUnits,
            'supplierReturnsUnits' => $supplierReturnsUnits,
            'stockInUnits' => $stockInUnits,
            'stockOutUnits' => $stockOutUnits,
            'transfersCount' => $transfersCount,
            'inventoryLedger' => $inventoryLedger,
            'categories' => Category::orderBy('name')->get(),

            // Transactions Metrics
            'transactionsLedger' => $transactionsLedger,
            'txCountsByType' => $txCountsByType,

            // Attendance Metrics
            'attPresent' => $attPresent,
            'attLate' => $attLate,
            'attPermission' => $attPermission,
            'attAbsent' => $attAbsent,
            'totalAttHours' => $totalAttHours,
            'employeeAttendanceSummary' => $employeeAttendanceSummary,
        ]);
    }

    /**
     * Export reports to CSV.
     */
    public function export(Request $request): StreamedResponse
    {
        $exportType = $request->input('type', 'sales');
        [$start, $end] = $this->resolveDateRange($request);
        $startDateStr = $start->toDateString();
        $endDateStr = $end->toDateString();

        $fileName = "skincare_{$exportType}_report_{$startDateStr}_to_{$endDateStr}.csv";

        return response()->streamDownload(function () use ($exportType, $start, $end, $startDateStr, $endDateStr) {
            $handle = fopen('php://output', 'w');
            // Write UTF-8 BOM for Excel compatibility
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));

            if ($exportType === 'sales') {
                fputcsv($handle, [
                    'Invoice #',
                    'Date',
                    'Time',
                    'Customer Name',
                    'Cashier',
                    'Payment Method',
                    'Subtotal ($)',
                    'Discounts ($)',
                    'Tax ($)',
                    'Final Total ($)',
                    'Status',
                ]);

                Sale::with(['user', 'customer'])
                    ->whereBetween('created_at', [$start, $end])
                    ->latest()
                    ->chunk(100, function ($sales) use ($handle) {
                        foreach ($sales as $sale) {
                            fputcsv($handle, [
                                $sale->invoice_number,
                                $sale->created_at->format('Y-m-d'),
                                $sale->created_at->format('H:i:s'),
                                $sale->customer ? $sale->customer->name : 'Walk-in Guest',
                                $sale->user->name ?? 'System',
                                strtoupper($sale->payment_type),
                                number_format($sale->subtotal, 2, '.', ''),
                                number_format($sale->discount, 2, '.', ''),
                                number_format($sale->tax, 2, '.', ''),
                                number_format($sale->final_total, 2, '.', ''),
                                ucfirst($sale->status),
                            ]);
                        }
                    });
            } elseif ($exportType === 'inventory') {
                fputcsv($handle, [
                    'Product Name',
                    'Barcode',
                    'Category',
                    'Supplier',
                    'Stock Qty',
                    'Low Threshold',
                    'Cost Price ($)',
                    'Sale Price ($)',
                    'Valuation at Cost ($)',
                    'Valuation at Retail ($)',
                    'Status',
                ]);

                Product::with(['category', 'supplier'])
                    ->orderBy('name')
                    ->chunk(100, function ($products) use ($handle) {
                        foreach ($products as $p) {
                            $status = $p->qty <= 0 ? 'Out of Stock' : ($p->qty <= $p->low_stock_threshold ? 'Low Stock' : 'In Stock');
                            fputcsv($handle, [
                                $p->name,
                                $p->barcode,
                                $p->category->name ?? 'None',
                                $p->supplier->name ?? 'None',
                                $p->qty,
                                $p->low_stock_threshold,
                                number_format($p->cost_price, 2, '.', ''),
                                number_format($p->sale_price, 2, '.', ''),
                                number_format($p->qty * $p->cost_price, 2, '.', ''),
                                number_format($p->qty * $p->sale_price, 2, '.', ''),
                                $status,
                            ]);
                        }
                    });
            } elseif ($exportType === 'transactions') {
                fputcsv($handle, [
                    'Date',
                    'Reference #',
                    'Type',
                    'Product Name',
                    'Quantity',
                    'Operator',
                    'Supplier',
                    'Notes',
                ]);

                StockTransaction::with(['product', 'user', 'supplier'])
                    ->whereDate('date', '>=', $startDateStr)
                    ->whereDate('date', '<=', $endDateStr)
                    ->latest('date')
                    ->chunk(100, function ($txs) use ($handle) {
                        foreach ($txs as $tx) {
                            fputcsv($handle, [
                                $tx->date ? $tx->date->format('Y-m-d') : '',
                                $tx->reference_number ?? '—',
                                strtoupper($tx->type),
                                $tx->product->name ?? 'Unknown',
                                $tx->qty,
                                $tx->user->name ?? 'System',
                                $tx->supplier->name ?? '—',
                                $tx->notes ?? '',
                            ]);
                        }
                    });
            } elseif ($exportType === 'employees' || $exportType === 'attendance') {
                fputcsv($handle, [
                    'Date',
                    'Staff Name',
                    'Job Position',
                    'Check In',
                    'Check Out',
                    'Working Hours',
                    'Status',
                    'Notes',
                ]);

                Attendance::with(['employee.position'])
                    ->whereDate('date', '>=', $startDateStr)
                    ->whereDate('date', '<=', $endDateStr)
                    ->latest('date')
                    ->chunk(100, function ($atts) use ($handle) {
                        foreach ($atts as $att) {
                            fputcsv($handle, [
                                $att->date ? $att->date->format('Y-m-d') : '',
                                $att->employee->name ?? 'Staff',
                                $att->employee->position->name ?? '—',
                                $att->check_in ?? '—',
                                $att->check_out ?? '—',
                                $att->working_hours ?? '0.0',
                                ucfirst($att->status),
                                $att->notes ?? '',
                            ]);
                        }
                    });
            }

            fclose($handle);
        }, $fileName, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
        ]);
    }

    /**
     * Resolve date range boundaries and period key from request inputs.
     */
    protected function resolveDateRange(Request $request): array
    {
        $period = $request->input('period', 'month');
        $startInput = $request->input('start_date');
        $endInput = $request->input('end_date');

        if ($startInput && $endInput) {
            $period = 'custom';
            $start = Carbon::parse($startInput)->startOfDay();
            $end = Carbon::parse($endInput)->endOfDay();
        } else {
            switch ($period) {
                case 'today':
                    $start = Carbon::today()->startOfDay();
                    $end = Carbon::today()->endOfDay();
                    break;
                case 'week':
                    $start = Carbon::now()->startOfWeek();
                    $end = Carbon::now()->endOfWeek();
                    break;
                case 'year':
                    $start = Carbon::now()->startOfYear();
                    $end = Carbon::now()->endOfYear();
                    break;
                case 'all':
                    $start = Carbon::parse('2020-01-01')->startOfDay();
                    $end = Carbon::now()->endOfDay();
                    break;
                case 'month':
                default:
                    $period = 'month';
                    $start = Carbon::now()->startOfMonth();
                    $end = Carbon::now()->endOfMonth();
                    break;
            }
        }

        return [$start, $end, $period];
    }
}
