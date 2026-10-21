<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('categories')->restrictOnDelete();
            $table->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $table->string('name')->index();
            $table->string('barcode', 100)->unique();
            $table->decimal('cost_price', 12, 2)->default(0.00);
            $table->decimal('sale_price', 12, 2)->default(0.00);
            $table->integer('qty')->default(0);
            $table->string('image')->nullable();
            $table->text('description')->nullable();
            $table->integer('low_stock_threshold')->default(10);
            $table->string('status', 20)->default('active')->index(); // active, inactive
            $table->timestamps();

            $table->index(['category_id', 'status']);
            $table->index(['status', 'qty']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
