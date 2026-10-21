<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Sale;
use App\Models\Stock;
use App\Models\StockTransaction;
use App\Models\StockTransfer;
use App\Models\StockTransferDetail;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class InventoryService
{
    /**
     * Atomically adjust stock, synchronize products.qty with stocks.qty,
     * and record an auditable StockTransaction.
     *
     * @throws InvalidArgumentException
     */
    public function adjustStock(
        int $productId,
        int $deltaQty,
        string $type,
        int $userId,
        ?int $supplierId = null,
        ?string $referenceNumber = null,
        ?string $notes = null,
        ?string $date = null
    ): StockTransaction {
        return DB::transaction(function () use (
            $productId,
            $deltaQty,
            $type,
            $userId,
            $supplierId,
            $referenceNumber,
            $notes,
            $date
        ) {
            // Lock the stock record for update to prevent race conditions
            $stock = Stock::where('product_id', $productId)->lockForUpdate()->first();

            if (!$stock) {
                $stock = Stock::create([
                    'product_id' => $productId,
                    'qty' => 0,
                ]);
            }

            $newQty = $stock->qty + $deltaQty;

            // Prevent stock from going negative on deductions
            if ($newQty < 0) {
                $product = Product::find($productId);
                $productName = $product ? $product->name : "Product #{$productId}";
                throw new InvalidArgumentException(
                    "Insufficient stock for [{$productName}]. Current available: {$stock->qty}, requested change: {$deltaQty}"
                );
            }

            // Update stocks table (primary source of truth)
            $stock->qty = $newQty;
            $stock->save();

            // Synchronize products table cached qty field
            Product::where('id', $productId)->update([
                'qty' => $newQty,
            ]);

            // Record immutable audit transaction
            return StockTransaction::create([
                'product_id' => $productId,
                'user_id' => $userId,
                'supplier_id' => $supplierId,
                'type' => $type,
                'qty' => $deltaQty,
                'reference_number' => $referenceNumber,
                'notes' => $notes,
                'date' => $date ? Carbon::parse($date)->toDateString() : Carbon::today()->toDateString(),
            ]);
        });
    }

    /**
     * Record inventory receiving (Stock-In) from supplier.
     */
    public function recordStockIn(
        int $productId,
        int $qty,
        int $userId,
        ?int $supplierId = null,
        ?string $referenceNumber = null,
        ?string $notes = null,
        ?float $unitCost = null,
        ?string $date = null
    ): StockTransaction {
        if ($qty <= 0) {
            throw new InvalidArgumentException('Stock-In quantity must be greater than zero.');
        }

        // Optionally update product cost price if specified
        if ($unitCost !== null && $unitCost >= 0) {
            Product::where('id', $productId)->update(['cost_price' => $unitCost]);
        }

        return $this->adjustStock(
            productId: $productId,
            deltaQty: $qty,
            type: StockTransaction::TYPE_STOCK_IN,
            userId: $userId,
            supplierId: $supplierId,
            referenceNumber: $referenceNumber,
            notes: $notes ?? 'Purchased stock received from supplier',
            date: $date
        );
    }

    /**
     * Record authorized stock removals for non-sale reasons.
     */
    public function recordStockOut(
        int $productId,
        int $qty,
        int $userId,
        string $reason,
        ?string $referenceNumber = null,
        ?string $notes = null,
        ?string $date = null
    ): StockTransaction {
        if ($qty <= 0) {
            throw new InvalidArgumentException('Stock-Out quantity must be greater than zero.');
        }

        $fullNotes = "Stock-out: {$reason}" . ($notes ? " | {$notes}" : '');

        return $this->adjustStock(
            productId: $productId,
            deltaQty: -$qty,
            type: StockTransaction::TYPE_STOCK_OUT,
            userId: $userId,
            supplierId: null,
            referenceNumber: $referenceNumber ?? ('SO-' . date('Ymd') . '-' . uniqid()),
            notes: $fullNotes,
            date: $date
        );
    }

    /**
     * Record damaged, expired, or spoiled skincare stock.
     */
    public function recordDamaged(
        int $productId,
        int $qty,
        int $userId,
        ?string $reason = null,
        ?string $referenceNumber = null,
        ?string $notes = null,
        ?string $date = null
    ): StockTransaction {
        if ($qty <= 0) {
            throw new InvalidArgumentException('Damaged quantity must be greater than zero.');
        }

        $fullNotes = "Damaged write-off" . ($reason ? ": {$reason}" : '') . ($notes ? " | {$notes}" : '');

        return $this->adjustStock(
            productId: $productId,
            deltaQty: -$qty,
            type: StockTransaction::TYPE_DAMAGED,
            userId: $userId,
            supplierId: null,
            referenceNumber: $referenceNumber ?? ('DMG-' . date('Ymd') . '-' . uniqid()),
            notes: $fullNotes,
            date: $date
        );
    }

    /**
     * Record inventory deduction from POS checkout.
     */
    public function recordSaleDeduction(
        int $productId,
        int $qty,
        int $userId,
        string $invoiceNumber
    ): StockTransaction {
        if ($qty <= 0) {
            throw new InvalidArgumentException('Sale deduction quantity must be greater than zero.');
        }

        return $this->adjustStock(
            productId: $productId,
            deltaQty: -$qty,
            type: StockTransaction::TYPE_SALE,
            userId: $userId,
            supplierId: null,
            referenceNumber: $invoiceNumber,
            notes: "POS Sale invoice: {$invoiceNumber}"
        );
    }

    /**
     * Record customer return restocking.
     */
    public function recordSaleReturn(
        int $productId,
        int $qty,
        int $userId,
        string $invoiceNumber,
        ?string $notes = null,
        ?string $date = null
    ): StockTransaction {
        if ($qty <= 0) {
            throw new InvalidArgumentException('Return quantity must be greater than zero.');
        }

        return $this->adjustStock(
            productId: $productId,
            deltaQty: $qty,
            type: StockTransaction::TYPE_RETURN,
            userId: $userId,
            supplierId: null,
            referenceNumber: $invoiceNumber,
            notes: $notes ?? "Customer return for invoice: {$invoiceNumber}",
            date: $date
        );
    }

    /**
     * Process customer return against original sale with condition & duplicate protection.
     */
    public function recordCustomerReturn(
        Sale $sale,
        int $productId,
        int $qty,
        int $userId,
        string $reason,
        string $condition = 'resellable',
        ?string $date = null
    ): StockTransaction {
        if ($qty <= 0) {
            throw new InvalidArgumentException('Return quantity must be greater than zero.');
        }

        // 1. Verify item exists on this sale
        $saleDetail = $sale->details()->where('product_id', $productId)->first();
        if (!$saleDetail) {
            throw new InvalidArgumentException("Product #{$productId} was not found on Invoice #{$sale->invoice_number}.");
        }

        // 2. Prevent duplicate / over returns
        $alreadyReturned = StockTransaction::where('reference_number', $sale->invoice_number)
            ->where('product_id', $productId)
            ->whereIn('type', [StockTransaction::TYPE_RETURN, StockTransaction::TYPE_DAMAGED])
            ->where('notes', 'like', '%Customer return%')
            ->sum(DB::raw('abs(qty)'));

        $maxReturnable = $saleDetail->qty - $alreadyReturned;
        if ($qty > $maxReturnable) {
            throw new InvalidArgumentException(
                "Cannot return {$qty} units. Originally sold: {$saleDetail->qty}, already returned: {$alreadyReturned}. Maximum returnable: {$maxReturnable}."
            );
        }

        $refDate = $date ? Carbon::parse($date)->toDateString() : Carbon::today()->toDateString();

        if ($condition === 'resellable') {
            return $this->adjustStock(
                productId: $productId,
                deltaQty: $qty,
                type: StockTransaction::TYPE_RETURN,
                userId: $userId,
                supplierId: null,
                referenceNumber: $sale->invoice_number,
                notes: "Customer return (Resellable): {$reason}",
                date: $refDate
            );
        } else {
            // Damaged/defective item returned: logged for audit without polluting sellable inventory balance
            return StockTransaction::create([
                'product_id' => $productId,
                'user_id' => $userId,
                'supplier_id' => null,
                'type' => StockTransaction::TYPE_DAMAGED,
                'qty' => -$qty,
                'reference_number' => $sale->invoice_number,
                'notes' => "Customer return (Damaged/Defective - not restocked): {$reason}",
                'date' => $refDate,
            ]);
        }
    }

    /**
     * Record supplier return (Return to Vendor for credit/refund).
     */
    public function recordSupplierReturn(
        int $productId,
        int $qty,
        int $supplierId,
        int $userId,
        string $reason,
        ?string $referenceNumber = null,
        ?string $notes = null,
        ?string $date = null
    ): StockTransaction {
        if ($qty <= 0) {
            throw new InvalidArgumentException('Supplier return quantity must be greater than zero.');
        }

        $ref = $referenceNumber ?? ('RTV-' . date('Ymd') . '-' . uniqid());
        $fullNotes = "Return to Vendor (Supplier #{$supplierId}): {$reason}" . ($notes ? " | {$notes}" : '');

        return $this->adjustStock(
            productId: $productId,
            deltaQty: -$qty,
            type: StockTransaction::TYPE_STOCK_OUT,
            userId: $userId,
            supplierId: $supplierId,
            referenceNumber: $ref,
            notes: $fullNotes,
            date: $date
        );
    }

    /**
     * Create an inter-branch or warehouse stock transfer.
     */
    public function createStockTransfer(
        int $userId,
        string $sourceLocation,
        string $destinationLocation,
        array $items,
        ?string $notes = null
    ): StockTransfer {
        if (trim($sourceLocation) === trim($destinationLocation)) {
            throw new InvalidArgumentException('Source and destination locations cannot be the same.');
        }

        if (empty($items)) {
            throw new InvalidArgumentException('Transfer must include at least one product.');
        }

        return DB::transaction(function () use ($userId, $sourceLocation, $destinationLocation, $items, $notes) {
            $refNumber = StockTransfer::generateReferenceNumber();

            $transfer = StockTransfer::create([
                'reference_number' => $refNumber,
                'source_location' => $sourceLocation,
                'destination_location' => $destinationLocation,
                'status' => 'in_transit',
                'user_id' => $userId,
                'notes' => $notes,
            ]);

            foreach ($items as $item) {
                $productId = (int) ($item['product_id'] ?? 0);
                $qty = (int) ($item['qty'] ?? 0);

                if ($productId <= 0 || $qty <= 0) {
                    continue;
                }

                StockTransferDetail::create([
                    'transfer_id' => $transfer->id,
                    'product_id' => $productId,
                    'qty' => $qty,
                ]);

                // Deduct stock from source warehouse
                $this->adjustStock(
                    productId: $productId,
                    deltaQty: -$qty,
                    type: StockTransaction::TYPE_TRANSFER,
                    userId: $userId,
                    supplierId: null,
                    referenceNumber: $refNumber,
                    notes: "Stock transfer dispatched to {$destinationLocation}"
                );
            }

            return $transfer;
        });
    }

    /**
     * Update stock transfer status (e.g. mark completed or cancelled).
     */
    public function updateTransferStatus(
        StockTransfer $transfer,
        string $newStatus,
        int $userId,
        ?string $reason = null
    ): StockTransfer {
        return DB::transaction(function () use ($transfer, $newStatus, $userId, $reason) {
            $transfer = StockTransfer::with('details')->where('id', $transfer->id)->lockForUpdate()->firstOrFail();

            if ($transfer->status === 'completed') {
                throw new InvalidArgumentException('Cannot modify an already completed stock transfer.');
            }

            if ($transfer->status === 'cancelled') {
                throw new InvalidArgumentException('Cannot modify a cancelled stock transfer.');
            }

            if ($newStatus === 'completed') {
                $transfer->status = 'completed';
                if ($reason) {
                    $transfer->notes = ($transfer->notes ? $transfer->notes . ' | ' : '') . $reason;
                }
                $transfer->save();

                // Log destination receipt
                foreach ($transfer->details as $detail) {
                    StockTransaction::create([
                        'product_id' => $detail->product_id,
                        'user_id' => $userId,
                        'supplier_id' => null,
                        'type' => StockTransaction::TYPE_TRANSFER,
                        'qty' => $detail->qty,
                        'reference_number' => $transfer->reference_number,
                        'notes' => "Transfer received at {$transfer->destination_location}",
                        'date' => Carbon::today()->toDateString(),
                    ]);
                }
            } elseif ($newStatus === 'cancelled') {
                // Return stock back to source location
                foreach ($transfer->details as $detail) {
                    $this->adjustStock(
                        productId: $detail->product_id,
                        deltaQty: $detail->qty,
                        type: StockTransaction::TYPE_TRANSFER,
                        userId: $userId,
                        supplierId: null,
                        referenceNumber: $transfer->reference_number,
                        notes: "Transfer cancelled: " . ($reason ?? 'Dispatched stock returned to source')
                    );
                }

                $transfer->status = 'cancelled';
                $transfer->notes = ($transfer->notes ? $transfer->notes . ' | ' : '') . "Cancelled: {$reason}";
                $transfer->save();
            }

            return $transfer;
        });
    }

    /**
     * Calculate comprehensive inventory valuation metrics at cost and retail.
     */
    public function calculateValuationSummary(): array
    {
        $stats = Product::join('stocks', 'products.id', '=', 'stocks.product_id')
            ->selectRaw('
                SUM(stocks.qty * products.cost_price) as total_cost_value,
                SUM(stocks.qty * products.sale_price) as total_retail_value,
                SUM(stocks.qty) as total_units
            ')->first();

        $totalCostValue = (float) ($stats->total_cost_value ?? 0);
        $totalRetailValue = (float) ($stats->total_retail_value ?? 0);
        $totalUnits = (int) ($stats->total_units ?? 0);
        $potentialProfit = max(0, $totalRetailValue - $totalCostValue);

        return [
            'total_cost_value' => $totalCostValue,
            'total_retail_value' => $totalRetailValue,
            'potential_profit' => $potentialProfit,
            'total_units' => $totalUnits,
            'total_skus' => Product::count(),
            'low_stock_count' => Product::whereRaw('qty <= low_stock_threshold')->where('qty', '>', 0)->count(),
            'out_of_stock_count' => Product::where('qty', '<=', 0)->count(),
            'in_stock_count' => Product::whereRaw('qty > low_stock_threshold')->count(),
        ];
    }
}
