<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\ProductResource;
use App\Http\Resources\ProductReviewResource;
use App\Http\Resources\PromotionResource;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\Promotion;
use App\Models\Setting;
use App\Services\StorefrontCache;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class StorefrontController extends Controller
{
    /**
     * Get store settings & financial configuration.
     */
    public function settings(): JsonResponse
    {
        $key = StorefrontCache::key('storefront_api_settings');

        $data = Cache::remember($key, 600, function () {
            $settings = Setting::getAll();

            return [
                'business_name' => $settings['business_name'] ?? 'SkinCare Luxury Boutique',
                'business_address' => $settings['business_address'] ?? 'Phnom Penh, Cambodia',
                'business_phone' => $settings['business_phone'] ?? '+855 23 888 999',
                'business_email' => $settings['business_email'] ?? 'contact@skincare-boutique.com',
                'currency' => $settings['currency'] ?? 'USD',
                'currency_symbol' => Setting::currencySymbol(),
                'exchange_rate_khr' => (float) ($settings['exchange_rate_khr'] ?? 4100.0),
                'tax_rate' => (float) ($settings['tax_rate'] ?? 5.0),
                'supported_currencies' => [
                    ['code' => 'USD', 'symbol' => '$', 'label' => 'US Dollar ($)'],
                    ['code' => 'KHR', 'symbol' => '៛', 'label' => 'Khmer Riel (៛)'],
                ],
                'payment_methods' => [
                    ['id' => 'card', 'name' => 'Credit / Debit Card', 'description' => 'Visa, MasterCard, UnionPay'],
                    ['id' => 'qr', 'name' => 'Bakong KHQR / QR Pay', 'description' => 'Instant mobile scan via all major banks'],
                    ['id' => 'cash', 'name' => 'Cash on Delivery (COD)', 'description' => 'Pay in cash upon doorstep delivery'],
                ],
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $data,
        ])->header('Cache-Control', 'public, max-age=300, stale-while-revalidate=600');
    }

    /**
     * Get store catalog statistics.
     */
    public function stats(): JsonResponse
    {
        $key = StorefrontCache::key('storefront_stats');

        $data = Cache::remember($key, 300, function () {
            return [
                'total_products' => Product::active()->count(),
                'total_categories' => Category::active()->count(),
                'in_stock_products' => Product::active()->where('qty', '>', 0)->count(),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $data,
        ])->header('Cache-Control', 'public, max-age=120, stale-while-revalidate=300');
    }

    /**
     * Get list of active categories.
     */
    public function categories(): JsonResponse
    {
        $key = StorefrontCache::key('storefront_categories');

        $data = Cache::remember($key, 600, function () {
            $categories = Category::active()
                ->withCount(['products' => fn ($q) => $q->active()])
                ->orderBy('name')
                ->get();

            return CategoryResource::collection($categories)->resolve();
        });

        return response()->json([
            'success' => true,
            'data' => $data,
        ])->header('Cache-Control', 'public, max-age=120, stale-while-revalidate=300');
    }

    /**
     * Get list of skincare brands with active product counts.
     */
    public function brands(): JsonResponse
    {
        $key = StorefrontCache::key('storefront_brands');

        $data = Cache::remember($key, 600, function () {
            $productNames = Product::active()->pluck('name');
            $knownBrands = [
                'The Ordinary',
                'Beauty of Joseon',
                'COSRX',
                'CeraVe',
                'La Roche-Posay',
                "Paula's Choice",
                "Kiehl's",
                'Anua',
                'Skin1004',
                'Innisfree',
                'Mediheal',
            ];

            $brandCounts = [];
            foreach ($productNames as $name) {
                foreach ($knownBrands as $brand) {
                    if (stripos($name, $brand) === 0) {
                        $brandCounts[$brand] = ($brandCounts[$brand] ?? 0) + 1;
                        break;
                    }
                }
            }

            $list = [];
            foreach ($brandCounts as $name => $count) {
                $list[] = ['name' => $name, 'products_count' => $count];
            }

            // Sort alphabetically
            usort($list, fn ($a, $b) => strcmp($a['name'], $b['name']));

            return $list;
        });

        return response()->json([
            'success' => true,
            'data' => $data,
        ])->header('Cache-Control', 'public, max-age=120, stale-while-revalidate=300');
    }

    /**
     * Get paginated products with search, filtering, and sorting.
     */
    public function products(Request $request): JsonResponse
    {
        $cacheKey = StorefrontCache::key('storefront_products', $request->all());

        $cached = Cache::remember($cacheKey, 300, function () use ($request) {
            $today = Carbon::today()->toDateString();

            $query = Product::active()
                ->with([
                    'category',
                    'promotions' => fn ($q) => $q->where('status', 'active')
                        ->whereDate('start_date', '<=', $today)
                        ->whereDate('end_date', '>=', $today),
                ])
                ->withCount(['reviews as reviews_count' => fn ($q) => $q->where('status', 'approved')])
                ->withAvg(['reviews as average_rating' => fn ($q) => $q->where('status', 'approved')], 'rating');

            // Search by keyword
            if ($request->filled('search')) {
                $search = trim($request->input('search'));
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'LIKE', "%{$search}%")
                      ->orWhere('description', 'LIKE', "%{$search}%")
                      ->orWhere('barcode', 'LIKE', "%{$search}%");
                });
            }

            // Category filter
            if ($request->filled('category_id')) {
                $query->where('category_id', $request->input('category_id'));
            }

            // Price range filters
            if ($request->filled('min_price')) {
                $query->where('sale_price', '>=', (float) $request->input('min_price'));
            }
            if ($request->filled('max_price')) {
                $query->where('sale_price', '<=', (float) $request->input('max_price'));
            }

            // In-stock only filter
            if ($request->boolean('in_stock_only')) {
                $query->where('qty', '>', 0);
            }

            // Brand filter
            if ($request->filled('brand')) {
                $brand = trim($request->input('brand'));
                $query->where('name', 'LIKE', "{$brand}%");
            }

            // Active promotion / discount filter
            if ($request->boolean('has_discount') || $request->boolean('promotion_only')) {
                $query->whereHas('promotions', function ($q) use ($today) {
                    $q->where('status', 'active')
                      ->whereDate('start_date', '<=', $today)
                      ->whereDate('end_date', '>=', $today);
                });
            }

            // Sorting
            $sort = $request->input('sort', 'latest');
            match ($sort) {
                'price_asc' => $query->orderBy('sale_price', 'asc'),
                'price_desc' => $query->orderBy('sale_price', 'desc'),
                'name_asc' => $query->orderBy('name', 'asc'),
                'name_desc' => $query->orderBy('name', 'desc'),
                'best_selling' => $query->withCount('saleDetails')->orderBy('sale_details_count', 'desc'),
                'featured' => $query->orderBy('qty', 'desc'),
                default => $query->latest(),
            };

            $perPage = min(50, max(6, (int) $request->input('per_page', 12)));
            $paginated = $query->paginate($perPage);

            return [
                'data' => ProductResource::collection($paginated->items())->resolve(),
                'pagination' => [
                    'current_page' => $paginated->currentPage(),
                    'last_page' => $paginated->lastPage(),
                    'per_page' => $paginated->perPage(),
                    'total' => $paginated->total(),
                ],
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $cached['data'],
            'pagination' => $cached['pagination'],
        ])->header('Cache-Control', 'public, max-age=60, stale-while-revalidate=120');
    }

    /**
     * Get single product details.
     */
    public function product(Product $product): JsonResponse
    {
        if ($product->status !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'Product is not available.',
            ], 404);
        }

        $key = StorefrontCache::key('storefront_product_' . $product->id);

        $data = Cache::remember($key, 300, function () use ($product) {
            $today = Carbon::today()->toDateString();
            $product->load([
                'category',
                'promotions' => fn ($q) => $q->where('status', 'active')
                    ->whereDate('start_date', '<=', $today)
                    ->whereDate('end_date', '>=', $today),
                'reviews' => fn ($q) => $q->where('status', 'approved')->with('customer')->latest()->take(10),
            ]);
            $product->loadCount(['reviews as reviews_count' => fn ($q) => $q->where('status', 'approved')]);
            $product->loadAvg(['reviews as average_rating' => fn ($q) => $q->where('status', 'approved')], 'rating');

            return (new ProductResource($product))->resolve();
        });

        return response()->json([
            'success' => true,
            'data' => $data,
        ])->header('Cache-Control', 'public, max-age=60, stale-while-revalidate=120');
    }

    /**
     * Get featured products.
     */
    public function featured(): JsonResponse
    {
        $key = StorefrontCache::key('storefront_featured');

        $data = Cache::remember($key, 300, function () {
            $today = Carbon::today()->toDateString();

            $items = Product::active()
                ->with([
                    'category',
                    'promotions' => fn ($q) => $q->where('status', 'active')
                        ->whereDate('start_date', '<=', $today)
                        ->whereDate('end_date', '>=', $today),
                ])
                ->withCount(['reviews as reviews_count' => fn ($q) => $q->where('status', 'approved')])
                ->withAvg(['reviews as average_rating' => fn ($q) => $q->where('status', 'approved')], 'rating')
                ->where('qty', '>', 0)
                ->inRandomOrder()
                ->take(8)
                ->get();

            return ProductResource::collection($items)->resolve();
        });

        return response()->json([
            'success' => true,
            'data' => $data,
        ])->header('Cache-Control', 'public, max-age=60, stale-while-revalidate=120');
    }

    /**
     * Get active promotions.
     */
    public function promotions(): JsonResponse
    {
        $key = StorefrontCache::key('storefront_promotions');

        $data = Cache::remember($key, 600, function () {
            $items = Promotion::active()->with('products')->get();
            return PromotionResource::collection($items)->resolve();
        });

        return response()->json([
            'success' => true,
            'data' => $data,
        ])->header('Cache-Control', 'public, max-age=120, stale-while-revalidate=300');
    }

    /**
     * Get product reviews.
     */
    public function reviews(Product $product): JsonResponse
    {
        $reviews = $product->reviews()
            ->where('status', 'approved')
            ->with('customer')
            ->latest()
            ->paginate(10);

        return response()->json([
            'success' => true,
            'data' => ProductReviewResource::collection($reviews->items()),
            'pagination' => [
                'current_page' => $reviews->currentPage(),
                'last_page' => $reviews->lastPage(),
                'total' => $reviews->total(),
            ],
        ]);
    }

    /**
     * Post product review (authenticated customer).
     */
    public function storeReview(Request $request, Product $product): JsonResponse
    {
        $customer = $request->user();

        $validated = $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'title' => 'nullable|string|max:150',
            'comment' => 'nullable|string|max:1000',
        ]);

        $review = ProductReview::updateOrCreate(
            ['customer_id' => $customer->id, 'product_id' => $product->id],
            [
                'rating' => $validated['rating'],
                'title' => $validated['title'] ?? null,
                'comment' => $validated['comment'] ?? null,
                'status' => 'approved',
            ]
        );

        StorefrontCache::bust();

        return response()->json([
            'success' => true,
            'message' => 'Thank you for your review!',
            'data' => new ProductReviewResource($review->load('customer')),
        ], 201);
    }
}
