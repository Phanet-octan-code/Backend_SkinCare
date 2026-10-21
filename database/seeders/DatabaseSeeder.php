<?php

namespace Database\Seeders;

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
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $this->seedUsers();
            $this->seedSettings();

            // Prevent duplicate seed entries if database already contains records
            if (Position::count() > 0 && Category::count() > 0 && Product::count() > 0) {
                if (isset($this->command)) {
                    $this->command->info('Database is already seeded with full catalog and demonstration records.');
                }
                return;
            }

            $categories = $this->seedCategories();
            $positions = $this->seedPositions();
            $this->seedEmployees($positions);
            $suppliers = $this->seedSuppliers();
            $customers = $this->seedCustomers();
            $products = $this->seedProducts($categories, $suppliers);
            $this->seedPromotions($products);
            $this->seedStocksAndTransactions($products, $suppliers);
            $this->seedAttendances();
            $this->seedSales($products, $customers);
            $this->seedStockTransfers($products);
        });
    }

    private function seedSettings(): void
    {
        $defaults = \App\Models\Setting::defaults();
        foreach ($defaults as $key => $val) {
            \App\Models\Setting::firstOrCreate(
                ['key' => $key],
                ['value' => $val]
            );
        }
    }

    private function seedUsers(): void
    {
        $defaultPassword = env('INITIAL_ADMIN_PASSWORD', 'Admin@SkinCare2026');

        $users = [
            [
                'name' => 'Store Owner & Admin',
                'email' => env('INITIAL_ADMIN_EMAIL', 'admin@skincare.com'),
                'password' => Hash::make($defaultPassword),
                'role' => 'admin',
                'status' => 'active',
            ],
            [
                'name' => 'Store Manager',
                'email' => 'manager@skincare.com',
                'password' => Hash::make($defaultPassword),
                'role' => 'manager',
                'status' => 'active',
            ],
            [
                'name' => 'Lead Cashier',
                'email' => 'cashier@skincare.com',
                'password' => Hash::make($defaultPassword),
                'role' => 'cashier',
                'status' => 'active',
            ],
            [
                'name' => 'Inventory Specialist',
                'email' => 'inventory@skincare.com',
                'password' => Hash::make($defaultPassword),
                'role' => 'inventory',
                'status' => 'active',
            ],
            [
                'name' => 'HR Coordinator',
                'email' => 'hr@skincare.com',
                'password' => Hash::make($defaultPassword),
                'role' => 'hr',
                'status' => 'active',
            ],
        ];

        foreach ($users as $userData) {
            User::updateOrCreate(['email' => $userData['email']], $userData);
        }
    }

    private function seedCategories(): array
    {
        $data = [
            ['name' => 'Cleansers & Washes', 'description' => 'Gentle foaming, gel, and oil-based facial cleansers'],
            ['name' => 'Toners & Essences', 'description' => 'Hydrating, balancing, and exfoliating facial toners'],
            ['name' => 'Serums & Ampoules', 'description' => 'Concentrated active formulas for targeted skin concerns'],
            ['name' => 'Moisturizers & Creams', 'description' => 'Barrier-restoring, ceramide, and peptide hydration creams'],
            ['name' => 'Sunscreens & UV Shields', 'description' => 'Broad-spectrum SPF 50+ PA++++ chemical and mineral sunscreens'],
            ['name' => 'Exfoliants & Peels', 'description' => 'AHA, BHA, and PHA chemical exfoliants and soothing scrubs'],
            ['name' => 'Eye & Lip Care', 'description' => 'Specialized anti-fatigue eye creams and nourishing lip treatments'],
            ['name' => 'Masks & Treatments', 'description' => 'Hydrogel sheet masks, clay purifying masks, and overnight sleeping packs'],
        ];

        $categories = [];
        foreach ($data as $cat) {
            $categories[$cat['name']] = Category::create([
                'name' => $cat['name'],
                'description' => $cat['description'],
                'status' => 'active',
            ]);
        }

        return $categories;
    }

    private function seedPositions(): array
    {
        $data = [
            ['name' => 'Store General Manager', 'base_salary' => 1500.00],
            ['name' => 'Senior Skincare Consultant', 'base_salary' => 850.00],
            ['name' => 'POS Cashier & Receptionist', 'base_salary' => 550.00],
            ['name' => 'Inventory Officer', 'base_salary' => 650.00],
            ['name' => 'HR & Payroll Officer', 'base_salary' => 750.00],
        ];

        $positions = [];
        foreach ($data as $pos) {
            $positions[$pos['name']] = Position::create($pos);
        }

        return $positions;
    }

    private function seedEmployees(array $positions): void
    {
        $data = [
            [
                'name' => 'Emily Thorne',
                'position_id' => $positions['Store General Manager']->id,
                'phone' => '+1-555-0101',
                'email' => 'emily.thorne@skincare.com',
                'start_date' => '2024-01-15',
                'status' => 'active',
            ],
            [
                'name' => 'Sophia Chen',
                'position_id' => $positions['Senior Skincare Consultant']->id,
                'phone' => '+1-555-0102',
                'email' => 'sophia.chen@skincare.com',
                'start_date' => '2024-03-01',
                'status' => 'active',
            ],
            [
                'name' => 'Liam Vance',
                'position_id' => $positions['POS Cashier & Receptionist']->id,
                'phone' => '+1-555-0103',
                'email' => 'liam.vance@skincare.com',
                'start_date' => '2024-06-10',
                'status' => 'active',
            ],
            [
                'name' => 'Marcus Reed',
                'position_id' => $positions['Inventory Officer']->id,
                'phone' => '+1-555-0104',
                'email' => 'marcus.reed@skincare.com',
                'start_date' => '2024-02-20',
                'status' => 'active',
            ],
            [
                'name' => 'Chloe Bennett',
                'position_id' => $positions['HR & Payroll Officer']->id,
                'phone' => '+1-555-0105',
                'email' => 'chloe.bennett@skincare.com',
                'start_date' => '2024-04-05',
                'status' => 'active',
            ],
        ];

        foreach ($data as $emp) {
            Employee::create($emp);
        }
    }

    private function seedSuppliers(): array
    {
        $data = [
            [
                'name' => 'DermaCare Asia Distribution',
                'phone' => '+65-6789-0111',
                'address' => '12 Marina Blvd, #18-01 Marina Bay Financial Centre, Singapore',
                'email' => 'orders@dermacare-asia.com',
                'status' => 'active',
            ],
            [
                'name' => 'PureSkin Naturals Wholesale',
                'phone' => '+82-2-555-8900',
                'address' => '73 Teheran-ro, Gangnam-gu, Seoul, South Korea',
                'email' => 'supply@pureskin-korea.kr',
                'status' => 'active',
            ],
            [
                'name' => 'Lumina Cosmecuticals Co.',
                'phone' => '+1-212-555-7800',
                'address' => '450 Lexington Ave, New York, NY 10017, USA',
                'email' => 'contact@luminacosmetics.com',
                'status' => 'active',
            ],
            [
                'name' => 'AuraBotanica Herbals Corp',
                'phone' => '+81-3-5550-1234',
                'address' => '5-7-1 Ginza, Chuo-ku, Tokyo 104-0061, Japan',
                'email' => 'info@aurabotanica.jp',
                'status' => 'active',
            ],
        ];

        $suppliers = [];
        foreach ($data as $sup) {
            $suppliers[] = Supplier::create($sup);
        }

        return $suppliers;
    }

    private function seedCustomers(): array
    {
        $data = [
            [
                'name' => 'Olivia Montgomery',
                'phone' => '+1-555-9011',
                'email' => 'olivia.m@example.com',
                'address' => '742 Evergreen Terrace, Springfield',
                'membership_number' => 'MEM-VIP-001',
                'membership_level' => 'Platinum',
                'status' => 'active',
            ],
            [
                'name' => 'Ava Katherine',
                'phone' => '+1-555-9012',
                'email' => 'ava.k@example.com',
                'address' => '10880 Wilshire Blvd, Los Angeles, CA',
                'membership_number' => 'MEM-VIP-002',
                'membership_level' => 'Gold',
                'status' => 'active',
            ],
            [
                'name' => 'Lucas Sterling',
                'phone' => '+1-555-9013',
                'email' => 'lucas.s@example.com',
                'address' => '221B Baker St, London NW1',
                'membership_number' => 'MEM-VIP-003',
                'membership_level' => 'Silver',
                'status' => 'active',
            ],
            [
                'name' => 'Mia Isabella',
                'phone' => '+1-555-9014',
                'email' => 'mia.isa@example.com',
                'address' => '55 Wall Street, New York, NY',
                'membership_number' => 'MEM-VIP-004',
                'membership_level' => 'Bronze',
                'status' => 'active',
            ],
        ];

        $customers = [];
        foreach ($data as $cust) {
            $customers[] = Customer::create($cust);
        }

        return $customers;
    }

    private function seedProducts(array $categories, array $suppliers): array
    {
        $sup1 = $suppliers[0]->id;
        $sup2 = $suppliers[1]->id;
        $sup3 = $suppliers[2]->id;
        $sup4 = $suppliers[3]->id;

        $items = [
            [
                'category_id' => $categories['Cleansers & Washes']->id,
                'supplier_id' => $sup1,
                'name' => 'CeraVe Hydrating Facial Cleanser 236ml',
                'barcode' => '3337875597180',
                'cost_price' => 9.50,
                'sale_price' => 16.99,
                'qty' => 45,
                'low_stock_threshold' => 10,
                'description' => 'Non-comedogenic ceramide cleanser formulated with hyaluronic acid for dry to normal skin.',
            ],
            [
                'category_id' => $categories['Serums & Ampoules']->id,
                'supplier_id' => $sup3,
                'name' => 'The Ordinary Niacinamide 10% + Zinc 1% 30ml',
                'barcode' => '769915190602',
                'cost_price' => 4.20,
                'sale_price' => 8.50,
                'qty' => 60,
                'low_stock_threshold' => 15,
                'description' => 'High-strength vitamin and mineral blemish formula to reduce sebum congestion.',
            ],
            [
                'category_id' => $categories['Toners & Essences']->id,
                'supplier_id' => $sup2,
                'name' => 'COSRX Advanced Snail 96 Mucin Power Essence 100ml',
                'barcode' => '8809416470009',
                'cost_price' => 12.00,
                'sale_price' => 22.00,
                'qty' => 38,
                'low_stock_threshold' => 8,
                'description' => 'Formulated with 96% snail secretion filtrate to deeply repair barrier and quench dehydration.',
            ],
            [
                'category_id' => $categories['Sunscreens & UV Shields']->id,
                'supplier_id' => $sup1,
                'name' => 'La Roche-Posay Anthelios UVMune 400 SPF50+ 50ml',
                'barcode' => '3337875777414',
                'cost_price' => 15.00,
                'sale_price' => 28.50,
                'qty' => 50,
                'low_stock_threshold' => 12,
                'description' => 'Ultra-long UVA protection, invisible fluid finish, water and sweat resistant.',
            ],
            [
                'category_id' => $categories['Exfoliants & Peels']->id,
                'supplier_id' => $sup3,
                'name' => "Paula's Choice Skin Perfecting 2% BHA Liquid 118ml",
                'barcode' => '0655439020108',
                'cost_price' => 20.00,
                'sale_price' => 35.00,
                'qty' => 28,
                'low_stock_threshold' => 6,
                'description' => 'Leave-on exfoliant with salicylic acid clears blackheads, refines pores, and evens tone.',
            ],
            [
                'category_id' => $categories['Sunscreens & UV Shields']->id,
                'supplier_id' => $sup2,
                'name' => 'Beauty of Joseon Relief Sun Rice + Probiotics SPF50+ 50ml',
                'barcode' => '8809738316752',
                'cost_price' => 9.00,
                'sale_price' => 18.00,
                'qty' => 55,
                'low_stock_threshold' => 10,
                'description' => 'Lightweight chemical sunscreen enriched with 30% rice extract and grain fermented probiotics.',
            ],
            [
                'category_id' => $categories['Serums & Ampoules']->id,
                'supplier_id' => $sup2,
                'name' => 'Innisfree Green Tea Seed Hyaluronic Serum 80ml',
                'barcode' => '8809652900742',
                'cost_price' => 16.00,
                'sale_price' => 30.00,
                'qty' => 30,
                'low_stock_threshold' => 8,
                'description' => 'Intensive moisturizing serum with Beauty Green Tea and 5 types of hyaluronic acid.',
            ],
            [
                'category_id' => $categories['Toners & Essences']->id,
                'supplier_id' => $sup2,
                'name' => 'Anua Heartleaf 77% Soothing Toner 250ml',
                'barcode' => '8809640730078',
                'cost_price' => 13.50,
                'sale_price' => 24.00,
                'qty' => 35,
                'low_stock_threshold' => 10,
                'description' => 'Sub-acidic soothing toner formulated with 77% Houttuynia Cordata extract to calm irritation.',
            ],
            [
                'category_id' => $categories['Serums & Ampoules']->id,
                'supplier_id' => $sup2,
                'name' => 'Skin1004 Madagascar Centella Asiatica Ampoule 100ml',
                'barcode' => '8809576261004',
                'cost_price' => 11.00,
                'sale_price' => 21.00,
                'qty' => 42,
                'low_stock_threshold' => 10,
                'description' => '100% pure Centella Asiatica extract from Madagascar restores damaged skin and soothes inflammation.',
            ],
            [
                'category_id' => $categories['Moisturizers & Creams']->id,
                'supplier_id' => $sup3,
                'name' => "Kiehl's Ultra Facial Cream 50ml",
                'barcode' => '3605970360757',
                'cost_price' => 22.00,
                'sale_price' => 39.00,
                'qty' => 25,
                'low_stock_threshold' => 5,
                'description' => '24-hour daily lightweight facial moisturizer with squalane and glacial glycoprotein.',
            ],
            [
                'category_id' => $categories['Masks & Treatments']->id,
                'supplier_id' => $sup4,
                'name' => 'Mediheal Tea Tree Essential Mask (10 Sheets)',
                'barcode' => '8809615054321',
                'cost_price' => 9.00,
                'sale_price' => 19.50,
                'qty' => 5, // Low stock on purpose to test alert
                'low_stock_threshold' => 10,
                'description' => 'Calming sheet mask infused with tea tree leaf oil, chamomile, and centella.',
            ],
            [
                'category_id' => $categories['Eye & Lip Care']->id,
                'supplier_id' => $sup4,
                'name' => 'Laneige Lip Sleeping Mask Berry 20g',
                'barcode' => '8809643064439',
                'cost_price' => 12.00,
                'sale_price' => 24.00,
                'qty' => 4, // Low stock on purpose to test alert
                'low_stock_threshold' => 8,
                'description' => 'Overnight leave-on lip mask enriched with Berry Fruit Complex, Vitamin C, and coconut oil.',
            ],
        ];

        $products = [];
        foreach ($items as $item) {
            $item['status'] = 'active';
            $products[] = Product::create($item);
        }

        return $products;
    }

    private function seedPromotions(array $products): void
    {
        // 1. General store-wide promotion (no product_promotion rows)
        $generalPromo = Promotion::create([
            'name' => 'Grand Opening Celebration - 10% Off Storewide',
            'discount_type' => 'percentage',
            'discount_value' => 10.00,
            'start_date' => Carbon::now()->subDays(5)->toDateString(),
            'end_date' => Carbon::now()->addDays(25)->toDateString(),
            'status' => 'active',
        ]);

        // 2. Product-specific promotion: Sunscreen Summer Defense ($5.00 fixed discount on sunscreens)
        $sunscreenPromo = Promotion::create([
            'name' => 'Summer UV Shield - $5.00 Off Selected Sunscreens',
            'discount_type' => 'fixed',
            'discount_value' => 5.00,
            'start_date' => Carbon::now()->subDays(2)->toDateString(),
            'end_date' => Carbon::now()->addDays(20)->toDateString(),
            'status' => 'active',
        ]);

        // Attach sunscreens (La Roche-Posay and Beauty of Joseon)
        $sunscreenIds = array_filter(array_map(function ($p) {
            return str_contains($p->name, 'Sun') || str_contains($p->name, 'Anthelios') ? $p->id : null;
        }, $products));

        $sunscreenPromo->products()->attach($sunscreenIds);

        // 3. Product-specific promotion: Active Serums Glow (15% off)
        $serumPromo = Promotion::create([
            'name' => 'Radiant Glow Serum Week - 15% Off Serums',
            'discount_type' => 'percentage',
            'discount_value' => 15.00,
            'start_date' => Carbon::now()->subDays(1)->toDateString(),
            'end_date' => Carbon::now()->addDays(14)->toDateString(),
            'status' => 'active',
        ]);

        $serumIds = array_filter(array_map(function ($p) {
            return str_contains($p->name, 'Serum') || str_contains($p->name, 'Niacinamide') || str_contains($p->name, 'Ampoule') ? $p->id : null;
        }, $products));

        $serumPromo->products()->attach($serumIds);
    }

    private function seedStocksAndTransactions(array $products, array $suppliers): void
    {
        $admin = User::where('role', 'admin')->first();
        $supplier = $suppliers[0];

        foreach ($products as $product) {
            // Seed stocks table as single source of truth
            Stock::create([
                'product_id' => $product->id,
                'qty' => $product->qty,
            ]);

            // Log initial stock-in transaction
            StockTransaction::create([
                'product_id' => $product->id,
                'user_id' => $admin->id,
                'supplier_id' => $supplier->id,
                'type' => StockTransaction::TYPE_STOCK_IN,
                'qty' => $product->qty,
                'reference_number' => 'PO-' . date('Ym') . '-' . str_pad((string) $product->id, 4, '0', STR_PAD_LEFT),
                'notes' => 'Initial bulk delivery receipt into main inventory',
                'date' => Carbon::now()->subDays(10)->toDateString(),
            ]);
        }
    }

    private function seedAttendances(): void
    {
        $employees = Employee::all();

        foreach ($employees as $employee) {
            // Seed attendance for today
            Attendance::create([
                'employee_id' => $employee->id,
                'date' => Carbon::today()->toDateString(),
                'check_in' => '08:55:00',
                'check_out' => '17:30:00',
                'status' => 'present',
                'notes' => 'On-time morning shift',
            ]);

            // Seed attendance for yesterday
            Attendance::create([
                'employee_id' => $employee->id,
                'date' => Carbon::yesterday()->toDateString(),
                'check_in' => '09:05:00',
                'check_out' => '17:35:00',
                'status' => 'late',
                'notes' => '5 minutes late due to traffic',
            ]);
        }
    }

    private function seedSales(array $products, array $customers): void
    {
        $cashier = User::where('role', 'cashier')->first() ?? User::first();
        $admin = User::where('role', 'admin')->first() ?? User::first();
        $vipCustomer = $customers[0]; // Olivia (Platinum)
        $goldCustomer = $customers[1]; // Ava (Gold)
        $silverCustomer = $customers[2]; // Lucas (Silver)

        // Base sales list with varied historical dates, payment types (cash, card, qr), and statuses
        $salesData = [
            // Today Sales
            [
                'user' => $cashier,
                'customer' => $vipCustomer,
                'invoice' => 'INV-' . date('Ymd') . '-0001',
                'created_at' => Carbon::now()->subHours(4),
                'payment_type' => 'card',
                'payment_status' => 'paid',
                'status' => 'completed',
                'items' => [
                    ['product' => $products[2], 'qty' => 1, 'price' => 22.00, 'discount' => 3.30], // COSRX Snail
                    ['product' => $products[4], 'qty' => 1, 'price' => 35.00, 'discount' => 5.25], // Paula's Choice
                    ['product' => $products[1], 'qty' => 1, 'price' => 8.50, 'discount' => 1.28],  // The Ordinary
                ],
            ],
            [
                'user' => $cashier,
                'customer' => null, // Walk-in Guest
                'invoice' => 'INV-' . date('Ymd') . '-0002',
                'created_at' => Carbon::now()->subHours(2),
                'payment_type' => 'cash',
                'payment_status' => 'paid',
                'status' => 'completed',
                'items' => [
                    ['product' => $products[0], 'qty' => 1, 'price' => 16.99, 'discount' => 0.00], // CeraVe Cleanser
                    ['product' => $products[3], 'qty' => 1, 'price' => 28.50, 'discount' => 0.00], // La Roche-Posay
                ],
            ],
            [
                'user' => $cashier,
                'customer' => $goldCustomer,
                'invoice' => 'INV-' . date('Ymd') . '-0003',
                'created_at' => Carbon::now()->subMinutes(45),
                'payment_type' => 'qr',
                'payment_status' => 'paid',
                'status' => 'completed',
                'items' => [
                    ['product' => $products[5], 'qty' => 2, 'price' => 18.00, 'discount' => 3.60], // Beauty of Joseon Sun
                    ['product' => $products[7], 'qty' => 1, 'price' => 24.00, 'discount' => 2.40], // Anua Toner
                ],
            ],
            // Yesterday Sales (for historical comparison)
            [
                'user' => $cashier,
                'customer' => $silverCustomer,
                'invoice' => 'INV-' . Carbon::yesterday()->format('Ymd') . '-0001',
                'created_at' => Carbon::yesterday()->setHour(11)->setMinute(15),
                'payment_type' => 'cash',
                'payment_status' => 'paid',
                'status' => 'completed',
                'items' => [
                    ['product' => $products[0], 'qty' => 2, 'price' => 16.99, 'discount' => 1.70],
                    ['product' => $products[9], 'qty' => 1, 'price' => 39.00, 'discount' => 1.95], // Kiehl's
                ],
            ],
            [
                'user' => $cashier,
                'customer' => null,
                'invoice' => 'INV-' . Carbon::yesterday()->format('Ymd') . '-0002',
                'created_at' => Carbon::yesterday()->setHour(14)->setMinute(30),
                'payment_type' => 'card',
                'payment_status' => 'paid',
                'status' => 'completed',
                'items' => [
                    ['product' => $products[3], 'qty' => 2, 'price' => 28.50, 'discount' => 0.00],
                    ['product' => $products[2], 'qty' => 1, 'price' => 22.00, 'discount' => 0.00],
                ],
            ],
            [
                'user' => $admin,
                'customer' => $vipCustomer,
                'invoice' => 'INV-' . Carbon::yesterday()->format('Ymd') . '-0003',
                'created_at' => Carbon::yesterday()->setHour(16)->setMinute(45),
                'payment_type' => 'qr',
                'payment_status' => 'paid',
                'status' => 'completed',
                'items' => [
                    ['product' => $products[8], 'qty' => 2, 'price' => 21.00, 'discount' => 6.30], // Centella
                    ['product' => $products[6], 'qty' => 1, 'price' => 30.00, 'discount' => 4.50], // Innisfree
                ],
            ],
            // Past 7 Days Sales
            [
                'user' => $cashier,
                'customer' => null,
                'invoice' => 'INV-' . Carbon::now()->subDays(2)->format('Ymd') . '-0001',
                'created_at' => Carbon::now()->subDays(2)->setHour(10)->setMinute(20),
                'payment_type' => 'cash',
                'payment_status' => 'paid',
                'status' => 'completed',
                'items' => [
                    ['product' => $products[5], 'qty' => 3, 'price' => 18.00, 'discount' => 0.00],
                ],
            ],
            [
                'user' => $cashier,
                'customer' => $goldCustomer,
                'invoice' => 'INV-' . Carbon::now()->subDays(3)->format('Ymd') . '-0001',
                'created_at' => Carbon::now()->subDays(3)->setHour(15)->setMinute(10),
                'payment_type' => 'card',
                'payment_status' => 'paid',
                'status' => 'completed',
                'items' => [
                    ['product' => $products[4], 'qty' => 1, 'price' => 35.00, 'discount' => 3.50],
                    ['product' => $products[0], 'qty' => 1, 'price' => 16.99, 'discount' => 1.70],
                ],
            ],
            [
                'user' => $cashier,
                'customer' => null,
                'invoice' => 'INV-' . Carbon::now()->subDays(4)->format('Ymd') . '-0001',
                'created_at' => Carbon::now()->subDays(4)->setHour(12)->setMinute(0),
                'payment_type' => 'qr',
                'payment_status' => 'paid',
                'status' => 'completed',
                'items' => [
                    ['product' => $products[2], 'qty' => 2, 'price' => 22.00, 'discount' => 0.00],
                    ['product' => $products[1], 'qty' => 2, 'price' => 8.50, 'discount' => 0.00],
                ],
            ],
            [
                'user' => $admin,
                'customer' => $silverCustomer,
                'invoice' => 'INV-' . Carbon::now()->subDays(5)->format('Ymd') . '-0001',
                'created_at' => Carbon::now()->subDays(5)->setHour(17)->setMinute(30),
                'payment_type' => 'card',
                'payment_status' => 'paid',
                'status' => 'completed',
                'items' => [
                    ['product' => $products[3], 'qty' => 1, 'price' => 28.50, 'discount' => 1.43],
                    ['product' => $products[7], 'qty' => 1, 'price' => 24.00, 'discount' => 1.20],
                ],
            ],
            // Earlier in Current Month
            [
                'user' => $cashier,
                'customer' => $vipCustomer,
                'invoice' => 'INV-' . Carbon::now()->subDays(10)->format('Ymd') . '-0001',
                'created_at' => Carbon::now()->subDays(10)->setHour(14)->setMinute(0),
                'payment_type' => 'card',
                'payment_status' => 'paid',
                'status' => 'completed',
                'items' => [
                    ['product' => $products[9], 'qty' => 2, 'price' => 39.00, 'discount' => 11.70],
                    ['product' => $products[2], 'qty' => 2, 'price' => 22.00, 'discount' => 6.60],
                ],
            ],
            // Cancelled Sale (to test exclusion)
            [
                'user' => $cashier,
                'customer' => null,
                'invoice' => 'INV-' . Carbon::now()->subDays(3)->format('Ymd') . '-0099',
                'created_at' => Carbon::now()->subDays(3)->setHour(18)->setMinute(0),
                'payment_type' => 'cash',
                'payment_status' => 'refunded',
                'status' => 'cancelled',
                'items' => [
                    ['product' => $products[0], 'qty' => 1, 'price' => 16.99, 'discount' => 0.00],
                ],
            ],
            // Previous Month Sales (for month-over-month comparison)
            [
                'user' => $cashier,
                'customer' => $goldCustomer,
                'invoice' => 'INV-' . Carbon::now()->subMonth()->setDay(15)->format('Ymd') . '-0001',
                'created_at' => Carbon::now()->subMonth()->setDay(15)->setHour(13)->setMinute(0),
                'payment_type' => 'card',
                'payment_status' => 'paid',
                'status' => 'completed',
                'items' => [
                    ['product' => $products[2], 'qty' => 2, 'price' => 22.00, 'discount' => 4.40],
                    ['product' => $products[3], 'qty' => 2, 'price' => 28.50, 'discount' => 5.70],
                ],
            ],
            [
                'user' => $cashier,
                'customer' => null,
                'invoice' => 'INV-' . Carbon::now()->subMonth()->setDay(22)->format('Ymd') . '-0001',
                'created_at' => Carbon::now()->subMonth()->setDay(22)->setHour(16)->setMinute(0),
                'payment_type' => 'cash',
                'payment_status' => 'paid',
                'status' => 'completed',
                'items' => [
                    ['product' => $products[5], 'qty' => 2, 'price' => 18.00, 'discount' => 0.00],
                    ['product' => $products[0], 'qty' => 1, 'price' => 16.99, 'discount' => 0.00],
                ],
            ],
        ];

        foreach ($salesData as $data) {
            $subtotal = 0;
            $totalDiscount = 0;
            foreach ($data['items'] as $item) {
                $subtotal += ($item['price'] * $item['qty']);
                $totalDiscount += $item['discount'];
            }
            $net = $subtotal - $totalDiscount;
            $tax = round($net * 0.05, 2); // 5% tax
            $finalTotal = round($net + $tax, 2);

            $sale = Sale::create([
                'user_id' => $data['user']->id,
                'customer_id' => $data['customer'] ? $data['customer']->id : null,
                'invoice_number' => $data['invoice'],
                'subtotal' => $subtotal,
                'discount' => $totalDiscount,
                'tax' => $tax,
                'final_total' => $finalTotal,
                'payment_type' => $data['payment_type'],
                'payment_status' => $data['payment_status'],
                'status' => $data['status'],
                'created_at' => $data['created_at'],
                'updated_at' => $data['created_at'],
            ]);

            foreach ($data['items'] as $item) {
                SaleDetail::create([
                    'sale_id' => $sale->id,
                    'product_id' => $item['product']->id,
                    'qty' => $item['qty'],
                    'price' => $item['price'],
                    'discount' => $item['discount'],
                    'subtotal' => round(($item['price'] * $item['qty']) - $item['discount'], 2),
                    'created_at' => $data['created_at'],
                    'updated_at' => $data['created_at'],
                ]);
            }

            if ($data['status'] === 'completed') {
                foreach ($data['items'] as $item) {
                    StockTransaction::create([
                        'product_id' => $item['product']->id,
                        'user_id' => $data['user']->id,
                        'type' => StockTransaction::TYPE_SALE,
                        'qty' => -$item['qty'],
                        'reference_number' => $sale->invoice_number,
                        'notes' => "POS Sale checkout invoice #{$sale->invoice_number}",
                        'date' => $data['created_at']->toDateString(),
                        'created_at' => $data['created_at'],
                        'updated_at' => $data['created_at'],
                    ]);
                }
            }
        }
    }

    private function seedStockTransfers(array $products): void
    {
        $inventoryUser = User::where('role', 'inventory')->first() ?? User::first();

        $transfer = StockTransfer::create([
            'reference_number' => 'TRF-' . date('Ymd') . '-0001',
            'source_location' => 'Central Distribution Warehouse',
            'destination_location' => 'Main Skincare Boutique',
            'status' => 'completed',
            'user_id' => $inventoryUser->id,
            'notes' => 'Weekly restock transfer of bestselling sunscreens and cleansers',
        ]);

        StockTransferDetail::create([
            'transfer_id' => $transfer->id,
            'product_id' => $products[0]->id, // CeraVe Cleanser
            'qty' => 20,
        ]);

        StockTransferDetail::create([
            'transfer_id' => $transfer->id,
            'product_id' => $products[3]->id, // La Roche-Posay Sunscreen
            'qty' => 25,
        ]);
    }
}
