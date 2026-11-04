<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CartItem;
use App\Models\Product;
use App\Services\EcommerceCheckoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function __construct(
        protected EcommerceCheckoutService $checkoutService
    ) {}

    /**
     * Calculate an authoritative real-time quote for a cart payload.
     */
    public function quote(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'items' => 'required|array',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.qty' => 'required|integer|min:1',
            'coupon_code' => 'nullable|string|max:50',
        ]);

        $customer = auth('sanctum')->user() ?? $request->user();
        try {
            $quote = $this->checkoutService->calculateQuote(
                $validated['items'],
                $customer,
                $validated['coupon_code'] ?? null
            );

            return response()->json([
                'success' => true,
                'data' => $quote,
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Retrieve authenticated customer's server-persisted cart.
     */
    public function index(Request $request): JsonResponse
    {
        $customer = $request->user();
        $sessionId = $request->header('X-Session-ID');

        $query = CartItem::with(['product' => fn ($q) => $q->active()]);

        if ($customer) {
            $query->where('customer_id', $customer->id);
        } elseif ($sessionId) {
            $query->where('session_id', $sessionId);
        } else {
            return response()->json([
                'success' => true,
                'data' => [
                    'items' => [],
                    'quote' => $this->checkoutService->calculateQuote([]),
                ],
            ]);
        }

        $cartItems = $query->get()->filter(fn ($item) => $item->product !== null);

        $itemsPayload = $cartItems->map(fn ($item) => [
            'product_id' => $item->product_id,
            'qty' => $item->qty,
        ])->toArray();

        $quote = $this->checkoutService->calculateQuote($itemsPayload, $customer);

        return response()->json([
            'success' => true,
            'data' => [
                'items' => $cartItems->map(fn ($item) => [
                    'id' => $item->id,
                    'product_id' => $item->product_id,
                    'name' => $item->product->name,
                    'image_url' => $item->product->image_url,
                    'sale_price' => (float) $item->product->sale_price,
                    'qty' => $item->qty,
                    'available_qty' => (int) $item->product->qty,
                    'in_stock' => $item->product->qty >= $item->qty,
                ]),
                'quote' => $quote,
            ],
        ]);
    }

    /**
     * Sync local React cart into server-side storage upon login or cart change.
     */
    public function sync(Request $request): JsonResponse
    {
        $customer = $request->user();
        $sessionId = $request->header('X-Session-ID');

        $validated = $request->validate([
            'items' => 'required|array',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.qty' => 'required|integer|min:1',
        ]);

        if (!$customer && !$sessionId) {
            return response()->json([
                'success' => false,
                'message' => 'Customer authentication or X-Session-ID header is required for cart sync.',
            ], 400);
        }

        foreach ($validated['items'] as $item) {
            $attributes = $customer
                ? ['customer_id' => $customer->id, 'product_id' => $item['product_id']]
                : ['session_id' => $sessionId, 'product_id' => $item['product_id']];

            CartItem::updateOrCreate($attributes, ['qty' => $item['qty']]);
        }

        return $this->index($request);
    }

    /**
     * Add single item to cart.
     */
    public function add(Request $request): JsonResponse
    {
        $customer = $request->user();
        $sessionId = $request->header('X-Session-ID');

        $validated = $request->validate([
            'product_id' => 'required|integer|exists:products,id',
            'qty' => 'nullable|integer|min:1',
        ]);

        $qtyToAdd = (int) ($validated['qty'] ?? 1);

        $attributes = $customer
            ? ['customer_id' => $customer->id, 'product_id' => $validated['product_id']]
            : ['session_id' => $sessionId, 'product_id' => $validated['product_id']];

        $cartItem = CartItem::firstOrNew($attributes);
        $cartItem->qty = ($cartItem->qty ?? 0) + $qtyToAdd;
        $cartItem->save();

        return response()->json([
            'success' => true,
            'message' => 'Item added to cart.',
            'cart_item' => $cartItem,
        ]);
    }

    /**
     * Remove item from cart.
     */
    public function remove(Request $request, Product $product): JsonResponse
    {
        $customer = $request->user();
        $sessionId = $request->header('X-Session-ID');

        $query = CartItem::where('product_id', $product->id);

        if ($customer) {
            $query->where('customer_id', $customer->id);
        } elseif ($sessionId) {
            $query->where('session_id', $sessionId);
        } else {
            return response()->json(['success' => false], 400);
        }

        $query->delete();

        return response()->json([
            'success' => true,
            'message' => 'Item removed from cart.',
        ]);
    }
}
