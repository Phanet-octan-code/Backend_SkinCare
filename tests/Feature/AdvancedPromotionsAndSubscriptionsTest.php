<?php

namespace Tests\Feature;

use App\Models\Bundle;
use App\Models\BundleItem;
use App\Models\Category;
use App\Models\Customer;
use App\Models\CustomerReferral;
use App\Models\LoyaltyTier;
use App\Models\LoyaltyTransaction;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\ReferralCampaign;
use App\Models\Sale;
use App\Models\Subscription;
use App\Models\Supplier;
use App\Models\User;
use App\Services\BundleService;
use App\Services\EcommerceCheckoutService;
use App\Services\LoyaltyService;
use App\Services\ReferralService;
use App\Services\RefundService;
use App\Services\SubscriptionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class AdvancedPromotionsAndSubscriptionsTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected Category $category;
    protected Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'email' => 'admin_promo@skinora.com',
            'role' => 'admin',
            'status' => 'active',
        ]);

        $this->category = Category::create([
            'name' => 'Botanical Serums',
            'slug' => 'botanical-serums',
            'status' => 'active',
        ]);

        $this->supplier = Supplier::create([
            'name' => 'Skinora Labs Geneva',
            'phone' => '+41 22 710 0000',
            'email' => 'labs@skinora.ch',
            'status' => 'active',
        ]);

        LoyaltyTier::defaultTier();
    }

    protected function createProduct(string $name, float $price, int $stock): Product
    {
        return Product::create([
            'category_id' => $this->category->id,
            'supplier_id' => $this->supplier->id,
            'name' => $name,
            'barcode' => 'BAR-' . rand(100000, 999999),
            'cost_price' => round($price * 0.4, 2),
            'sale_price' => $price,
            'qty' => $stock,
            'status' => 'active',
        ]);
    }

    /**
     * Test 1: Bundles stock availability calculation and constituent inventory deduction.
     */
    public function test_bundle_stock_and_atomic_constituent_inventory_deduction(): void
    {
        $p1 = $this->createProduct('Rose Cleanser', 25.00, 10);
        $p2 = $this->createProduct('Glow Serum', 50.00, 4); // Limiting component: 4 units

        $bundle = Bundle::create([
            'name' => 'Morning Glow Duo',
            'type' => 'bundle',
            'price' => 60.00, // Retail value = $75, save $15
            'status' => 'active',
        ]);

        BundleItem::create(['bundle_id' => $bundle->id, 'product_id' => $p1->id, 'quantity' => 1]);
        BundleItem::create(['bundle_id' => $bundle->id, 'product_id' => $p2->id, 'quantity' => 1]);

        // 1. Available quantity should be limited by p2 (4 sets)
        $this->assertEquals(4, $bundle->available_qty);
        $this->assertEquals(75.00, $bundle->calculated_original_price);
        $this->assertEquals(15.00, $bundle->savings_amount);
        $this->assertEquals(20, $bundle->savings_percentage);

        // 2. Checkout 2 bundles
        $checkoutService = app(EcommerceCheckoutService::class);
        $customer = Customer::create([
            'name' => 'Audrey Hepburn',
            'email' => 'audrey@beauty.com',
            'membership_level' => 'Bronze',
            'status' => 'active',
        ]);

        $sale = $checkoutService->processCheckout(
            items: [['bundle_id' => $bundle->id, 'qty' => 2]],
            customer: $customer,
            shippingAddress: '123 Avenue Montaigne, Paris',
            paymentType: 'card'
        );

        $this->assertDatabaseHas('sales', ['id' => $sale->id, 'payment_status' => Sale::PAYMENT_PENDING]);
        $this->assertDatabaseHas('sale_details', ['sale_id' => $sale->id, 'bundle_id' => $bundle->id, 'qty' => 2]);

        // Component products should be decremented atomically: p1: 10 - 2 = 8, p2: 4 - 2 = 2
        $this->assertEquals(8, $p1->fresh()->qty);
        $this->assertEquals(2, $p2->fresh()->qty);

        // 3. New available bundle qty should now be 2
        $this->assertEquals(2, $bundle->fresh()->available_qty);
    }

    /**
     * Test 2: Bundle checkout rejects overselling when any component product lacks stock.
     */
    public function test_bundle_checkout_rejects_insufficient_constituent_stock(): void
    {
        $p1 = $this->createProduct('Night Cream', 40.00, 10);
        $p2 = $this->createProduct('Eye Balm', 30.00, 1); // Only 1 in stock!

        $bundle = Bundle::create([
            'name' => 'Eye & Night Ritual',
            'type' => 'bundle',
            'price' => 55.00,
            'status' => 'active',
        ]);

        BundleItem::create(['bundle_id' => $bundle->id, 'product_id' => $p1->id, 'quantity' => 1]);
        BundleItem::create(['bundle_id' => $bundle->id, 'product_id' => $p2->id, 'quantity' => 1]);

        $checkoutService = app(EcommerceCheckoutService::class);
        $customer = Customer::create(['name' => 'Emma', 'email' => 'emma@beauty.com', 'status' => 'active']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Insufficient stock for component 'Eye Balm'");

        // Attempting to buy 2 sets when Eye Balm has only 1 unit
        $checkoutService->processCheckout(
            items: [['bundle_id' => $bundle->id, 'qty' => 2]],
            customer: $customer,
            shippingAddress: 'London, UK',
            paymentType: 'card'
        );
    }

    /**
     * Test 3: Flash sales & limited-time offers enforce time window and usage limits.
     */
    public function test_flash_sale_time_window_and_usage_quota_enforcement(): void
    {
        $checkoutService = app(EcommerceCheckoutService::class);

        // Active flash sale
        $flash = Promotion::create([
            'name' => 'Midnight Flash 30',
            'code' => 'FLASH30',
            'discount_type' => 'percentage',
            'discount_value' => 30.00,
            'is_flash_sale' => true,
            'start_date' => Carbon::yesterday(),
            'end_date' => Carbon::tomorrow(),
            'starts_at' => Carbon::now()->subHour(),
            'ends_at' => Carbon::now()->addHour(),
            'total_usage_limit' => 2,
            'current_usage_count' => 0,
            'min_spend' => 50.00,
            'status' => 'active',
        ]);

        // 1. Evaluate valid flash sale
        $eval = $checkoutService->evaluateCoupon('FLASH30', 100.00);
        $this->assertNotNull($eval);
        $this->assertEquals(30.00, $eval['discount']);

        // 2. Minimum spend check
        try {
            $checkoutService->evaluateCoupon('FLASH30', 40.00);
            $this->fail('Should have rejected below min_spend');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('minimum order subtotal of $50', $e->getMessage());
        }

        // 3. Expired flash sale
        $flash->update(['ends_at' => Carbon::now()->subMinute()]);
        try {
            $checkoutService->evaluateCoupon('FLASH30', 100.00);
            $this->fail('Should have rejected expired flash sale');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('invalid or has expired', $e->getMessage());
        }
    }

    /**
     * Test 4: Loyalty point accrual, tier progression, and points redemption.
     */
    public function test_loyalty_point_accrual_tier_progression_and_redemption(): void
    {
        $loyaltyService = app(LoyaltyService::class);
        $checkoutService = app(EcommerceCheckoutService::class);

        $customer = Customer::create([
            'name' => 'Sophia Loren',
            'email' => 'sophia@skinora.com',
            'membership_level' => 'Bronze',
            'loyalty_points' => 0,
            'lifetime_points' => 0,
            'status' => 'active',
        ]);

        // 1. Accrue points: 500 spend = 500 points
        $customer->addLoyaltyPoints(500, 'earned_purchase');
        $this->assertEquals(500, $customer->loyalty_points);
        $this->assertEquals(500, $customer->lifetime_points);

        // 500 lifetime points should auto-promote customer to Silver tier (>= 300 pts)
        $this->assertEquals('Silver', $customer->fresh()->membership_level);

        // 2. Redeem points at checkout: 200 points = $10.00 discount
        $redemption = $loyaltyService->calculateRedemptionDiscount($customer, 200, 100.00);
        $this->assertEquals(200, $redemption['points_to_redeem']);
        $this->assertEquals(10.00, $redemption['discount']);

        $p = $this->createProduct('Hydrating Toner', 50.00, 20);

        $sale = $checkoutService->processCheckout(
            items: [['product_id' => $p->id, 'qty' => 2]], // Subtotal = $100
            customer: $customer,
            shippingAddress: 'Rome, Italy',
            paymentType: 'card',
            redeemPoints: 200
        );

        // Customer's loyalty points balance should decrease by 200, but new points earned for net order
        $this->assertEquals(200, $sale->loyalty_points_redeemed);
        $this->assertEquals(10.00, $sale->loyalty_points_discount);
        $this->assertDatabaseHas('loyalty_transactions', [
            'customer_id' => $customer->id,
            'type' => 'redeemed_discount',
            'points' => -200,
        ]);
    }

    /**
     * Test 5: Referral campaign with anti-fraud safeguards.
     */
    public function test_referral_campaign_rewards_and_anti_fraud(): void
    {
        $referralService = app(ReferralService::class);
        $checkoutService = app(EcommerceCheckoutService::class);

        $referrer = Customer::create([
            'name' => 'Grace Kelly',
            'email' => 'grace@monaco.mc',
            'status' => 'active',
        ]);
        $refCode = $referrer->referral_code;

        $referee = Customer::create([
            'name' => 'Diana Spencer',
            'email' => 'diana@kensington.uk',
            'status' => 'active',
        ]);

        // 1. Anti-fraud: Referrer cannot use their own referral code
        try {
            $referralService->validateReferralCode($refCode, $referrer, 50.00);
            $this->fail('Should have blocked self-referral');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('cannot use your own referral code', $e->getMessage());
        }

        // 2. Valid referral for new customer with min spend
        $validation = $referralService->validateReferralCode($refCode, $referee, 50.00);
        $this->assertTrue($validation['valid']);
        $this->assertEquals(10.00, $validation['discount']);

        $p = $this->createProduct('Rosewater Mist', 60.00, 10);

        $sale = $checkoutService->processCheckout(
            items: [['product_id' => $p->id, 'qty' => 1]],
            customer: $referee,
            shippingAddress: 'London, UK',
            paymentType: 'cash', // Cash confirms order immediately
            referralCode: $refCode
        );

        $this->assertEquals($refCode, $sale->applied_referral_code);

        // Referrer should be rewarded 200 loyalty points
        $this->assertDatabaseHas('customer_referrals', [
            'referrer_id' => $referrer->id,
            'referee_id' => $referee->id,
            'status' => 'rewarded',
        ]);
        $this->assertEquals(200, $referrer->fresh()->loyalty_points);

        // 3. Anti-fraud: Second order cannot use referral again
        try {
            $referralService->validateReferralCode($refCode, $referee, 50.00);
            $this->fail('Should have blocked repeated referral');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('only valid on your first Skinora order', $e->getMessage());
        }
    }

    /**
     * Test 6: Recurring skincare subscriptions: create, pause, resume, cancel, and renewal batch.
     */
    public function test_skincare_subscription_lifecycle_and_renewal_batch(): void
    {
        $subscriptionService = app(SubscriptionService::class);
        $customer = Customer::create([
            'name' => 'Cate Blanchett',
            'email' => 'cate@melbourne.au',
            'status' => 'active',
        ]);

        $product = $this->createProduct('Stem Cell Elixir', 100.00, 20);

        // 1. Create 30-day replenishment subscription
        $sub = $subscriptionService->createSubscription(
            customer: $customer,
            product: $product,
            bundle: null,
            intervalDays: 30,
            quantity: 1,
            paymentMethod: 'card',
            shippingAddress: 'Melbourne, Australia'
        );

        $this->assertTrue($sub->isActive());
        $this->assertEquals(30, $sub->billing_interval_days);
        $this->assertEquals(90.00, $sub->recurring_subtotal); // 10% subscribe & save discount

        // 2. Pause
        $subscriptionService->pause($sub);
        $this->assertTrue($sub->fresh()->isPaused());

        // 3. Resume
        $subscriptionService->resume($sub);
        $this->assertTrue($sub->fresh()->isActive());

        // 4. Renewal batch execution when due
        $sub->update(['next_billing_date' => Carbon::yesterday()]); // Make due
        $renewals = $subscriptionService->processRenewals();

        $this->assertEquals(1, $renewals['processed']);
        $this->assertEquals(1, $renewals['succeeded']);
        $this->assertDatabaseHas('sales', ['subscription_id' => $sub->id]);

        // Stock was decremented: 20 - 1 = 19
        $this->assertEquals(19, $product->fresh()->qty);

        // Next billing date pushed forward by 30 days
        $this->assertTrue($sub->fresh()->next_billing_date->isFuture());

        // 5. Customer cancellation
        $subscriptionService->cancel($sub, 'Customer routine completed');
        $this->assertTrue($sub->fresh()->isCancelled());
        $this->assertEquals('Customer routine completed', $sub->fresh()->cancellation_reason);
    }

    /**
     * Test 7: Full & partial refunds atomically restock bundle items and reverse loyalty points.
     */
    public function test_refund_logic_restocks_bundle_items_and_reverses_loyalty_points(): void
    {
        $refundService = app(RefundService::class);
        $checkoutService = app(EcommerceCheckoutService::class);

        $p1 = $this->createProduct('Peptide Cleanser', 30.00, 10);
        $p2 = $this->createProduct('Ceramide Mist', 40.00, 10);

        $bundle = Bundle::create([
            'name' => 'Barrier Repair Set',
            'type' => 'bundle',
            'price' => 60.00,
            'status' => 'active',
        ]);

        BundleItem::create(['bundle_id' => $bundle->id, 'product_id' => $p1->id, 'quantity' => 1]);
        BundleItem::create(['bundle_id' => $bundle->id, 'product_id' => $p2->id, 'quantity' => 1]);

        $customer = Customer::create([
            'name' => 'Anne Hathaway',
            'email' => 'anne@ny.us',
            'status' => 'active',
        ]);

        // 1. Place order for 2 bundles
        $sale = $checkoutService->processCheckout(
            items: [['bundle_id' => $bundle->id, 'qty' => 2]],
            customer: $customer,
            shippingAddress: 'Manhattan, NYC',
            paymentType: 'card'
        );

        // Stock decreased by 2 for both
        $this->assertEquals(8, $p1->fresh()->qty);
        $this->assertEquals(8, $p2->fresh()->qty);

        $pointsBeforeRefund = $customer->fresh()->loyalty_points;
        $this->assertGreaterThan(0, $pointsBeforeRefund);

        // 2. Execute full refund with inventory restock
        $refundService->processRefund(
            sale: $sale,
            refundAmount: (float) $sale->final_total,
            reason: 'Customer return unopened packaging',
            restockInventory: true,
            actor: $this->admin
        );

        $sale = $sale->fresh();
        $this->assertEquals(Sale::PAYMENT_REFUNDED, $sale->payment_status);
        $this->assertEquals(Sale::STATUS_RETURNED, $sale->status);
        $this->assertEquals((float) $sale->final_total, (float) $sale->refunded_amount);

        // Both bundle constituent products should be restocked back to 10!
        $this->assertEquals(10, $p1->fresh()->qty);
        $this->assertEquals(10, $p2->fresh()->qty);

        // Loyalty points earned should be reversed
        $this->assertEquals(0, $customer->fresh()->loyalty_points);
        $this->assertDatabaseHas('loyalty_transactions', [
            'customer_id' => $customer->id,
            'type' => 'refund_reversal',
        ]);
    }
}

