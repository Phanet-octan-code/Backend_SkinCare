<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Sale;
use App\Services\InventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CustomerOrderController extends Controller
{
    /**
     * List all past orders for the authenticated customer.
     */
    public function index(Request $request): JsonResponse
    {
        $customer = $request->user();

        $orders = $customer->sales()
            ->with(['details.product'])
            ->latest()
            ->paginate(10);

        return response()->json([
            'success' => true,
            'data' => OrderResource::collection($orders->items()),
            'pagination' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
            ],
        ]);
    }

    /**
     * Get specific order details for the authenticated customer.
     */
    public function show(Request $request, Sale $order): JsonResponse
    {
        $customer = $request->user();

        // Strict ownership check
        if ($order->customer_id !== $customer->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: You do not have permission to view this order.',
            ], 403);
        }

        $order->load(['details.product', 'customer']);

        return response()->json([
            'success' => true,
            'data' => new OrderResource($order),
        ]);
    }

    /**
     * Track an order by Invoice / Tracking Number and Email/Phone (Guest or Member).
     */
    public function track(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'reference' => 'required|string|max:100', // Invoice number or Tracking number
            'identifier' => 'required|string|max:150', // Email or Phone number
        ]);

        $reference = trim($validated['reference']);
        $identifier = trim($validated['identifier']);

        $order = Sale::with(['details.product', 'customer'])
            ->where(function ($q) use ($reference) {
                $q->where('invoice_number', $reference)
                  ->orWhere('tracking_number', $reference);
            })
            ->where(function ($q) use ($identifier) {
                $q->whereHas('customer', function ($cq) use ($identifier) {
                    $cq->where('email', 'LIKE', "%{$identifier}%")
                       ->orWhere('phone', 'LIKE', "%{$identifier}%");
                })
                ->orWhere('shipping_address', 'LIKE', "%{$identifier}%");
            })
            ->first();

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'No order found matching the provided reference and contact details.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => new OrderResource($order),
        ]);
    }

    /**
     * Cancel an online order and restore inventory via POS InventoryService.
     */
    public function cancel(Request $request, Sale $order, InventoryService $inventoryService): JsonResponse
    {
        $customer = $request->user();

        // Strict ownership check
        if ($order->customer_id !== $customer->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: You do not have permission to modify this order.',
            ], 403);
        }

        // Only pending or confirmed orders can be cancelled by customer
        if (!in_array($order->status, [Sale::STATUS_PENDING, Sale::STATUS_CONFIRMED], true)) {
            return response()->json([
                'success' => false,
                'message' => "Order #{$order->invoice_number} cannot be cancelled as its status is already '{$order->status}'.",
            ], 422);
        }

        $validated = $request->validate([
            'reason' => 'nullable|string|max:255',
        ]);

        $reason = $validated['reason'] ?? 'Cancelled by customer';

        DB::transaction(function () use ($order, $inventoryService, $customer, $reason) {
            // Restore inventory for each item
            $order->load('details');
            foreach ($order->details as $detail) {
                $inventoryService->recordSaleReturn(
                    productId: $detail->product_id,
                    qty: $detail->qty,
                    userId: $order->user_id,
                    invoiceNumber: $order->invoice_number,
                    notes: "Online order #{$order->invoice_number} cancelled by customer #{$customer->id}: {$reason}"
                );
            }

            // Update order status
            $order->status = Sale::STATUS_CANCELLED;
            if ($order->payment_status === Sale::PAYMENT_PAID) {
                $order->payment_status = Sale::PAYMENT_REFUNDED;
            } else {
                $order->payment_status = 'cancelled';
            }

            $cancelNote = "[Cancelled by Customer at " . now()->toDateTimeString() . " | Reason: {$reason}]";
            $order->order_notes = $order->order_notes ? "{$order->order_notes} | {$cancelNote}" : $cancelNote;
            $order->save();
        });

        return response()->json([
            'success' => true,
            'message' => "Order #{$order->invoice_number} has been cancelled and items returned to stock.",
            'order' => new OrderResource($order->load(['details.product', 'customer'])),
        ]);
    }
}
