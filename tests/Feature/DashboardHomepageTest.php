<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleDetail;
use App\Models\Supplier;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardHomepageTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $cashier;
    protected Product $product1;
    protected Product $product2;
    protected Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::firstOrCreate(
            ['email' => 'admin@skincare.com'],
            ['name' => 'Admin User', 'password' => bcrypt('password'), 'role' => 'admin', 'status' => 'active']
        );

        $this->cashier = User::firstOrCreate(
            ['email' => 'cashier@skincare.com'],
            ['name' => 'Lead Cashier', 'password' => bcrypt('password'), 'role' => 'cashier', 'status' => 'active']
        );

        $category = Category::firstOrCreate(['name' => 'Serums & Essences'], ['status' => 'active']);
        $supplier = Supplier::firstOrCreate(['name' => 'Derma Care Supplier'], ['status' => 'active']);

        $this->customer = Customer::firstOrCreate(
            ['email' => 'customer@test.com'],
            ['name' => 'Alice Skincare', 'membership_level' => 'Platinum', 'status' => 'active']
        );

        $this->product1 = Product::create([
            'name' => 'Hydra Repair Serum 30ml',
            'category_id' => $category->id,
            'supplier_id' => $supplier->id,
            'barcode' => 'TEST-001',
            'cost_price' => 10.00,
            'sale_price' => 25.00,
            'qty' => 4, // Below low stock threshold
            'low_stock_threshold' => 10,
            'status' => 'active',
        ]);

        $this->product2 = Product::create([
            'name' => 'Soothing Green Tea Toner 150ml',
            'category_id' => $category->id,
            'supplier_id' => $supplier->id,
            'barcode' => 'TEST-002',
            'cost_price' => 15.00,
            'sale_price' => 30.00,
            'qty' => 50,
            'low_stock_threshold' => 10,
            'status' => 'active',
        ]);
    }

    public function test_dashboard_renders_for_administrator(): void
    {
        $this->actingAs($this->admin);

        $response = $this->get('/dashboard');

        $response->assertStatus(200);
        $response->assertSee('Dashboard');
        $response->assertSee('Overview of your skincare business performance.');
        $response->assertSee('New Sale');
        $response->assertSee('Sales Revenue Overview');
        $response->assertSee('Recent Transactions');
        $response->assertSee('Best-Selling Products');
        $response->assertSee('Payment Methods');
        $response->assertSee('Low Stock Alerts');
        $response->assertSee('Inventory Summary');
        $response->assertSee('Wholesale Cost'); // Manager/Admin visible
    }

    public function test_dashboard_kpis_and_calculations_with_sales(): void
    {
        $this->actingAs($this->admin);

        // Create completed sale for today
        $saleToday = Sale::create([
            'user_id' => $this->admin->id,
            'customer_id' => $this->customer->id,
            'invoice_number' => 'INV-TEST-0001',
            'subtotal' => 50.00,
            'discount' => 5.00,
            'tax' => 2.25,
            'final_total' => 47.25,
            'payment_type' => 'card',
            'payment_status' => 'paid',
            'status' => 'completed',
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        SaleDetail::create([
            'sale_id' => $saleToday->id,
            'product_id' => $this->product1->id,
            'qty' => 2,
            'price' => 25.00,
            'discount' => 5.00,
            'subtotal' => 45.00,
        ]);

        // Create cancelled sale (must be excluded from revenue)
        $saleCancelled = Sale::create([
            'user_id' => $this->admin->id,
            'customer_id' => null,
            'invoice_number' => 'INV-TEST-CANCELLED',
            'subtotal' => 100.00,
            'discount' => 0.00,
            'tax' => 5.00,
            'final_total' => 105.00,
            'payment_type' => 'cash',
            'payment_status' => 'refunded',
            'status' => 'cancelled',
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        $response = $this->get('/dashboard');

        $response->assertStatus(200);
        $response->assertSee('$47.25'); // Today's sales total
        $response->assertDontSee('$105.00'); // Cancelled sale must NOT be counted in revenue
        $response->assertSee('Hydra Repair Serum 30ml'); // Best-selling item
    }

    public function test_dashboard_filters_by_date_period(): void
    {
        $this->actingAs($this->admin);

        // Today sale
        Sale::create([
            'user_id' => $this->admin->id,
            'invoice_number' => 'INV-TODAY',
            'subtotal' => 60.00,
            'final_total' => 60.00,
            'payment_type' => 'cash',
            'status' => 'completed',
            'created_at' => Carbon::now(),
        ]);

        // Yesterday sale
        Sale::create([
            'user_id' => $this->admin->id,
            'invoice_number' => 'INV-YESTERDAY',
            'subtotal' => 80.00,
            'final_total' => 80.00,
            'payment_type' => 'qr',
            'status' => 'completed',
            'created_at' => Carbon::yesterday()->setHour(12),
        ]);

        // Filter: Today
        $respToday = $this->get('/dashboard?period=today');
        $respToday->assertStatus(200);
        $respToday->assertSee('$60.00');

        // Filter: Yesterday
        $respYesterday = $this->get('/dashboard?period=yesterday');
        $respYesterday->assertStatus(200);
        $respYesterday->assertSee('Yesterday');

        // Filter: Custom Range
        $respCustom = $this->get('/dashboard?period=custom&start_date=' . Carbon::yesterday()->toDateString() . '&end_date=' . Carbon::today()->toDateString());
        $respCustom->assertStatus(200);
        $respCustom->assertSee('Custom');
    }

    public function test_dashboard_role_restrictions_for_cashier(): void
    {
        $this->actingAs($this->cashier);

        $response = $this->get('/dashboard');

        $response->assertStatus(200);
        $response->assertSee('Dashboard');
        // Cashier shouldn't see wholesale cost figures
        $response->assertDontSee('Wholesale Cost');
        $response->assertDontSee('Potential Margin');
    }

    public function test_dashboard_renders_zero_states_cleanly_when_empty(): void
    {
        $this->actingAs($this->admin);

        // Delete all products and sales
        Sale::query()->delete();
        Product::query()->delete();

        $response = $this->get('/dashboard');

        $response->assertStatus(200);
        $response->assertSee('$0.00');
        $response->assertSee('No transactions recorded yet');
        $response->assertSee('All items well stocked');
    }
}
