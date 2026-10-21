<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleDetail;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Display the polished, role-aware skincare management dashboard.
     */
    public function index(Request $request): View
    {
        $user = Auth::user();
        $isCashier = $user->role === 'cashier';
        $isAdmin = $user->isAdmin();
        $isManager = $user->isManager();

        $today = Carbon::today();
        $yesterday = Carbon::yesterday();

        // ── 1. Date Range & Period Filtering ──
        $period = $request->input('period', '7days');
        $customStart = $request->input('start_date');
        $customEnd = $request->input('end_date');

        switch ($period) {
            case 'today':
                $startDate = $today->copy()->startOfDay();
                $endDate = $today->copy()->endOfDay();
                $periodLabel = 'Today (' . $today->format('M j, Y') . ')';
                break;

            case 'yesterday':
                $startDate = $yesterday->copy()->startOfDay();
                $endDate = $yesterday->copy()->endOfDay();
                $periodLabel = 'Yesterday (' . $yesterday->format('M j, Y') . ')';
                break;

            case 'this_month':
                $startDate = Carbon::now()->startOfMonth();
                $endDate = Carbon::now()->endOfMonth();
                $periodLabel = 'This Month (' . Carbon::now()->format('F Y') . ')';
                break;

            case 'last_month':
                $startDate = Carbon::now()->subMonth()->startOfMonth();
                $endDate = Carbon::now()->subMonth()->endOfMonth();
                $periodLabel = 'Last Month (' . Carbon::now()->subMonth()->format('F Y') . ')';
                break;

            case 'custom':
                $startDate = $customStart ? Carbon::parse($customStart)->startOfDay() : Carbon::now()->subDays(29)->startOfDay();
                $endDate = $customEnd ? Carbon::parse($customEnd)->endOfDay() : Carbon::now()->endOfDay();
                if ($startDate->gt($endDate)) {
                    $tmp = $startDate;
                    $startDate = $endDate->copy()->startOfDay();
                    $endDate = $tmp->copy()->endOfDay();
                }
                $periodLabel = 'Custom (' . $startDate->format('M j, Y') . ' - ' . $endDate->format('M j, Y') . ')';
                break;

            case '7days':
            default:
                $period = '7days';
                $startDate = Carbon::now()->subDays(6)->startOfDay();
                $endDate = Carbon::now()->endOfDay();
                $periodLabel = 'Last 7 Days (' . $startDate->format('M j') . ' - ' . $endDate->format('M j') . ')';
                break;
        }

        // Base completed sales query scoped by role (cashier sees personal shift/sales, manager/admin store-wide)
        $completedBase = Sale::where('status', 'completed');
        if ($isCashier) {
            $completedBase->where('user_id', $user->id);
        }

        // ── 2. KPI 1: Today's Sales & Trend vs Yesterday ──
        $todayQuery = (clone $completedBase)->whereDate('created_at', $today);
        $todaySalesTotal = (float) $todayQuery->sum('final_total');
        $todaySalesCount = (int) $todayQuery->count();

        $yesterdayQuery = (clone $completedBase)->whereDate('created_at', $yesterday);
        $yesterdaySalesTotal = (float) $yesterdayQuery->sum('final_total');

        $todayTrend = null;
        $todayTrendUp = true;
        if ($yesterdaySalesTotal > 0) {
            $diff = $todaySalesTotal - $yesterdaySalesTotal;
            $percent = round(($diff / $yesterdaySalesTotal) * 100, 1);
            $todayTrend = ($percent >= 0 ? '+' : '') . $percent . '% vs yesterday';
            $todayTrendUp = $percent >= 0;
        } elseif ($todaySalesTotal > 0 && $yesterdaySalesTotal == 0) {
            $todayTrend = '+$' . number_format($todaySalesTotal, 2) . ' vs yesterday';
            $todayTrendUp = true;
        }

        // ── 3. KPI 2: Monthly Revenue & Trend vs Previous Month ──
        $thisMonthStart = Carbon::now()->startOfMonth();
        $thisMonthEnd = Carbon::now()->endOfMonth();
        $lastMonthStart = Carbon::now()->subMonth()->startOfMonth();
        $lastMonthEnd = Carbon::now()->subMonth()->endOfMonth();

        $monthlyRevenue = (float) (clone $completedBase)
            ->whereBetween('created_at', [$thisMonthStart, $thisMonthEnd])
            ->sum('final_total');

        $lastMonthRevenue = (float) (clone $completedBase)
            ->whereBetween('created_at', [$lastMonthStart, $lastMonthEnd])
            ->sum('final_total');

        $monthlyTrend = null;
        $monthlyTrendUp = true;
        if ($lastMonthRevenue > 0) {
            $diffMonth = $monthlyRevenue - $lastMonthRevenue;
            $percentMonth = round(($diffMonth / $lastMonthRevenue) * 100, 1);
            $monthlyTrend = ($percentMonth >= 0 ? '+' : '') . $percentMonth . '% vs last month';
            $monthlyTrendUp = $percentMonth >= 0;
        } elseif ($monthlyRevenue > 0 && $lastMonthRevenue == 0) {
            $monthlyTrend = 'Initial active cycle';
            $monthlyTrendUp = true;
        }

        // ── 4. KPI 3: Total Products ──
        $totalProducts = Product::count();
        $activeProductsCount = Product::where('status', 'active')->count();
        $activeCategoriesCount = Category::where('status', 'active')->count();

        // ── 5. KPI 4: Low Stock Items ──
        $lowStockCount = Product::lowStock()->count();
        $outOfStockCount = Product::where('qty', '<=', 0)->count();

        // ── 6. KPI 5: Total Customers ──
        $totalCustomers = Customer::count();
        $vipCustomersCount = Customer::whereIn('membership_level', ['Platinum', 'Gold', 'Silver'])->count();

        // ── 7. KPI 6: Total Transactions in Selected Period ──
        $periodSalesQuery = (clone $completedBase)->whereBetween('created_at', [$startDate, $endDate]);
        $periodTransactionsCount = (int) $periodSalesQuery->count();
        $periodRevenue = (float) $periodSalesQuery->sum('final_total');
        $averageOrderValue = $periodTransactionsCount > 0 ? round($periodRevenue / $periodTransactionsCount, 2) : 0.00;

        // ── 8. Chart Series (Daily, Weekly, Monthly) ──
        // A. Daily Series for Selected Range
        $dailyRecords = (clone $completedBase)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('DATE(created_at) as sale_date, SUM(final_total) as total')
            ->groupBy('sale_date')
            ->pluck('total', 'sale_date');

        $dailyChartLabels = [];
        $dailyChartData = [];
        $cursor = $startDate->copy();
        while ($cursor->lte($endDate)) {
            $key = $cursor->format('Y-m-d');
            $dailyChartLabels[] = $cursor->format('M j');
            $dailyChartData[] = (float) ($dailyRecords[$key] ?? 0.00);
            $cursor->addDay();
        }

        // B. Weekly Trend (Past 8 Weeks)
        $weeklyChartLabels = [];
        $weeklyChartData = [];
        for ($w = 7; $w >= 0; $w--) {
            $wStart = Carbon::now()->subWeeks($w)->startOfWeek();
            $wEnd = Carbon::now()->subWeeks($w)->endOfWeek();
            $wTotal = (float) (clone $completedBase)
                ->whereBetween('created_at', [$wStart, $wEnd])
                ->sum('final_total');
            $weeklyChartLabels[] = $wStart->format('M j') . ' - ' . $wEnd->format('M j');
            $weeklyChartData[] = round($wTotal, 2);
        }

        // C. Monthly Trend (Past 6 Months)
        $monthlyChartLabels = [];
        $monthlyChartData = [];
        for ($m = 5; $m >= 0; $m--) {
            $mDate = Carbon::now()->subMonths($m);
            $mStart = $mDate->copy()->startOfMonth();
            $mEnd = $mDate->copy()->endOfMonth();
            $mTotal = (float) (clone $completedBase)
                ->whereBetween('created_at', [$mStart, $mEnd])
                ->sum('final_total');
            $monthlyChartLabels[] = $mDate->format('M Y');
            $monthlyChartData[] = round($mTotal, 2);
        }

        // ── 9. Sales by Payment Method (Doughnut Chart) ──
        $paymentRows = (clone $completedBase)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('payment_type, SUM(final_total) as total, COUNT(*) as count')
            ->groupBy('payment_type')
            ->get();

        $paymentMap = [
            'cash' => ['label' => 'Cash', 'total' => 0.00, 'count' => 0, 'color' => '#71856B'],
            'card' => ['label' => 'Card', 'total' => 0.00, 'count' => 0, 'color' => '#557288'],
            'qr'   => ['label' => 'QR / QRIS', 'total' => 0.00, 'count' => 0, 'color' => '#D4973B'],
        ];

        $paymentTotalSum = 0.00;
        foreach ($paymentRows as $row) {
            $type = strtolower($row->payment_type);
            if (in_array($type, ['qris', 'qr'], true)) {
                $canonical = 'qr';
            } elseif (in_array($type, ['card', 'credit_card', 'debit_card'], true)) {
                $canonical = 'card';
            } elseif ($type === 'cash') {
                $canonical = 'cash';
            } else {
                $canonical = $type;
                if (!isset($paymentMap[$canonical])) {
                    $paymentMap[$canonical] = [
                        'label' => ucfirst($type),
                        'total' => 0.00,
                        'count' => 0,
                        'color' => '#8C7355',
                    ];
                }
            }

            $amount = (float) $row->total;
            $paymentMap[$canonical]['total'] += $amount;
            $paymentMap[$canonical]['count'] += (int) $row->count;
            $paymentTotalSum += $amount;
        }

        // Compute percentage shares
        foreach ($paymentMap as $k => $val) {
            $paymentMap[$k]['percentage'] = $paymentTotalSum > 0 
                ? round(($val['total'] / $paymentTotalSum) * 100, 1) 
                : 0.0;
        }

        // ── 10. Best-Selling Products (Top 5) ──
        $bestSellers = SaleDetail::select(
                'product_id',
                DB::raw('SUM(qty) as total_units_sold'),
                DB::raw('SUM(subtotal) as total_revenue')
            )
            ->whereHas('sale', function ($query) use ($isCashier, $user) {
                $query->where('status', 'completed');
                if ($isCashier) {
                    $query->where('user_id', $user->id);
                }
            })
            ->groupBy('product_id')
            ->orderByDesc('total_units_sold')
            ->take(5)
            ->with(['product.category'])
            ->get();

        // ── 11. Recent Transactions ──
        $recentSales = Sale::where('status', 'completed')
            ->with(['user', 'customer'])
            ->when($isCashier, fn($q) => $q->where('user_id', $user->id))
            ->latest()
            ->take(8)
            ->get();

        // ── 12. Low Stock Alerts ──
        $lowStockProducts = Product::with(['category', 'supplier'])
            ->lowStock()
            ->orderBy('qty')
            ->take(5)
            ->get();

        // ── 13. Inventory Summary ──
        $totalUnitsInStock = (int) Product::sum('qty');
        $inStockHealthyCount = Product::where('qty', '>', DB::raw('low_stock_threshold'))->count();
        $lowStockWarningCount = Product::where('qty', '<=', DB::raw('low_stock_threshold'))->where('qty', '>', 0)->count();
        $criticallyOutOfStockCount = Product::where('qty', '<=', 0)->count();

        // Wholesale cost and potential profit restricted to Manager/Admin
        $inventoryCostValuation = ($isManager || $isAdmin)
            ? (float) (Product::selectRaw('SUM(qty * cost_price) as total_cost')->value('total_cost') ?? 0.00)
            : null;

        $inventoryRetailValuation = (float) (Product::selectRaw('SUM(qty * sale_price) as total_retail')->value('total_retail') ?? 0.00);

        $inventoryPotentialProfit = ($isManager || $isAdmin && $inventoryCostValuation !== null)
            ? max(0.00, $inventoryRetailValuation - $inventoryCostValuation)
            : null;

        return view('dashboard', [
            'user' => $user,
            'isCashier' => $isCashier,
            'isAdmin' => $isAdmin,
            'isManager' => $isManager,
            'period' => $period,
            'periodLabel' => $periodLabel,
            'startDate' => $startDate->toDateString(),
            'endDate' => $endDate->toDateString(),

            // KPI Cards
            'todaySalesTotal' => $todaySalesTotal,
            'todaySalesCount' => $todaySalesCount,
            'todayTrend' => $todayTrend,
            'todayTrendUp' => $todayTrendUp,

            'monthlyRevenue' => $monthlyRevenue,
            'monthlyTrend' => $monthlyTrend,
            'monthlyTrendUp' => $monthlyTrendUp,

            'totalProducts' => $totalProducts,
            'activeProductsCount' => $activeProductsCount,
            'activeCategoriesCount' => $activeCategoriesCount,

            'lowStockCount' => $lowStockCount,
            'outOfStockCount' => $outOfStockCount,

            'totalCustomers' => $totalCustomers,
            'vipCustomersCount' => $vipCustomersCount,

            'periodTransactionsCount' => $periodTransactionsCount,
            'periodRevenue' => $periodRevenue,
            'averageOrderValue' => $averageOrderValue,

            // Charts
            'chartData' => [
                'daily' => [
                    'labels' => $dailyChartLabels,
                    'data' => $dailyChartData,
                ],
                'weekly' => [
                    'labels' => $weeklyChartLabels,
                    'data' => $weeklyChartData,
                ],
                'monthly' => [
                    'labels' => $monthlyChartLabels,
                    'data' => $monthlyChartData,
                ],
            ],

            'paymentMap' => $paymentMap,
            'paymentTotalSum' => $paymentTotalSum,

            // Analytics Lists & Tables
            'bestSellers' => $bestSellers,
            'recentSales' => $recentSales,
            'lowStockProducts' => $lowStockProducts,

            // Inventory Summary
            'totalUnitsInStock' => $totalUnitsInStock,
            'inventoryCostValuation' => $inventoryCostValuation,
            'inventoryRetailValuation' => $inventoryRetailValuation,
            'inventoryPotentialProfit' => $inventoryPotentialProfit,
            'inStockHealthyCount' => $inStockHealthyCount,
            'lowStockWarningCount' => $lowStockWarningCount,
            'criticallyOutOfStockCount' => $criticallyOutOfStockCount,
        ]);
    }
}
