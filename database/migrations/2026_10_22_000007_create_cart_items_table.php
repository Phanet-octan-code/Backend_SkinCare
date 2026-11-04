<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cart_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->cascadeOnDelete();
            $table->string('session_id', 100)->nullable();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->integer('qty')->default(1);
            $table->timestamps();

            $table->index(['customer_id', 'product_id']);
            $table->index(['session_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cart_items');
    }
};
