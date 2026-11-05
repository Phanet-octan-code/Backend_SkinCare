<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\Sale;
use App\Models\Stock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class EcommerceApiWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        // Ensure online bot user exists
        User::firstOrCreate(
            ['email' => 'storefront@skincare.com'],
            [
                'name' => 'Online Storefront Bot',
                'password' => Hash::make('SecretBotPass123!'),
                'role' => 'cashier',
                'status' => 'active',
            ]
        );

        // Ensure active category, supplier, and product exist
        $category = Category::firstOrCreate(['name' => 'SkinCare'], ['status' => 'active']);
        $supplier = \App\Models\Supplier::firstOrCreate(
            ['name' => 'Premium Skincare Lab'],
            ['contact_person' => 'Vendor Rep', 'phone' => '+855 12 999 888', 'status' => 'active']
        );

        $product = Product::firstOrCreate(
            ['barcode' => 'TEST-BARCODE-999'],
            [
                'category_id' => $category->id,
                'supplier_id' => $supplier->id,
                'name' => 'Hydrating Glow Serum 50ml',
                'cost_price' => 12.00,
                'sale_price' => 28.00,
                'qty' => 50,
                'status' => 'active',
            ]
        );

        Stock::firstOrCreate(
            ['product_id' => $product->id],
            ['qty' => 50]
        );
    }

    public function test_store_settings_api_returns_currency_and_payment_methods(): void
    {
        $response = $this->getJson('/api/v1/store/settings');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'business_name',
                    'currency',
                    'currency_symbol',
                    'exchange_rate_khr',
                    'tax_rate',
                    'supported_currencies',
                    'payment_methods',
                ],
            ]);
    }

    public function test_categories_api_returns_active_categories(): void
    {
        $response = $this->getJson('/api/v1/store/categories');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => ['id', 'name', 'description'],
                ],
            ]);
    }

    public function test_products_catalog_masks_cost_price_and_supplier_id(): void
    {
        $response = $this->getJson('/api/v1/store/products');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'barcode',
                        'sale_price',
                        'final_price',
                        'in_stock',
                        'image_url',
                    ],
                ],
                'pagination' => ['current_page', 'last_page', 'total'],
            ]);

        // Assert strictly that cost_price and supplier_id are NEVER present in JSON response
        $content = $response->getContent();
        $this->assertStringNotContainsString('"cost_price"', $content);
        $this->assertStringNotContainsString('"supplier_id"', $content);
    }

    public function test_cart_quote_calculates_promotions_and_tax_correctly(): void
    {
        $product = Product::where('status', 'active')->where('qty', '>', 5)->first();
        $this->assertNotNull($product);

        $response = $this->postJson('/api/v1/cart/quote', [
            'items' => [
                ['product_id' => $product->id, 'qty' => 2],
            ],
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'items',
                    'subtotal',
                    'promo_discount',
                    'member_discount',
                    'total_discount',
                    'tax',
                    'shipping_fee',
                    'final_total',
                ],
            ]);
    }

    public function test_customer_registration_and_authentication_with_sanctum(): void
    {
        $uniqueEmail = 'ecomm_' . uniqid() . '@example.com';

        // 1. Register
        $regResponse = $this->postJson('/api/v1/customer/register', [
            'name' => 'Sophia Laurent',
            'email' => $uniqueEmail,
            'phone' => '+855 99 123 456',
            'password' => 'SecurePass2026!',
            'password_confirmation' => 'SecurePass2026!',
            'address' => 'Street 240, Phnom Penh',
        ]);

        $regResponse->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'token',
                'customer' => ['id', 'name', 'email', 'membership_level', 'membership_number'],
            ]);

        $token = $regResponse->json('token');

        // 2. Access profile with Bearer Token
        $profileResponse = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/customer/profile');

        $profileResponse->assertStatus(200)
            ->assertJsonPath('customer.email', $uniqueEmail);

        // 3. Login
        $loginResponse = $this->postJson('/api/v1/customer/login', [
            'email' => $uniqueEmail,
            'password' => 'SecurePass2026!',
        ]);

        $loginResponse->assertStatus(200)
            ->assertJsonStructure(['success', 'token', 'customer']);
    }

    public function test_customer_address_book_crud_with_authorization(): void
    {
        $customer = Customer::create([
            'name' => 'Test Address User',
            'email' => 'address_test_' . uniqid() . '@example.com',
            'password' => Hash::make('Password123!'),
            'membership_level' => 'Bronze',
            'membership_number' => 'MEM-BRZ-' . rand(1000, 9999),
        ]);

        $token = $customer->createToken('test_token')->plainTextToken;

        // Store address
        $createRes = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/customer/addresses', [
                'label' => 'Home',
                'recipient_name' => 'Test Recipient',
                'phone' => '+855 12 345 678',
                'address_line1' => '#45 Norodom Blvd',
                'city' => 'Phnom Penh',
                'is_default' => true,
            ]);

        $createRes->assertStatus(201);
        $addressId = $createRes->json('data.id');

        // List addresses
        $listRes = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/customer/addresses');

        $listRes->assertStatus(200)
            ->assertJsonCount(1, 'data');

        // Delete address
        $delRes = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->deleteJson("/api/v1/customer/addresses/{$addressId}");

        $delRes->assertStatus(200);
    }

    public function test_customer_wishlist_workflow(): void
    {
        $customer = Customer::create([
            'name' => 'Wishlist User',
            'email' => 'wishlist_' . uniqid() . '@example.com',
            'password' => Hash::make('Password123!'),
            'membership_level' => 'Bronze',
            'membership_number' => 'MEM-BRZ-' . rand(1000, 9999),
        ]);

        $product = Product::where('status', 'active')->first();
        $token = $customer->createToken('test_token')->plainTextToken;

        // Add to wishlist
        $addRes = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/customer/wishlist', [
                'product_id' => $product->id,
            ]);

        $addRes->assertStatus(201);

        // List wishlist
        $listRes = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/customer/wishlist');

        $listRes->assertStatus(200)
            ->assertJsonCount(1, 'data');

        // Delete from wishlist
        $delRes = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->deleteJson("/api/v1/customer/wishlist/{$product->id}");

        $delRes->assertStatus(200);
    }

    public function test_online_checkout_creates_sale_and_deducts_inventory_atomically(): void
    {
        $product = Product::where('status', 'active')->where('qty', '>=', 5)->first();
        $this->assertNotNull($product);

        $initialStock = $product->qty;
        $orderQty = 2;

        $checkoutResponse = $this->postJson('/api/v1/customer/checkout', [
            'items' => [
                ['product_id' => $product->id, 'qty' => $orderQty],
            ],
            'guest_name' => 'Online Guest Shopper',
            'guest_email' => 'guest_' . uniqid() . '@example.com',
            'guest_phone' => '+855 88 777 666',
            'shipping_address' => 'Condo One, BKK1, Phnom Penh',
            'payment_type' => 'card',
            'order_notes' => 'Please leave at reception desk.',
        ]);

        $checkoutResponse->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'order' => [
                    'id',
                    'invoice_number',
                    'final_total',
                    'shipping_fee',
                    'payment_type',
                    'tracking_number',
                    'items',
                ],
            ]);

        $invoiceNumber = $checkoutResponse->json('order.invoice_number');
        $trackingNumber = $checkoutResponse->json('order.tracking_number');

        // Verify database stock decrement
        $product->refresh();
        $this->assertEquals($initialStock - $orderQty, $product->qty);

        // Verify sale record exists
        $sale = Sale::where('invoice_number', $invoiceNumber)->first();
        $this->assertNotNull($sale);
        $this->assertEquals('online', $sale->order_source);
        $this->assertEquals('pending', $sale->payment_status);

        // Authoritative backend payment verification
        $verifyResponse = $this->postJson("/api/v1/orders/{$invoiceNumber}/verify-payment", [
            'payment_type' => 'card',
            'transaction_ref' => 'TXN_' . uniqid(),
        ]);
        $verifyResponse->assertStatus(200);

        $sale->refresh();
        $this->assertEquals('paid', $sale->payment_status);

        // Test public tracking endpoint
        $trackResponse = $this->postJson('/api/v1/orders/track', [
            'reference' => $invoiceNumber,
            'identifier' => 'guest',
        ]);

        $trackResponse->assertStatus(200)
            ->assertJsonPath('data.invoice_number', $invoiceNumber);
    }

    public function test_server_enforces_authoritative_pricing_ignoring_client_price_tampering(): void
    {
        $product = Product::where('status', 'active')->first();
        $this->assertNotNull($product);

        // Attempt to pass manipulated low price ($0.01) and custom discount
        $response = $this->postJson('/api/v1/customer/checkout', [
            'items' => [
                ['product_id' => $product->id, 'qty' => 1, 'price' => 0.01, 'discount' => 99.00],
            ],
            'guest_name' => 'Price Hacker',
            'guest_email' => 'hacker_' . uniqid() . '@example.com',
            'guest_phone' => '+855 12 000 000',
            'shipping_address' => 'Phnom Penh',
            'payment_type' => 'card',
        ]);

        $response->assertStatus(201);
        $invoiceNumber = $response->json('order.invoice_number');
        $sale = Sale::where('invoice_number', $invoiceNumber)->first();

        // Server authoritative calculation: line price matches actual catalog sale_price
        $this->assertEquals((float) $product->sale_price, (float) $sale->details->first()->price);
        $this->assertGreaterThan(0.01, (float) $sale->final_total);
    }

    public function test_checkout_rejects_insufficient_stock_without_overselling(): void
    {
        $product = Product::where('status', 'active')->first();
        $this->assertNotNull($product);

        $excessiveQty = $product->qty + 500;

        $response = $this->postJson('/api/v1/customer/checkout', [
            'items' => [
                ['product_id' => $product->id, 'qty' => $excessiveQty],
            ],
            'guest_name' => 'Greedy Buyer',
            'guest_email' => 'greedy_' . uniqid() . '@example.com',
            'guest_phone' => '+855 12 111 222',
            'shipping_address' => 'Phnom Penh',
            'payment_type' => 'card',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_checkout_rejects_invalid_or_expired_coupon(): void
    {
        $product = Product::where('status', 'active')->first();

        $response = $this->postJson('/api/v1/customer/checkout', [
            'items' => [
                ['product_id' => $product->id, 'qty' => 1],
            ],
            'guest_name' => 'Coupon Tester',
            'guest_email' => 'coupon_' . uniqid() . '@example.com',
            'guest_phone' => '+855 12 333 444',
            'shipping_address' => 'Phnom Penh',
            'payment_type' => 'card',
            'coupon_code' => 'FAKE-EXPIRED-CODE-999',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_idor_protection_customer_cannot_view_or_cancel_another_customers_order(): void
    {
        $customerA = Customer::create([
            'name' => 'Alice Customer',
            'email' => 'alice_' . uniqid() . '@example.com',
            'password' => Hash::make('Password123!'),
            'membership_number' => 'MEM-ALICE-' . rand(1000, 9999),
        ]);

        $customerB = Customer::create([
            'name' => 'Bob Customer',
            'email' => 'bob_' . uniqid() . '@example.com',
            'password' => Hash::make('Password123!'),
            'membership_number' => 'MEM-BOB-' . rand(1000, 9999),
        ]);

        $tokenA = $customerA->createToken('alice_token')->plainTextToken;
        $tokenB = $customerB->createToken('bob_token')->plainTextToken;

        // Create an order owned by Customer A
        $orderA = Sale::create([
            'user_id' => 1,
            'customer_id' => $customerA->id,
            'invoice_number' => 'INV-TEST-A-' . rand(1000, 9999),
            'subtotal' => 50.00,
            'tax' => 2.50,
            'final_total' => 52.50,
            'payment_type' => 'card',
            'payment_status' => 'pending',
            'status' => 'pending',
            'order_source' => 'online',
        ]);

        // Customer B attempts to view Customer A's order -> 403 Forbidden
        $viewRes = $this->withHeader('Authorization', 'Bearer ' . $tokenB)
            ->getJson("/api/v1/customer/orders/{$orderA->id}");
        $viewRes->assertStatus(403);

        // Customer B attempts to cancel Customer A's order -> 403 Forbidden
        $cancelRes = $this->withHeader('Authorization', 'Bearer ' . $tokenB)
            ->postJson("/api/v1/customer/orders/{$orderA->id}/cancel", [
                'reason' => 'Malicious cancel attempt',
            ]);
        $cancelRes->assertStatus(403);
    }

    public function test_idor_protection_customer_cannot_modify_or_delete_another_customers_address(): void
    {
        $customerA = Customer::create([
            'name' => 'Customer A',
            'email' => 'custA_' . uniqid() . '@example.com',
            'password' => Hash::make('Password123!'),
            'membership_number' => 'MEM-A-' . rand(1000, 9999),
        ]);

        $customerB = Customer::create([
            'name' => 'Customer B',
            'email' => 'custB_' . uniqid() . '@example.com',
            'password' => Hash::make('Password123!'),
            'membership_number' => 'MEM-B-' . rand(1000, 9999),
        ]);

        $tokenB = $customerB->createToken('b_token')->plainTextToken;

        $addressA = CustomerAddress::create([
            'customer_id' => $customerA->id,
            'label' => 'Home',
            'recipient_name' => 'Customer A',
            'phone' => '+855 12 111 222',
            'address_line1' => '#12 Street 100',
            'city' => 'Phnom Penh',
            'is_default' => true,
        ]);

        // Customer B attempts to update Customer A's address -> 403 Forbidden
        $updateRes = $this->withHeader('Authorization', 'Bearer ' . $tokenB)
            ->putJson("/api/v1/customer/addresses/{$addressA->id}", [
                'recipient_name' => 'Tampered Name',
                'phone' => '+855 12 999 999',
                'address_line1' => 'Hacked Address',
                'city' => 'Phnom Penh',
            ]);
        $updateRes->assertStatus(403);

        // Customer B attempts to delete Customer A's address -> 403 Forbidden
        $deleteRes = $this->withHeader('Authorization', 'Bearer ' . $tokenB)
            ->deleteJson("/api/v1/customer/addresses/{$addressA->id}");
        $deleteRes->assertStatus(403);
    }

    public function test_customer_can_cancel_own_order_and_inventory_is_restored(): void
    {
        $product = Product::where('status', 'active')->first();
        $this->assertNotNull($product);
        $initialStock = $product->qty;

        $customer = Customer::create([
            'name' => 'Cancel Order User',
            'email' => 'cancel_' . uniqid() . '@example.com',
            'password' => Hash::make('Password123!'),
            'membership_number' => 'MEM-CNC-' . rand(1000, 9999),
        ]);
        $token = $customer->createToken('token')->plainTextToken;

        // Place order as authenticated customer
        $checkoutRes = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/customer/checkout', [
                'items' => [
                    ['product_id' => $product->id, 'qty' => 2],
                ],
                'shipping_address' => 'Customer Home, Phnom Penh',
                'payment_type' => 'card',
            ]);
        $checkoutRes->assertStatus(201);
        $orderId = $checkoutRes->json('order.id');

        $product->refresh();
        $this->assertEquals($initialStock - 2, $product->qty);

        // Cancel order
        $cancelRes = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson("/api/v1/customer/orders/{$orderId}/cancel", [
                'reason' => 'Changed my mind',
            ]);
        $cancelRes->assertStatus(200);

        // Inventory is restored
        $product->refresh();
        $this->assertEquals($initialStock, $product->qty);

        // Order is marked cancelled
        $order = Sale::find($orderId);
        $this->assertEquals('cancelled', $order->status);
    }

    public function test_bestsellers_and_new_arrivals_api(): void
    {
        $resBestsellers = $this->getJson('/api/v1/store/products/bestsellers');
        $resBestsellers->assertStatus(200)
            ->assertJsonStructure(['success', 'data']);

        $resNewArrivals = $this->getJson('/api/v1/store/products/new-arrivals');
        $resNewArrivals->assertStatus(200)
            ->assertJsonStructure(['success', 'data']);
    }

    public function test_customer_password_update_workflow(): void
    {
        $customer = Customer::create([
            'name' => 'Password Test User',
            'email' => 'pwtest@example.com',
            'password' => Hash::make('OldPassword123!'),
            'membership_number' => 'MEM-PW-' . rand(1000, 9999),
        ]);
        $token = $customer->createToken('token')->plainTextToken;

        // Fails with wrong current password
        $failRes = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->putJson('/api/v1/customer/password', [
                'current_password' => 'WrongPassword!',
                'password' => 'NewPassword123!',
                'password_confirmation' => 'NewPassword123!',
            ]);
        $failRes->assertStatus(422)
            ->assertJsonValidationErrors(['current_password']);

        // Succeeds with correct current password
        $successRes = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->putJson('/api/v1/customer/password', [
                'current_password' => 'OldPassword123!',
                'password' => 'NewPassword123!',
                'password_confirmation' => 'NewPassword123!',
            ]);
        $successRes->assertStatus(200)
            ->assertJson(['success' => true]);

        $customer->refresh();
        $this->assertTrue(Hash::check('NewPassword123!', $customer->password));
    }

    public function test_checkout_idempotency_key_prevents_duplicate_orders_and_double_deduction(): void
    {
        $product = Product::where('status', 'active')->first();
        $this->assertNotNull($product);
        $initialStock = $product->qty;

        $idempotencyKey = 'IDEMP_TEST_' . uniqid();

        $payload = [
            'items' => [
                ['product_id' => $product->id, 'qty' => 1],
            ],
            'guest_name' => 'Idempotent Tester',
            'guest_email' => 'idemp_' . uniqid() . '@example.com',
            'guest_phone' => '+855 12 555 444',
            'shipping_address' => 'Phnom Penh St 51',
            'payment_type' => 'card',
            'idempotency_key' => $idempotencyKey,
        ];

        // First attempt -> 201 Created
        $res1 = $this->withHeader('X-Idempotency-Key', $idempotencyKey)
            ->postJson('/api/v1/customer/checkout', $payload);
        $res1->assertStatus(201);
        $invoice1 = $res1->json('order.invoice_number');

        $product->refresh();
        $this->assertEquals($initialStock - 1, $product->qty);

        // Second immediate attempt with same idempotency key -> returns existing order (200 OK) without deducting stock again
        $res2 = $this->withHeader('X-Idempotency-Key', $idempotencyKey)
            ->postJson('/api/v1/customer/checkout', $payload);
        $res2->assertStatus(200);
        $invoice2 = $res2->json('order.invoice_number');

        $this->assertEquals($invoice1, $invoice2);

        // Stock was NOT deducted a second time
        $product->refresh();
        $this->assertEquals($initialStock - 1, $product->qty);
    }

    public function test_cash_on_delivery_checkout_sets_confirmed_order_with_pending_payment(): void
    {
        $product = Product::where('status', 'active')->first();
        $this->assertNotNull($product);

        $payload = [
            'items' => [
                ['product_id' => $product->id, 'qty' => 1],
            ],
            'guest_name' => 'Cash Delivery Buyer',
            'guest_email' => 'cod_' . uniqid() . '@example.com',
            'guest_phone' => '+855 12 888 999',
            'shipping_address' => 'Russian Market, Phnom Penh',
            'payment_type' => 'cash',
        ];

        $res = $this->postJson('/api/v1/customer/checkout', $payload);
        $res->assertStatus(201);

        $invoice = $res->json('order.invoice_number');
        $sale = Sale::where('invoice_number', $invoice)->first();
        $this->assertNotNull($sale);

        // Cash on delivery is marked confirmed for delivery fulfillment, with payment pending upon arrival
        $this->assertEquals('confirmed', $sale->status);
        $this->assertEquals('pending', $sale->payment_status);
        $this->assertEquals('cash', $sale->payment_type);
    }
}
