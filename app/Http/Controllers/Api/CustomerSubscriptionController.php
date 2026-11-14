<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Services\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerSubscriptionController extends Controller
{
    public function __construct(
        protected SubscriptionService $subscriptionService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $customer = $request->user();

        $subscriptions = $customer->subscriptions()
            ->with(['product', 'bundle'])
            ->latest()
            ->get()
            ->map(function ($sub) {
                return [
                    'id' => $sub->id,
                    'item_name' => $sub->item_name,
                    'image_url' => $sub->item_image_url,
                    'is_bundle' => $sub->bundle_id !== null,
                    'quantity' => (int) $sub->quantity,
                    'unit_price' => (float) $sub->unit_price,
                    'discount_percentage' => (float) $sub->discount_percentage,
                    'recurring_subtotal' => (float) $sub->recurring_subtotal,
                    'billing_interval_days' => (int) $sub->billing_interval_days,
                    'status' => $sub->status,
                    'next_billing_date' => $sub->next_billing_date?->format('Y-m-d'),
                    'last_billing_date' => $sub->last_billing_date?->format('Y-m-d'),
                    'shipping_address' => $sub->shipping_address,
                    'payment_method' => $sub->payment_method,
                    'created_at' => $sub->created_at?->toISOString(),
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $subscriptions,
        ]);
    }

    public function pause(Request $request, Subscription $subscription): JsonResponse
    {
        if ($subscription->customer_id !== $request->user()->id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        try {
            $updated = $this->subscriptionService->pause($subscription);
            return response()->json([
                'success' => true,
                'message' => 'Subscription paused successfully.',
                'data' => $updated,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function resume(Request $request, Subscription $subscription): JsonResponse
    {
        if ($subscription->customer_id !== $request->user()->id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        try {
            $updated = $this->subscriptionService->resume($subscription);
            return response()->json([
                'success' => true,
                'message' => 'Subscription resumed successfully.',
                'data' => $updated,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function cancel(Request $request, Subscription $subscription): JsonResponse
    {
        if ($subscription->customer_id !== $request->user()->id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        $reason = $request->input('reason', 'Cancelled by customer via mobile/web app');

        try {
            $updated = $this->subscriptionService->cancel($subscription, $reason);
            return response()->json([
                'success' => true,
                'message' => 'Subscription cancelled successfully.',
                'data' => $updated,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }
}

