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
use Illuminate\Support\Facades\File;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function __construct(
        protected InventoryService $inventoryService
    ) {}

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

        // Filter by status (active / inactive)
        if ($request->filled('status') && in_array($request->input('status'), ['active', 'inactive'], true)) {
            $query->where('status', $request->input('status'));
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

        // Active storewide promotions applicable to all products
        $storewidePromotions = Promotion::where('status', 'active')
            ->whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today)
            ->whereDoesntHave('products')
            ->get();

        return view('products.index', [
            'products' => $products,
            'categories' => Category::where('status', 'active')->orderBy('name')->get(),
            'suppliers' => Supplier::where('status', 'active')->orderBy('name')->get(),
            'storewidePromotions' => $storewidePromotions,
            'totalProducts' => Product::count(),
            'activeCatalogCount' => Product::where('status', 'active')->count(),
            'lowStockCount' => Product::whereRaw('qty <= low_stock_threshold')->where('qty', '>', 0)->count(),
            'outOfStockCount' => Product::where('qty', '<=', 0)->count(),
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
     * Store a new product with image validation and audited stock initialization.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'barcode' => ['required', 'string', 'max:100', 'unique:products,barcode'],
            'category_id' => ['required', 'exists:categories,id'],
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'cost_price' => ['required', 'numeric', 'min:0'],
            'sale_price' => ['required', 'numeric', 'min:0'],
            'qty' => ['nullable', 'integer', 'min:0'],
            'low_stock_threshold' => ['required', 'integer', 'min:1'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'description' => ['nullable', 'string', 'max:1000'],
            'status' => ['required', 'in:active,inactive'],
        ], [
            'barcode.unique' => 'A skincare product with this barcode already exists.',
            'image.max' => 'The product image must not exceed 2MB in size.',
            'image.mimes' => 'The image must be a file of type: jpg, jpeg, png, webp.',
        ]);

        // Handle image upload
        $imagePath = null;
        if ($request->hasFile('image')) {
            $imageFile = $request->file('image');
            $fileName = 'prod_' . time() . '_' . uniqid() . '.' . $imageFile->getClientOriginalExtension();
            $destination = public_path('images/products');
            if (!File::isDirectory($destination)) {
                File::makeDirectory($destination, 0755, true);
            }
            $imageFile->move($destination, $fileName);
            $imagePath = 'images/products/' . $fileName;
        }

        $initialQty = (int) ($request->input('qty', 0));

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

        return redirect()->route('products.index')
            ->with('success', "Product '{$product->name}' created successfully.");
    }

    /**
     * Update an existing product.
     */
    public function update(Request $request, Product $product): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'barcode' => ['required', 'string', 'max:100', 'unique:products,barcode,' . $product->id],
            'category_id' => ['required', 'exists:categories,id'],
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'cost_price' => ['required', 'numeric', 'min:0'],
            'sale_price' => ['required', 'numeric', 'min:0'],
            'low_stock_threshold' => ['required', 'integer', 'min:1'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'description' => ['nullable', 'string', 'max:1000'],
            'status' => ['required', 'in:active,inactive'],
        ], [
            'barcode.unique' => 'A skincare product with this barcode already exists.',
            'image.max' => 'The product image must not exceed 2MB in size.',
        ]);

        // Handle image replacement
        $imagePath = $product->image;
        if ($request->hasFile('image')) {
            $imageFile = $request->file('image');
            $fileName = 'prod_' . time() . '_' . uniqid() . '.' . $imageFile->getClientOriginalExtension();
            $destination = public_path('images/products');
            if (!File::isDirectory($destination)) {
                File::makeDirectory($destination, 0755, true);
            }
            $imageFile->move($destination, $fileName);

            // Clean up old custom image if exists and not default
            if ($product->image && File::exists(public_path($product->image)) && str_contains($product->image, 'prod_')) {
                File::delete(public_path($product->image));
            }

            $imagePath = 'images/products/' . $fileName;
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

        return redirect()->route('products.index')
            ->with('success', "Product '{$product->name}' updated successfully.");
    }

    /**
     * Toggle active/inactive status safely.
     */
    public function toggleStatus(Product $product): RedirectResponse
    {
        $newStatus = $product->status === 'active' ? 'inactive' : 'active';
        $product->update(['status' => $newStatus]);

        return redirect()->route('products.index')
            ->with('success', "Product '{$product->name}' status changed to " . ucfirst($newStatus) . '.');
    }

    /**
     * Safely delete product, preventing deletion if referenced by sales or inventory transactions.
     */
    public function destroy(Product $product): RedirectResponse
    {
        $saleCount = $product->saleDetails()->count();
        $txnCount = $product->stockTransactions()->count();

        if ($saleCount > 0 || $txnCount > 0) {
            $reason = [];
            if ($saleCount > 0) {
                $reason[] = "{$saleCount} sales invoice item(s)";
            }
            if ($txnCount > 0) {
                $reason[] = "{$txnCount} stock audit transaction(s)";
            }

            return redirect()->route('products.index')
                ->with('error', "Cannot delete product '{$product->name}' because it has " . implode(' and ', $reason) . '. Please deactivate the product instead to protect audit records.');
        }

        $productName = $product->name;

        // Clean up image file
        if ($product->image && File::exists(public_path($product->image)) && str_contains($product->image, 'prod_')) {
            File::delete(public_path($product->image));
        }

        // Delete stock record and promotions pivot
        $product->stock()->delete();
        $product->promotions()->detach();
        $product->delete();

        return redirect()->route('products.index')
            ->with('success', "Product '{$productName}' deleted successfully.");
    }
}
