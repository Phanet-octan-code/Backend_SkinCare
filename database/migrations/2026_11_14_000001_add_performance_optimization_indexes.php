<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Disable transaction wrapping so PostgreSQL DDL index creation runs cleanly.
     */
    public $withinTransaction = false;

    /**
     * Run the migrations.
     * Strategic database indexes for high-speed API performance and eliminating sequential table scans.
     */
    public function up(): void
    {
        // 1. Critical index on product_promotion pivot (accelerates product -> promotions eager loading)
        DB::statement('CREATE INDEX IF NOT EXISTS idx_product_promotion_product_id ON product_promotion (product_id);');

        // 2. High-frequency sorting & filtering indexes for active products
        DB::statement('CREATE INDEX IF NOT EXISTS idx_products_status_created_at ON products (status, created_at);');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_products_status_sale_price ON products (status, sale_price);');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_products_status_id ON products (status, id);');

        // 3. Customer default address lookup acceleration
        DB::statement('CREATE INDEX IF NOT EXISTS idx_cust_addr_cust_default ON customer_addresses (customer_id, is_default);');

        // 4. Sales order source, status, and creation date index
        DB::statement('CREATE INDEX IF NOT EXISTS idx_sales_source_status_created ON sales (order_source, status, created_at);');

        // 5. Active promotions date range evaluation acceleration
        DB::statement('CREATE INDEX IF NOT EXISTS idx_promotions_status_dates ON promotions (status, start_date, end_date);');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS idx_product_promotion_product_id;');
        DB::statement('DROP INDEX IF EXISTS idx_products_status_created_at;');
        DB::statement('DROP INDEX IF EXISTS idx_products_status_sale_price;');
        DB::statement('DROP INDEX IF EXISTS idx_products_status_id;');
        DB::statement('DROP INDEX IF EXISTS idx_cust_addr_cust_default;');
        DB::statement('DROP INDEX IF EXISTS idx_sales_source_status_created;');
        DB::statement('DROP INDEX IF EXISTS idx_promotions_status_dates;');
    }
};
