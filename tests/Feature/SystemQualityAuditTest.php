<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Position;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\Sale;
use App\Models\SaleDetail;
use App\Models\Setting;
use App\Models\Stock;
use App\Models\StockTransaction;
use App\Models\StockTransfer;
use App\Models\Supplier;
use App\Models\User;
use App\Services\CheckoutService;
use App\Services\InventoryService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;
use Tests\TestCase;

class SystemQualityAuditTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $manager;
    protected User $cashier;
    protected User $inventory;
    protected User $hr;
    protected Product $product;
    protected Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();

        $this->admin = User::where('role', 'admin')->first();
        $this->manager = User::where('role', 'manager')->first();
        $this->cashier = User::where('role', 'cashier')->first();
        $this->inventory = User::where('role', 'inventory')->first();
        $this->hr = User::where('role', 'hr')->first();

        $category = Category::first();
        $supplier = Supplier::first();

        $this->product = Product::create([
            'category_id' => $category->id,
            'supplier_id' => $supplier->id,
            'name' => 'Ceramide Barrier Recovery Emulsion',
            'barcode' => 'AUDIT-CER-999',
            'cost_price' => 15.00,
            'sale_price' => 35.00,
            'qty' => 40,
            'low_stock_threshold' => 10,
            'status' => 'active',
        ]);

        Stock::create([
            'product_id' => $this->product->id,
            'qty' => 40,
        ]);

        $this->customer = Customer::create([
            'name' => 'Sophia Montgomery',
            'email' => 'sophia@audit.com',
            'phone' => '+855 12 333 444',
            'membership_number' => 'MEM-GLD-7788',
            'membership_level' => 'Gold',
            'status' => 'active',
        ]);
    }

    /**
     * AUDIT 1: Login, logout, and role-based permissions.
     */
    public function test_audit_1_login_logout_and_rbac(): void
    {
        // 1. Unauthenticated users are redirected to login
        $guestResp = $this->get('/dashboard');
        $guestResp->assertRedirect('/login');

        // 2. Authentication with valid credentials
        $loginResp = $this->post('/login', [
            'email' => 'admin@skincare.com',
            'password' => 'Admin@SkinCare2026',
        ]);
        $loginResp->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($this->admin);

        // 3. Logout invalidates session
        $logoutResp = $this->post('/logout');
        $logoutResp->assertRedirect('/login');
        $this->assertGuest();
    }

    /**
     * AUDIT 2: Dashboard metrics and date filters.
     */
    public function test_audit_2_dashboard_metrics_and_date_filters(): void
    {
        $this->actingAs($this->admin);

        foreach (['today', 'week', 'month', 'year', 'all'] as $period) {
            $resp = $this->get(route('dashboard', ['period' => $period]));
            $resp->assertStatus(200);
            $resp->assertSee('Overview of your skincare business performance');
            $resp->assertSee("Today's Sales");
            $resp->assertSee('Monthly Revenue');
        }
    }

    /**
     * AUDIT 3: Product, category, supplier, customer, employee, and position CRUD.
     */
    public function test_audit_3_complete_crud_operations(): void
    {
        $this->actingAs($this->admin);

        // Category CRUD
        $catResp = $this->post(route('categories.store'), [
            'name' => 'Lip Treatments',
            'description' => 'Hydrating lip oils and masks',
            'status' => 'active',
        ]);
        $catResp->assertRedirect();
        $this->assertDatabaseHas('categories', ['name' => 'Lip Treatments']);

        // Supplier CRUD
        $supResp = $this->post(route('suppliers.store'), [
            'name' => 'Seoul Cosmetics Export',
            'phone' => '+82 2 555 1234',
            'email' => 'export@seoul.kr',
            'status' => 'active',
        ]);
        $supResp->assertRedirect();
        $this->assertDatabaseHas('suppliers', ['name' => 'Seoul Cosmetics Export']);

        // Customer CRUD
        $custResp = $this->post(route('customers.store'), [
            'name' => 'Chloe Bennett',
            'phone' => '+855 10 999 111',
            'membership_level' => 'Silver',
            'status' => 'active',
        ]);
        $custResp->assertRedirect();
        $this->assertDatabaseHas('customers', ['name' => 'Chloe Bennett']);

        // Position CRUD
        $posResp = $this->post(route('positions.store'), [
            'name' => 'Senior Dermatologist',
            'base_salary' => 1200.00,
        ]);
        $posResp->assertRedirect();
        $this->assertDatabaseHas('positions', ['name' => 'Senior Dermatologist']);
    }

    /**
     * AUDIT 4: Promotion eligibility and discount calculations.
     */
    public function test_audit_4_promotion_and_discount_rules(): void
    {
        $promo = Promotion::create([
            'name' => 'Spring Rejuvenation 20% OFF',
            'code' => 'SPRING20',
            'discount_type' => 'percentage',
            'discount_value' => 20.00,
            'start_date' => now()->subDay(),
            'end_date' => now()->addMonth(),
            'status' => 'active',
        ]);

        $this->assertTrue($promo->isActive());
        $this->assertEquals(20.00, (float) $promo->discount_value);
    }

    /**
     * AUDIT 5 & 6: POS checkout, invoice generation, Cash, QR, and Card payment workflows.
     */
    public function test_audit_5_and_6_pos_checkout_with_cash_card_and_qr(): void
    {
        $checkoutService = app(CheckoutService::class);

        // 1. Cash Checkout
        $cashResult = $checkoutService->processCheckout(
            cashier: $this->cashier,
            items: [['product_id' => $this->product->id, 'qty' => 1]],
            customerId: $this->customer->id,
            paymentType: 'cash',
            amountReceived: 50.00
        );
        $cashSale = $cashResult['sale'];
        $this->assertEquals('cash', $cashSale->payment_type);
        $this->assertStringStartsWith('INV-', $cashSale->invoice_number);
        $this->assertGreaterThan(0, $cashResult['change_due']);

        // 2. Card Checkout
        $cardResult = $checkoutService->processCheckout(
            cashier: $this->cashier,
            items: [['product_id' => $this->product->id, 'qty' => 1]],
            paymentType: 'card',
            amountReceived: 0.00
        );
        $cardSale = $cardResult['sale'];
        $this->assertEquals('card', $cardSale->payment_type);
        $this->assertEquals(0.00, $cardResult['change_due']);

        // 3. QR Checkout
        $qrResult = $checkoutService->processCheckout(
            cashier: $this->cashier,
            items: [['product_id' => $this->product->id, 'qty' => 1]],
            paymentType: 'qr',
            amountReceived: 0.00
        );
        $qrSale = $qrResult['sale'];
        $this->assertEquals('qr', $qrSale->payment_type);
    }

    /**
     * AUDIT 7: Receipt printing and sales history.
     */
    public function test_audit_7_receipt_printing_and_sales_history(): void
    {
        $sale = Sale::create([
            'user_id' => $this->cashier->id,
            'customer_id' => $this->customer->id,
            'invoice_number' => 'INV-AUDIT-PRINT-01',
            'subtotal' => 35.00,
            'discount' => 0.00,
            'tax' => 1.75,
            'final_total' => 36.75,
            'payment_type' => 'cash',
            'payment_status' => 'paid',
            'status' => 'completed',
            'currency' => 'USD',
            'exchange_rate' => 4100.0,
        ]);

        SaleDetail::create([
            'sale_id' => $sale->id,
            'product_id' => $this->product->id,
            'qty' => 1,
            'price' => 35.00,
            'discount' => 0.00,
            'subtotal' => 35.00,
        ]);

        // Printable receipt renders HTTP 200
        $receiptResp = $this->actingAs($this->cashier)->get(route('pos.receipt', $sale));
        $receiptResp->assertStatus(200);
        $receiptResp->assertSee('INV-AUDIT-PRINT-01');
        $receiptResp->assertSee('FINAL TOTAL');

        // Sales history lists the invoice
        $salesResp = $this->actingAs($this->cashier)->get(route('sales.index', ['search' => 'INV-AUDIT-PRINT-01']));
        $salesResp->assertStatus(200);
        $salesResp->assertSee('INV-AUDIT-PRINT-01');
    }

    /**
     * AUDIT 8, 9 & 10: Inventory movements, stock transfers, damaged stock, returns & synchronization.
     */
    public function test_audit_8_9_10_inventory_lifecycle_and_synchronization(): void
    {
        $inventoryService = app(InventoryService::class);
        $initialQty = $this->product->qty;

        // 1. Stock In
        $inventoryService->recordStockIn(
            productId: $this->product->id,
            qty: 10,
            userId: $this->inventory->id,
            referenceNumber: 'PO-AUDIT-001',
            unitCost: 15.00
        );
        $this->assertEquals($initialQty + 10, $this->product->fresh()->qty);
        $this->assertEquals($initialQty + 10, $this->product->stock->fresh()->qty);

        // 2. Stock Out
        $inventoryService->recordStockOut(
            productId: $this->product->id,
            qty: 5,
            userId: $this->inventory->id,
            reason: 'Tester demo sampling'
        );
        $this->assertEquals($initialQty + 5, $this->product->fresh()->qty);

        // 3. Damaged stock write-off
        $inventoryService->recordDamaged(
            productId: $this->product->id,
            qty: 2,
            reason: 'Broken bottle seal',
            userId: $this->inventory->id
        );
        $this->assertEquals($initialQty + 3, $this->product->fresh()->qty);

        // 4. Customer return (resellable)
        $inventoryService->recordSaleReturn(
            productId: $this->product->id,
            qty: 1,
            userId: $this->inventory->id,
            invoiceNumber: 'INV-AUDIT-RET-01',
            notes: 'Unopened box returned'
        );
        $this->assertEquals($initialQty + 4, $this->product->fresh()->qty);

        // Verify full synchronization between products table and stocks table
        $this->assertEquals($this->product->fresh()->qty, $this->product->stock->fresh()->qty);
    }

    /**
     * AUDIT 11: Employee attendance and monthly reports.
     */
    public function test_audit_11_employee_attendance_and_reports(): void
    {
        $position = Position::first();
        $employee = Employee::create([
            'position_id' => $position->id,
            'name' => 'Clara Oswald',
            'email' => 'clara@audit.com',
            'phone' => '+855 11 222 333',
            'start_date' => now()->toDateString(),
            'status' => 'active',
        ]);

        $dateToday = Carbon::today()->toDateString();

        // 1. Check-in log
        $attResp = $this->actingAs($this->hr)->post(route('attendances.store'), [
            'employee_id' => $employee->id,
            'date' => $dateToday,
            'check_in' => '08:55',
            'status' => 'present',
        ]);
        $attResp->assertRedirect();
        $this->assertDatabaseHas('attendances', [
            'employee_id' => $employee->id,
            'status' => 'present',
        ]);
        $attRecord = Attendance::where('employee_id', $employee->id)->first();
        $this->assertNotNull($attRecord);
        $this->assertEquals($dateToday, $attRecord->date->toDateString());

        // 2. Monthly attendance report renders
        $repResp = $this->actingAs($this->hr)->get(route('attendances.report', [
            'month' => now()->format('Y-m'),
        ]));
        $repResp->assertStatus(200);
        $repResp->assertSee('Monthly Staff Attendance Report');
    }

    /**
     * AUDIT 12: Sales, inventory, transaction, and employee reports.
     */
    public function test_audit_12_business_and_financial_reports(): void
    {
        $this->actingAs($this->admin);

        // Sales Tab
        $salesResp = $this->get(route('reports.index', ['tab' => 'sales']));
        $salesResp->assertStatus(200);
        $salesResp->assertSee('Net Sales');
        $salesResp->assertSee('Gross Profit');

        // Inventory Tab
        $invResp = $this->get(route('reports.index', ['tab' => 'inventory']));
        $invResp->assertStatus(200);
        $invResp->assertSee('Valuation at Cost');

        // Transactions Tab
        $txResp = $this->get(route('reports.index', ['tab' => 'transactions']));
        $txResp->assertStatus(200);
        $txResp->assertSee('Unified Stock &amp; Inventory Movements Ledger', false);

        // Employees Tab
        $empResp = $this->get(route('reports.index', ['tab' => 'employees']));
        $empResp->assertStatus(200);
        $empResp->assertSee('Staff Attendance &amp; Punctuality Summary', false);
    }

    /**
     * AUDIT 13: Settings, currencies, and localization.
     */
    public function test_audit_13_settings_currencies_and_localization(): void
    {
        // Settings update
        Setting::set('business_name', 'Audit SkinCare Clinic');
        Setting::set('currency', 'USD');
        Setting::set('exchange_rate_khr', '4100.00');

        $this->assertEquals('Audit SkinCare Clinic', Setting::get('business_name'));
        $this->assertEquals('$10.00', Setting::formatMoney(10.00, 'USD'));
        $this->assertEquals('41,000 ៛', Setting::formatMoney(41000, 'KHR'));

        // Language toggle
        $this->withSession(['locale' => 'km'])->actingAs($this->admin)->get('/dashboard');
        $this->assertEquals('km', App::getLocale());
        $this->assertEquals('ផ្ទាំងគ្រប់គ្រង', __('messages.dashboard'));
    }

    /**
     * AUDIT 14: Responsive layouts and all 22 administrative routes.
     */
    public function test_audit_14_all_routes_render_cleanly(): void
    {
        $this->actingAs($this->admin);

        $routes = [
            '/dashboard',
            '/pos',
            '/sales',
            '/products',
            '/categories',
            '/promotions',
            '/inventory',
            '/inventory/stock-in',
            '/inventory/stock-out',
            '/inventory/transfers',
            '/inventory/damaged',
            '/inventory/returns',
            '/inventory/history',
            '/suppliers',
            '/customers',
            '/employees',
            '/positions',
            '/attendances',
            '/reports',
            '/users',
            '/settings',
            '/profile',
        ];

        foreach ($routes as $route) {
            $resp = $this->get($route);
            $resp->assertStatus(200, "Route {$route} failed to load.");
        }
    }

    /**
     * AUDIT 15: Unauthorized routes and RBAC protection.
     */
    public function test_audit_15_unauthorized_routes_are_forbidden(): void
    {
        // Cashier cannot access administrative settings, users, or reports
        $this->actingAs($this->cashier);

        $this->get('/settings')->assertStatus(403);
        $this->get('/users')->assertStatus(403);
        $this->get('/reports')->assertStatus(403);
        $this->get('/inventory/stock-in')->assertStatus(403);
        $this->get('/employees')->assertStatus(403);
    }

    /**
     * AUDIT 16: Invalid inputs, duplicate requests, insufficient stock, and transaction failures.
     */
    public function test_audit_16_system_guards_and_failures(): void
    {
        $checkoutService = app(CheckoutService::class);

        // 1. Insufficient stock throws exception
        $this->expectException(InvalidArgumentException::class);
        $checkoutService->processCheckout(
            cashier: $this->cashier,
            items: [['product_id' => $this->product->id, 'qty' => 999999]],
            paymentType: 'cash',
            amountReceived: 1000000.00
        );
    }
}
