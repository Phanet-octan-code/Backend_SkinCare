<?php

use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\CheckoutController;
use App\Http\Controllers\Api\CustomerAddressController;
use App\Http\Controllers\Api\CustomerAuthController;
use App\Http\Controllers\Api\CustomerLoyaltyController;
use App\Http\Controllers\Api\CustomerNotificationController;
use App\Http\Controllers\Api\CustomerOrderController;
use App\Http\Controllers\Api\CustomerSubscriptionController;
use App\Http\Controllers\Api\CustomerWishlistController;
use App\Http\Controllers\Api\StorefrontController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| SkinCare E-Commerce REST API Routes
| Prefix: /api/v1
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->middleware(['throttle:120,1'])->group(function () {

    // ──────────────────────────────────────────────
    // 1. PUBLIC STOREFRONT MODULES
    // ──────────────────────────────────────────────
    Route::prefix('store')->group(function () {
        Route::get('/bootstrap', [StorefrontController::class, 'bootstrap']);
        Route::get('/settings', [StorefrontController::class, 'settings']);
        Route::get('/shipping-zones', [StorefrontController::class, 'shippingZones']);
        Route::get('/stats', [StorefrontController::class, 'stats']);
        Route::get('/categories', [StorefrontController::class, 'categories']);
        Route::get('/brands', [StorefrontController::class, 'brands']);
        Route::get('/products', [StorefrontController::class, 'products']);
        Route::get('/products/featured', [StorefrontController::class, 'featured']);
        Route::get('/products/bestsellers', [StorefrontController::class, 'bestsellers']);
        Route::get('/products/new-arrivals', [StorefrontController::class, 'newArrivals']);
        Route::get('/products/{product}', [StorefrontController::class, 'product']);
        Route::get('/products/{product}/reviews', [StorefrontController::class, 'reviews']);
        Route::get('/promotions', [StorefrontController::class, 'promotions']);
        Route::get('/bundles', [StorefrontController::class, 'bundles']);
        Route::get('/bundles/{bundle}', [StorefrontController::class, 'bundle']);
        Route::get('/flash-sales', [StorefrontController::class, 'flashSales']);
    });

    // Referral code validation (Public / Guest / Member)
    Route::post('/referral/validate', [CustomerLoyaltyController::class, 'validateReferral']);

    // Cart quoting (Public / Guest / Member)
    Route::post('/cart/quote', [CartController::class, 'quote']);

    // Order Tracking (Public / Guest / Member)
    Route::post('/orders/track', [CustomerOrderController::class, 'track']);

    // ──────────────────────────────────────────────
    // 2. CUSTOMER AUTHENTICATION (Public)
    // ──────────────────────────────────────────────
    Route::prefix('customer')->group(function () {
        Route::post('/register', [CustomerAuthController::class, 'register']);
        Route::post('/login', [CustomerAuthController::class, 'login']);
        Route::post('/forgot-password', [CustomerAuthController::class, 'forgotPassword']);
        Route::post('/reset-password', [CustomerAuthController::class, 'resetPassword']);
    });

    // ──────────────────────────────────────────────
    // 3. CHECKOUT & ORDERS (Guest or Authenticated)
    // ──────────────────────────────────────────────
    Route::post('/customer/checkout', [CheckoutController::class, 'checkout']);
    Route::post('/orders/{order}/verify-payment', [CheckoutController::class, 'verifyPayment']);

    // ──────────────────────────────────────────────
    // 4. AUTHENTICATED CUSTOMER PORTAL (Sanctum)
    // ──────────────────────────────────────────────
    Route::middleware('auth:sanctum')->group(function () {
        // Customer profile
        Route::prefix('customer')->group(function () {
            Route::post('/logout', [CustomerAuthController::class, 'logout']);
            Route::get('/profile', [CustomerAuthController::class, 'profile']);
            Route::put('/profile', [CustomerAuthController::class, 'updateProfile']);
            Route::put('/password', [CustomerAuthController::class, 'updatePassword']);

            // Addresses
            Route::get('/addresses', [CustomerAddressController::class, 'index']);
            Route::post('/addresses', [CustomerAddressController::class, 'store']);
            Route::put('/addresses/{address}', [CustomerAddressController::class, 'update']);
            Route::delete('/addresses/{address}', [CustomerAddressController::class, 'destroy']);

            // Order History & Management
            Route::get('/orders', [CustomerOrderController::class, 'index']);
            Route::get('/orders/{order}', [CustomerOrderController::class, 'show']);
            Route::post('/orders/{order}/cancel', [CustomerOrderController::class, 'cancel']);

            // Wishlist
            Route::get('/wishlist', [CustomerWishlistController::class, 'index']);
            Route::post('/wishlist/{product?}', [CustomerWishlistController::class, 'store']);
            Route::delete('/wishlist/{product}', [CustomerWishlistController::class, 'destroy']);

            // Customer Notifications
            Route::get('/notifications', [CustomerNotificationController::class, 'index']);
            Route::post('/notifications/{id}/read', [CustomerNotificationController::class, 'markAsRead']);
            Route::post('/notifications/read-all', [CustomerNotificationController::class, 'markAllAsRead']);

            // Loyalty Rewards & Referral Center
            Route::get('/loyalty', [CustomerLoyaltyController::class, 'profile']);

            // Skincare Replenishment Subscriptions
            Route::get('/subscriptions', [CustomerSubscriptionController::class, 'index']);
            Route::post('/subscriptions/{subscription}/pause', [CustomerSubscriptionController::class, 'pause']);
            Route::post('/subscriptions/{subscription}/resume', [CustomerSubscriptionController::class, 'resume']);
            Route::post('/subscriptions/{subscription}/cancel', [CustomerSubscriptionController::class, 'cancel']);
        });

        // Reviews submission
        Route::post('/store/products/{product}/reviews', [StorefrontController::class, 'storeReview']);

        // Server-side Cart Persistence
        Route::prefix('cart')->group(function () {
            Route::get('/', [CartController::class, 'index']);
            Route::post('/sync', [CartController::class, 'sync']);
            Route::post('/add', [CartController::class, 'add']);
            Route::delete('/{product}', [CartController::class, 'remove']);
        });
    });
});
