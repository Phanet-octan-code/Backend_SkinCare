<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->foreignId('bundle_id')->nullable()->constrained('bundles')->nullOnDelete();
            $table->string('status', 30)->default('active')->index(); // active, paused, cancelled, expired
            $table->unsignedInteger('billing_interval_days')->default(30); // 30, 60, 90
            $table->decimal('discount_percentage', 5, 2)->default(10.00); // 10% subscribe & save
            $table->decimal('unit_price', 12, 2);
            $table->unsignedInteger('quantity')->default(1);
            $table->date('next_billing_date')->index();
            $table->date('last_billing_date')->nullable();
            $table->foreignId('last_sale_id')->nullable()->constrained('sales')->nullOnDelete();
            $table->string('payment_method', 30)->default('card'); // card, stripe, cod, bakong
            $table->string('stripe_subscription_id')->nullable()->index();
            $table->text('shipping_address');
            $table->timestamp('paused_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancellation_reason')->nullable();
            $table->timestamps();
        });

        // Add subscription_id to sales table for renewal traceability
        Schema::table('sales', function (Blueprint $table) {
            $table->foreignId('subscription_id')->nullable()->after('customer_id')->constrained('subscriptions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropForeign(['subscription_id']);
            $table->dropColumn('subscription_id');
        });

        Schema::dropIfExists('subscriptions');
    }
};

