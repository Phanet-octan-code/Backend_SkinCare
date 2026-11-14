<?php

namespace App\Services;

use App\Models\Bundle;
use App\Models\BundleItem;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class BundleService
{
    /**
     * Create or update bundle with constituent items.
     */
    public function saveBundle(array $data, ?Bundle $bundle = null): Bundle
    {
        return DB::transaction(function () use ($data, $bundle) {
            $bundleData = [
                'name' => $data['name'],
                'type' => $data['type'] ?? 'bundle',
                'description' => $data['description'] ?? null,
                'image' => $data['image'] ?? null,
                'price' => $data['price'],
                'original_price' => $data['original_price'] ?? null,
                'packaging_details' => $data['packaging_details'] ?? null,
                'status' => $data['status'] ?? 'active',
            ];

            if ($bundle) {
                $bundle->update($bundleData);
            } else {
                $bundle = Bundle::create($bundleData);
            }

            // Sync items if provided
            if (isset($data['items']) && is_array($data['items'])) {
                $bundle->items()->delete();
                foreach ($data['items'] as $item) {
                    $productId = (int) ($item['product_id'] ?? 0);
                    $qty = max(1, (int) ($item['quantity'] ?? 1));
                    $isFreeGift = (bool) ($item['is_free_gift'] ?? false);

                    if ($productId > 0) {
                        BundleItem::create([
                            'bundle_id' => $bundle->id,
                            'product_id' => $productId,
                            'quantity' => $qty,
                            'is_free_gift' => $isFreeGift,
                        ]);
                    }
                }
            }

            return $bundle->fresh(['items.product']);
        });
    }

    /**
     * Check if a bundle has sufficient component inventory for a requested quantity.
     */
    public function checkStock(Bundle $bundle, int $requestedQty): array
    {
        $items = $bundle->items()->with('product')->get();
        if ($items->isEmpty()) {
            return [
                'available' => false,
                'available_qty' => 0,
                'reason' => 'Bundle contains no products.',
            ];
        }

        $minQty = PHP_INT_MAX;
        foreach ($items as $item) {
            $product = $item->product;
            if (!$product || $product->status !== 'active') {
                return [
                    'available' => false,
                    'available_qty' => 0,
                    'reason' => "Component product '{$item->product?->name}' is unavailable.",
                ];
            }

            $perBundle = max(1, (int) $item->quantity);
            $possible = (int) floor($product->qty / $perBundle);
            $minQty = min($minQty, $possible);

            if ($product->qty < ($perBundle * $requestedQty)) {
                return [
                    'available' => false,
                    'available_qty' => $minQty,
                    'reason' => "Insufficient stock for component '{$product->name}'. Available: {$product->qty}, Required: " . ($perBundle * $requestedQty),
                ];
            }
        }

        return [
            'available' => true,
            'available_qty' => $minQty,
            'reason' => null,
        ];
    }
}

