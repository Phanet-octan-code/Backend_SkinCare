<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\CustomerWishlist;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerWishlistController extends Controller
{
    /**
     * List all wishlisted products for the authenticated customer.
     */
    public function index(Request $request): JsonResponse
    {
        $products = $request->user()
            ->wishlistProducts()
            ->with(['category', 'promotions' => fn ($q) => $q->active()])
            ->get();

        return response()->json([
            'success' => true,
            'data' => ProductResource::collection($products),
        ]);
    }

    /**
     * Add a product to wishlist.
     */
    public function store(Request $request, ?Product $product = null): JsonResponse
    {
        $customer = $request->user();

        $productId = $product?->id ?? $request->input('product_id');

        if (!$productId || !Product::where('id', $productId)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'The selected product id is invalid or does not exist.',
            ], 422);
        }

        $wishlist = CustomerWishlist::firstOrCreate([
            'customer_id' => $customer->id,
            'product_id' => $productId,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Product saved to your wishlist.',
            'product_id' => (int) $wishlist->product_id,
        ], 201);
    }

    /**
     * Remove a product from wishlist.
     */
    public function destroy(Request $request, Product $product): JsonResponse
    {
        $customer = $request->user();

        CustomerWishlist::where('customer_id', $customer->id)
            ->where('product_id', $product->id)
            ->delete();

        return response()->json([
            'success' => true,
            'message' => 'Product removed from your wishlist.',
            'product_id' => $product->id,
        ]);
    }
}
