<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One reliable source of truth for stock balances per product
        Schema::create('stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->unique()->constrained('products')->cascadeOnDelete();
            $table->integer('qty')->default(0);
            $table->timestamps();

            $table->index('qty');
        });

        // Auditable stock movements ledger
        Schema::create('stock_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->string('type', 30)->index(); // stock-in, stock-out, sale, return, transfer, damaged, adjustment
            $table->integer('qty'); // Positive for additions, negative or absolute tracked with type
            $table->string('reference_number', 50)->nullable()->index();
            $table->text('notes')->nullable();
            $table->date('date')->index();
            $table->timestamps();

            $table->index(['product_id', 'type', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_transactions');
        Schema::dropIfExists('stocks');
    }
};
