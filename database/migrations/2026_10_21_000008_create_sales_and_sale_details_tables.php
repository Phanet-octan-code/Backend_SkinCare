<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->string('invoice_number', 50)->unique();
            $table->decimal('subtotal', 12, 2)->default(0.00);
            $table->decimal('discount', 12, 2)->default(0.00);
            $table->decimal('tax', 12, 2)->default(0.00);
            $table->decimal('final_total', 12, 2)->default(0.00);
            $table->string('payment_type', 30)->default('cash')->index(); // cash, card, transfer, qris, split
            $table->string('payment_status', 20)->default('paid')->index(); // paid, partial, pending, refunded
            $table->string('status', 20)->default('completed')->index(); // completed, pending, cancelled, returned
            $table->timestamps();

            $table->index(['created_at', 'status']);
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('sale_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained('sales')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->integer('qty');
            $table->decimal('price', 12, 2);
            $table->decimal('discount', 12, 2)->default(0.00);
            $table->decimal('subtotal', 12, 2);
            $table->timestamps();

            $table->index(['sale_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_details');
        Schema::dropIfExists('sales');
    }
};
