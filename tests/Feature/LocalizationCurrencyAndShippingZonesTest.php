<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Setting;
use App\Models\ShippingZone;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocalizationCurrencyAndShippingZonesTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Customer $customer;
    protected Category $category;
    protected Product $product;
    protected ShippingZone $ppZone;
    protected ShippingZone $provZone;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['role' => 'admin', 'status' => 'active']);

        $this->category = Category::create([
            'name' => 'Serums & Essences',
            'name_km' => 'សេរ៉ូម និងទឹកតូណាបំប៉ន',
            'description' => 'Targeted clinical botanicals.',
            'description_km' => 'រូបមន្តចំរាញ់ពីរុក្ខជាតិធម្មជាតិ។',
            'status' => 'active',
        ]);

        $this->product = Product::create([
            'category_id' => $this->category->id,
            'supplier_id' => \App\Models\Supplier::create([
                'name' => 'Lab Botanica',
                'contact_name' => 'Dr. S. Vance',
                'email' => 'lab@botanica.com',
                'phone' => '+85512345678',
            ])->id,
            'name' => 'Botanical Vitamin C Elixir',
            'name_km' => 'សេរ៉ូមវីតាមីន C ជំនួយស្បែកភ្លឺថ្លាធម្មជាតិ',
            'barcode' => 'SKN-VITC-001',
            'cost_price' => 20.00,
            'sale_price' => 50.00,
            'qty' => 100,
            'description' => 'High-potency clinical antioxidant.',
            'description_km' => 'រូបមន្តវីតាមីន C ការពារភាពចាស់។',
            'status' => 'active',
        ]);

        $this->customer = Customer::create([
            'name' => 'Channary Sok',
            'email' => 'channary@skinora.kh',
            'password' => bcrypt('Password123!'),
            'membership_number' => 'MEM-BRZ-5501',
            'loyalty_points' => 0,
            'lifetime_points' => 0,
        ]);

        // Shipping zones
        $this->ppZone = ShippingZone::create([
            'code' => 'phnom_penh',
            'name_en' => 'Phnom Penh Urban Express',
            'name_km' => 'រាជធានីភ្នំពេញ (ដឹកជញ្ជូនរហ័ស)',
            'standard_fee' => 1.50,
            'free_shipping_threshold' => 40.00,
            'estimated_delivery_en' => '1-2 Business Days',
            'estimated_delivery_km' => '១-២ ថ្ងៃធ្វើការ',
            'provinces' => ['Phnom Penh', 'ភ្នំពេញ'],
            'is_active' => true,
        ]);

        $this->provZone = ShippingZone::create([
            'code' => 'provinces',
            'name_en' => 'Provincial Express Delivery',
            'name_km' => 'តាមបណ្ដាខេត្តទូទាំងប្រទេស',
            'standard_fee' => 3.00,
            'free_shipping_threshold' => 60.00,
            'estimated_delivery_en' => '2-4 Business Days',
            'estimated_delivery_km' => '២-៤ ថ្ងៃធ្វើការ',
            'provinces' => ['Siem Reap', 'Battambang', 'សៀមរាប'],
            'is_active' => true,
        ]);

        Setting::set('exchange_rate_khr', 4100.00);
        Setting::set('currency', 'USD');
    }

    public function test_api_returns_khmer_translations_when_locale_requested(): void
    {
        // 1. English default request
        $resEn = $this->getJson('/api/v1/store/products/' . $this->product->id);
        $resEn->assertOk()
            ->assertJsonPath('data.name', 'Botanical Vitamin C Elixir')
            ->assertJsonPath('data.name_km', 'សេរ៉ូមវីតាមីន C ជំនួយស្បែកភ្លឺថ្លាធម្មជាតិ');

        // 2. Khmer header request (Accept-Language: km)
        $resKm = $this->withHeader('Accept-Language', 'km')
            ->getJson('/api/v1/store/products/' . $this->product->id);
        $resKm->assertOk()
            ->assertJsonPath('data.name', 'សេរ៉ូមវីតាមីន C ជំនួយស្បែកភ្លឺថ្លាធម្មជាតិ')
            ->assertJsonPath('data.name_en', 'Botanical Vitamin C Elixir');

        // 3. Category Khmer translation via query ?lang=km
        $resCat = $this->getJson('/api/v1/store/categories?lang=km');
        $resCat->assertOk();
        $this->assertEquals('សេរ៉ូម និងទឹកតូណាបំប៉ន', $resCat->json('data.0.name'));
    }

    public function test_storefront_settings_and_shipping_zones(): void
    {
        $res = $this->getJson('/api/v1/store/settings');
        $res->assertOk()
            ->assertJsonPath('data.currency', 'USD');
        $this->assertEquals(4100.0, (float) $res->json('data.exchange_rate_khr'));
        $res->assertJsonStructure([
            'data' => [
                'supported_currencies',
                'shipping_zones',
            ],
        ]);

        $resZones = $this->getJson('/api/v1/store/shipping-zones');
        $resZones->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.code', 'phnom_penh')
            ->assertJsonPath('data.1.code', 'provinces');
        $this->assertEquals(1.5, (float) $resZones->json('data.0.standard_fee'));
        $this->assertEquals(3.0, (float) $resZones->json('data.1.standard_fee'));
    }

    public function test_cart_quote_applies_regional_shipping_rates_and_thresholds(): void
    {
        // 1 item = $50.00
        // For Phnom Penh: free shipping threshold is $40.00 => qualified (fee = $0.00)
        $resPP = $this->postJson('/api/v1/cart/quote', [
            'items' => [
                ['product_id' => $this->product->id, 'qty' => 1],
            ],
            'shipping_zone_code' => 'phnom_penh',
        ]);
        $resPP->assertOk()
            ->assertJsonPath('data.shipping_zone.code', 'phnom_penh');
        $this->assertEquals(0.0, (float) $resPP->json('data.shipping_fee'));

        // For Provincial: free shipping threshold is $60.00 => $50.00 not qualified (fee = $3.00)
        $resProv = $this->postJson('/api/v1/cart/quote', [
            'items' => [
                ['product_id' => $this->product->id, 'qty' => 1],
            ],
            'shipping_zone_code' => 'provinces',
        ]);
        $resProv->assertOk()
            ->assertJsonPath('data.shipping_zone.code', 'provinces');
        $this->assertEquals(3.0, (float) $resProv->json('data.shipping_fee'));
    }

    public function test_checkout_preserves_historical_exchange_rate_and_khr_currency(): void
    {
        // Place order in KHR
        $res = $this->actingAs($this->customer, 'sanctum')
            ->postJson('/api/v1/customer/checkout', [
                'items' => [
                    ['product_id' => $this->product->id, 'qty' => 1],
                ],
                'shipping_address' => 'Street 214, Daun Penh, Phnom Penh, Cambodia',
                'shipping_zone_code' => 'phnom_penh',
                'payment_type' => 'qr',
                'currency' => 'KHR',
            ]);

        $res->assertCreated();
        $saleId = $res->json('order.id');
        $invoiceNumber = $res->json('order.invoice_number');

        $sale = Sale::find($saleId);
        $this->assertNotNull($sale);
        $this->assertEquals('KHR', $sale->currency);
        $this->assertEquals(4100.0, (float) $sale->exchange_rate);
        $this->assertEquals('phnom_penh', $sale->shippingZone->code);

        // Subtotal $50.00 + 5% tax ($2.50) + free shipping ($0.00) = $52.50 base USD
        $this->assertEquals(52.50, (float) $sale->final_total);

        // KHR formatted calculation at 4100 rate: $52.50 * 4100 = 215,250 ៛
        $this->assertEquals('215,250 ៛', $sale->formatted_final_total);
        $this->assertEquals(215250.0, $sale->currency_final_total);

        // CRITICAL INVARIANT: Admin updates live exchange rate to 4300.00
        Setting::set('exchange_rate_khr', 4300.00);

        // Fresh instance from DB
        $freshSale = $sale->fresh();
        // The existing order MUST retain historical rate 4100.00 and $52.50 * 4100 = 215,250 ៛
        $this->assertEquals(4100.0, (float) $freshSale->exchange_rate);
        $this->assertEquals('KHR', $freshSale->currency);
        $this->assertEquals('215,250 ៛', $freshSale->formatted_final_total);
        $this->assertEquals(215250.0, $freshSale->currency_final_total);

        // API order endpoint also returns preserved historical amounts
        $orderRes = $this->actingAs($this->customer, 'sanctum')
            ->getJson('/api/v1/customer/orders/' . $sale->id);
        $orderRes->assertOk()
            ->assertJsonPath('data.currency', 'KHR')
            ->assertJsonPath('data.formatted_final_total', '215,250 ៛');
        $this->assertEquals(4100.0, (float) $orderRes->json('data.exchange_rate'));
        $this->assertEquals(215250.0, (float) $orderRes->json('data.currency_final_total'));
    }
}
