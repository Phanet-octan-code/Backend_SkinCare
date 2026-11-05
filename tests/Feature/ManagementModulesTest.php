<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\Sale;
use App\Models\SaleDetail;
use App\Models\Stock;
use App\Models\StockTransaction;
use App\Models\Supplier;
use App\Models\User;
use App\Services\CheckoutService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ManagementModulesTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $manager;
    protected User $cashier;
    protected User $inventoryUser;
    protected Category $category;
    protected Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::firstOrCreate(
            ['email' => 'admin_test@skincare.com'],
            ['name' => 'Admin Tester', 'password' => bcrypt('password'), 'role' => 'admin', 'status' => 'active']
        );

        $this->manager = User::firstOrCreate(
            ['email' => 'manager_test@skincare.com'],
            ['name' => 'Manager Tester', 'password' => bcrypt('password'), 'role' => 'manager', 'status' => 'active']
        );

        $this->cashier = User::firstOrCreate(
            ['email' => 'cashier_test@skincare.com'],
            ['name' => 'Cashier Tester', 'password' => bcrypt('password'), 'role' => 'cashier', 'status' => 'active']
        );

        $this->inventoryUser = User::firstOrCreate(
            ['email' => 'inventory_test@skincare.com'],
            ['name' => 'Inventory Tester', 'password' => bcrypt('password'), 'role' => 'inventory', 'status' => 'active']
        );

        $this->category = Category::firstOrCreate(
            ['name' => 'Facial Serums & Ampoules'],
            ['description' => 'Targeted concentrated formulations', 'status' => 'active']
        );

        $this->supplier = Supplier::firstOrCreate(
            ['name' => 'Dermalogica Lab Supplies'],
            [
                'phone' => '+1 800 555 0199',
                'email' => 'supply@dermalogica.test',
                'address' => '100 Derma Way, Los Angeles, CA',
                'status' => 'active',
            ]
        );
    }

    // ==========================================
    // CATEGORY MODULE TESTS
    // ==========================================

    public function test_category_crud_and_validation(): void
    {
        $this->actingAs($this->admin);

        // 1. List
        $response = $this->get('/categories');
        $response->assertStatus(200);
        $response->assertSee('Facial Serums & Ampoules');

        // 2. Create valid category
        $createResponse = $this->post('/categories', [
            'name' => 'Exfoliating Toners & AHA',
            'description' => 'Gentle chemical peel toners',
            'status' => 'active',
        ]);
        $createResponse->assertRedirect('/categories');
        $createResponse->assertSessionHas('success');
        $this->assertDatabaseHas('categories', ['name' => 'Exfoliating Toners & AHA']);

        $newCategory = Category::where('name', 'Exfoliating Toners & AHA')->first();

        // 3. Validation: duplicate name rejected
        $dupResponse = $this->post('/categories', [
            'name' => 'Exfoliating Toners & AHA',
            'status' => 'active',
        ]);
        $dupResponse->assertSessionHasErrors(['name']);

        // 4. Update category
        $updateResponse = $this->put("/categories/{$newCategory->id}", [
            'name' => 'Exfoliating Toners & BHA Clarifiers',
            'description' => 'Updated formulation scope',
            'status' => 'inactive',
        ]);
        $updateResponse->assertRedirect('/categories');
        $this->assertDatabaseHas('categories', [
            'id' => $newCategory->id,
            'name' => 'Exfoliating Toners & BHA Clarifiers',
            'status' => 'inactive',
        ]);

        // 5. Toggle Status
        $toggleResponse = $this->patch("/categories/{$newCategory->id}/toggle-status");
        $toggleResponse->assertRedirect('/categories');
        $newCategory->refresh();
        $this->assertEquals('active', $newCategory->status);

        // 6. Delete unassociated category
        $deleteResponse = $this->delete("/categories/{$newCategory->id}");
        $deleteResponse->assertRedirect('/categories');
        $deleteResponse->assertSessionHas('success');
        $this->assertDatabaseMissing('categories', ['id' => $newCategory->id]);
    }

    public function test_category_deletion_prevented_when_products_exist(): void
    {
        $this->actingAs($this->admin);

        $cat = Category::create([
            'name' => 'Protected Category',
            'status' => 'active',
        ]);

        $prod = Product::create([
            'category_id' => $cat->id,
            'supplier_id' => $this->supplier->id,
            'name' => 'Protected Cleanser',
            'barcode' => 'PROT_CAT_001',
            'cost_price' => 10.00,
            'sale_price' => 20.00,
            'qty' => 5,
            'low_stock_threshold' => 2,
            'status' => 'active',
        ]);

        // Attempt deletion
        $deleteResponse = $this->delete("/categories/{$cat->id}");
        $deleteResponse->assertRedirect('/categories');
        $deleteResponse->assertSessionHas('error');
        $this->assertDatabaseHas('categories', ['id' => $cat->id]);

        // Clean up
        $prod->forceDelete();
        $cat->delete();
    }

    // ==========================================
    // SUPPLIER MODULE TESTS
    // ==========================================

    public function test_supplier_crud_and_safe_deletion_guards(): void
    {
        $this->actingAs($this->manager);

        // 1. Create supplier
        $createResponse = $this->post('/suppliers', [
            'name' => 'Tokyo Botanical Beauty Labs',
            'phone' => '+81 3 5555 0123',
            'email' => 'partner@tokyobotanical.test',
            'address' => 'Ginza 5-Chome, Tokyo, Japan',
            'status' => 'active',
        ]);
        $createResponse->assertRedirect('/suppliers');
        $createResponse->assertSessionHas('success');

        $supp = Supplier::where('name', 'Tokyo Botanical Beauty Labs')->first();
        $this->assertNotNull($supp);

        // 2. Update supplier
        $updateResponse = $this->put("/suppliers/{$supp->id}", [
            'name' => 'Tokyo Botanical Beauty Labs Global',
            'phone' => '+81 3 5555 9999',
            'email' => 'partner@tokyobotanical.test',
            'address' => 'Ginza 6-Chome, Tokyo, Japan',
            'status' => 'inactive',
        ]);
        $updateResponse->assertRedirect('/suppliers');
        $supp->refresh();
        $this->assertEquals('Tokyo Botanical Beauty Labs Global', $supp->name);
        $this->assertEquals('inactive', $supp->status);

        // 3. Toggle status
        $this->patch("/suppliers/{$supp->id}/toggle-status");
        $supp->refresh();
        $this->assertEquals('active', $supp->status);

        // 4. Test safe deletion guard when product is attached
        $prod = Product::create([
            'category_id' => $this->category->id,
            'supplier_id' => $supp->id,
            'name' => 'Tokyo Rice Bran Essence',
            'barcode' => 'TOKYO_001',
            'cost_price' => 15.00,
            'sale_price' => 32.00,
            'qty' => 10,
            'low_stock_threshold' => 3,
            'status' => 'active',
        ]);

        $failDelete = $this->delete("/suppliers/{$supp->id}");
        $failDelete->assertRedirect('/suppliers');
        $failDelete->assertSessionHas('error');
        $this->assertDatabaseHas('suppliers', ['id' => $supp->id]);

        // Remove product and delete supplier cleanly
        $prod->forceDelete();
        $successDelete = $this->delete("/suppliers/{$supp->id}");
        $successDelete->assertRedirect('/suppliers');
        $successDelete->assertSessionHas('success');
        $this->assertDatabaseMissing('suppliers', ['id' => $supp->id]);
    }

    // ==========================================
    // PROMOTIONS MODULE TESTS
    // ==========================================

    public function test_promotions_crud_validation_and_resolution(): void
    {
        $this->actingAs($this->admin);

        // 1. Validation: End date before start date rejected
        $badDateResponse = $this->post('/promotions', [
            'name' => 'Invalid Date Promotion',
            'discount_type' => 'percentage',
            'discount_value' => 20,
            'start_date' => '2026-11-10',
            'end_date' => '2026-11-01',
            'status' => 'active',
        ]);
        $badDateResponse->assertSessionHasErrors(['end_date']);

        // 2. Validation: Percentage over 100 rejected
        $badPctResponse = $this->post('/promotions', [
            'name' => 'Invalid 150% Promo',
            'discount_type' => 'percentage',
            'discount_value' => 150,
            'start_date' => '2026-11-01',
            'end_date' => '2026-11-15',
            'status' => 'active',
        ]);
        $badPctResponse->assertSessionHasErrors(['discount_value']);

        // 3. Create General (Storewide) promotion
        $today = Carbon::today()->toDateString();
        $nextMonth = Carbon::today()->addDays(30)->toDateString();

        $genPromoResponse = $this->post('/promotions', [
            'name' => 'Storewide Radiance Sale 10%',
            'discount_type' => 'percentage',
            'discount_value' => 10.00,
            'start_date' => $today,
            'end_date' => $nextMonth,
            'status' => 'active',
            'scope_type' => 'general',
        ]);
        $genPromoResponse->assertRedirect('/promotions');
        $genPromoResponse->assertSessionHas('success');

        $genPromo = Promotion::where('name', 'Storewide Radiance Sale 10%')->first();
        $this->assertTrue($genPromo->isGeneral());

        // 4. Create Product-Specific promotion
        $testProd = Product::create([
            'category_id' => $this->category->id,
            'supplier_id' => $this->supplier->id,
            'name' => 'Retinol 1% Intensive Night Oil',
            'barcode' => 'RETINOL_SPECIAL_01',
            'cost_price' => 20.00,
            'sale_price' => 50.00,
            'qty' => 20,
            'low_stock_threshold' => 5,
            'status' => 'active',
        ]);
        Stock::create(['product_id' => $testProd->id, 'qty' => 20]);

        $specPromoResponse = $this->post('/promotions', [
            'name' => 'Retinol VIP Flash $15 OFF',
            'discount_type' => 'fixed',
            'discount_value' => 15.00,
            'start_date' => $today,
            'end_date' => $nextMonth,
            'status' => 'active',
            'scope_type' => 'specific',
            'product_ids' => [$testProd->id],
        ]);
        $specPromoResponse->assertRedirect('/promotions');
        $specPromo = Promotion::where('name', 'Retinol VIP Flash $15 OFF')->first();
        $this->assertFalse($specPromo->isGeneral());
        $this->assertTrue($specPromo->appliesToProduct($testProd->id));

        // 5. Test POS resolution: when overlapping (10% storewide = $5 off, $15 off product specific),
        // POS backend selects the best discount ($15) and does not exceed selling price
        $checkoutService = app(CheckoutService::class);
        $cartResult = $checkoutService->processCheckout(
            cashier: $this->admin,
            items: [['product_id' => $testProd->id, 'qty' => 1]],
            customerId: null,
            paymentType: 'cash',
            amountReceived: 50.00
        );

        $this->assertNotNull($cartResult['sale']);
        $saleDetail = $cartResult['sale']->details->first();
        // $15 discount applied
        $this->assertEquals(15.00, (float) $saleDetail->discount);
        $this->assertEquals(35.00, (float) $saleDetail->subtotal);

        // 6. Test filter tabs (active, scheduled, expired)
        $scheduledPromo = Promotion::create([
            'name' => 'Future Holiday Deal',
            'discount_type' => 'percentage',
            'discount_value' => 25.00,
            'start_date' => Carbon::today()->addDays(10)->toDateString(),
            'end_date' => Carbon::today()->addDays(20)->toDateString(),
            'status' => 'active',
        ]);

        $expiredPromo = Promotion::create([
            'name' => 'Past Summer Sale',
            'discount_type' => 'percentage',
            'discount_value' => 20.00,
            'start_date' => Carbon::today()->subDays(20)->toDateString(),
            'end_date' => Carbon::today()->subDays(5)->toDateString(),
            'status' => 'active',
        ]);

        $activeTabResponse = $this->get('/promotions?tab=active');
        $activeTabResponse->assertStatus(200);
        $activeTabResponse->assertSee('Storewide Radiance Sale 10%');

        $schedTabResponse = $this->get('/promotions?tab=scheduled');
        $schedTabResponse->assertStatus(200);
        $schedTabResponse->assertSee('Future Holiday Deal');

        $expTabResponse = $this->get('/promotions?tab=expired');
        $expTabResponse->assertStatus(200);
        $expTabResponse->assertSee('Past Summer Sale');

        // Clean up
        $genPromo->delete();
        $specPromo->delete();
        $scheduledPromo->delete();
        $expiredPromo->delete();
    }

    // ==========================================
    // PRODUCTS MODULE TESTS
    // ==========================================

    public function test_product_crud_image_upload_and_inventory_service_sync(): void
    {
        $this->actingAs($this->admin);

        // 1. Create a dummy test image
        $fakeImage = UploadedFile::fake()->image('skincare_ampoule.jpg', 400, 400)->size(500);

        // 2. Create product with initial qty 25
        $createResponse = $this->post('/products', [
            'name' => 'Peptide Multi-Barrier Complex',
            'barcode' => 'PEPTIDE_BARRIER_999',
            'category_id' => $this->category->id,
            'supplier_id' => $this->supplier->id,
            'cost_price' => 18.50,
            'sale_price' => 38.00,
            'qty' => 25,
            'low_stock_threshold' => 8,
            'status' => 'active',
            'description' => 'Targeted copper peptide rebuilding serum',
            'image' => $fakeImage,
        ]);

        $createResponse->assertRedirect('/products');
        $createResponse->assertSessionHas('success');

        $product = Product::where('barcode', 'PEPTIDE_BARRIER_999')->first();
        $this->assertNotNull($product);
        $this->assertEquals(25, $product->qty);
        $this->assertNotNull($product->image);
        $this->assertTrue(File::exists(public_path($product->image)));

        // Verify Stock table is synchronized
        $stock = Stock::where('product_id', $product->id)->first();
        $this->assertNotNull($stock);
        $this->assertEquals(25, $stock->qty);

        // Verify StockTransaction was created by InventoryService
        $txn = StockTransaction::where('product_id', $product->id)->first();
        $this->assertNotNull($txn);
        $this->assertEquals(StockTransaction::TYPE_STOCK_IN, $txn->type);
        $this->assertEquals(25, $txn->qty);

        // 3. Duplicate barcode validation error
        $dupResponse = $this->post('/products', [
            'name' => 'Duplicate Barcode Item',
            'barcode' => 'PEPTIDE_BARRIER_999',
            'category_id' => $this->category->id,
            'supplier_id' => $this->supplier->id,
            'cost_price' => 10.00,
            'sale_price' => 20.00,
            'low_stock_threshold' => 5,
            'status' => 'active',
        ]);
        $dupResponse->assertSessionHasErrors(['barcode']);

        // 4. Search and filters on index
        $searchResponse = $this->get('/products?search=Peptide');
        $searchResponse->assertStatus(200);
        $searchResponse->assertSee('Peptide Multi-Barrier Complex');

        $barcodeResponse = $this->get('/products?search=PEPTIDE_BARRIER_999');
        $barcodeResponse->assertStatus(200);
        $barcodeResponse->assertSee('Peptide Multi-Barrier Complex');

        $filterCatResponse = $this->get('/products?category_id=' . $this->category->id);
        $filterCatResponse->assertStatus(200);
        $filterCatResponse->assertSee('Peptide Multi-Barrier Complex');

        $filterStockResponse = $this->get('/products?stock_status=in_stock');
        $filterStockResponse->assertStatus(200);
        $filterStockResponse->assertSee('Peptide Multi-Barrier Complex');

        // 5. Update product
        $updateResponse = $this->put("/products/{$product->id}", [
            'name' => 'Peptide Multi-Barrier Complex Pro',
            'barcode' => 'PEPTIDE_BARRIER_999',
            'category_id' => $this->category->id,
            'supplier_id' => $this->supplier->id,
            'cost_price' => 20.00,
            'sale_price' => 42.00,
            'low_stock_threshold' => 10,
            'status' => 'active',
            'description' => 'Updated peptide formulation with zinc PCA',
        ]);
        $updateResponse->assertRedirect('/products');
        $product->refresh();
        $this->assertEquals('Peptide Multi-Barrier Complex Pro', $product->name);
        $this->assertEquals(42.00, (float) $product->sale_price);

        // 6. Safe Deactivation (Toggle Status)
        $toggleResponse = $this->patch("/products/{$product->id}/toggle-status");
        $toggleResponse->assertRedirect('/products');
        $product->refresh();
        $this->assertEquals('inactive', $product->status);

        // 7. Show Product
        $showResponse = $this->get("/products/{$product->id}");
        $showResponse->assertStatus(200);
        $showResponse->assertSee('Peptide Multi-Barrier Complex Pro');

        $showJsonResponse = $this->getJson("/products/{$product->id}");
        $showJsonResponse->assertStatus(200);
        $showJsonResponse->assertJsonFragment(['name' => 'Peptide Multi-Barrier Complex Pro']);

        // 8. Safe Soft Deletion: Product is soft deleted and marked trashed while protecting historical audit trail
        $successDelete = $this->delete("/products/{$product->id}");
        $successDelete->assertRedirect('/products');
        $successDelete->assertSessionHas('success');
        $this->assertSoftDeleted('products', ['id' => $product->id]);

        // Clean up test file and database records
        if ($product->image && File::exists(public_path($product->image))) {
            File::delete(public_path($product->image));
        }
        $txn->delete();
        $stock->delete();
        $product->forceDelete();
    }

    public function test_product_store_via_ajax_or_json_response(): void
    {
        $this->actingAs($this->admin);

        $response = $this->postJson('/products', [
            'name' => 'Ceramide Barrier Cream',
            'barcode' => 'CERAMIDE_CREAM_777',
            'category_id' => $this->category->id,
            'supplier_id' => $this->supplier->id,
            'cost_price' => 12.00,
            'sale_price' => 28.00,
            'qty' => 10,
            'low_stock_threshold' => 5,
            'status' => 'active',
            'description' => 'Rich barrier repair moisturizer',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
        ]);
        $response->assertJsonPath('product.barcode', 'CERAMIDE_CREAM_777');

        $this->assertDatabaseHas('products', [
            'barcode' => 'CERAMIDE_CREAM_777',
            'name' => 'Ceramide Barrier Cream',
        ]);
    }

    // ==========================================
    // AUTHORIZATION & RBAC TESTS
    // ==========================================

    public function test_cashier_is_forbidden_from_management_modules(): void
    {
        $this->actingAs($this->cashier);

        // Cashier cannot access Products management
        $this->get('/products')->assertStatus(403);
        $this->post('/products', [])->assertStatus(403);

        // Cashier cannot access Categories management
        $this->get('/categories')->assertStatus(403);
        $this->post('/categories', [])->assertStatus(403);

        // Cashier cannot access Promotions management
        $this->get('/promotions')->assertStatus(403);
        $this->post('/promotions', [])->assertStatus(403);

        // Cashier cannot access Suppliers management
        $this->get('/suppliers')->assertStatus(403);
        $this->post('/suppliers', [])->assertStatus(403);
    }
}
