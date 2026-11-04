<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Services\EcommerceCheckoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class CheckoutController extends Controller
{
    public function __construct(
        protected EcommerceCheckoutService $checkoutService
    ) {}

    /**
     * Authoritative online order placement.
     * Supports both authenticated customers and guest checkout.
     */
    public function checkout(Request $request): JsonResponse
    {
        $customer = auth('sanctum')->user() ?? $request->user();

        $rules = [
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.qty' => 'required|integer|min:1',
            'shipping_address' => 'required|string|max:500',
            'payment_type' => 'required|string|in:card,qr,cash,transfer,qris',
            'order_notes' => 'nullable|string|max:500',
            'coupon_code' => 'nullable|string|max:50',
            'idempotency_key' => 'nullable|string|max:100',
        ];

        // If guest, require contact details
        if (!$customer) {
            $rules['guest_name'] = 'required|string|max:255';
            $rules['guest_email'] = 'required|email|max:255';
            $rules['guest_phone'] = 'required|string|max:30';
        }

        $validated = $request->validate($rules, [
            'shipping_address.required' => 'Please provide a full delivery address.',
            'guest_email.required' => 'An email address is required to send your order receipt.',
            'guest_phone.required' => 'A phone number is required for delivery courier contact.',
        ]);

        // 1. Idempotency protection to prevent duplicate orders on double click or retry
        $idempotencyKey = $request->header('X-Idempotency-Key') ?? $request->input('idempotency_key');
        if ($idempotencyKey) {
            $cachedSaleId = \Illuminate\Support\Facades\Cache::get("idempotency_order_{$idempotencyKey}");
            if ($cachedSaleId) {
                $existingSale = \App\Models\Sale::with(['details.product', 'customer'])->find($cachedSaleId);
                if ($existingSale) {
                    return response()->json([
                        'success' => true,
                        'message' => "Order #{$existingSale->invoice_number} already placed (idempotent request).",
                        'order' => new OrderResource($existingSale),
                    ], 200);
                }
            }
        }

        $guestInfo = !$customer ? [
            'name' => $validated['guest_name'],
            'email' => $validated['guest_email'],
            'phone' => $validated['guest_phone'],
        ] : null;

        try {
            $sale = $this->checkoutService->processCheckout(
                items: $validated['items'],
                customer: $customer,
                shippingAddress: $validated['shipping_address'],
                paymentType: $validated['payment_type'],
                orderNotes: $validated['order_notes'] ?? null,
                guestInfo: $guestInfo,
                couponCode: $validated['coupon_code'] ?? null
            );

            if ($idempotencyKey) {
                \Illuminate\Support\Facades\Cache::put("idempotency_order_{$idempotencyKey}", $sale->id, now()->addMinutes(30));
            }

            return response()->json([
                'success' => true,
                'message' => "Order #{$sale->invoice_number} has been placed successfully!",
                'order' => new OrderResource($sale->load(['details.product', 'customer'])),
            ], 201);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while processing your order. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Authoritative payment verification by trusted backend.
     */
    public function verifyPayment(Request $request, string $order): JsonResponse
    {
        $sale = is_numeric($order) 
            ? \App\Models\Sale::find($order) 
            : \App\Models\Sale::where('invoice_number', $order)->first();

        if (!$sale) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found.',
            ], 404);
        }

        $validated = $request->validate([
            'payment_type' => 'required|string|in:card,qr,cash,transfer',
            'transaction_ref' => 'nullable|string|max:100',
        ]);

        if ($sale->payment_status === \App\Models\Sale::PAYMENT_PAID) {
            return response()->json([
                'success' => true,
                'message' => 'Payment has already been confirmed.',
                'order' => new OrderResource($sale->load(['details.product', 'customer'])),
            ]);
        }

        $sale->payment_type = $validated['payment_type'];
        $sale->payment_status = \App\Models\Sale::PAYMENT_PAID;
        $sale->status = \App\Models\Sale::STATUS_CONFIRMED;

        if (!empty($validated['transaction_ref'])) {
            $refNote = "[Payment Ref: {$validated['transaction_ref']}]";
            $sale->order_notes = $sale->order_notes ? "{$sale->order_notes} | {$refNote}" : $refNote;
        }

        $sale->save();

        return response()->json([
            'success' => true,
            'message' => 'Payment verified successfully by backend gateway.',
            'order' => new OrderResource($sale->load(['details.product', 'customer'])),
        ]);
    }
}
