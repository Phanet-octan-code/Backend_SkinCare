<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promotions', function (Blueprint $table) {
            $table->id();
            $table->string('name')->index();
            $table->string('discount_type', 20); // percentage, fixed
            $table->decimal('discount_value', 12, 2);
            $table->date('start_date')->index();
            $table->date('end_date')->index();
            $table->string('status', 20)->default('active')->index(); // active, inactive
            $table->timestamps();
        });

        // Pivot table for product-specific promotions
        Schema::create('product_promotion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('promotion_id')->constrained('promotions')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['promotion_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_promotion');
        Schema::dropIfExists('promotions');
    }
};
