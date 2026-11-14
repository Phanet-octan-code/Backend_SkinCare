<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add loyalty & referral attributes to customers table
        Schema::table('customers', function (Blueprint $table) {
            $table->unsignedInteger('loyalty_points')->default(0)->after('membership_level');
            $table->unsignedInteger('lifetime_points')->default(0)->after('loyalty_points');
            $table->string('referral_code', 30)->nullable()->unique()->after('lifetime_points');
            $table->foreignId('referred_by_id')->nullable()->after('referral_code')->constrained('customers')->nullOnDelete();
        });

        // Configurable Loyalty Tiers
        Schema::create('loyalty_tiers', function (Blueprint $table) {
            $table->id();
            $table->string('tier_name', 30)->unique(); // Bronze, Silver, Gold, Platinum
            $table->decimal('min_spend', 12, 2)->default(0.00);
            $table->unsignedInteger('min_points')->default(0);
            $table->decimal('discount_percentage', 5, 2)->default(0.00); // 0%, 5%, 10%, 15%
            $table->decimal('points_multiplier', 4, 2)->default(1.00); // 1.0x, 1.25x, 1.5x, 2.0x
            $table->boolean('free_shipping')->default(false);
            $table->string('perks_description')->nullable();
            $table->timestamps();
        });

        // Ledger of loyalty point movements
        Schema::create('loyalty_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('sale_id')->nullable()->constrained('sales')->nullOnDelete();
            $table->integer('points'); // Positive for earn, negative for burn/reversal
            $table->string('type', 40)->index(); // 'earned_purchase', 'redeemed_discount', 'referral_bonus', 'tier_adjustment', 'refund_reversal'
            $table->string('description')->nullable();
            $table->timestamps();
        });

        // Referral Campaigns
        Schema::create('referral_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('name')->default('Skinora Radiance Referral Club');
            $table->unsignedInteger('referrer_reward_points')->default(200); // 200 pts ($10 value)
            $table->string('referee_discount_type', 20)->default('fixed'); // 'fixed', 'percentage'
            $table->decimal('referee_discount_value', 12, 2)->default(10.00); // $10 off first purchase
            $table->decimal('min_order_spend', 12, 2)->default(30.00);
            $table->string('status', 20)->default('active')->index();
            $table->timestamps();
        });

        // Customer referral tracking
        Schema::create('customer_referrals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('referrer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('referee_id')->constrained('customers')->cascadeOnDelete();
            $table->string('referral_code', 30)->index();
            $table->foreignId('sale_id')->nullable()->constrained('sales')->nullOnDelete();
            $table->string('status', 20)->default('pending')->index(); // pending, rewarded, cancelled
            $table->timestamp('rewarded_at')->nullable();
            $table->timestamps();

            $table->unique(['referee_id']); // Each referee can only be referred once
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_referrals');
        Schema::dropIfExists('referral_campaigns');
        Schema::dropIfExists('loyalty_transactions');
        Schema::dropIfExists('loyalty_tiers');

        Schema::table('customers', function (Blueprint $table) {
            $table->dropForeign(['referred_by_id']);
            $table->dropColumn([
                'loyalty_points',
                'lifetime_points',
                'referral_code',
                'referred_by_id',
            ]);
        });
    }
};

