<?php

namespace App\Services;

use App\Models\CartItem;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\Sale;
use App\Models\SaleDetail;
use App\Models\Setting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class EcommerceCheckoutService
{
    public function __construct(
        protected InventoryService $inventoryService
    ) {}

    /**
     * Evaluate and validate promo code / coupon code.
     */
    public function evaluateCoupon(?string $couponCode, float $subtotal): ?array
    {
        if (!$couponCode) {
            return null;
        }

        $code = strtoupper(trim($couponCode));
        if ($code === '') {
            return null;
        }

        // Support popular predefined skincare e-commerce promotional codes
        $coupons = [
            'GLOW10' => ['type' => 'percentage', 'value' => 10.0, 'description' => '10% Off Radiance Coupon'],
            'WELCOME10' => ['type' => 'percentage', 'value' => 10.0, 'description' => '10% Welcome First Order Coupon'],
            'SKINCARE5' => ['type' => 'fixed', 'value' => 5.0, 'description' => '$5.00 Off Beauty Treat Coupon'],
            'SAVE5' => ['type' => 'fixed', 'value' => 5.0, 'description' => '$5.00 Cart Savings Coupon'],
            'VIP20' => ['type' => 'percentage', 'value' => 20.0, 'description' => '20% Off VIP Prestige Coupon'],
            'FREESHIP' => ['type' => 'shipping', 'value' => 100.0, 'description' => 'Free Express Shipping Coupon'],
        ];

        if (isset($coupons[$code])) {
            $conf = $coupons[$code];
            $discount = 0.00;
            if ($conf['type'] === 'percentage') {
                $discount = round($subtotal * ($conf['value'] / 100), 2);
            } elseif ($conf['type'] === 'fixed') {
                $discount = min($subtotal, $conf['value']);
            }
            return [
                'code' => $code,
                'type' => $conf['type'],
                'value' => $conf['value'],
                'discount' => $discount,
                'description' => $conf['description'],
            ];
        }

        // Check active database promotion matching code/name
        $dbPromo = Promotion::active()
            ->where(function ($q) use ($code) {
                $q->where('name', 'LIKE', "%{$code}%");
            })
            ->first();

        if ($dbPromo) {
            $val = (float) $dbPromo->discount_value;
            $discount = $dbPromo->discount_type === 'percentage'
                ? round($subtotal * ($val / 100), 2)
                : min($subtotal, $val);

            return [
                'code' => $code,
                'type' => $dbPromo->discount_type,
                'value' => $val,
                'discount' => $discount,
                'description' => $dbPromo->name,
            ];
        }

        throw new InvalidArgumentException("Coupon code '{$code}' is invalid or has expired.");
    }

    /**
     * Calculate an authoritative quote for a given cart.
     */
    public function calculateQuote(array $items, ?Customer $customer = null, ?string $couponCode = null): array
    {
        if (empty($items)) {
            return [
                'items' => [],
                'subtotal' => 0.00,
                'discount' => 0.00,
                'member_discount' => 0.00,
                'promo_discount' => 0.00,
                'coupon_discount' => 0.00,
                'applied_coupon' => null,
                'tax' => 0.00,
                'shipping_fee' => 0.00,
                'final_total' => 0.00,
                'currency' => Setting::get('currency', 'USD'),
                'exchange_rate' => (float) Setting::get('exchange_rate_khr', 4100.0),
            ];
        }

        // 1. Consolidate cart items
        $consolidated = [];
        foreach ($items as $item) {
            $pid = (int) ($item['product_id'] ?? 0);
            $qty = (int) ($item['qty'] ?? 1);
            if ($pid > 0 && $qty > 0) {
                $consolidated[$pid] = ($consolidated[$pid] ?? 0) + $qty;
            }
        }

        // 2. Fetch active promotions
        $today = Carbon::today()->toDateString();
        $activePromos = Promotion::active()->with('products')->get();

        // 3. Customer tier rate
        $memberRate = $customer ? (float) $customer->membership_discount_percentage : 0.0;

        $subtotal = 0.00;
        $totalPromoDiscount = 0.00;
        $totalMemberDiscount = 0.00;
        $quotedItems = [];

        foreach ($consolidated as $productId => $qty) {
            $product = Product::find($productId);
            if (!$product || $product->status !== 'active') {
                continue;
            }

            $unitPrice = (float) $product->sale_price;
            $rawLine = $unitPrice * $qty;
            $subtotal += $rawLine;

            // Best promotion discount
            $bestPromo = 0.00;
            $promoName = null;
            foreach ($activePromos as $promo) {
                if ($promo->appliesToProduct($product->id)) {
                    $val = (float) $promo->discount_value;
                    $calc = $promo->discount_type === 'percentage'
                        ? $rawLine * ($val / 100)
                        : min($rawLine, $val * $qty);

                    if ($calc > $bestPromo) {
                        $bestPromo = $calc;
                        $promoName = $promo->name;
                    }
                }
            }

            // Membership tier discount on remainder
            $memberDiscount = 0.00;
            if ($memberRate > 0) {
                $memberDiscount = ($rawLine - $bestPromo) * ($memberRate / 100);
            }

            $lineDiscount = round($bestPromo + $memberDiscount, 2);
            $totalPromoDiscount += $bestPromo;
            $totalMemberDiscount += $memberDiscount;
            $finalLineSubtotal = max(0.00, round($rawLine - $lineDiscount, 2));

            $quotedItems[] = [
                'product_id' => $product->id,
                'name' => $product->name,
                'image_url' => $product->image_url,
                'unit_price' => $unitPrice,
                'qty' => $qty,
                'available_qty' => (int) $product->qty,
                'in_stock' => $product->qty >= $qty,
                'line_subtotal' => $rawLine,
                'promo_discount' => round($bestPromo, 2),
                'member_discount' => round($memberDiscount, 2),
                'total_discount' => $lineDiscount,
                'final_line_total' => $finalLineSubtotal,
                'promo_name' => $promoName,
            ];
        }

        // 4. Coupon evaluation
        $appliedCoupon = $this->evaluateCoupon($couponCode, $subtotal);
        $couponDiscount = $appliedCoupon ? $appliedCoupon['discount'] : 0.00;

        $totalDiscount = round($totalPromoDiscount + $totalMemberDiscount + $couponDiscount, 2);
        $taxPercent = (float) Setting::get('tax_rate', 5.0);
        $netTaxable = max(0.00, $subtotal - $totalDiscount);
        $tax = round($netTaxable * ($taxPercent / 100), 2);

        // Standard shipping: Free if coupon is FREESHIP or net >= $50
        $isFreeShipping = ($appliedCoupon && $appliedCoupon['type'] === 'shipping') || $netTaxable >= 50.00 || $netTaxable == 0.00;
        $shippingFee = $isFreeShipping ? 0.00 : 3.00;
        $finalTotal = round($netTaxable + $tax + $shippingFee, 2);

        return [
            'items' => $quotedItems,
            'subtotal' => round($subtotal, 2),
            'promo_discount' => round($totalPromoDiscount, 2),
            'member_discount' => round($totalMemberDiscount, 2),
            'coupon_discount' => round($couponDiscount, 2),
            'applied_coupon' => $appliedCoupon,
            'total_discount' => $totalDiscount,
            'tax' => round($tax, 2),
            'tax_percent' => $taxPercent,
            'shipping_fee' => round($shippingFee, 2),
            'free_shipping_threshold' => 50.00,
            'final_total' => $finalTotal,
            'currency' => Setting::get('currency', 'USD'),
            'exchange_rate' => (float) Setting::get('exchange_rate_khr', 4100.0),
        ];
    }

    /**
     * Process authoritative online checkout transaction with atomic inventory locks.
     */
    public function processCheckout(
        array $items,
        ?Customer $customer = null,
        ?string $shippingAddress = null,
        string $paymentType = 'card',
        ?string $orderNotes = null,
        ?array $guestInfo = null,
        ?string $couponCode = null
    ): Sale {
        if (empty($items)) {
            throw new InvalidArgumentException('Shopping cart cannot be empty.');
        }

        $paymentType = strtolower(trim($paymentType));
        if (!in_array($paymentType, ['card', 'qr', 'cash', 'transfer', 'qris'], true)) {
            throw new InvalidArgumentException("Unsupported payment type [{$paymentType}]. Supported: card, qr, cash.");
        }

        return DB::transaction(function () use (
            $items,
            $customer,
            $shippingAddress,
            $paymentType,
            $orderNotes,
            $guestInfo,
            $couponCode
        ) {
            // 1. Resolve or establish customer record
            $resolvedCustomer = $customer;
            if (!$resolvedCustomer && !empty($guestInfo['email'])) {
                // Find or create customer CRM record for guest
                $guestEmail = trim($guestInfo['email']);
                $resolvedCustomer = Customer::where('email', $guestEmail)->first();

                if (!$resolvedCustomer) {
                    $tierCode = 'BRZ';
                    $memNum = 'MEM-' . $tierCode . '-' . rand(1000, 9999);
                    while (Customer::where('membership_number', $memNum)->exists()) {
                        $memNum = 'MEM-' . $tierCode . '-' . rand(1000, 9999);
                    }

                    $resolvedCustomer = Customer::create([
                        'name' => trim($guestInfo['name'] ?? 'Guest Customer'),
                        'email' => $guestEmail,
                        'phone' => trim($guestInfo['phone'] ?? null),
                        'address' => $shippingAddress,
                        'membership_number' => $memNum,
                        'membership_level' => 'Bronze',
                        'status' => 'active',
                    ]);
                }
            }

            // 2. Fetch Online Storefront Bot Cashier
            $botUser = User::where('email', 'storefront@skincare.com')->first();
            if (!$botUser) {
                $botUser = User::firstOrCreate(
                    ['email' => 'storefront@skincare.com'],
                    [
                        'name' => 'Online Storefront Bot',
                        'password' => bcrypt(uniqid()),
                        'role' => 'cashier',
                        'status' => 'active',
                    ]
                );
            }

            // 3. Consolidate Cart
            $consolidated = [];
            foreach ($items as $item) {
                $pid = (int) ($item['product_id'] ?? 0);
                $qty = (int) ($item['qty'] ?? 1);
                if ($pid > 0 && $qty > 0) {
                    $consolidated[$pid] = ($consolidated[$pid] ?? 0) + $qty;
                }
            }

            if (empty($consolidated)) {
                throw new InvalidArgumentException('No valid products selected in cart.');
            }

            // Sort to avoid deadlocks
            ksort($consolidated);

            $activePromos = Promotion::active()->with('products')->get();
            $memberRate = $resolvedCustomer ? (float) $resolvedCustomer->membership_discount_percentage : 0.0;

            $subtotal = 0.00;
            $totalDiscount = 0.00;
            $lineItems = [];

            // 4. Lock products, verify stock, and compute exact pricing
            foreach ($consolidated as $productId => $qty) {
                $product = Product::where('id', $productId)->lockForUpdate()->first();

                if (!$product) {
                    throw new InvalidArgumentException("Product with ID #{$productId} was not found.");
                }

                if ($product->status !== 'active') {
                    throw new InvalidArgumentException("Product '{$product->name}' is currently unavailable.");
                }

                if ($product->qty < $qty) {
                    throw new InvalidArgumentException(
                        "Insufficient stock for '{$product->name}'. Available: {$product->qty}, Requested: {$qty}."
                    );
                }

                $unitPrice = (float) $product->sale_price;
                $rawItemSubtotal = $unitPrice * $qty;
                $subtotal += $rawItemSubtotal;

                // Promotion discount
                $bestPromo = 0.00;
                foreach ($activePromos as $promo) {
                    if ($promo->appliesToProduct($product->id)) {
                        $val = (float) $promo->discount_value;
                        $calc = $promo->discount_type === 'percentage'
                            ? $rawItemSubtotal * ($val / 100)
                            : min($rawItemSubtotal, $val * $qty);

                        if ($calc > $bestPromo) {
                            $bestPromo = $calc;
                        }
                    }
                }

                // Member discount
                $memberDiscount = 0.00;
                if ($memberRate > 0) {
                    $memberDiscount = ($rawItemSubtotal - $bestPromo) * ($memberRate / 100);
                }

                $itemDiscount = round($bestPromo + $memberDiscount, 2);
                $itemDiscount = min($rawItemSubtotal, $itemDiscount);
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

            // 5. Evaluate Coupon Code
            $appliedCoupon = $this->evaluateCoupon($couponCode, $subtotal);
            $couponDiscount = $appliedCoupon ? $appliedCoupon['discount'] : 0.00;
            $totalDiscount += $couponDiscount;

            // 6. Calculate Tax and Shipping
            $taxPercent = (float) Setting::get('tax_rate', 5.0);
            $netTaxable = max(0.00, $subtotal - $totalDiscount);
            $tax = round($netTaxable * ($taxPercent / 100), 2);
            $isFreeShipping = ($appliedCoupon && $appliedCoupon['type'] === 'shipping') || $netTaxable >= 50.00 || $netTaxable == 0.00;
            $shippingFee = $isFreeShipping ? 0.00 : 3.00;
            $finalTotal = round($netTaxable + $tax + $shippingFee, 2);

            // 7. Generate Invoice & Tracking Numbers
            $invoiceNumber = Sale::generateInvoiceNumber();
            $trackingNumber = 'TRK-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));

            // Format notes
            $fullNotes = $orderNotes ? trim($orderNotes) : '';
            if ($appliedCoupon) {
                $couponNote = "[Coupon Applied: {$appliedCoupon['code']} (-" . Setting::formatMoney($couponDiscount) . ")]";
                $fullNotes = $fullNotes ? "{$fullNotes} | {$couponNote}" : $couponNote;
            }

            // 8. Order Status and Payment Status separation
            $paymentStatus = Sale::PAYMENT_PENDING;
            $orderStatus = Sale::STATUS_PENDING;

            if ($paymentType === 'cash') {
                $paymentStatus = Sale::PAYMENT_PENDING;
                $orderStatus = Sale::STATUS_CONFIRMED; // Cash on delivery is confirmed for fulfillment
            }

            // 9. Persist Sale Master Record
            $sale = Sale::create([
                'user_id' => $botUser->id,
                'customer_id' => $resolvedCustomer?->id,
                'invoice_number' => $invoiceNumber,
                'subtotal' => round($subtotal, 2),
                'discount' => round($totalDiscount, 2),
                'tax' => round($tax, 2),
                'shipping_fee' => round($shippingFee, 2),
                'final_total' => round($finalTotal, 2),
                'shipping_address' => $shippingAddress,
                'tracking_number' => $trackingNumber,
                'order_notes' => $fullNotes ?: null,
                'order_source' => 'online',
                'payment_type' => $paymentType,
                'payment_status' => $paymentStatus,
                'status' => $orderStatus,
                'currency' => Setting::get('currency', 'USD'),
                'exchange_rate' => (float) Setting::get('exchange_rate_khr', 4100.0),
            ]);

            // 10. Persist Line Items & Atomically Deduct Inventory using POS Service
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

                // Inventory deduction using canonical POS service
                $this->inventoryService->recordSaleDeduction(
                    productId: $item['product']->id,
                    qty: $item['qty'],
                    userId: $botUser->id,
                    invoiceNumber: $sale->invoice_number
                );
            }

            // 11. Clear Server-Side Cart for Authenticated Customer
            if ($customer) {
                CartItem::where('customer_id', $customer->id)
                    ->whereIn('product_id', array_keys($consolidated))
                    ->delete();
            }

            $sale->setRelation('details', collect($persistedDetails));
            $sale->setRelation('customer', $resolvedCustomer);

            return $sale;
        });
    }
}
