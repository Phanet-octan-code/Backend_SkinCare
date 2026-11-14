<?php

namespace App\Services;

use App\Models\Bundle;
use App\Models\CartItem;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\Sale;
use App\Models\SaleDetail;
use App\Models\Setting;
use App\Models\Subscription;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class EcommerceCheckoutService
{
    public function __construct(
        protected InventoryService $inventoryService,
        protected LoyaltyService $loyaltyService,
        protected ReferralService $referralService
    ) {}

    /**
     * Evaluate and validate promo code / coupon code, including flash sales.
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

        // Check active database promotion matching code or name
        $dbPromo = Promotion::active()
            ->where(function ($q) use ($code) {
                $q->where('code', $code)
                  ->orWhere('name', 'LIKE', "%{$code}%");
            })
            ->first();

        if ($dbPromo) {
            if ($dbPromo->min_spend && $subtotal < (float) $dbPromo->min_spend) {
                throw new InvalidArgumentException(
                    "Promotion requires a minimum order subtotal of \${$dbPromo->min_spend}."
                );
            }

            $val = (float) $dbPromo->discount_value;
            $discount = $dbPromo->discount_type === 'percentage'
                ? round($subtotal * ($val / 100), 2)
                : min($subtotal, $val);

            return [
                'code' => $code,
                'promo_id' => $dbPromo->id,
                'type' => $dbPromo->discount_type,
                'value' => $val,
                'discount' => $discount,
                'description' => $dbPromo->name,
            ];
        }

        throw new InvalidArgumentException("Coupon code '{$code}' is invalid or has expired.");
    }

    /**
     * Calculate an authoritative quote for a cart with bundles, loyalty points, and referrals.
     */
    public function calculateQuote(
        array $items,
        ?Customer $customer = null,
        ?string $couponCode = null,
        int $redeemPoints = 0,
        ?string $referralCode = null,
        ?string $shippingZoneCode = null,
        ?string $shippingAddress = null,
        ?string $currency = null
    ): array {
        if (empty($items)) {
            $curr = in_array(strtoupper($currency ?? ''), ['USD', 'KHR'], true) ? strtoupper($currency) : Setting::get('currency', 'USD');
            $rate = (float) Setting::get('exchange_rate_khr', 4100.0);
            return [
                'items' => [],
                'subtotal' => 0.00,
                'discount' => 0.00,
                'member_discount' => 0.00,
                'promo_discount' => 0.00,
                'coupon_discount' => 0.00,
                'applied_coupon' => null,
                'loyalty_points_discount' => 0.00,
                'points_redeemed' => 0,
                'referral_discount' => 0.00,
                'applied_referral' => null,
                'tax' => 0.00,
                'shipping_fee' => 0.00,
                'shipping_zone' => null,
                'final_total' => 0.00,
                'currency_final_total' => 0.00,
                'points_to_earn' => 0,
                'currency' => $curr,
                'exchange_rate' => $rate,
            ];
        }

        $activePromos = \Illuminate\Support\Facades\Cache::remember('active_promotions_with_products', 120, function () {
            return Promotion::active()->with('products')->get();
        });
        $memberRate = $customer ? (float) $customer->membership_discount_percentage : 0.0;

        // Batch pre-fetch all products and bundles in 1 query each (eliminates N+1 queries)
        $productIds = collect($items)->pluck('product_id')->filter()->unique()->all();
        $bundleIds = collect($items)->pluck('bundle_id')->filter()->unique()->all();

        $loadedProducts = !empty($productIds)
            ? Product::whereIn('id', $productIds)->where('status', 'active')->get()->keyBy('id')
            : collect();

        $loadedBundles = !empty($bundleIds)
            ? Bundle::with('items.product')->whereIn('id', $bundleIds)->where('status', 'active')->get()->keyBy('id')
            : collect();

        $subtotal = 0.00;
        $totalPromoDiscount = 0.00;
        $totalMemberDiscount = 0.00;
        $quotedItems = [];

        foreach ($items as $item) {
            $bundleId = (int) ($item['bundle_id'] ?? 0);
            $productId = (int) ($item['product_id'] ?? 0);
            $qty = max(1, (int) ($item['qty'] ?? 1));

            if ($bundleId > 0) {
                // Quoting a Product Bundle / Gift Set (from pre-fetched map)
                $bundle = $loadedBundles->get($bundleId);
                if (!$bundle) {
                    continue;
                }

                $unitPrice = (float) $bundle->price;
                $rawLine = $unitPrice * $qty;
                $subtotal += $rawLine;

                $availQty = $bundle->available_qty;
                $inStock = $availQty >= $qty;

                // Member discount applies to bundles
                $memberDiscount = 0.00;
                if ($memberRate > 0) {
                    $memberDiscount = round($rawLine * ($memberRate / 100), 2);
                }

                $totalMemberDiscount += $memberDiscount;
                $finalLineSubtotal = max(0.00, round($rawLine - $memberDiscount, 2));

                $quotedItems[] = [
                    'bundle_id' => $bundle->id,
                    'product_id' => null,
                    'is_bundle' => true,
                    'name' => $bundle->name . ($bundle->type === 'gift_set' ? ' (Gift Set)' : ' (Bundle)'),
                    'image_url' => $bundle->image_url,
                    'unit_price' => $unitPrice,
                    'original_price' => $bundle->calculated_original_price,
                    'qty' => $qty,
                    'available_qty' => $availQty,
                    'in_stock' => $inStock,
                    'line_subtotal' => $rawLine,
                    'promo_discount' => 0.00,
                    'member_discount' => $memberDiscount,
                    'total_discount' => $memberDiscount,
                    'final_line_total' => $finalLineSubtotal,
                    'promo_name' => null,
                ];
            } elseif ($productId > 0) {
                // Quoting an Individual Product (from pre-fetched map)
                $product = $loadedProducts->get($productId);
                if (!$product) {
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
                    'bundle_id' => null,
                    'product_id' => $product->id,
                    'is_bundle' => false,
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
        }

        // Coupon evaluation
        $appliedCoupon = $this->evaluateCoupon($couponCode, $subtotal);
        $couponDiscount = $appliedCoupon ? $appliedCoupon['discount'] : 0.00;

        // Referral Code evaluation
        $appliedReferral = null;
        $referralDiscount = 0.00;
        if ($referralCode) {
            $appliedReferral = $this->referralService->validateReferralCode($referralCode, $customer, $subtotal);
            $referralDiscount = $appliedReferral['discount'];
        }

        // Loyalty Points Redemption
        $pointsRedeemed = 0;
        $loyaltyPointsDiscount = 0.00;
        if ($customer && $redeemPoints > 0) {
            $redemption = $this->loyaltyService->calculateRedemptionDiscount($customer, $redeemPoints, $subtotal);
            $pointsRedeemed = $redemption['points_to_redeem'];
            $loyaltyPointsDiscount = $redemption['discount'];
        }

        $totalDiscount = round(
            $totalPromoDiscount + $totalMemberDiscount + $couponDiscount + $referralDiscount + $loyaltyPointsDiscount,
            2
        );

        $taxPercent = (float) Setting::get('tax_rate', 5.0);
        $netTaxable = max(0.00, $subtotal - $totalDiscount);
        $tax = round($netTaxable * ($taxPercent / 100), 2);

        // Resolve regional shipping zone and fees
        $zone = \App\Models\ShippingZone::resolveForAddress($shippingAddress, $shippingZoneCode);
        $standardFee = $zone ? (float) $zone->standard_fee : 3.00;
        $freeThreshold = $zone ? (float) $zone->free_shipping_threshold : 50.00;

        $hasTierFreeShip = $customer && (strtolower($customer->membership_level ?? '') === 'platinum');
        $isFreeShipping = ($appliedCoupon && $appliedCoupon['type'] === 'shipping')
            || $hasTierFreeShip
            || ($freeThreshold > 0 && $netTaxable >= $freeThreshold)
            || $netTaxable == 0.00;

        $shippingFee = $isFreeShipping ? 0.00 : $standardFee;
        $finalTotal = round($netTaxable + $tax + $shippingFee, 2);

        $orderCurrency = in_array(strtoupper($currency ?? ''), ['USD', 'KHR'], true)
            ? strtoupper($currency)
            : Setting::get('currency', 'USD');
        $exchangeRate = (float) Setting::get('exchange_rate_khr', 4100.0);
        $currencyFinalTotal = $orderCurrency === 'KHR' ? (float) round($finalTotal * $exchangeRate) : $finalTotal;

        $pointsToEarn = $customer ? $this->loyaltyService->calculatePointsEarned($customer, $finalTotal) : 0;

        return [
            'items' => $quotedItems,
            'subtotal' => round($subtotal, 2),
            'promo_discount' => round($totalPromoDiscount, 2),
            'member_discount' => round($totalMemberDiscount, 2),
            'coupon_discount' => round($couponDiscount, 2),
            'applied_coupon' => $appliedCoupon,
            'referral_discount' => round($referralDiscount, 2),
            'applied_referral' => $appliedReferral,
            'loyalty_points_discount' => round($loyaltyPointsDiscount, 2),
            'points_redeemed' => $pointsRedeemed,
            'total_discount' => $totalDiscount,
            'tax' => round($tax, 2),
            'tax_percent' => $taxPercent,
            'shipping_fee' => round($shippingFee, 2),
            'free_shipping_threshold' => $freeThreshold,
            'shipping_zone' => $zone ? [
                'id' => $zone->id,
                'code' => $zone->code,
                'name' => $zone->localized_name,
                'name_en' => $zone->name_en,
                'name_km' => $zone->name_km,
                'standard_fee' => (float) $zone->standard_fee,
                'free_shipping_threshold' => (float) $zone->free_shipping_threshold,
                'estimated_delivery' => $zone->localized_delivery,
            ] : null,
            'final_total' => $finalTotal,
            'currency_final_total' => $currencyFinalTotal,
            'points_to_earn' => $pointsToEarn,
            'currency' => $orderCurrency,
            'exchange_rate' => $exchangeRate,
        ];
    }

    /**
     * Process authoritative online checkout transaction with atomic inventory locks,
     * bundles support, loyalty points, referrals, and recurring subscriptions.
     */
    public function processCheckout(
        array $items,
        ?Customer $customer = null,
        ?string $shippingAddress = null,
        string $paymentType = 'card',
        ?string $orderNotes = null,
        ?array $guestInfo = null,
        ?string $couponCode = null,
        int $redeemPoints = 0,
        ?string $referralCode = null,
        ?int $subscriptionId = null,
        bool $isSubscription = false,
        int $subscriptionIntervalDays = 30,
        ?string $currency = null,
        ?string $shippingZoneCode = null
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
            $couponCode,
            $redeemPoints,
            $referralCode,
            $subscriptionId,
            $isSubscription,
            $subscriptionIntervalDays,
            $currency,
            $shippingZoneCode
        ) {
            // 1. Resolve or establish customer record
            $resolvedCustomer = $customer;
            if (!$resolvedCustomer && !empty($guestInfo['email'])) {
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

            // 2. Fetch Online Storefront Bot Cashier (Cached / Static)
            static $cachedBotUser = null;
            if (!$cachedBotUser) {
                $cachedBotUser = User::where('email', 'storefront@skincare.com')->first();
                if (!$cachedBotUser) {
                    $cachedBotUser = User::firstOrCreate(
                        ['email' => 'storefront@skincare.com'],
                        [
                            'name' => 'Online Storefront Bot',
                            'password' => bcrypt(uniqid()),
                            'role' => 'cashier',
                            'status' => 'active',
                        ]
                    );
                }
            }
            $botUser = $cachedBotUser;

            $activePromos = \Illuminate\Support\Facades\Cache::remember('active_promotions_with_products', 120, function () {
            return Promotion::active()->with('products')->get();
        });
            $memberRate = $resolvedCustomer ? (float) $resolvedCustomer->membership_discount_percentage : 0.0;

            $subtotal = 0.00;
            $totalDiscount = 0.00;
            $lineItems = [];
            $bundleItemsDeductions = [];

            // 3. Process each line item with atomic stock verification
            foreach ($items as $item) {
                $bundleId = (int) ($item['bundle_id'] ?? 0);
                $productId = (int) ($item['product_id'] ?? 0);
                $qty = max(1, (int) ($item['qty'] ?? 1));

                if ($bundleId > 0) {
                    // Bundle Processing
                    $bundle = Bundle::where('id', $bundleId)->lockForUpdate()->first();
                    if (!$bundle || $bundle->status !== 'active') {
                        throw new InvalidArgumentException("Bundle #{$bundleId} is currently unavailable.");
                    }

                    // Check and lock constituent products
                    $bundleConstituents = $bundle->items()->with('product')->get();
                    if ($bundleConstituents->isEmpty()) {
                        throw new InvalidArgumentException("Bundle '{$bundle->name}' contains no products.");
                    }

                    foreach ($bundleConstituents as $cItem) {
                        $lockedProduct = Product::where('id', $cItem->product_id)->lockForUpdate()->first();
                        if (!$lockedProduct || $lockedProduct->status !== 'active') {
                            throw new InvalidArgumentException("Component product in '{$bundle->name}' is unavailable.");
                        }

                        $requiredUnits = $cItem->quantity * $qty;
                        if ($lockedProduct->qty < $requiredUnits) {
                            throw new InvalidArgumentException(
                                "Insufficient stock for component '{$lockedProduct->name}' in bundle '{$bundle->name}'. Available: {$lockedProduct->qty}, Required: {$requiredUnits}."
                            );
                        }

                        $bundleItemsDeductions[] = [
                            'product_id' => $lockedProduct->id,
                            'qty' => $requiredUnits,
                        ];
                    }

                    $unitPrice = (float) $bundle->price;
                    $rawSub = $unitPrice * $qty;
                    $subtotal += $rawSub;

                    $memberDiscount = 0.00;
                    if ($memberRate > 0) {
                        $memberDiscount = round($rawSub * ($memberRate / 100), 2);
                    }

                    $totalDiscount += $memberDiscount;
                    $finalLine = round($rawSub - $memberDiscount, 2);

                    $lineItems[] = [
                        'bundle' => $bundle,
                        'product' => null,
                        'qty' => $qty,
                        'price' => $unitPrice,
                        'discount' => $memberDiscount,
                        'subtotal' => $finalLine,
                    ];
                } elseif ($productId > 0) {
                    // Single Product Processing
                    $product = Product::where('id', $productId)->lockForUpdate()->first();
                    if (!$product || $product->status !== 'active') {
                        throw new InvalidArgumentException("Product with ID #{$productId} is unavailable.");
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

                    $itemDiscount = min($rawItemSubtotal, round($bestPromo + $memberDiscount, 2));
                    $totalDiscount += $itemDiscount;
                    $finalLineSubtotal = round($rawItemSubtotal - $itemDiscount, 2);

                    $lineItems[] = [
                        'bundle' => null,
                        'product' => $product,
                        'qty' => $qty,
                        'price' => $unitPrice,
                        'discount' => $itemDiscount,
                        'subtotal' => $finalLineSubtotal,
                    ];
                }
            }

            // 4. Coupon Discount
            $appliedCoupon = $this->evaluateCoupon($couponCode, $subtotal);
            $couponDiscount = $appliedCoupon ? $appliedCoupon['discount'] : 0.00;
            $totalDiscount += $couponDiscount;

            if ($appliedCoupon && !empty($appliedCoupon['promo_id'])) {
                Promotion::where('id', $appliedCoupon['promo_id'])->increment('current_usage_count');
            }

            // 5. Referral Code Discount
            $appliedReferral = null;
            $referralDiscount = 0.00;
            $referrer = null;
            if ($referralCode) {
                $appliedReferral = $this->referralService->validateReferralCode($referralCode, $resolvedCustomer, $subtotal);
                $referralDiscount = $appliedReferral['discount'];
                $totalDiscount += $referralDiscount;
                $referrer = Customer::find($appliedReferral['referrer_id']);
            }

            // 6. Loyalty Points Redemption
            $pointsToRedeem = 0;
            $loyaltyDiscount = 0.00;
            if ($resolvedCustomer && $redeemPoints > 0) {
                $redemption = $this->loyaltyService->calculateRedemptionDiscount($resolvedCustomer, $redeemPoints, $subtotal);
                $pointsToRedeem = $redemption['points_to_redeem'];
                $loyaltyDiscount = $redemption['discount'];
                $totalDiscount += $loyaltyDiscount;
            }

            // 7. Calculate Tax and Shipping
            $taxPercent = (float) Setting::get('tax_rate', 5.0);
            $netTaxable = max(0.00, $subtotal - $totalDiscount);
            $tax = round($netTaxable * ($taxPercent / 100), 2);

            $zone = \App\Models\ShippingZone::resolveForAddress($shippingAddress, $shippingZoneCode);
            $standardFee = $zone ? (float) $zone->standard_fee : 3.00;
            $freeThreshold = $zone ? (float) $zone->free_shipping_threshold : 50.00;

            $hasTierFreeShip = $resolvedCustomer && (strtolower($resolvedCustomer->membership_level ?? '') === 'platinum');
            $isFreeShipping = ($appliedCoupon && $appliedCoupon['type'] === 'shipping')
                || $hasTierFreeShip
                || ($freeThreshold > 0 && $netTaxable >= $freeThreshold)
                || $netTaxable == 0.00;

            $shippingFee = $isFreeShipping ? 0.00 : $standardFee;
            $finalTotal = round($netTaxable + $tax + $shippingFee, 2);

            // Determine locked order currency and historical exchange rate at time of creation
            $orderCurrency = in_array(strtoupper($currency ?? ''), ['USD', 'KHR'], true)
                ? strtoupper($currency)
                : Setting::get('currency', 'USD');
            $activeRate = (float) Setting::get('exchange_rate_khr', 4100.0);

            // 8. Order Status and Payment Status
            $invoiceNumber = Sale::generateInvoiceNumber();
            $trackingNumber = 'TRK-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));

            $paymentStatus = Sale::PAYMENT_PENDING;
            $orderStatus = Sale::STATUS_PENDING;

            if ($paymentType === 'cash') {
                $paymentStatus = Sale::PAYMENT_PENDING;
                $orderStatus = Sale::STATUS_CONFIRMED;
            }

            // Notes
            $notesArr = [];
            if ($orderNotes) $notesArr[] = trim($orderNotes);
            if ($zone) $notesArr[] = "[Zone: {$zone->name_en}]";
            if ($appliedCoupon) $notesArr[] = "[Coupon: {$appliedCoupon['code']} (-" . Setting::formatMoney($couponDiscount) . ")]";
            if ($appliedReferral) $notesArr[] = "[Referral: {$appliedReferral['code']} (-" . Setting::formatMoney($referralDiscount) . ")]";
            if ($loyaltyDiscount > 0) $notesArr[] = "[Loyalty: -{$pointsToRedeem} pts (-" . Setting::formatMoney($loyaltyDiscount) . ")]";

            // 9. Persist Sale
            $sale = Sale::create([
                'user_id' => $botUser->id,
                'customer_id' => $resolvedCustomer?->id,
                'subscription_id' => $subscriptionId,
                'invoice_number' => $invoiceNumber,
                'subtotal' => round($subtotal, 2),
                'discount' => round($totalDiscount, 2),
                'tax' => round($tax, 2),
                'shipping_fee' => round($shippingFee, 2),
                'shipping_zone_id' => $zone?->id,
                'shipping_zone_name' => $zone?->name_en,
                'final_total' => round($finalTotal, 2),
                'applied_referral_code' => $referralCode ? strtoupper(trim($referralCode)) : null,
                'loyalty_points_redeemed' => $pointsToRedeem,
                'loyalty_points_discount' => round($loyaltyDiscount, 2),
                'shipping_address' => $shippingAddress,
                'tracking_number' => $trackingNumber,
                'order_notes' => implode(' | ', $notesArr) ?: null,
                'order_source' => 'online',
                'payment_type' => $paymentType,
                'payment_status' => $paymentStatus,
                'status' => $orderStatus,
                'currency' => $orderCurrency,
                'exchange_rate' => $activeRate,
            ]);

            // 10. Persist Details & Deduct Stock
            $persistedDetails = [];
            foreach ($lineItems as $item) {
                $detail = SaleDetail::create([
                    'sale_id' => $sale->id,
                    'product_id' => $item['product']?->id,
                    'bundle_id' => $item['bundle']?->id,
                    'qty' => $item['qty'],
                    'price' => $item['price'],
                    'discount' => $item['discount'],
                    'subtotal' => $item['subtotal'],
                ]);
                $detail->setRelation('product', $item['product']);
                $detail->setRelation('bundle', $item['bundle']);
                $persistedDetails[] = $detail;

                if ($item['product']) {
                    $this->inventoryService->recordSaleDeduction(
                        productId: $item['product']->id,
                        qty: $item['qty'],
                        userId: $botUser->id,
                        invoiceNumber: $sale->invoice_number
                    );
                }
            }

            // Deduct constituent bundle products
            foreach ($bundleItemsDeductions as $deduction) {
                $this->inventoryService->recordSaleDeduction(
                    productId: $deduction['product_id'],
                    qty: $deduction['qty'],
                    userId: $botUser->id,
                    invoiceNumber: $sale->invoice_number
                );
            }

            // 11. Deduct redeemed points from customer account
            if ($resolvedCustomer && $pointsToRedeem > 0) {
                $resolvedCustomer->deductLoyaltyPoints(
                    points: $pointsToRedeem,
                    type: 'redeemed_discount',
                    saleId: $sale->id,
                    description: "Redeemed for order #{$sale->invoice_number}"
                );
            }

            // 12. Record referral usage
            if ($referrer && $resolvedCustomer && $referralCode) {
                $this->referralService->recordReferralUsage(
                    sale: $sale,
                    referrer: $referrer,
                    referee: $resolvedCustomer,
                    code: $referralCode
                );

                if ($orderStatus === Sale::STATUS_CONFIRMED) {
                    $this->referralService->rewardReferrer($sale);
                }
            }

            // 13. Award loyalty points for this sale
            $this->loyaltyService->awardPointsForSale($sale);

            // 14. Optional Recurring Skincare Subscription Creation
            if ($isSubscription && $resolvedCustomer && !$subscriptionId) {
                $firstItem = $lineItems[0] ?? null;
                if ($firstItem) {
                    Subscription::create([
                        'customer_id' => $resolvedCustomer->id,
                        'product_id' => $firstItem['product']?->id,
                        'bundle_id' => $firstItem['bundle']?->id,
                        'status' => Subscription::STATUS_ACTIVE,
                        'billing_interval_days' => in_array($subscriptionIntervalDays, [30, 60, 90]) ? $subscriptionIntervalDays : 30,
                        'discount_percentage' => 10.00,
                        'unit_price' => $firstItem['price'],
                        'quantity' => $firstItem['qty'],
                        'next_billing_date' => Carbon::today()->addDays($subscriptionIntervalDays),
                        'last_billing_date' => Carbon::today(),
                        'last_sale_id' => $sale->id,
                        'payment_method' => $paymentType,
                        'shipping_address' => $shippingAddress ?? 'Customer Profile Address',
                    ]);
                }
            }

            // 15. Clear customer cart
            if ($customer) {
                CartItem::where('customer_id', $customer->id)->delete();
            }

            $sale->setRelation('details', collect($persistedDetails));
            $sale->setRelation('customer', $resolvedCustomer);

            return $sale;
        });
    }
}
