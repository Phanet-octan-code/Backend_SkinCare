<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_transfers', function (Blueprint $table) {
            $table->id();
            $table->string('reference_number', 50)->unique();
            $table->string('source_location')->index();
            $table->string('destination_location')->index();
            $table->string('status', 20)->default('pending')->index(); // pending, in_transit, completed, cancelled
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('stock_transfer_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transfer_id')->constrained('stock_transfers')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->integer('qty');
            $table->timestamps();

            $table->index(['transfer_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_transfer_details');
        Schema::dropIfExists('stock_transfers');
    }
};
