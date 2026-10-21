<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\Sale;
use App\Models\Stock;
use App\Models\StockTransaction;
use App\Models\Supplier;
use App\Models\User;
use App\Services\CheckoutService;
use App\Services\InventoryService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class PosAndSalesWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected User $cashier;
    protected User $otherCashier;
    protected User $manager;
    protected Product $product;
    protected Customer $vipCustomer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cashier = User::firstOrCreate(
            ['email' => 'cashier@skincare.com'],
            ['name' => 'Main Cashier', 'password' => bcrypt('password'), 'role' => 'cashier', 'status' => 'active']
        );

        $this->otherCashier = User::firstOrCreate(
            ['email' => 'cashier2@skincare.com'],
            ['name' => 'Second Cashier', 'password' => bcrypt('password'), 'role' => 'cashier', 'status' => 'active']
        );

        $this->manager = User::firstOrCreate(
            ['email' => 'manager@skincare.com'],
            ['name' => 'Store Manager', 'password' => bcrypt('password'), 'role' => 'manager', 'status' => 'active']
        );

        $category = Category::firstOrCreate(['name' => 'Hydration Serums'], ['status' => 'active']);
        $supplier = Supplier::firstOrCreate(['name' => 'K-Beauty Direct'], ['status' => 'active']);

        $this->vipCustomer = Customer::create([
            'name' => 'Victoria Sterling',
            'email' => 'victoria@sterling.com',
            'phone' => '+1-555-7788',
            'membership_number' => 'MEM-PLA-9999',
            'membership_level' => 'Platinum', // 15% discount
            'status' => 'active',
        ]);

        $this->product = Product::create([
            'name' => 'COSRX Snail 96 Mucin Power Essence 100ml',
            'barcode' => '8809416470009',
            'category_id' => $category->id,
            'supplier_id' => $supplier->id,
            'cost_price' => 12.00,
            'sale_price' => 22.00,
            'qty' => 10,
            'low_stock_threshold' => 3,
            'status' => 'active',
        ]);

        Stock::create([
            'product_id' => $this->product->id,
            'qty' => 10,
        ]);
    }

    public function test_pos_terminal_renders_with_catalog_and_scanner_ready(): void
    {
        $this->actingAs($this->cashier);

        $response = $this->get('/pos');

        $response->assertStatus(200);
        $response->assertSee('POS Terminal');
        $response->assertSee('Current Ticket');
        $response->assertSee('8809416470009'); // Product barcode in catalog
        $response->assertSee('COSRX Snail 96 Mucin Power Essence 100ml');
        $response->assertSee('Walk-in Guest');
    }

    public function test_successful_cash_checkout_deducts_inventory_and_creates_records(): void
    {
        $this->actingAs($this->cashier);

        // 2 units at $22.00 = $44.00, tax 5% ($2.20) = $46.20 total
        $payload = [
            'items' => [
                ['product_id' => $this->product->id, 'qty' => 2],
            ],
            'customer_id' => null, // Guest checkout
            'payment_type' => 'cash',
            'amount_received' => 50.00,
        ];

        $response = $this->postJson('/pos/checkout', $payload);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'sale' => [
                'subtotal' => 44.00,
                'final_total' => 46.20,
                'amount_received' => 50.00,
                'change_due' => 3.80,
                'customer_name' => 'Walk-in Guest',
            ],
        ]);

        // Verify Sale in database
        $this->assertDatabaseHas('sales', [
            'user_id' => $this->cashier->id,
            'customer_id' => null,
            'subtotal' => 44.00,
            'final_total' => 46.20,
            'payment_type' => 'cash',
            'status' => 'completed',
        ]);

        // Verify Sale Details
        $this->assertDatabaseHas('sale_details', [
            'product_id' => $this->product->id,
            'qty' => 2,
            'price' => 22.00,
            'subtotal' => 44.00,
        ]);

        // Verify Inventory Deduction (10 - 2 = 8 units)
        $this->assertEquals(8, $this->product->fresh()->qty);
        $this->assertEquals(8, Stock::where('product_id', $this->product->id)->first()->qty);

        // Verify StockTransaction Audit Record
        $this->assertDatabaseHas('stock_transactions', [
            'product_id' => $this->product->id,
            'user_id' => $this->cashier->id,
            'type' => StockTransaction::TYPE_SALE,
            'qty' => -2,
        ]);
    }

    public function test_vip_customer_loyalty_discount_is_applied_server_side(): void
    {
        $this->actingAs($this->cashier);

        // 1 unit at $22.00. Platinum VIP gets 15% discount = $3.30 discount. Net = $18.70.
        // Tax 5% on $18.70 = $0.94. Final Total = $19.64.
        $payload = [
            'items' => [
                ['product_id' => $this->product->id, 'qty' => 1],
            ],
            'customer_id' => $this->vipCustomer->id,
            'payment_type' => 'card',
            'amount_received' => 19.64,
        ];

        $response = $this->postJson('/pos/checkout', $payload);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'sale' => [
                'subtotal' => 22.00,
                'discount' => 3.30,
                'final_total' => 19.64,
                'customer_tier' => 'Platinum',
            ],
        ]);

        $this->assertDatabaseHas('sales', [
            'customer_id' => $this->vipCustomer->id,
            'discount' => 3.30,
            'final_total' => 19.64,
        ]);
    }

    public function test_checkout_prevents_overselling_when_stock_insufficient(): void
    {
        $this->actingAs($this->cashier);

        // Product only has 10 units in stock. Request 15 units.
        $payload = [
            'items' => [
                ['product_id' => $this->product->id, 'qty' => 15],
            ],
            'payment_type' => 'cash',
            'amount_received' => 400.00,
        ];

        $response = $this->postJson('/pos/checkout', $payload);

        $response->assertStatus(422);
        $response->assertJsonFragment(['success' => false]);
        $this->assertStringContainsString('Insufficient stock', $response->json('message'));

        // Ensure stock was NOT deducted
        $this->assertEquals(10, $this->product->fresh()->qty);
        // Ensure no sale record created
        $this->assertEquals(0, Sale::count());
    }

    public function test_checkout_rejects_cash_when_amount_received_is_less_than_total(): void
    {
        $this->actingAs($this->cashier);

        // 1 unit at $22.00 + tax = $23.10. Cash handed: $15.00 (Short by $8.10)
        $payload = [
            'items' => [
                ['product_id' => $this->product->id, 'qty' => 1],
            ],
            'payment_type' => 'cash',
            'amount_received' => 15.00,
        ];

        $response = $this->postJson('/pos/checkout', $payload);

        $response->assertStatus(422);
        $this->assertStringContainsString('less than total', $response->json('message'));

        // Stock unchanged
        $this->assertEquals(10, $this->product->fresh()->qty);
    }

    public function test_sale_cancellation_voids_sale_and_restocks_inventory(): void
    {
        $this->actingAs($this->cashier);

        // 1. Process sale of 4 units
        $checkoutService = app(CheckoutService::class);
        $result = $checkoutService->processCheckout(
            cashier: $this->cashier,
            items: [['product_id' => $this->product->id, 'qty' => 4]],
            paymentType: 'card'
        );

        $sale = $result['sale'];
        $this->assertEquals(6, $this->product->fresh()->qty); // 10 - 4 = 6

        // 2. Cancel sale as manager
        $this->actingAs($this->manager);
        $cancelResponse = $this->post("/sales/{$sale->id}/cancel", [
            'reason' => 'Customer requested immediate exchange at boutique desk',
        ]);

        $cancelResponse->assertRedirect();

        // Verify sale status is cancelled & refunded
        $freshSale = $sale->fresh();
        $this->assertEquals('cancelled', $freshSale->status);
        $this->assertEquals('refunded', $freshSale->payment_status);

        // Verify inventory is completely restocked (6 + 4 = 10)
        $this->assertEquals(10, $this->product->fresh()->qty);

        // Verify return audit transaction created
        $this->assertDatabaseHas('stock_transactions', [
            'product_id' => $this->product->id,
            'type' => StockTransaction::TYPE_RETURN,
            'qty' => 4,
            'reference_number' => $sale->invoice_number,
        ]);
    }

    public function test_cashier_cannot_access_or_cancel_other_cashiers_sales(): void
    {
        $checkoutService = app(CheckoutService::class);
        $result = $checkoutService->processCheckout(
            cashier: $this->otherCashier,
            items: [['product_id' => $this->product->id, 'qty' => 1]],
            paymentType: 'card'
        );

        $sale = $result['sale'];

        // Main cashier tries to cancel other cashier's sale
        $this->actingAs($this->cashier);
        $response = $this->post("/sales/{$sale->id}/cancel", ['reason' => 'Unauthorized void attempt']);

        $response->assertStatus(403);
    }

    public function test_printable_receipt_renders_successfully(): void
    {
        $this->actingAs($this->cashier);

        $checkoutService = app(CheckoutService::class);
        $result = $checkoutService->processCheckout(
            cashier: $this->cashier,
            items: [['product_id' => $this->product->id, 'qty' => 1]],
            customerId: $this->vipCustomer->id,
            paymentType: 'card'
        );

        $sale = $result['sale'];

        $response = $this->get("/pos/receipt/{$sale->id}");

        $response->assertStatus(200);
        $response->assertSee($sale->invoice_number);
        $response->assertSee('SKINCARE BOUTIQUE');
        $response->assertSee('Victoria Sterling');
        $response->assertSee('COSRX Snail 96 Mucin Power Essence 100ml');
        $response->assertSee('Print Receipt');
    }
}
