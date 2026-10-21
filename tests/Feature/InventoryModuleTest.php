<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleDetail;
use App\Models\Stock;
use App\Models\StockTransaction;
use App\Models\StockTransfer;
use App\Models\StockTransferDetail;
use App\Models\Supplier;
use App\Models\User;
use App\Services\CheckoutService;
use App\Services\InventoryService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class InventoryModuleTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $manager;
    protected User $inventoryUser;
    protected User $cashier;
    protected Category $category;
    protected Supplier $supplier;
    protected Product $product;
    protected InventoryService $inventoryService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->inventoryService = app(InventoryService::class);

        $this->admin = User::firstOrCreate(
            ['email' => 'admin_inv@skincare.com'],
            ['name' => 'Admin Inventory', 'password' => bcrypt('password'), 'role' => 'admin', 'status' => 'active']
        );

        $this->manager = User::firstOrCreate(
            ['email' => 'manager_inv@skincare.com'],
            ['name' => 'Manager Inventory', 'password' => bcrypt('password'), 'role' => 'manager', 'status' => 'active']
        );

        $this->inventoryUser = User::firstOrCreate(
            ['email' => 'staff_inv@skincare.com'],
            ['name' => 'Inventory Staff', 'password' => bcrypt('password'), 'role' => 'inventory', 'status' => 'active']
        );

        $this->cashier = User::firstOrCreate(
            ['email' => 'cashier_inv@skincare.com'],
            ['name' => 'Cashier User', 'password' => bcrypt('password'), 'role' => 'cashier', 'status' => 'active']
        );

        $this->category = Category::firstOrCreate(
            ['name' => 'Hydrating Formulas'],
            ['description' => 'Hyaluronic & Barrier treatments', 'status' => 'active']
        );

        $this->supplier = Supplier::firstOrCreate(
            ['name' => 'La Roche-Posay Distribution'],
            [
                'phone' => '+1 800 555 1234',
                'email' => 'order@laroche.test',
                'address' => '500 Fifth Avenue, New York, NY',
                'status' => 'active',
            ]
        );

        $this->product = Product::create([
            'category_id' => $this->category->id,
            'supplier_id' => $this->supplier->id,
            'name' => 'Hyalu B5 Pure Hyaluronic Serum 30ml',
            'barcode' => 'HYALU_B5_SERUM_30',
            'cost_price' => 20.00,
            'sale_price' => 39.50,
            'qty' => 50,
            'low_stock_threshold' => 10,
            'status' => 'active',
        ]);

        Stock::create([
            'product_id' => $this->product->id,
            'qty' => 50,
        ]);
    }

    // ==========================================
    // 1. STOCK IN TESTS
    // ==========================================

    public function test_stock_in_receiving_updates_balances_and_cost(): void
    {
        $this->actingAs($this->inventoryUser);

        $response = $this->post('/inventory/stock-in', [
            'product_id' => $this->product->id,
            'qty' => 25,
            'supplier_id' => $this->supplier->id,
            'unit_cost' => 22.00,
            'reference_number' => 'PO-RECEIPT-8899',
            'date' => Carbon::today()->toDateString(),
            'notes' => 'Received 25 bottles in batch LOT-88',
        ]);

        $response->assertRedirect('/inventory/stock-in');
        $response->assertSessionHas('success');

        // Check stock balance updated
        $this->product->refresh();
        $stock = Stock::where('product_id', $this->product->id)->first();
        $this->assertEquals(75, $this->product->qty);
        $this->assertEquals(75, $stock->qty);
        $this->assertEquals(22.00, (float) $this->product->cost_price);

        // Check StockTransaction created
        $txn = StockTransaction::where('reference_number', 'PO-RECEIPT-8899')->first();
        $this->assertNotNull($txn);
        $this->assertEquals(StockTransaction::TYPE_STOCK_IN, $txn->type);
        $this->assertEquals(25, $txn->qty);
        $this->assertEquals($this->supplier->id, $txn->supplier_id);
    }

    public function test_stock_in_validates_quantity_must_be_positive(): void
    {
        $this->actingAs($this->inventoryUser);

        $response = $this->post('/inventory/stock-in', [
            'product_id' => $this->product->id,
            'qty' => 0,
        ]);

        $response->assertSessionHasErrors(['qty']);
    }

    // ==========================================
    // 2. STOCK OUT TESTS
    // ==========================================

    public function test_stock_out_deducts_inventory_with_reason(): void
    {
        $this->actingAs($this->inventoryUser);

        $response = $this->post('/inventory/stock-out', [
            'product_id' => $this->product->id,
            'qty' => 5,
            'reason' => 'Store Tester / Counter Marketing Display',
            'reference_number' => 'SO-TESTER-001',
            'date' => Carbon::today()->toDateString(),
            'notes' => 'Placed on main retail display counter',
        ]);

        $response->assertRedirect('/inventory/stock-out');
        $response->assertSessionHas('success');

        $this->product->refresh();
        $stock = Stock::where('product_id', $this->product->id)->first();
        $this->assertEquals(45, $this->product->qty);
        $this->assertEquals(45, $stock->qty);

        $txn = StockTransaction::where('reference_number', 'SO-TESTER-001')->first();
        $this->assertNotNull($txn);
        $this->assertEquals(StockTransaction::TYPE_STOCK_OUT, $txn->type);
        $this->assertEquals(-5, $txn->qty);
        $this->assertStringContainsString('Store Tester', $txn->notes);
    }

    public function test_stock_out_prevents_negative_inventory(): void
    {
        $this->actingAs($this->inventoryUser);

        // Current qty is 50, trying to deduct 60
        $response = $this->post('/inventory/stock-out', [
            'product_id' => $this->product->id,
            'qty' => 60,
            'reason' => 'Massive deduction',
        ]);

        $response->assertSessionHas('error');

        $this->product->refresh();
        $this->assertEquals(50, $this->product->qty);
    }

    // ==========================================
    // 3. STOCK TRANSFER TESTS
    // ==========================================

    public function test_stock_transfer_workflow_dispatch_and_complete(): void
    {
        $this->actingAs($this->inventoryUser);

        // 1. Validation: Same source and destination rejected
        $badLocation = $this->post('/inventory/transfers', [
            'source_location' => 'Central Warehouse & Distribution Hub',
            'destination_location' => 'Central Warehouse & Distribution Hub',
            'items' => [
                ['product_id' => $this->product->id, 'qty' => 10],
            ],
        ]);
        $badLocation->assertSessionHasErrors(['destination_location']);

        // 2. Dispatch valid transfer
        $dispatchResponse = $this->post('/inventory/transfers', [
            'source_location' => 'Central Warehouse & Distribution Hub',
            'destination_location' => 'Flagship Boutique (Downtown)',
            'items' => [
                ['product_id' => $this->product->id, 'qty' => 10],
            ],
            'notes' => 'Transfer via courier dispatch #TRF-LOG-01',
        ]);

        $dispatchResponse->assertRedirect('/inventory/transfers');
        $dispatchResponse->assertSessionHas('success');

        // Stock at source warehouse deducted from 50 to 40
        $this->product->refresh();
        $this->assertEquals(40, $this->product->qty);

        $transfer = StockTransfer::latest('id')->first();
        $this->assertNotNull($transfer);
        $this->assertEquals('in_transit', $transfer->status);
        $this->assertEquals('Central Warehouse & Distribution Hub', $transfer->source_location);
        $this->assertEquals('Flagship Boutique (Downtown)', $transfer->destination_location);
        $this->assertEquals(10, $transfer->details->first()->qty);

        // 3. Complete transfer at destination
        $completeResponse = $this->patch("/inventory/transfers/{$transfer->id}/status", [
            'status' => 'completed',
            'reason' => 'Verified intact by store receiver',
        ]);

        $completeResponse->assertRedirect('/inventory/transfers');
        $completeResponse->assertSessionHas('success');

        $transfer->refresh();
        $this->assertEquals('completed', $transfer->status);
    }

    public function test_stock_transfer_cancellation_restocks_source(): void
    {
        $this->actingAs($this->inventoryUser);

        // Create transfer of 15 units (stock goes from 50 to 35)
        $transfer = $this->inventoryService->createStockTransfer(
            userId: $this->inventoryUser->id,
            sourceLocation: 'Central Warehouse & Distribution Hub',
            destinationLocation: 'Aesthetic Spa & Treatment Suite',
            items: [['product_id' => $this->product->id, 'qty' => 15]],
            notes: 'Spa replenishment'
        );

        $this->product->refresh();
        $this->assertEquals(35, $this->product->qty);

        // Cancel transfer
        $cancelResponse = $this->patch("/inventory/transfers/{$transfer->id}/status", [
            'status' => 'cancelled',
            'reason' => 'Spa suite cancelled request',
        ]);

        $cancelResponse->assertRedirect('/inventory/transfers');
        $cancelResponse->assertSessionHas('success');

        // Verify stock restored to 50
        $this->product->refresh();
        $this->assertEquals(50, $this->product->qty);

        $transfer->refresh();
        $this->assertEquals('cancelled', $transfer->status);
    }

    // ==========================================
    // 4. DAMAGED / EXPIRED STOCK TESTS
    // ==========================================

    public function test_record_damaged_stock_write_off(): void
    {
        $this->actingAs($this->inventoryUser);

        $response = $this->post('/inventory/damaged', [
            'product_id' => $this->product->id,
            'qty' => 3,
            'reason' => 'Broken Glass Dropper / Cracked Bottle',
            'reference_number' => 'DMG-AUDIT-004',
            'date' => Carbon::today()->toDateString(),
            'notes' => 'Dropped from top shelf during stock count',
        ]);

        $response->assertRedirect('/inventory/damaged');
        $response->assertSessionHas('success');

        $this->product->refresh();
        $this->assertEquals(47, $this->product->qty);

        $txn = StockTransaction::where('reference_number', 'DMG-AUDIT-004')->first();
        $this->assertNotNull($txn);
        $this->assertEquals(StockTransaction::TYPE_DAMAGED, $txn->type);
        $this->assertEquals(-3, $txn->qty);
    }

    public function test_cannot_write_off_more_damaged_than_available_stock(): void
    {
        $this->actingAs($this->inventoryUser);

        $response = $this->post('/inventory/damaged', [
            'product_id' => $this->product->id,
            'qty' => 999,
            'reason' => 'Defective',
        ]);

        $response->assertSessionHas('error');
        $this->product->refresh();
        $this->assertEquals(50, $this->product->qty);
    }

    // ==========================================
    // 5. RETURNS & DUPLICATE PROTECTION TESTS
    // ==========================================

    public function test_customer_return_resellable_restocks_inventory(): void
    {
        $this->actingAs($this->manager);

        // 1. Create a sale of 4 units
        $sale = Sale::create([
            'invoice_number' => 'INV-TEST-RETURN-01',
            'user_id' => $this->admin->id,
            'customer_id' => null,
            'subtotal' => 158.00,
            'discount_amount' => 0.00,
            'tax_amount' => 7.90,
            'final_total' => 165.90,
            'payment_type' => 'cash',
            'status' => 'completed',
            'payment_status' => 'paid',
        ]);

        $detail = SaleDetail::create([
            'sale_id' => $sale->id,
            'product_id' => $this->product->id,
            'qty' => 4,
            'price' => 39.50,
            'discount' => 0.00,
            'subtotal' => 158.00,
        ]);

        // Stock was 50, now minus 4 from sale = 46
        $this->inventoryService->recordSaleDeduction($this->product->id, 4, $this->admin->id, $sale->invoice_number);
        $this->product->refresh();
        $this->assertEquals(46, $this->product->qty);

        // 2. Return 2 resellable units
        $returnResponse = $this->post('/inventory/returns/customer', [
            'sale_id' => $sale->id,
            'product_id' => $this->product->id,
            'qty' => 2,
            'condition' => 'resellable',
            'reason' => 'Customer bought wrong variation; returned sealed box',
        ]);

        $returnResponse->assertRedirect('/inventory/returns');
        $returnResponse->assertSessionHas('success');

        // Stock restored from 46 to 48
        $this->product->refresh();
        $this->assertEquals(48, $this->product->qty);

        // 3. Return 1 damaged unit (customer used, defective pump)
        $damagedReturn = $this->post('/inventory/returns/customer', [
            'sale_id' => $sale->id,
            'product_id' => $this->product->id,
            'qty' => 1,
            'condition' => 'damaged',
            'reason' => 'Defective pump mechanism',
        ]);
        $damagedReturn->assertRedirect('/inventory/returns');

        // Sellable stock must NOT increase when damaged! (remains 48)
        $this->product->refresh();
        $this->assertEquals(48, $this->product->qty);

        // 4. Duplicate / Over Return Prevention:
        // Originally sold 4. Already returned: 2 (resellable) + 1 (damaged) = 3.
        // Trying to return 2 more (total 5 > 4) must fail!
        $overReturn = $this->post('/inventory/returns/customer', [
            'sale_id' => $sale->id,
            'product_id' => $this->product->id,
            'qty' => 2,
            'condition' => 'resellable',
            'reason' => 'Excess return attempt',
        ]);

        $overReturn->assertSessionHas('error');
    }

    public function test_supplier_return_to_vendor_deducts_stock(): void
    {
        $this->actingAs($this->inventoryUser);

        $response = $this->post('/inventory/returns/supplier', [
            'product_id' => $this->product->id,
            'supplier_id' => $this->supplier->id,
            'qty' => 5,
            'reason' => 'Near expiry batch return for vendor credit',
            'reference_number' => 'RTV-CREDIT-990',
            'notes' => 'Supplier credit note #CN-5512',
        ]);

        $response->assertRedirect('/inventory/returns');
        $response->assertSessionHas('success');

        $this->product->refresh();
        $this->assertEquals(45, $this->product->qty);

        $txn = StockTransaction::where('reference_number', 'RTV-CREDIT-990')->first();
        $this->assertNotNull($txn);
        $this->assertEquals(-5, $txn->qty);
        $this->assertEquals($this->supplier->id, $txn->supplier_id);
    }

    // ==========================================
    // 6. STOCK HISTORY AUDIT TRAIL TESTS
    // ==========================================

    public function test_stock_history_audit_trail_and_filters(): void
    {
        $this->actingAs($this->inventoryUser);

        // Log a couple of distinct transactions
        $this->inventoryService->recordStockIn($this->product->id, 10, $this->inventoryUser->id, $this->supplier->id, 'HIST-IN-01');
        $this->inventoryService->recordStockOut($this->product->id, 2, $this->inventoryUser->id, 'Tester display', 'HIST-OUT-01');

        $historyResponse = $this->get('/inventory/history');
        $historyResponse->assertStatus(200);
        $historyResponse->assertSee('HIST-IN-01');
        $historyResponse->assertSee('HIST-OUT-01');

        // Filter by type: stock-in
        $typeFilterResponse = $this->get('/inventory/history?type=stock-in');
        $typeFilterResponse->assertStatus(200);
        $typeFilterResponse->assertSee('HIST-IN-01');
        $typeFilterResponse->assertDontSee('HIST-OUT-01');

        // Filter by search ref
        $searchResponse = $this->get('/inventory/history?search=HIST-OUT-01');
        $searchResponse->assertStatus(200);
        $searchResponse->assertSee('HIST-OUT-01');
        $searchResponse->assertDontSee('HIST-IN-01');
    }

    // ==========================================
    // 7. INVENTORY VALUATION & THRESHOLD ALERTS
    // ==========================================

    public function test_inventory_valuation_and_threshold_alert_updates(): void
    {
        $this->actingAs($this->manager);

        // Product has 50 units @ cost $20.00 = $1,000.00
        $valuation = $this->inventoryService->calculateValuationSummary();
        $this->assertEquals(1000.00, $valuation['total_cost_value']);
        $this->assertEquals(1975.00, $valuation['total_retail_value']); // 50 * 39.50
        $this->assertEquals(975.00, $valuation['potential_profit']);

        // Update threshold via controller
        $threshResponse = $this->post("/inventory/update-threshold/{$this->product->id}", [
            'low_stock_threshold' => 60, // Above current 50, so now it's low stock!
        ]);

        $threshResponse->assertRedirect();
        $this->product->refresh();
        $this->assertEquals(60, $this->product->low_stock_threshold);
        $this->assertTrue($this->product->isLowStock());
    }

    // ==========================================
    // 8. RBAC AUTHORIZATION TESTS
    // ==========================================

    public function test_cashier_is_forbidden_from_inventory_operations(): void
    {
        $this->actingAs($this->cashier);

        // Cashier cannot access inventory screens
        $this->get('/inventory')->assertStatus(403);
        $this->get('/inventory/stock-in')->assertStatus(403);
        $this->post('/inventory/stock-in', [])->assertStatus(403);
        $this->get('/inventory/stock-out')->assertStatus(403);
        $this->post('/inventory/stock-out', [])->assertStatus(403);
        $this->get('/inventory/transfers')->assertStatus(403);
        $this->post('/inventory/transfers', [])->assertStatus(403);
        $this->get('/inventory/damaged')->assertStatus(403);
        $this->post('/inventory/damaged', [])->assertStatus(403);
        $this->get('/inventory/returns')->assertStatus(403);
        $this->post('/inventory/returns/customer', [])->assertStatus(403);
        $this->get('/inventory/history')->assertStatus(403);
    }
}
