<?php

namespace App\Services;

use App\Models\Sale;
use App\Models\SaleDetail;
use App\Models\StockTransaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class RefundService
{
    public function __construct(
        protected InventoryService $inventoryService,
        protected LoyaltyService $loyaltyService
    ) {}

    /**
     * Process full or partial refund for a sale.
     */
    public function processRefund(
        Sale $sale,
        float $refundAmount,
        string $reason,
        bool $restockInventory = true,
        ?User $actor = null
    ): Sale {
        $sale = $sale->fresh(['details.product', 'details.bundle.items.product', 'customer']) ?? $sale;

        if ($refundAmount <= 0) {
            throw new InvalidArgumentException('Refund amount must be greater than zero.');
        }

        $maxRefundable = round((float) $sale->final_total - (float) $sale->refunded_amount, 2);
        if ($refundAmount > $maxRefundable) {
            throw new InvalidArgumentException(
                "Refund amount (\${$refundAmount}) exceeds maximum refundable balance (\${$maxRefundable})."
            );
        }

        return DB::transaction(function () use ($sale, $refundAmount, $reason, $restockInventory, $actor) {
            $totalRefunded = round((float) $sale->refunded_amount + $refundAmount, 2);
            $isFullRefund = $totalRefunded >= (float) $sale->final_total;

            // 1. Restock Inventory if requested and not already returned
            if ($restockInventory) {
                $this->restockSaleItems($sale, $actor);
            }

            // 2. Reverse earned loyalty points proportionally
            $this->loyaltyService->reversePointsForRefund($sale, $refundAmount);

            // 3. Update Payment Status and Order Status
            $newPaymentStatus = $isFullRefund ? Sale::PAYMENT_REFUNDED : 'partially_refunded';
            $newOrderStatus = $isFullRefund ? Sale::STATUS_RETURNED : $sale->status;

            $sale->update([
                'refunded_amount' => $totalRefunded,
                'refund_reason' => $reason,
                'refunded_at' => now(),
                'payment_status' => $newPaymentStatus,
                'status' => $newOrderStatus,
            ]);

            return $sale->fresh(['details.product', 'details.bundle.items.product', 'customer']);
        });
    }

    /**
     * Atomically restock sale line items, including bundle constituent products.
     */
    protected function restockSaleItems(Sale $sale, ?User $actor = null): void
    {
        $userId = $actor?->id ?? $sale->user_id ?? User::first()?->id ?? 1;

        foreach ($sale->details as $detail) {
            if ($detail->bundle_id && $detail->bundle) {
                // Bundle: Restock each constituent product
                foreach ($detail->bundle->items as $item) {
                    $product = $item->product;
                    if ($product) {
                        $qtyToReturn = $item->quantity * $detail->qty;
                        $product->increment('qty', $qtyToReturn);

                        StockTransaction::create([
                            'product_id' => $product->id,
                            'user_id' => $userId,
                            'type' => 'return',
                            'qty' => $qtyToReturn,
                            'reference_number' => $sale->invoice_number,
                            'date' => now()->toDateString(),
                            'notes' => "Refund restock for Bundle '{$detail->bundle->name}' in Sale #{$sale->invoice_number}",
                        ]);
                    }
                }
            } elseif ($detail->product_id && $detail->product) {
                // Standard single product
                $product = $detail->product;
                $product->increment('qty', $detail->qty);

                StockTransaction::create([
                    'product_id' => $product->id,
                    'user_id' => $userId,
                    'type' => 'return',
                    'qty' => $detail->qty,
                    'reference_number' => $sale->invoice_number,
                    'date' => now()->toDateString(),
                    'notes' => "Refund restock for Sale #{$sale->invoice_number}",
                ]);
            }
        }
    }
}
