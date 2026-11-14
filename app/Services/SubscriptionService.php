<?php

namespace App\Services;

use App\Models\Bundle;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Subscription;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class SubscriptionService
{
    public function __construct(
        protected EcommerceCheckoutService $checkoutService
    ) {}

    /**
     * Create a recurring skincare replenishment subscription.
     */
    public function createSubscription(
        Customer $customer,
        ?Product $product,
        ?Bundle $bundle,
        int $intervalDays,
        int $quantity,
        string $paymentMethod,
        string $shippingAddress,
        ?Sale $initialSale = null,
        float $discountPercentage = 10.0
    ): Subscription {
        if (!$product && !$bundle) {
            throw new InvalidArgumentException('Subscription must specify either a product or a bundle.');
        }

        if (!in_array($intervalDays, [30, 60, 90], true)) {
            $intervalDays = 30;
        }

        $unitPrice = $bundle ? (float) $bundle->price : (float) $product->sale_price;

        return Subscription::create([
            'customer_id' => $customer->id,
            'product_id' => $product?->id,
            'bundle_id' => $bundle?->id,
            'status' => Subscription::STATUS_ACTIVE,
            'billing_interval_days' => $intervalDays,
            'discount_percentage' => $discountPercentage,
            'unit_price' => $unitPrice,
            'quantity' => max(1, $quantity),
            'next_billing_date' => Carbon::today()->addDays($intervalDays),
            'last_billing_date' => Carbon::today(),
            'last_sale_id' => $initialSale?->id,
            'payment_method' => $paymentMethod,
            'shipping_address' => $shippingAddress,
        ]);
    }

    /**
     * Customer or Admin pause.
     */
    public function pause(Subscription $subscription): Subscription
    {
        if ($subscription->isCancelled()) {
            throw new InvalidArgumentException('Cancelled subscriptions cannot be paused.');
        }

        $subscription->pause();
        return $subscription->fresh();
    }

    /**
     * Customer or Admin resume.
     */
    public function resume(Subscription $subscription): Subscription
    {
        if ($subscription->isCancelled()) {
            throw new InvalidArgumentException('Cancelled subscriptions cannot be resumed.');
        }

        $subscription->resume();
        return $subscription->fresh();
    }

    /**
     * Customer or Admin cancellation.
     */
    public function cancel(Subscription $subscription, ?string $reason = null): Subscription
    {
        $subscription->cancel($reason);
        return $subscription->fresh();
    }

    /**
     * Process all due recurring skincare subscriptions.
     */
    public function processRenewals(): array
    {
        $due = Subscription::dueForRenewal()
            ->with(['customer', 'product', 'bundle'])
            ->get();

        $results = [
            'processed' => 0,
            'succeeded' => 0,
            'failed' => 0,
            'sales' => [],
            'errors' => [],
        ];

        foreach ($due as $subscription) {
            $results['processed']++;
            try {
                $sale = $this->renewSubscription($subscription);
                $results['succeeded']++;
                $results['sales'][] = $sale->id;
            } catch (\Throwable $e) {
                $results['failed']++;
                $results['errors'][] = [
                    'subscription_id' => $subscription->id,
                    'error' => $e->getMessage(),
                ];
                Log::error("Subscription renewal failed for #{$subscription->id}: " . $e->getMessage());
            }
        }

        return $results;
    }

    /**
     * Execute renewal order for a single subscription.
     */
    public function renewSubscription(Subscription $subscription): Sale
    {
        return DB::transaction(function () use ($subscription) {
            $customer = $subscription->customer;
            $items = [];

            if ($subscription->bundle_id) {
                $items[] = [
                    'bundle_id' => $subscription->bundle_id,
                    'qty' => $subscription->quantity,
                ];
            } else {
                $items[] = [
                    'product_id' => $subscription->product_id,
                    'qty' => $subscription->quantity,
                ];
            }

            // Note subscription renewal in order notes
            $orderNotes = "Automated Recurring Skincare Subscription #{$subscription->id} (Every {$subscription->billing_interval_days} Days)";

            $sale = $this->checkoutService->processCheckout(
                items: $items,
                customer: $customer,
                shippingAddress: $subscription->shipping_address,
                paymentType: $subscription->payment_method,
                orderNotes: $orderNotes,
                subscriptionId: $subscription->id
            );

            // Advance billing cycle
            $interval = $subscription->billing_interval_days ?: 30;
            $subscription->update([
                'last_billing_date' => Carbon::today(),
                'next_billing_date' => Carbon::today()->addDays($interval),
                'last_sale_id' => $sale->id,
            ]);

            return $sale;
        });
    }
}

