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
use App\Models\Stock;
use App\Models\StockTransaction;
use App\Models\StockTransfer;
use App\Models\StockTransferDetail;
use App\Models\Supplier;
use App\Models\User;
use App\Services\InventoryService;
use App\Services\SupabaseService;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class DatabaseArchitectureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function users_are_seeded_with_expected_roles_and_helpers_work(): void
    {
        $admin = User::where('role', 'admin')->first();
        $this->assertNotNull($admin);
        $this->assertTrue($admin->isAdmin());
        $this->assertTrue($admin->isActive());

        $cashier = User::where('role', 'cashier')->first();
        $this->assertNotNull($cashier);
        $this->assertTrue($cashier->isCashier());
        $this->assertFalse($cashier->isAdmin());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function categories_and_products_have_correct_relationships(): void
    {
        $product = Product::with(['category', 'supplier', 'stock'])->first();

        $this->assertNotNull($product);
        $this->assertInstanceOf(Category::class, $product->category);
        $this->assertInstanceOf(Supplier::class, $product->supplier);
        $this->assertInstanceOf(Stock::class, $product->stock);
        $this->assertEquals($product->qty, $product->stock->qty);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function low_stock_scope_identifies_depleted_items(): void
    {
        $lowStockProducts = Product::lowStock()->get();

        $this->assertGreaterThan(0, $lowStockProducts->count());
        foreach ($lowStockProducts as $item) {
            $this->assertTrue($item->isLowStock());
            $this->assertLessThanOrEqual($item->low_stock_threshold, $item->qty);
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function customer_membership_tier_calculates_loyalty_discounts(): void
    {
        $platinum = Customer::where('membership_level', 'Platinum')->first();
        $silver = Customer::where('membership_level', 'Silver')->first();
        $bronze = Customer::where('membership_level', 'Bronze')->first();

        $this->assertEquals(15.0, $platinum->membership_discount_percentage);
        $this->assertEquals(5.0, $silver->membership_discount_percentage);
        $this->assertEquals(0.0, $bronze->membership_discount_percentage);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function general_and_product_specific_promotions_function_correctly(): void
    {
        $generalPromo = Promotion::where('name', 'LIKE', '%Storewide%')->first();
        $this->assertNotNull($generalPromo);
        $this->assertTrue($generalPromo->isGeneral());

        $productPromo = Promotion::where('name', 'LIKE', '%Sunscreens%')->first();
        $this->assertNotNull($productPromo);
        $this->assertFalse($productPromo->isGeneral());
        $this->assertGreaterThan(0, $productPromo->products()->count());

        $attachedProduct = $productPromo->products()->first();
        $this->assertTrue($productPromo->appliesToProduct($attachedProduct->id));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function inventory_service_synchronizes_stocks_and_products_qty(): void
    {
        $product = Product::first();
        $initialQty = $product->qty;
        $admin = User::first();

        $service = new InventoryService();

        // 1. Record stock in of 10 units
        $service->recordStockIn(
            productId: $product->id,
            qty: 10,
            userId: $admin->id,
            supplierId: $product->supplier_id,
            referenceNumber: 'TEST-PO-001'
        );

        $product->refresh();
        $stock = Stock::where('product_id', $product->id)->first();

        $this->assertEquals($initialQty + 10, $stock->qty);
        $this->assertEquals($stock->qty, $product->qty);

        // 2. Record sale deduction of 5 units
        $service->recordSaleDeduction(
            productId: $product->id,
            qty: 5,
            userId: $admin->id,
            invoiceNumber: 'TEST-INV-001'
        );

        $product->refresh();
        $stock->refresh();

        $this->assertEquals($initialQty + 5, $stock->qty);
        $this->assertEquals($stock->qty, $product->qty);

        // 3. Verify stock transaction records
        $lastTx = StockTransaction::where('reference_number', 'TEST-INV-001')->first();
        $this->assertNotNull($lastTx);
        $this->assertEquals(StockTransaction::TYPE_SALE, $lastTx->type);
        $this->assertEquals(-5, $lastTx->qty);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function inventory_service_prevents_negative_stock_exception(): void
    {
        $product = Product::first();
        $admin = User::first();
        $service = new InventoryService();

        $this->expectException(InvalidArgumentException::class);
        $service->adjustStock(
            productId: $product->id,
            deltaQty: -($product->qty + 500),
            type: StockTransaction::TYPE_STOCK_OUT,
            userId: $admin->id
        );
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function duplicate_attendance_per_employee_per_date_is_prevented(): void
    {
        $employee = Employee::first();
        $date = Carbon::today()->addDays(5)->toDateString();

        Attendance::create([
            'employee_id' => $employee->id,
            'date' => $date,
            'check_in' => '09:00:00',
            'status' => 'present',
        ]);

        $this->expectException(QueryException::class);

        // Second check-in on the exact same date for the same employee must fail
        Attendance::create([
            'employee_id' => $employee->id,
            'date' => $date,
            'check_in' => '09:15:00',
            'status' => 'late',
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function sales_and_details_maintain_audit_and_guest_checkout(): void
    {
        $sale = Sale::with(['details', 'customer', 'user'])->first();
        $this->assertNotNull($sale);
        $this->assertNotEmpty($sale->invoice_number);
        $this->assertGreaterThan(0, $sale->details->count());

        // Guest sale verification (nullable customer_id)
        $guestSale = Sale::whereNull('customer_id')->first();
        $this->assertNotNull($guestSale);
        $this->assertNull($guestSale->customer);
        $this->assertNotNull($guestSale->user);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function stock_transfers_link_with_details_and_products(): void
    {
        $transfer = StockTransfer::with(['details.product', 'user'])->first();
        $this->assertNotNull($transfer);
        $this->assertGreaterThan(0, $transfer->details->count());
        $this->assertInstanceOf(Product::class, $transfer->details->first()->product);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function supabase_service_connects_to_rest_api(): void
    {
        $supabase = new SupabaseService();
        $status = $supabase->ping();

        $this->assertTrue($status['success']);
        $this->assertTrue($status['connected']);
        $this->assertEquals(200, $status['status']);
    }
}
