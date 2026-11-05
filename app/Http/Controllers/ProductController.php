<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\Stock;
use App\Models\Supplier;
use App\Services\InventoryService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function __construct(
        protected InventoryService $inventoryService
    ) {}

    public static function clearProductCaches(): void
    {
        Cache::forget('products_catalog_counts');
        Cache::forget('pos_catalog_products');
        Cache::forget('pos_terminal_catalog_bundle');
        Cache::forget('active_categories_list');
        Cache::forget('active_suppliers_list');
        Cache::forget('inventory_valuation_summary');
        Cache::forget('topbar_low_stock_alerts');
        Cache::forget('active_products_compact');
    }

    /**
     * Display product catalog with search, category/supplier filters, stock status, sorting, and pagination.
     * Authorized for: Admin, Manager.
     */
    public function index(Request $request): View
    {
        $today = Carbon::today()->toDateString();

        $query = Product::with([
            'category',
            'supplier',
            'stock',
            'promotions' => function ($q) use ($today) {
                $q->where('status', 'active')
                  ->whereDate('start_date', '<=', $today)
                  ->whereDate('end_date', '>=', $today);
            },
        ]);

        // Search by name or barcode
        if ($request->filled('search')) {
            $search = trim($request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('barcode', 'like', "%{$search}%");
            });
        }

        // Filter by category
        if ($request->filled('category_id')) {
            $query->where('category_id', $request->input('category_id'));
        }

        // Filter by supplier
        if ($request->filled('supplier_id')) {
            $query->where('supplier_id', $request->input('supplier_id'));
        }

        // Filter by status (active / inactive / trashed)
        if ($request->filled('status')) {
            $status = $request->input('status');
            if ($status === 'trashed') {
                $query->onlyTrashed();
            } elseif (in_array($status, ['active', 'inactive'], true)) {
                $query->where('status', $status);
            }
        }

        // Filter by stock status
        if ($request->filled('stock_status')) {
            $stockStatus = $request->input('stock_status');
            if ($stockStatus === 'low') {
                $query->whereRaw('qty <= low_stock_threshold')->where('qty', '>', 0);
            } elseif ($stockStatus === 'out') {
                $query->where('qty', '<=', 0);
            } elseif ($stockStatus === 'in_stock') {
                $query->whereRaw('qty > low_stock_threshold');
            }
        }

        // Sorting
        $sort = $request->input('sort', 'latest');
        match ($sort) {
            'name_asc' => $query->orderBy('name', 'asc'),
            'name_desc' => $query->orderBy('name', 'desc'),
            'price_asc' => $query->orderBy('sale_price', 'asc'),
            'price_desc' => $query->orderBy('sale_price', 'desc'),
            'qty_asc' => $query->orderBy('qty', 'asc'),
            'qty_desc' => $query->orderBy('qty', 'desc'),
            default => $query->latest(),
        };

        $products = $query->paginate(15)->withQueryString();

        // Cached lookup data to minimize remote latency
        $categories = Cache::remember('active_categories_list', 120, function () {
            return Category::where('status', 'active')->orderBy('name')->get();
        });

        $suppliers = Cache::remember('active_suppliers_list', 120, function () {
            return Supplier::where('status', 'active')->orderBy('name')->get();
        });

        $storewidePromotions = Cache::remember('storewide_promotions_' . $today, 120, function () use ($today) {
            return Promotion::where('status', 'active')
                ->whereDate('start_date', '<=', $today)
                ->whereDate('end_date', '>=', $today)
                ->whereDoesntHave('products')
                ->get();
        });

        // Combined single-query counts
        $counts = Cache::remember('products_catalog_counts', 60, function () {
            $row = Product::selectRaw("
                COUNT(*) as total,
                COUNT(CASE WHEN status = 'active' THEN 1 END) as active_count,
                COUNT(CASE WHEN qty <= low_stock_threshold AND qty > 0 THEN 1 END) as low_count,
                COUNT(CASE WHEN qty <= 0 THEN 1 END) as out_count
            ")->first();

            return [
                'total' => (int) ($row->total ?? 0),
                'active' => (int) ($row->active_count ?? 0),
                'low' => (int) ($row->low_count ?? 0),
                'out' => (int) ($row->out_count ?? 0),
            ];
        });

        return view('products.index', [
            'products' => $products,
            'categories' => $categories,
            'suppliers' => $suppliers,
            'storewidePromotions' => $storewidePromotions,
            'totalProducts' => $counts['total'],
            'activeCatalogCount' => $counts['active'],
            'lowStockCount' => $counts['low'],
            'outOfStockCount' => $counts['out'],
        ]);
    }

    /**
     * Show product details (supports AJAX/JSON or HTML modal).
     */
    public function show(Product $product): View|JsonResponse
    {
        $today = Carbon::today()->toDateString();
        $product->load([
            'category',
            'supplier',
            'stock',
            'promotions' => function ($q) use ($today) {
                $q->where('status', 'active')
                  ->whereDate('start_date', '<=', $today)
                  ->whereDate('end_date', '>=', $today);
            },
        ]);

        if (request()->wantsJson() || request()->ajax()) {
            return response()->json([
                'success' => true,
                'product' => $product,
            ]);
        }

        return view('products.show', [
            'product' => $product,
        ]);
    }

    /**
     * Generate an authentic, scanner-compliant, unique EAN-13 barcode.
     */
    public static function generateUniqueBarcode(): string
    {
        do {
            // Standard internal in-store retail prefix: 200
            // Followed by 9 random digits = 12 base digits
            $base = '200' . str_pad((string) random_int(100000000, 999999999), 9, '0', STR_PAD_LEFT);

            // Calculate EAN-13 checksum (modulo-10)
            $sum = 0;
            for ($i = 0; $i < 12; $i++) {
                $digit = (int) $base[$i];
                $sum += ($i % 2 === 0) ? $digit : $digit * 3;
            }
            $checkDigit = (10 - ($sum % 10)) % 10;
            $barcode = $base . $checkDigit;
        } while (Product::where('barcode', $barcode)->exists());

        return $barcode;
    }

    /**
     * API / AJAX endpoint to generate a unique barcode.
     */
    public function generateBarcode(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'barcode' => self::generateUniqueBarcode(),
        ]);
    }

    /**
     * Store a new product with image validation and audited stock initialization.
     */
    public function store(Request $request): RedirectResponse|JsonResponse
    {
        // Auto-generate unique barcode if omitted
        if (!$request->filled('barcode')) {
            $request->merge(['barcode' => self::generateUniqueBarcode()]);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'barcode' => [
                'required',
                'string',
                'max:100',
                Rule::unique('products', 'barcode')->whereNull('deleted_at'),
            ],
            'category_id' => ['required', 'exists:categories,id'],
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'cost_price' => ['required', 'numeric', 'min:0'],
            'sale_price' => ['required', 'numeric', 'min:0'],
            'qty' => ['nullable', 'integer', 'min:0'],
            'low_stock_threshold' => ['required', 'integer', 'min:1'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'description' => ['nullable', 'string', 'max:1000'],
            'status' => ['required', 'in:active,inactive'],
        ], [
            'barcode.unique' => 'A skincare product with this barcode already exists.',
            'image.max' => 'The product image must not exceed 10MB in size.',
            'image.mimes' => 'The image must be a file of type: jpg, jpeg, png, webp.',
        ]);

        $imagePath = null;
        try {
            // Handle image upload
            if ($request->hasFile('image')) {
                $imageFile = $request->file('image');
                $ext = strtolower($imageFile->getClientOriginalExtension() ?: 'jpg');
                $fileName = 'prod_' . time() . '_' . uniqid() . '.' . $ext;
                $destination = public_path('images/products');
                if (!File::isDirectory($destination)) {
                    File::makeDirectory($destination, 0755, true);
                }
                $imageFile->move($destination, $fileName);
                $imagePath = 'images/products/' . $fileName;
            }

            $initialQty = (int) ($request->input('qty', 0));

            DB::beginTransaction();

            // Create product with qty initialized to 0
            $product = Product::create([
                'name' => $validated['name'],
                'barcode' => $validated['barcode'],
                'category_id' => $validated['category_id'],
                'supplier_id' => $validated['supplier_id'],
                'cost_price' => $validated['cost_price'],
                'sale_price' => $validated['sale_price'],
                'qty' => 0,
                'image' => $imagePath,
                'description' => $validated['description'] ?? null,
                'low_stock_threshold' => $validated['low_stock_threshold'],
                'status' => $validated['status'],
            ]);

            // Initialize stock record
            Stock::create([
                'product_id' => $product->id,
                'qty' => 0,
            ]);

            // If starting stock is provided, record via InventoryService to guarantee audit trail
            if ($initialQty > 0) {
                $this->inventoryService->recordStockIn(
                    productId: $product->id,
                    qty: $initialQty,
                    userId: Auth::id() ?? 1,
                    supplierId: $product->supplier_id,
                    referenceNumber: 'INIT-' . $product->barcode,
                    notes: 'Initial inventory establishment upon product creation'
                );
            }

            DB::commit();

            self::clearProductCaches();

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => "Product '{$product->name}' created successfully.",
                    'product' => $product,
                ]);
            }

            return redirect()->route('products.index')
                ->with('success', "Product '{$product->name}' created successfully.");

        } catch (\Illuminate\Validation\ValidationException $e) {
            if ($imagePath && File::exists(public_path($imagePath))) {
                @File::delete(public_path($imagePath));
            }
            throw $e;
        } catch (\Throwable $e) {
            DB::rollBack();
            if ($imagePath && File::exists(public_path($imagePath))) {
                @File::delete(public_path($imagePath));
            }
            Log::error('Product creation failed: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'input' => $request->except(['image']),
            ]);

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unable to save formulation: ' . $e->getMessage(),
                ], 422);
            }

            return redirect()->back()
                ->withInput()
                ->with('error', 'Unable to save formulation: ' . $e->getMessage());
        }
    }

    /**
     * Update an existing product.
     */
    public function update(Request $request, Product $product): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'barcode' => [
                'required',
                'string',
                'max:100',
                Rule::unique('products', 'barcode')->ignore($product->id)->whereNull('deleted_at'),
            ],
            'category_id' => ['required', 'exists:categories,id'],
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'cost_price' => ['required', 'numeric', 'min:0'],
            'sale_price' => ['required', 'numeric', 'min:0'],
            'low_stock_threshold' => ['required', 'integer', 'min:1'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'description' => ['nullable', 'string', 'max:1000'],
            'status' => ['required', 'in:active,inactive'],
        ], [
            'barcode.unique' => 'A skincare product with this barcode already exists.',
            'image.max' => 'The product image must not exceed 10MB in size.',
        ]);

        $imagePath = $product->image;
        $newUploadedFile = null;
        try {
            // Handle image replacement
            if ($request->hasFile('image')) {
                $imageFile = $request->file('image');
                $ext = strtolower($imageFile->getClientOriginalExtension() ?: 'jpg');
                $fileName = 'prod_' . time() . '_' . uniqid() . '.' . $ext;
                $destination = public_path('images/products');
                if (!File::isDirectory($destination)) {
                    File::makeDirectory($destination, 0755, true);
                }
                $imageFile->move($destination, $fileName);
                $newUploadedFile = 'images/products/' . $fileName;

                // Clean up old custom image if exists and not default
                if ($product->image && File::exists(public_path($product->image)) && str_contains($product->image, 'prod_')) {
                    @File::delete(public_path($product->image));
                }

                $imagePath = $newUploadedFile;
            }

            $product->update([
                'name' => $validated['name'],
                'barcode' => $validated['barcode'],
                'category_id' => $validated['category_id'],
                'supplier_id' => $validated['supplier_id'],
                'cost_price' => $validated['cost_price'],
                'sale_price' => $validated['sale_price'],
                'image' => $imagePath,
                'description' => $validated['description'] ?? null,
                'low_stock_threshold' => $validated['low_stock_threshold'],
                'status' => $validated['status'],
            ]);

            self::clearProductCaches();

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => "Product '{$product->name}' updated successfully.",
                    'product' => $product,
                ]);
            }

            return redirect()->route('products.index')
                ->with('success', "Product '{$product->name}' updated successfully.");

        } catch (\Illuminate\Validation\ValidationException $e) {
            if ($newUploadedFile && File::exists(public_path($newUploadedFile))) {
                @File::delete(public_path($newUploadedFile));
            }
            throw $e;
        } catch (\Throwable $e) {
            if ($newUploadedFile && File::exists(public_path($newUploadedFile))) {
                @File::delete(public_path($newUploadedFile));
            }
            Log::error('Product update failed: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'product_id' => $product->id,
            ]);

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unable to update formulation: ' . $e->getMessage(),
                ], 422);
            }

            return redirect()->back()
                ->withInput()
                ->with('error', 'Unable to update formulation: ' . $e->getMessage());
        }
    }

    /**
     * Toggle active/inactive status safely.
     */
    public function toggleStatus(Product $product): RedirectResponse|JsonResponse
    {
        $newStatus = $product->status === 'active' ? 'inactive' : 'active';
        $product->update(['status' => $newStatus]);

        self::clearProductCaches();

        if (request()->wantsJson() || request()->ajax()) {
            return response()->json([
                'success' => true,
                'message' => "Product '{$product->name}' status changed to " . ucfirst($newStatus) . '.',
                'status' => $newStatus,
            ]);
        }

        return redirect()->route('products.index')
            ->with('success', "Product '{$product->name}' status changed to " . ucfirst($newStatus) . '.');
    }

    /**
     * Safely delete product using soft deletes to preserve sales and inventory audit records.
     */
    public function destroy(Product $product): RedirectResponse|JsonResponse
    {
        $productName = $product->name;

        // Perform safe soft delete (preserves stock audit trail and sales records)
        $product->delete();

        self::clearProductCaches();
        \App\Http\Controllers\PosController::clearPosCaches();

        if (request()->wantsJson() || request()->ajax()) {
            return response()->json([
                'success' => true,
                'message' => "Product '{$productName}' deleted successfully.",
            ]);
        }

        return redirect()->route('products.index')
            ->with('success', "Product '{$productName}' deleted successfully.");
    }

    /**
     * Safely delete multiple products in bulk using soft deletes.
     * Authorized for: Admin, Manager.
     */
    public function bulkDestroy(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['required', 'integer', 'exists:products,id'],
        ], [
            'ids.required' => 'Please select at least one product to delete.',
            'ids.min' => 'Please select at least one product to delete.',
        ]);

        $ids = $validated['ids'];
        $count = Product::whereIn('id', $ids)->delete();

        self::clearProductCaches();
        \App\Http\Controllers\PosController::clearPosCaches();

        $message = "Successfully deleted {$count} " . ($count === 1 ? 'product' : 'products') . ' from catalog.';

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'deleted_count' => $count,
            ]);
        }

        return redirect()->route('products.index')->with('success', $message);
    }

    /**
     * Restore a soft-deleted product.
     */
    public function restore(int $id): RedirectResponse|JsonResponse
    {
        $product = Product::onlyTrashed()->findOrFail($id);
        $product->restore();

        self::clearProductCaches();

        if (request()->wantsJson() || request()->ajax()) {
            return response()->json([
                'success' => true,
                'message' => "Product '{$product->name}' restored successfully.",
            ]);
        }

        return redirect()->route('products.index')
            ->with('success', "Product '{$product->name}' restored successfully.");
    }
}
