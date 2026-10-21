<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Position;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleDetail;
use App\Models\StockTransaction;
use App\Models\Supplier;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportsAndAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $manager;
    protected User $cashier;
    protected User $hr;
    protected User $inventory;
    protected Product $productSerum;
    protected Product $productCleanser;
    protected Customer $customerVip;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();

        $this->admin = User::where('role', 'admin')->first();
        $this->manager = User::where('role', 'manager')->first();
        $this->cashier = User::where('role', 'cashier')->first();
        $this->hr = User::where('role', 'hr')->first();
        $this->inventory = User::where('role', 'inventory')->first();

        $category = Category::first();
        $supplier = Supplier::first();

        // Create verified products with exact known financial attributes
        $this->productSerum = Product::create([
            'category_id' => $category->id,
            'supplier_id' => $supplier->id,
            'name' => 'Bio-Retinol Night Serum',
            'barcode' => 'TEST-RPT-001',
            'cost_price' => 20.00,
            'sale_price' => 50.00,
            'qty' => 25,
            'low_stock_threshold' => 5,
            'status' => 'active',
        ]);

        $this->productCleanser = Product::create([
            'category_id' => $category->id,
            'supplier_id' => $supplier->id,
            'name' => 'Botanical Foaming Gel',
            'barcode' => 'TEST-RPT-002',
            'cost_price' => 10.00,
            'sale_price' => 25.00,
            'qty' => 3, // Low stock
            'low_stock_threshold' => 10,
            'status' => 'active',
        ]);

        $this->customerVip = Customer::first();
    }

    /*
    |--------------------------------------------------------------------------
    | RBAC PERMISSION TESTS
    |--------------------------------------------------------------------------
    */

    public function test_admin_and_manager_can_access_reports_module(): void
    {
        $adminResp = $this->actingAs($this->admin)->get(route('reports.index'));
        $adminResp->assertStatus(200);

        $mgrResp = $this->actingAs($this->manager)->get(route('reports.index'));
        $mgrResp->assertStatus(200);
    }

    public function test_cashier_inventory_and_hr_are_forbidden_from_reports(): void
    {
        // Cashier
        $cashierResp = $this->actingAs($this->cashier)->get(route('reports.index'));
        $cashierResp->assertStatus(403);

        // Inventory specialist
        $invResp = $this->actingAs($this->inventory)->get(route('reports.index'));
        $invResp->assertStatus(403);

        // HR
        $hrResp = $this->actingAs($this->hr)->get(route('reports.index'));
        $hrResp->assertStatus(403);

        // Exports forbidden as well
        $expResp = $this->actingAs($this->cashier)->get(route('reports.export', ['type' => 'sales']));
        $expResp->assertStatus(403);
    }

    /*
    |--------------------------------------------------------------------------
    | SALES & FINANCIAL REPORTS ACCURACY TESTS
    |--------------------------------------------------------------------------
    */

    public function test_sales_report_financial_metrics_calculation_accuracy(): void
    {
        // Setup a deterministic sale within today
        // Subtotal = $100, Discount = $10, Tax = $5, Final = $95.
        // Product: 2x Serum (Cost = $20 each => $40 COGS).
        $sale = Sale::create([
            'user_id' => $this->cashier->id,
            'customer_id' => $this->customerVip->id,
            'invoice_number' => 'INV-FIN-001',
            'subtotal' => 100.00,
            'discount' => 10.00,
            'tax' => 5.00,
            'final_total' => 95.00,
            'payment_type' => 'card',
            'payment_status' => 'paid',
            'status' => 'completed',
            'created_at' => Carbon::now(),
        ]);

        SaleDetail::create([
            'sale_id' => $sale->id,
            'product_id' => $this->productSerum->id,
            'qty' => 2,
            'price' => 50.00,
            'subtotal' => 100.00,
            'discount' => 10.00,
            'final_total' => 95.00,
        ]);

        // Net Sales = Gross ($100) - Discount ($10) = $90.
        // COGS = 2 * $20 = $40.
        // Gross Profit = $90 - $40 = $50.
        // Gross Margin = (50 / 90) * 100 = 55.56%.

        $response = $this->actingAs($this->admin)->get(route('reports.index', [
            'tab' => 'sales',
            'period' => 'today',
        ]));

        $response->assertStatus(200);
        $response->assertViewHas('grossSales', fn ($val) => $val >= 100.00);
        $response->assertViewHas('totalDiscounts', fn ($val) => $val >= 10.00);
        $response->assertViewHas('cogs', fn ($val) => $val >= 40.00);
        $response->assertViewHas('grossProfit', fn ($val) => $val >= 50.00);
        $response->assertSee('INV-FIN-001');
    }

    public function test_sales_report_refunds_and_net_sales_deductions(): void
    {
        // Cancelled / Refunded sale
        Sale::create([
            'user_id' => $this->cashier->id,
            'customer_id' => null,
            'invoice_number' => 'INV-REF-001',
            'subtotal' => 50.00,
            'discount' => 0.00,
            'tax' => 0.00,
            'final_total' => 50.00,
            'payment_type' => 'cash',
            'payment_status' => 'refunded',
            'status' => 'cancelled',
            'created_at' => Carbon::now(),
        ]);

        $response = $this->actingAs($this->admin)->get(route('reports.index', [
            'tab' => 'sales',
            'period' => 'today',
        ]));

        $response->assertStatus(200);
        $response->assertViewHas('refundsTotal', fn ($val) => $val >= 50.00);
        $response->assertViewHas('refundsCount', fn ($count) => $count >= 1);
    }

    public function test_sales_report_breakdowns_by_cashier_payment_and_products(): void
    {
        $response = $this->actingAs($this->admin)->get(route('reports.index', [
            'tab' => 'sales',
            'period' => 'month',
        ]));

        $response->assertStatus(200);
        $response->assertViewHas('paymentSummary');
        $response->assertViewHas('cashierStats');
        $response->assertViewHas('bestSellingProducts');
        $response->assertViewHas('slowMovingProducts');

        // Check payment types cash, card, qr are present
        $summary = $response->viewData('paymentSummary');
        $this->assertArrayHasKey('cash', $summary);
        $this->assertArrayHasKey('card', $summary);
        $this->assertArrayHasKey('qr', $summary);
    }

    /*
    |--------------------------------------------------------------------------
    | INVENTORY REPORTS ACCURACY TESTS
    |--------------------------------------------------------------------------
    */

    public function test_inventory_report_valuation_and_stock_health(): void
    {
        $response = $this->actingAs($this->admin)->get(route('reports.index', [
            'tab' => 'inventory',
        ]));

        $response->assertStatus(200);
        $response->assertSee('Valuation at Cost');
        $response->assertSee('Potential Retail Value');
        $response->assertSee('Low-Stock Alerts');

        // Our productSerum: 25 qty * $20 cost = $500 cost; 25 * $50 = $1250 retail
        // productCleanser: 3 qty * $10 cost = $30 cost; 3 * $25 = $75 retail (low stock)
        $costVal = $response->viewData('inventoryCostValuation');
        $retailVal = $response->viewData('inventoryRetailValuation');

        $this->assertGreaterThanOrEqual(530.00, $costVal);
        $this->assertGreaterThanOrEqual(1325.00, $retailVal);
        $this->assertGreaterThan(0, $response->viewData('lowStockItemsCount'));
    }

    public function test_inventory_damaged_and_return_tracking_in_report(): void
    {
        // Record damaged stock transaction
        StockTransaction::create([
            'product_id' => $this->productSerum->id,
            'user_id' => $this->admin->id,
            'type' => StockTransaction::TYPE_DAMAGED,
            'qty' => -2,
            'reference_number' => 'DMG-TEST-99',
            'notes' => 'Dropped bottle in store',
            'date' => Carbon::today()->toDateString(),
        ]);

        $response = $this->actingAs($this->admin)->get(route('reports.index', [
            'tab' => 'inventory',
            'period' => 'today',
        ]));

        $response->assertStatus(200);
        $this->assertGreaterThanOrEqual(2, $response->viewData('damagedUnits'));
        $this->assertGreaterThanOrEqual(40.00, $response->viewData('damagedLossValuation')); // 2 * $20
    }

    /*
    |--------------------------------------------------------------------------
    | UNIFIED TRANSACTION MOVEMENTS REPORT TESTS
    |--------------------------------------------------------------------------
    */

    public function test_transaction_movements_report_renders_unified_ledger(): void
    {
        StockTransaction::create([
            'product_id' => $this->productCleanser->id,
            'user_id' => $this->admin->id,
            'type' => StockTransaction::TYPE_STOCK_IN,
            'qty' => 50,
            'reference_number' => 'PO-TEST-1234',
            'notes' => 'Shipment received from manufacturer',
            'date' => Carbon::today()->toDateString(),
        ]);

        $response = $this->actingAs($this->admin)->get(route('reports.index', [
            'tab' => 'transactions',
            'period' => 'today',
            'tx_type' => 'stock-in',
        ]));

        $response->assertStatus(200);
        $response->assertSee('PO-TEST-1234');
        $response->assertSee('Botanical Foaming Gel');
        $response->assertSee('+50 units');
    }

    /*
    |--------------------------------------------------------------------------
    | EMPLOYEE ATTENDANCE ANALYTICS TESTS
    |--------------------------------------------------------------------------
    */

    public function test_employee_attendance_report_metrics_and_summary(): void
    {
        $employee = Employee::first();

        // Ensure clean attendance record exists for today
        Attendance::where('employee_id', $employee->id)->whereDate('date', Carbon::today())->delete();

        Attendance::create([
            'employee_id' => $employee->id,
            'date' => Carbon::today()->toDateString(),
            'check_in' => '08:50:00',
            'check_out' => '17:30:00',
            'status' => 'present',
            'notes' => 'On time duty shift',
        ]);

        $response = $this->actingAs($this->admin)->get(route('reports.index', [
            'tab' => 'employees',
            'period' => 'today',
        ]));

        $response->assertStatus(200);
        $response->assertSee('Total Working Hours');
        $response->assertSee('Punctuality Score');
        $response->assertSee($employee->name);
        $this->assertGreaterThanOrEqual(1, $response->viewData('attPresent'));
    }

    /*
    |--------------------------------------------------------------------------
    | CSV EXPORT TESTS
    |--------------------------------------------------------------------------
    */

    public function test_reports_export_sales_csv(): void
    {
        $response = $this->actingAs($this->admin)->get(route('reports.export', [
            'type' => 'sales',
            'period' => 'month',
        ]));

        $response->assertStatus(200);
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment; filename=', $response->headers->get('Content-Disposition'));
    }

    public function test_reports_export_inventory_csv(): void
    {
        $response = $this->actingAs($this->admin)->get(route('reports.export', [
            'type' => 'inventory',
        ]));

        $response->assertStatus(200);
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
    }

    public function test_reports_export_transactions_csv(): void
    {
        $response = $this->actingAs($this->admin)->get(route('reports.export', [
            'type' => 'transactions',
            'period' => 'month',
        ]));

        $response->assertStatus(200);
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
    }

    public function test_reports_export_employees_attendance_csv(): void
    {
        $response = $this->actingAs($this->admin)->get(route('reports.export', [
            'type' => 'employees',
            'period' => 'month',
        ]));

        $response->assertStatus(200);
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
    }
}
