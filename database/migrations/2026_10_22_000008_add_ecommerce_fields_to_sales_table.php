<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->string('order_source', 20)->default('pos')->after('status')->index(); // pos, online
            $table->decimal('shipping_fee', 12, 2)->default(0.00)->after('final_total');
            $table->text('shipping_address')->nullable()->after('shipping_fee');
            $table->string('tracking_number', 100)->nullable()->after('shipping_address')->index();
            $table->text('order_notes')->nullable()->after('tracking_number');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn([
                'order_source',
                'shipping_fee',
                'shipping_address',
                'tracking_number',
                'order_notes',
            ]);
        });
    }
};
