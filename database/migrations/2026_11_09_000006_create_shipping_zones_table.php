<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipping_zones', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name_en');
            $table->string('name_km');
            $table->decimal('standard_fee', 10, 2)->default(2.50);
            $table->decimal('free_shipping_threshold', 10, 2)->nullable()->default(50.00);
            $table->string('estimated_delivery_en')->default('1-2 Business Days');
            $table->string('estimated_delivery_km')->default('១-២ ថ្ងៃធ្វើការ');
            $table->json('provinces')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->foreignId('shipping_zone_id')->nullable()->after('shipping_fee')->constrained('shipping_zones')->nullOnDelete();
            $table->string('shipping_zone_name')->nullable()->after('shipping_zone_id');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropForeign(['shipping_zone_id']);
            $table->dropColumn(['shipping_zone_id', 'shipping_zone_name']);
        });

        Schema::dropIfExists('shipping_zones');
    }
};

