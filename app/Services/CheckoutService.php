<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\Sale;
use App\Models\SaleDetail;
use App\Models\Setting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class CheckoutService
{
    public function __construct(
        protected InventoryService $inventoryService
    ) {}

    /**
     * Process an authoritative server-side POS checkout transaction.
     *
     * @param User $cashier Active authenticated cashier
     * @param array $items Array of items: [['product_id' => int, 'qty' => int], ...]
     * @param int|null $customerId Nullable registered customer ID (null = guest checkout)
     * @param string $paymentType Payment method: 'cash', 'card', 'qr'
     * @param float $amountReceived Cash amount handed by customer (for cash payments)
     * @param float|null $manualDiscountRate Optional manager-authorized discount rate (0 to 100)
     * @param string|null $notes Optional checkout notes
     * @return array Result containing sale, details, change_due, and receipt payload
     *
     * @throws InvalidArgumentException|ValidationException
     */
    public function processCheckout(
        User $cashier,
        array $items,
        ?int $customerId = null,
        string $paymentType = 'cash',
        float $amountReceived = 0.00,
        ?float $manualDiscountRate = 0.00,
        ?string $notes = null
    ): array {
        if (empty($items)) {
            throw new InvalidArgumentException('Cart cannot be empty. Please add at least one product.');
        }

        // Standardize payment type
        $paymentType = strtolower(trim($paymentType));
        if (!in_array($paymentType, ['cash', 'card', 'qr', 'transfer', 'qris'], true)) {
            throw new InvalidArgumentException("Unsupported payment type [{$paymentType}]. Supported: cash, card, qr.");
        }

        return DB::transaction(function () use (
            $cashier,
            $items,
            $customerId,
            $paymentType,
            $amountReceived,
            $manualDiscountRate,
            $notes
        ) {
            // 1. Resolve Customer & Membership Tier
            $customer = null;
            $membershipDiscountRate = 0.0;
            if ($customerId) {
                $customer = Customer::find($customerId);
                if ($customer) {
                    $membershipDiscountRate = (float) $customer->membership_discount_percentage;
                }
            }

            // 2. Load active store promotions
            $activePromotions = Promotion::active()->with('products')->get();

            // Consolidate identical items in cart payload
            $consolidatedItems = [];
            foreach ($items as $item) {
                $pid = (int) ($item['product_id'] ?? 0);
                $qty = (int) ($item['qty'] ?? 1);
                if ($pid <= 0 || $qty <= 0) {
                    continue;
                }
                $consolidatedItems[$pid] = ($consolidatedItems[$pid] ?? 0) + $qty;
            }

            if (empty($consolidatedItems)) {
                throw new InvalidArgumentException('No valid products selected in cart.');
            }

            // 3. Lock products and calculate line items with authoritative prices
            $lineItems = [];
            $subtotal = 0.00;
            $totalDiscount = 0.00;

            // Sort keys to prevent deadlock when locking multiple rows
            ksort($consolidatedItems);

            foreach ($consolidatedItems as $productId => $qty) {
                // Lock product row for update to guarantee concurrency safety
                $product = Product::where('id', $productId)->lockForUpdate()->first();

                if (!$product) {
                    throw new InvalidArgumentException("Product with ID #{$productId} was not found in catalog.");
                }

                if ($product->status !== 'active') {
                    throw new InvalidArgumentException("Product [{$product->name}] is inactive and cannot be sold.");
                }

                // Prevent overselling
                if ($product->qty < $qty) {
                    throw new InvalidArgumentException(
                        "Insufficient stock for [{$product->name}]. Requested: {$qty}, Available: {$product->qty}"
                    );
                }

                $unitPrice = (float) $product->sale_price;
                $rawItemSubtotal = $unitPrice * $qty;
                $subtotal += $rawItemSubtotal;

                // Determine best promotional discount for this item
                $bestPromoDiscount = 0.00;
                foreach ($activePromotions as $promo) {
                    if ($promo->appliesToProduct($product->id)) {
                        $val = (float) $promo->discount_value;
                        if ($promo->discount_type === 'percentage') {
                            $calc = $rawItemSubtotal * ($val / 100);
                        } else {
                            $calc = min($rawItemSubtotal, $val * $qty);
                        }
                        if ($calc > $bestPromoDiscount) {
                            $bestPromoDiscount = $calc;
                        }
                    }
                }

                // Customer membership tier discount
                $memberDiscount = 0.00;
                if ($membershipDiscountRate > 0) {
                    $memberDiscount = ($rawItemSubtotal - $bestPromoDiscount) * ($membershipDiscountRate / 100);
                }

                // Manual authorized discount (capped)
                $manualDiscount = 0.00;
                if ($manualDiscountRate && $manualDiscountRate > 0) {
                    $cappedRate = min(100.0, max(0.0, (float) $manualDiscountRate));
                    $manualDiscount = ($rawItemSubtotal - $bestPromoDiscount - $memberDiscount) * ($cappedRate / 100);
                }

                $itemDiscount = round($bestPromoDiscount + $memberDiscount + $manualDiscount, 2);
                $itemDiscount = min($rawItemSubtotal, $itemDiscount); // Never exceed line total
                $totalDiscount += $itemDiscount;

                $finalLineSubtotal = round($rawItemSubtotal - $itemDiscount, 2);

                $lineItems[] = [
                    'product' => $product,
                    'qty' => $qty,
                    'price' => $unitPrice,
                    'discount' => $itemDiscount,
                    'subtotal' => $finalLineSubtotal,
                ];
            }

            // 4. Calculate Tax & Final Totals
            $taxPercent = (float) Setting::get('tax_rate', 5.0);
            $taxRate = $taxPercent / 100;
            $netTaxable = max(0.00, $subtotal - $totalDiscount);
            $tax = round($netTaxable * $taxRate, 2);
            $finalTotal = round($netTaxable + $tax, 2);

            // 5. Validate Cash Tender
            $changeDue = 0.00;
            if ($paymentType === 'cash') {
                if ($amountReceived < $finalTotal) {
                    $shortfall = number_format($finalTotal - $amountReceived, 2);
                    throw new InvalidArgumentException(
                        "Cash received ($" . number_format($amountReceived, 2) . ") is less than total ($" . number_format($finalTotal, 2) . "). Shortfall: $" . $shortfall
                    );
                }
                $changeDue = round($amountReceived - $finalTotal, 2);
            } else {
                $amountReceived = $finalTotal;
            }

            // 6. Generate Unique Invoice Number
            $invoiceNumber = Sale::generateInvoiceNumber();

            // 7. Persist Sale Master Record with recorded currency & rate
            $sale = Sale::create([
                'user_id' => $cashier->id,
                'customer_id' => $customer?->id,
                'invoice_number' => $invoiceNumber,
                'subtotal' => round($subtotal, 2),
                'discount' => round($totalDiscount, 2),
                'tax' => round($tax, 2),
                'final_total' => round($finalTotal, 2),
                'payment_type' => $paymentType,
                'payment_status' => 'paid',
                'status' => 'completed',
                'currency' => Setting::get('currency', 'USD'),
                'exchange_rate' => (float) Setting::get('exchange_rate_khr', 4100.0),
            ]);

            // 8. Persist Sale Details & Deduct Stock Atomically
            $persistedDetails = [];
            foreach ($lineItems as $item) {
                $detail = SaleDetail::create([
                    'sale_id' => $sale->id,
                    'product_id' => $item['product']->id,
                    'qty' => $item['qty'],
                    'price' => $item['price'],
                    'discount' => $item['discount'],
                    'subtotal' => $item['subtotal'],
                ]);
                $detail->setRelation('product', $item['product']);
                $persistedDetails[] = $detail;

                // Inventory deduction with audit trail
                $this->inventoryService->recordSaleDeduction(
                    productId: $item['product']->id,
                    qty: $item['qty'],
                    userId: $cashier->id,
                    invoiceNumber: $sale->invoice_number
                );
            }

            $sale->setRelation('details', collect($persistedDetails));
            $sale->setRelation('customer', $customer);
            $sale->setRelation('user', $cashier);

            return [
                'sale' => $sale,
                'details' => $persistedDetails,
                'amount_received' => $amountReceived,
                'change_due' => $changeDue,
                'customer' => $customer,
                'cashier' => $cashier,
            ];
        });
    }

    /**
     * Cancel an existing completed sale and restock all items atomically.
     *
     * @param Sale $sale
     * @param User $authorizer User authorizing cancellation (Admin, Manager, or assigned Cashier)
     * @param string $reason Audit rationale
     * @return Sale
     */
    public function cancelSale(Sale $sale, User $authorizer, string $reason): Sale
    {
        if ($sale->status === 'cancelled') {
            throw new InvalidArgumentException("Sale [{$sale->invoice_number}] has already been cancelled.");
        }

        if ($sale->status === 'returned') {
            throw new InvalidArgumentException("Sale [{$sale->invoice_number}] has already been processed as returned.");
        }

        return DB::transaction(function () use ($sale, $authorizer, $reason) {
            // Lock sale record
            $lockedSale = Sale::with('details.product')->where('id', $sale->id)->lockForUpdate()->firstOrFail();

            foreach ($lockedSale->details as $detail) {
                // Restock inventory and record return audit
                $this->inventoryService->recordSaleReturn(
                    productId: $detail->product_id,
                    qty: $detail->qty,
                    userId: $authorizer->id,
                    invoiceNumber: $lockedSale->invoice_number,
                    notes: "Cancellation void by {$authorizer->name}: {$reason}"
                );
            }

            $lockedSale->status = 'cancelled';
            $lockedSale->payment_status = 'refunded';
            $lockedSale->save();

            return $lockedSale;
        });
    }

    /**
     * Process return/refund for a specific sale (full or partial items).
     *
     * @param Sale $sale
     * @param array $returnedItems Array of: [['detail_id' => int, 'qty' => int], ...]
     * @param User $authorizer
     * @param string $reason
     * @return Sale
     */
    public function processReturn(Sale $sale, array $returnedItems, User $authorizer, string $reason): Sale
    {
        if (in_array($sale->status, ['cancelled'], true)) {
            throw new InvalidArgumentException("Cannot return items from a cancelled sale.");
        }

        return DB::transaction(function () use ($sale, $returnedItems, $authorizer, $reason) {
            $lockedSale = Sale::with('details.product')->where('id', $sale->id)->lockForUpdate()->firstOrFail();

            foreach ($returnedItems as $ret) {
                $detailId = (int) ($ret['detail_id'] ?? 0);
                $returnQty = (int) ($ret['qty'] ?? 0);

                if ($returnQty <= 0) {
                    continue;
                }

                $detail = $lockedSale->details->firstWhere('id', $detailId);
                if (!$detail) {
                    throw new InvalidArgumentException("Line item #{$detailId} not found in this sale.");
                }

                if ($returnQty > $detail->qty) {
                    throw new InvalidArgumentException("Return quantity ({$returnQty}) exceeds sold quantity ({$detail->qty}).");
                }

                // Restock returned units
                $this->inventoryService->recordSaleReturn(
                    productId: $detail->product_id,
                    qty: $returnQty,
                    userId: $authorizer->id,
                    invoiceNumber: $lockedSale->invoice_number,
                    notes: "Customer return authorized by {$authorizer->name}: {$reason}"
                );
            }

            $lockedSale->status = 'returned';
            $lockedSale->payment_status = 'refunded';
            $lockedSale->save();

            return $lockedSale;
        });
    }
}
