<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bundles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->index();
            $table->string('slug')->unique();
            $table->string('type', 30)->default('bundle')->index(); // 'bundle', 'gift_set'
            $table->text('description')->nullable();
            $table->string('image')->nullable();
            $table->decimal('price', 12, 2);
            $table->decimal('original_price', 12, 2)->nullable();
            $table->text('packaging_details')->nullable();
            $table->string('status', 20)->default('active')->index(); // active, inactive
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('bundle_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bundle_id')->constrained('bundles')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->unsignedInteger('quantity')->default(1);
            $table->boolean('is_free_gift')->default(false);
            $table->timestamps();

            $table->unique(['bundle_id', 'product_id']);
        });

        // Add bundle_id to cart_items and sale_details
        Schema::table('cart_items', function (Blueprint $table) {
            $table->foreignId('bundle_id')->nullable()->after('product_id')->constrained('bundles')->nullOnDelete();
            $table->foreignId('product_id')->nullable()->change();
        });

        Schema::table('sale_details', function (Blueprint $table) {
            $table->foreignId('bundle_id')->nullable()->after('product_id')->constrained('bundles')->nullOnDelete();
            $table->foreignId('product_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('sale_details', function (Blueprint $table) {
            $table->dropForeign(['bundle_id']);
            $table->dropColumn('bundle_id');
        });

        Schema::table('cart_items', function (Blueprint $table) {
            $table->dropForeign(['bundle_id']);
            $table->dropColumn('bundle_id');
        });

        Schema::dropIfExists('bundle_items');
        Schema::dropIfExists('bundles');
    }
};

