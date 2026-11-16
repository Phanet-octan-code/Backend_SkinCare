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
     * Strategic indexes to accelerate GET, POST, PUT, and DELETE operations.
     */
    public function up(): void
    {
        // 1. Critical index on sale_details(product_id)
        // Accelerates: best-selling sorting, review verification, product sales reporting
        DB::statement('CREATE INDEX IF NOT EXISTS idx_sale_details_product_id ON sale_details (product_id);');

        // 2. Composite index on customer_referrals(referrer_id, status)
        // Accelerates: referral counting and reward validation
        DB::statement('CREATE INDEX IF NOT EXISTS idx_customer_referrals_referrer_status ON customer_referrals (referrer_id, status);');

        // 3. Composite index on loyalty_transactions(customer_id, created_at)
        // Accelerates: loyalty points ledger and transaction history
        DB::statement('CREATE INDEX IF NOT EXISTS idx_loyalty_transactions_customer_created ON loyalty_transactions (customer_id, created_at DESC);');

        // 4. Composite index on subscriptions(customer_id, status)
        // Accelerates: customer subscription listing and status filtering
        DB::statement('CREATE INDEX IF NOT EXISTS idx_subscriptions_customer_status ON subscriptions (customer_id, status);');

        // 5. Composite index on sales(customer_id, created_at)
        // Accelerates: customer order history pagination
        DB::statement('CREATE INDEX IF NOT EXISTS idx_sales_customer_created ON sales (customer_id, created_at DESC);');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS idx_sale_details_product_id;');
        DB::statement('DROP INDEX IF EXISTS idx_customer_referrals_referrer_status;');
        DB::statement('DROP INDEX IF EXISTS idx_loyalty_transactions_customer_created;');
        DB::statement('DROP INDEX IF EXISTS idx_subscriptions_customer_status;');
        DB::statement('DROP INDEX IF EXISTS idx_sales_customer_created;');
    }
};

