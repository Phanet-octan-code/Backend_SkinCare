<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Setting;
use App\Models\Stock;
use App\Models\Supplier;
use App\Models\User;
use App\Services\CheckoutService;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class SettingsAndUiModuleTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $manager;
    protected User $cashier;
    protected User $inventory;
    protected User $hr;
    protected Product $product;

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
            'name' => 'Rose Hydrating Mist',
            'barcode' => 'TEST-ROSE-MIST',
            'cost_price' => 10.00,
            'sale_price' => 25.00,
            'qty' => 50,
            'low_stock_threshold' => 10,
            'status' => 'active',
        ]);

        Stock::create([
            'product_id' => $this->product->id,
            'qty' => 50,
        ]);
    }

    /**
     * Test admin can view settings management dashboard.
     */
    public function test_admin_can_view_settings_page(): void
    {
        $response = $this->actingAs($this->admin)->get(route('settings.index'));

        $response->assertStatus(200);
        $response->assertSee('System & Store Settings');
        $response->assertSee('Boutique Information & Branding');
        $response->assertSee('Currency Configuration & Rounding');
        $response->assertSee('Invoice Numbering & Standards');
        $response->assertSee('Boutique Working Hours & Punctuality');
        $response->assertSee('Role Matrix & Module Permissions');
    }

    /**
     * Test non-admin roles (manager, cashier, inventory, hr) are strictly forbidden from settings.
     */
    public function test_non_admin_users_are_forbidden_from_settings(): void
    {
        $unauthorizedUsers = [$this->manager, $this->cashier, $this->inventory, $this->hr];

        foreach ($unauthorizedUsers as $user) {
            $responseGet = $this->actingAs($user)->get(route('settings.index'));
            $responseGet->assertStatus(403);

            $responsePost = $this->actingAs($user)->post(route('settings.update'), [
                'business_name' => 'Hacked Store Name',
            ]);
            $responsePost->assertStatus(403);
        }
    }

    /**
     * Test admin can update system and store settings successfully.
     */
    public function test_admin_can_update_settings_successfully(): void
    {
        $updatePayload = [
            'business_name' => 'Luxury Glow Skincare Clinic',
            'business_address' => '456 Monivong Blvd, Phnom Penh',
            'business_phone' => '+855 23 999 888',
            'business_email' => 'glow@skincare-clinic.com',
            'currency' => 'KHR',
            'exchange_rate_khr' => 4150,
            'tax_rate' => 10.00,
            'invoice_prefix' => 'GLOW-',
            'invoice_number_format' => 'prefix_seq',
            'receipt_footer' => 'Stay radiant! Visit glow-skincare.com',
            'printer_type' => '80mm Thermal Receipt (ESC/POS)',
            'cash_drawer_kick' => 1,
            'default_low_stock_threshold' => 15,
            'work_start_time' => '08:30',
            'work_end_time' => '17:30',
            'late_threshold_time' => '08:45',
            'standard_daily_hours' => 9.0,
        ];

        $response = $this->actingAs($this->admin)->post(route('settings.update'), $updatePayload);

        $response->assertRedirect(route('settings.index'));
        $response->assertSessionHas('success');

        // Verify values persisted in database and cache updated
        $this->assertEquals('Luxury Glow Skincare Clinic', Setting::get('business_name'));
        $this->assertEquals('KHR', Setting::get('currency'));
        $this->assertEquals('4150.00', Setting::get('exchange_rate_khr'));
        $this->assertEquals('10.00', Setting::get('tax_rate'));
        $this->assertEquals('GLOW-', Setting::get('invoice_prefix'));
        $this->assertEquals('prefix_seq', Setting::get('invoice_number_format'));
        $this->assertEquals('Stay radiant! Visit glow-skincare.com', Setting::get('receipt_footer'));
        $this->assertEquals('15', Setting::get('default_low_stock_threshold'));
        $this->assertEquals('08:30', Setting::get('work_start_time'));
        $this->assertEquals('08:45', Setting::get('late_threshold_time'));
        $this->assertEquals('9', Setting::get('standard_daily_hours'));
    }

    /**
     * Test USD and KHR currency formatting and rounding rules.
     */
    public function test_currency_formatting_for_usd_and_khr(): void
    {
        // USD: 2 decimal places with $ symbol
        $this->assertEquals('$12.50', Setting::formatMoney(12.50, 'USD'));
        $this->assertEquals('$100.00', Setting::formatMoney(100, 'USD'));
        $this->assertEquals('12.50', Setting::formatMoney(12.50, 'USD', false));

        // KHR: 0 decimal places rounded to whole riel with ៛ symbol
        $this->assertEquals('51,250 ៛', Setting::formatMoney(51250, 'KHR'));
        $this->assertEquals('50,001 ៛', Setting::formatMoney(50000.75, 'KHR'));
        $this->assertEquals('51,250', Setting::formatMoney(51250, 'KHR', false));

        // Symbols
        $this->assertEquals('$', Setting::currencySymbol('USD'));
        $this->assertEquals('៛', Setting::currencySymbol('KHR'));
    }

    /**
     * Test currency conversion utility between USD and KHR.
     */
    public function test_currency_conversion_utility(): void
    {
        Setting::set('exchange_rate_khr', '4100.00');

        // USD to KHR
        $khrAmount = Setting::convert(10.0, 'USD', 'KHR');
        $this->assertEquals(41000.0, $khrAmount);

        // KHR to USD
        $usdAmount = Setting::convert(41000.0, 'KHR', 'USD');
        $this->assertEquals(10.0, $usdAmount);

        // Same currency
        $this->assertEquals(50.0, Setting::convert(50.0, 'USD', 'USD'));
    }

    /**
     * Test historical sale preserves its explicit recorded currency and exchange rate.
     */
    public function test_historical_sale_preserves_explicit_currency_and_rate(): void
    {
        // 1. Initial sale in USD
        $sale = Sale::create([
            'user_id' => $this->cashier->id,
            'invoice_number' => 'TEST-HIST-001',
            'subtotal' => 50.00,
            'discount' => 0.00,
            'tax' => 2.50,
            'final_total' => 52.50,
            'payment_type' => 'cash',
            'payment_status' => 'paid',
            'status' => 'completed',
            'currency' => 'USD',
            'exchange_rate' => 4100.0,
        ]);

        $this->assertEquals('$52.50', $sale->formatted_final_total);

        // 2. Later admin updates system currency setting to KHR
        Setting::set('currency', 'KHR');

        // 3. Historical sale must STILL format as USD ($52.50) without silent reinterpretation!
        $sale->refresh();
        $this->assertEquals('USD', $sale->currency);
        $this->assertEquals('$52.50', $sale->formatAmount($sale->final_total));
    }

    /**
     * Test POS checkout applies configurable tax rate and records explicit currency.
     */
    public function test_pos_checkout_applies_configured_tax_rate_and_records_currency(): void
    {
        // Configure 10% tax rate
        Setting::set('tax_rate', '10.00');
        Setting::set('currency', 'USD');

        $checkoutService = app(CheckoutService::class);

        $result = $checkoutService->processCheckout(
            cashier: $this->cashier,
            items: [['product_id' => $this->product->id, 'qty' => 2]],
            paymentType: 'cash',
            amountReceived: 100.00
        );

        $sale = $result['sale'];

        // Verify tax equals 10% of net taxable amount (subtotal - discount)
        $expectedTax = round(((float) $sale->subtotal - (float) $sale->discount) * 0.10, 2);
        $this->assertEquals(50.00, (float) $sale->subtotal);
        $this->assertEquals($expectedTax, (float) $sale->tax);
        $this->assertEquals(round((float) $sale->subtotal - (float) $sale->discount + $expectedTax, 2), (float) $sale->final_total);
        $this->assertEquals('USD', $sale->currency);
    }

    /**
     * Test dynamic invoice numbering rules (custom prefix and formats).
     */
    public function test_dynamic_invoice_numbering_rules(): void
    {
        // 1. Prefix + Continuous sequence format
        Setting::set('invoice_prefix', 'CLINIC-');
        Setting::set('invoice_number_format', 'prefix_seq');

        $inv1 = Sale::generateInvoiceNumber();
        $this->assertEquals('CLINIC-0001', $inv1);

        // Simulate created sale
        Sale::create([
            'user_id' => $this->cashier->id,
            'invoice_number' => $inv1,
            'subtotal' => 20.00,
            'discount' => 0.00,
            'tax' => 1.00,
            'final_total' => 21.00,
            'payment_type' => 'cash',
            'status' => 'completed',
        ]);

        $inv2 = Sale::generateInvoiceNumber();
        $this->assertEquals('CLINIC-0002', $inv2);

        // 2. Prefix + Date + Sequence format
        Setting::set('invoice_number_format', 'prefix_date_seq');
        $dateStr = date('Ymd');
        $invWithDate = Sale::generateInvoiceNumber();
        $this->assertStringStartsWith("CLINIC-{$dateStr}-", $invWithDate);
    }

    /**
     * Test language switcher route and localized translations between EN and KM.
     */
    public function test_language_switcher_and_localization(): void
    {
        // 1. Switch to Khmer
        $responseKm = $this->actingAs($this->cashier)->get(route('language.switch', 'km'));
        $responseKm->assertSessionHas('locale', 'km');

        // 2. Request with Khmer locale in session
        $responseDashboard = $this->withSession(['locale' => 'km'])
            ->actingAs($this->cashier)
            ->get(route('dashboard'));

        $responseDashboard->assertStatus(200);
        $this->assertEquals('km', App::getLocale());

        // Check Khmer translated strings
        $this->assertEquals('ផ្ទាំងគ្រប់គ្រង', __('messages.dashboard'));
        $this->assertEquals('ចំណុចលក់ (POS)', __('messages.pos_terminal'));
        $this->assertEquals('សន្និធិ និងស្តុក', __('messages.inventory_stock'));

        // 3. Switch back to English
        $responseEn = $this->actingAs($this->cashier)->get(route('language.switch', 'en'));
        $responseEn->assertSessionHas('locale', 'en');

        $this->withSession(['locale' => 'en'])
            ->actingAs($this->cashier)
            ->get(route('dashboard'));

        $this->assertEquals('en', App::getLocale());
        $this->assertEquals('Dashboard', __('messages.dashboard'));
    }
}
