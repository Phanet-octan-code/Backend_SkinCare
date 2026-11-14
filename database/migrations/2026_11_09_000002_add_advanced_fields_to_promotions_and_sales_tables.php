<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('promotions', function (Blueprint $table) {
            $table->string('code', 50)->nullable()->unique()->after('name');
            $table->boolean('is_flash_sale')->default(false)->index()->after('discount_value');
            $table->dateTime('starts_at')->nullable()->index()->after('end_date');
            $table->dateTime('ends_at')->nullable()->index()->after('starts_at');
            $table->unsignedInteger('total_usage_limit')->nullable()->after('ends_at');
            $table->unsignedInteger('current_usage_count')->default(0)->after('total_usage_limit');
            $table->decimal('min_spend', 12, 2)->nullable()->after('current_usage_count');
            $table->string('badge_text', 100)->nullable()->after('min_spend');
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->decimal('refunded_amount', 12, 2)->default(0.00)->after('final_total');
            $table->text('refund_reason')->nullable()->after('refunded_amount');
            $table->timestamp('refunded_at')->nullable()->after('refund_reason');
            $table->string('applied_referral_code', 50)->nullable()->after('refunded_at');
            $table->unsignedInteger('loyalty_points_redeemed')->default(0)->after('applied_referral_code');
            $table->decimal('loyalty_points_discount', 12, 2)->default(0.00)->after('loyalty_points_redeemed');
            $table->unsignedInteger('loyalty_points_earned')->default(0)->after('loyalty_points_discount');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn([
                'refunded_amount',
                'refund_reason',
                'refunded_at',
                'applied_referral_code',
                'loyalty_points_redeemed',
                'loyalty_points_discount',
                'loyalty_points_earned',
            ]);
        });

        Schema::table('promotions', function (Blueprint $table) {
            $table->dropColumn([
                'code',
                'is_flash_sale',
                'starts_at',
                'ends_at',
                'total_usage_limit',
                'current_usage_count',
                'min_spend',
                'badge_text',
            ]);
        });
    }
};

