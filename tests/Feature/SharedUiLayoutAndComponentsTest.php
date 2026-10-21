<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Position;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SharedUiLayoutAndComponentsTest extends TestCase
{
    protected User $admin;
    protected User $cashier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::firstOrCreate(
            ['email' => 'admin@skincare.com'],
            ['name' => 'Admin User', 'password' => bcrypt('password'), 'role' => 'admin', 'status' => 'active']
        );

        $this->cashier = User::firstOrCreate(
            ['email' => 'cashier@skincare.com'],
            ['name' => 'Cashier User', 'password' => bcrypt('password'), 'role' => 'cashier', 'status' => 'active']
        );

        // Ensure baseline data exists
        $category = Category::firstOrCreate(['name' => 'Serums & Essences'], ['status' => 'active']);
        $supplier = Supplier::firstOrCreate(['name' => 'Dermalogica Direct'], ['status' => 'active']);
        $position = Position::firstOrCreate(['name' => 'Senior Esthetician'], ['base_salary' => 850.00]);
        Employee::firstOrCreate(['email' => 'staff1@skincare.com'], [
            'name' => 'Jessica Alba',
            'position_id' => $position->id,
            'status' => 'active',
            'start_date' => now()->subMonths(6)
        ]);
        Product::firstOrCreate(['barcode' => 'TEST999888111'], [
            'name' => 'Niacinamide 10% Formula',
            'category_id' => $category->id,
            'supplier_id' => $supplier->id,
            'cost_price' => 12.00,
            'sale_price' => 24.00,
            'qty' => 15,
            'low_stock_threshold' => 5,
            'status' => 'active'
        ]);
    }

    public function test_all_21_routes_render_cleanly_for_administrator(): void
    {
        $this->actingAs($this->admin);

        $routes = [
            'dashboard' => '/dashboard',
            'pos' => '/pos',
            'sales' => '/sales',
            'products' => '/products',
            'categories' => '/categories',
            'promotions' => '/promotions',
            'inventory' => '/inventory',
            'inventory.stock-in' => '/inventory/stock-in',
            'inventory.stock-out' => '/inventory/stock-out',
            'inventory.transfers' => '/inventory/transfers',
            'inventory.damaged' => '/inventory/damaged',
            'inventory.returns' => '/inventory/returns',
            'inventory.history' => '/inventory/history',
            'suppliers' => '/suppliers',
            'customers' => '/customers',
            'employees' => '/employees',
            'positions' => '/positions',
            'attendances' => '/attendances',
            'reports' => '/reports',
            'users' => '/users',
            'settings' => '/settings',
            'profile' => '/profile',
        ];

        foreach ($routes as $name => $uri) {
            $response = $this->get($uri);
            $response->assertStatus(200, "Route [{$name}] ({$uri}) failed to render 200 OK");
            $response->assertSee('id="sidebar"', false);
            $response->assertSee('id="topbar"', false);
            $response->assertSee('id="main-content"', false);
            $response->assertSee('SkinCare POS', false);
        }
    }

    public function test_shared_components_present_in_rendered_html(): void
    {
        $this->actingAs($this->admin);

        // Dashboard checks
        $response = $this->get('/dashboard');
        $response->assertStatus(200);
        $response->assertSee('card-skincare', false);
        $response->assertSee('brand-icon-box', false);
        $response->assertSee('toast-container', false);
        $response->assertSee('SkinCareToast', false);

        // Product Catalog checks
        $prodResponse = $this->get('/products');
        $prodResponse->assertStatus(200);
        $prodResponse->assertSee('table-skincare', false);
        $prodResponse->assertSee('Add New Product', false);

        // Attendance checks
        $attResponse = $this->get('/attendances');
        $attResponse->assertStatus(200);
        $attResponse->assertSee('Staff Attendance Logs', false);
        $attResponse->assertSee('Present Today', false);
    }
}
