<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Stock;
use App\Models\StockTransaction;
use App\Models\StockTransfer;
use App\Models\Supplier;
use App\Services\InventoryService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use InvalidArgumentException;

class InventoryController extends Controller
{
    public function __construct(
        protected InventoryService $inventoryService
    ) {}

    /**
     * Display inventory balances, valuation metrics, and stock thresholds.
     */
    public function index(Request $request): View
    {
        $query = Stock::with(['product.category', 'product.supplier']);

        if ($request->filled('search')) {
            $search = trim($request->input('search'));
            $query->whereHas('product', function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('barcode', 'LIKE', "%{$search}%");
            });
        }

        if ($request->filled('category_id')) {
            $query->whereHas('product', function ($q) use ($request) {
                $q->where('category_id', $request->input('category_id'));
            });
        }

        if ($request->filled('stock_status')) {
            $status = $request->input('stock_status');
            if ($status === 'low') {
                $query->whereHas('product', function ($q) {
                    $q->whereRaw('qty <= low_stock_threshold')->where('qty', '>', 0);
                });
            } elseif ($status === 'out') {
                $query->where('qty', '<=', 0);
            } elseif ($status === 'in_stock') {
                $query->whereHas('product', function ($q) {
                    $q->whereRaw('qty > low_stock_threshold');
                });
            }
        }

        $sort = $request->input('sort', 'latest');
        match ($sort) {
            'qty_asc' => $query->orderBy('qty', 'asc'),
            'qty_desc' => $query->orderBy('qty', 'desc'),
            'product_name' => $query->join('products', 'stocks.product_id', '=', 'products.id')->orderBy('products.name', 'asc')->select('stocks.*'),
            default => $query->latest(),
        };

        $stocks = $query->paginate(15)->withQueryString();
        $valuation = $this->inventoryService->calculateValuationSummary();

        return view('inventory.index', [
            'stocks' => $stocks,
            'valuation' => $valuation,
            'categories' => Cache::remember('active_categories_list', 120, fn() => Category::where('status', 'active')->orderBy('name')->get()),
            'filters' => $request->only(['search', 'category_id', 'stock_status', 'sort']),
        ]);
    }

    /**
     * Display Stock In (Purchases & Supplier deliveries).
     */
    public function stockIn(Request $request): View
    {
        $query = StockTransaction::with(['product.category', 'user', 'supplier'])
            ->where('type', StockTransaction::TYPE_STOCK_IN);

        if ($request->filled('search')) {
            $search = trim($request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('reference_number', 'LIKE', "%{$search}%")
                  ->orWhere('notes', 'LIKE', "%{$search}%")
                  ->orWhereHas('product', function ($pq) use ($search) {
                      $pq->where('name', 'LIKE', "%{$search}%")->orWhere('barcode', 'LIKE', "%{$search}%");
                  });
            });
        }

        $transactions = $query->latest('date')->latest('id')->paginate(15)->withQueryString();

        return view('inventory.stock-in', [
            'transactions' => $transactions,
            'products' => Product::where('status', 'active')->select(['id', 'name', 'barcode', 'supplier_id', 'cost_price', 'qty'])->orderBy('name')->get(),
            'suppliers' => Supplier::where('status', 'active')->select(['id', 'name'])->orderBy('name')->get(),
            'totalReceivedUnits' => StockTransaction::where('type', StockTransaction::TYPE_STOCK_IN)->sum('qty'),
        ]);
    }

    /**
     * Process stock-in receiving from supplier.
     */
    public function processStockIn(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'qty' => ['required', 'integer', 'min:1'],
            'supplier_id' => ['nullable', 'exists:suppliers,id'],
            'unit_cost' => ['nullable', 'numeric', 'min:0'],
            'reference_number' => ['nullable', 'string', 'max:50'],
            'date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $product = Product::findOrFail($validated['product_id']);
        $supplierId = $validated['supplier_id'] ?? $product->supplier_id;
        $refNumber = ($validated['reference_number'] ?? null) ?: ('PO-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4)));

        try {
            $this->inventoryService->recordStockIn(
                productId: $product->id,
                qty: (int) $validated['qty'],
                userId: Auth::id(),
                supplierId: $supplierId,
                referenceNumber: $refNumber,
                notes: $validated['notes'] ?? 'Purchased stock received from supplier',
                unitCost: isset($validated['unit_cost']) ? (float) $validated['unit_cost'] : null,
                date: $validated['date'] ?? null
            );

            Cache::forget('inventory_valuation_summary');
            Cache::forget('products_catalog_counts');

            $msg = "Received {$validated['qty']} units of [{$product->name}] under Ref: {$refNumber}.";

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => $msg,
                ]);
            }

            return redirect()->route('inventory.stock-in')
                ->with('success', $msg);
        } catch (InvalidArgumentException $e) {
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                ], 422);
            }
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    /**
     * Display Stock Out adjustments.
     */
    public function stockOut(Request $request): View
    {
        $query = StockTransaction::with(['product.category', 'user'])
            ->where('type', StockTransaction::TYPE_STOCK_OUT);

        if ($request->filled('search')) {
            $search = trim($request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('reference_number', 'LIKE', "%{$search}%")
                  ->orWhere('notes', 'LIKE', "%{$search}%")
                  ->orWhereHas('product', function ($pq) use ($search) {
                      $pq->where('name', 'LIKE', "%{$search}%")->orWhere('barcode', 'LIKE', "%{$search}%");
                  });
            });
        }

        $transactions = $query->latest('date')->latest('id')->paginate(15)->withQueryString();

        return view('inventory.stock-out', [
            'transactions' => $transactions,
            'products' => Product::where('status', 'active')->select(['id', 'name', 'qty'])->orderBy('name')->get(),
            'totalDeductedUnits' => abs(StockTransaction::where('type', StockTransaction::TYPE_STOCK_OUT)->sum('qty')),
        ]);
    }

    /**
     * Process authorized stock removal for non-sale reasons.
     */
    public function processStockOut(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'qty' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'max:200'],
            'reference_number' => ['nullable', 'string', 'max:50'],
            'date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $product = Product::findOrFail($validated['product_id']);
        $refNumber = ($validated['reference_number'] ?? null) ?: ('SO-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4)));

        try {
            $this->inventoryService->recordStockOut(
                productId: $product->id,
                qty: (int) $validated['qty'],
                userId: Auth::id(),
                reason: $validated['reason'],
                referenceNumber: $refNumber,
                notes: $validated['notes'] ?? null,
                date: $validated['date'] ?? null
            );

            Cache::forget('inventory_valuation_summary');
            Cache::forget('products_catalog_counts');

            $msg = "Deducted {$validated['qty']} units of [{$product->name}] for: {$validated['reason']}.";

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['success' => true, 'message' => $msg]);
            }

            return redirect()->route('inventory.stock-out')->with('success', $msg);
        } catch (InvalidArgumentException $e) {
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
            }
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    /**
     * Display Stock Transfers between store locations.
     */
    public function transfers(Request $request): View
    {
        $query = StockTransfer::with(['user', 'details.product']);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('search')) {
            $search = trim($request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('reference_number', 'LIKE', "%{$search}%")
                  ->orWhere('source_location', 'LIKE', "%{$search}%")
                  ->orWhere('destination_location', 'LIKE', "%{$search}%");
            });
        }

        $transfers = $query->latest('id')->paginate(15)->withQueryString();
        $configuredBranches = config('inventory.branches', [
            'Central Warehouse' => 'Central Warehouse & Distribution Hub',
            'Downtown Flagship' => 'Flagship Boutique (Downtown)',
            'Spa Suite' => 'Aesthetic Spa & Treatment Suite',
        ]);

        return view('inventory.transfers', [
            'transfers' => $transfers,
            'branches' => $configuredBranches,
            'products' => Product::where('status', 'active')->where('qty', '>', 0)->orderBy('name')->get(),
        ]);
    }

    /**
     * Process creation of stock transfer.
     */
    public function processTransfer(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'source_location' => ['required', 'string', 'max:100'],
            'destination_location' => ['required', 'string', 'max:100', 'different:source_location'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.qty' => ['required', 'integer', 'min:1'],
            'notes' => ['nullable', 'string', 'max:500'],
        ], [
            'destination_location.different' => 'Destination location must be different from source location.',
            'items.min' => 'Please add at least one product line to transfer.',
        ]);

        try {
            $transfer = $this->inventoryService->createStockTransfer(
                userId: Auth::id(),
                sourceLocation: $validated['source_location'],
                destinationLocation: $validated['destination_location'],
                items: $validated['items'],
                notes: $validated['notes'] ?? null
            );

            $msg = "Transfer #{$transfer->reference_number} created and dispatched successfully.";

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['success' => true, 'message' => $msg]);
            }

            return redirect()->route('inventory.transfers')->with('success', $msg);
        } catch (InvalidArgumentException $e) {
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
            }
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    /**
     * Update stock transfer status (completed / cancelled).
     */
    public function updateTransferStatus(Request $request, StockTransfer $transfer): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:completed,cancelled'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $this->inventoryService->updateTransferStatus(
                transfer: $transfer,
                newStatus: $validated['status'],
                userId: Auth::id(),
                reason: $validated['reason'] ?? null
            );

            $actionText = $validated['status'] === 'completed' ? 'marked as Completed' : 'Cancelled and restocked';
            $msg = "Transfer #{$transfer->reference_number} has been {$actionText}.";

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['success' => true, 'message' => $msg]);
            }

            return redirect()->route('inventory.transfers')->with('success', $msg);
        } catch (InvalidArgumentException $e) {
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
            }
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    /**
     * Display Damaged & Expired stock.
     */
    public function damaged(Request $request): View
    {
        $query = StockTransaction::with(['product.category', 'user'])
            ->where('type', StockTransaction::TYPE_DAMAGED);

        if ($request->filled('search')) {
            $search = trim($request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('reference_number', 'LIKE', "%{$search}%")
                  ->orWhere('notes', 'LIKE', "%{$search}%")
                  ->orWhereHas('product', function ($pq) use ($search) {
                      $pq->where('name', 'LIKE', "%{$search}%")->orWhere('barcode', 'LIKE', "%{$search}%");
                  });
            });
        }

        $transactions = $query->latest('date')->latest('id')->paginate(15)->withQueryString();

        return view('inventory.damaged', [
            'transactions' => $transactions,
            'products' => Product::where('status', 'active')->where('qty', '>', 0)->select(['id', 'name', 'qty'])->orderBy('name')->get(),
            'totalDamagedUnits' => abs(StockTransaction::where('type', StockTransaction::TYPE_DAMAGED)->sum('qty')),
        ]);
    }

    /**
     * Process broken, damaged, or expired stock write-off.
     */
    public function processDamaged(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'qty' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'max:200'],
            'reference_number' => ['nullable', 'string', 'max:50'],
            'date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $product = Product::findOrFail($validated['product_id']);
        $refNumber = ($validated['reference_number'] ?? null) ?: ('DMG-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4)));

        try {
            $this->inventoryService->recordDamaged(
                productId: $product->id,
                qty: (int) $validated['qty'],
                userId: Auth::id(),
                reason: $validated['reason'],
                referenceNumber: $refNumber,
                notes: $validated['notes'] ?? null,
                date: $validated['date'] ?? null
            );

            Cache::forget('inventory_valuation_summary');
            Cache::forget('products_catalog_counts');

            $msg = "Written off {$validated['qty']} damaged/expired units of [{$product->name}].";

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['success' => true, 'message' => $msg]);
            }

            return redirect()->route('inventory.damaged')->with('success', $msg);
        } catch (InvalidArgumentException $e) {
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
            }
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    /**
     * Display Customer & Supplier Returns ledger.
     */
    public function returns(Request $request): View
    {
        $query = StockTransaction::with(['product.category', 'user', 'supplier'])
            ->where(function ($q) {
                $q->where('type', StockTransaction::TYPE_RETURN)
                  ->orWhere(function ($sub) {
                      $sub->where('type', StockTransaction::TYPE_STOCK_OUT)
                          ->where('notes', 'LIKE', '%Return to Vendor%');
                  });
            });

        if ($request->filled('search')) {
            $search = trim($request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('reference_number', 'LIKE', "%{$search}%")
                  ->orWhere('notes', 'LIKE', "%{$search}%")
                  ->orWhereHas('product', function ($pq) use ($search) {
                      $pq->where('name', 'LIKE', "%{$search}%")->orWhere('barcode', 'LIKE', "%{$search}%");
                  });
            });
        }

        $transactions = $query->latest('date')->latest('id')->paginate(15)->withQueryString();

        return view('inventory.returns', [
            'transactions' => $transactions,
            'recentSales' => Sale::with(['details.product'])->whereIn('status', ['completed', 'returned'])->latest('id')->take(20)->get(),
            'products' => Product::where('status', 'active')->orderBy('name')->get(),
            'suppliers' => Supplier::where('status', 'active')->orderBy('name')->get(),
        ]);
    }

    /**
     * Process customer return against original sale invoice.
     */
    public function processCustomerReturn(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'sale_id' => ['required', 'exists:sales,id'],
            'product_id' => ['required', 'exists:products,id'],
            'qty' => ['required', 'integer', 'min:1'],
            'condition' => ['required', 'in:resellable,damaged'],
            'reason' => ['required', 'string', 'max:500'],
            'date' => ['nullable', 'date'],
        ]);

        $sale = Sale::with('details')->findOrFail($validated['sale_id']);
        $product = Product::findOrFail($validated['product_id']);

        try {
            $this->inventoryService->recordCustomerReturn(
                sale: $sale,
                productId: $product->id,
                qty: (int) $validated['qty'],
                userId: Auth::id(),
                reason: $validated['reason'],
                condition: $validated['condition'],
                date: $validated['date'] ?? null
            );

            $condText = $validated['condition'] === 'resellable' ? 'Restocked to inventory' : 'Logged as damaged (not restocked)';
            $msg = "Return processed for Invoice #{$sale->invoice_number} ({$validated['qty']} units of [{$product->name}] - {$condText}).";

            Cache::forget('inventory_valuation_summary');

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['success' => true, 'message' => $msg]);
            }

            return redirect()->route('inventory.returns')->with('success', $msg);
        } catch (InvalidArgumentException $e) {
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
            }
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    /**
     * Process supplier return (Return to Vendor).
     */
    public function processSupplierReturn(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'qty' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'max:500'],
            'reference_number' => ['nullable', 'string', 'max:50'],
            'date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $product = Product::findOrFail($validated['product_id']);
        $supplier = Supplier::findOrFail($validated['supplier_id']);

        try {
            $this->inventoryService->recordSupplierReturn(
                productId: $product->id,
                qty: (int) $validated['qty'],
                supplierId: $supplier->id,
                userId: Auth::id(),
                reason: $validated['reason'],
                referenceNumber: $validated['reference_number'] ?? null,
                notes: $validated['notes'] ?? null,
                date: $validated['date'] ?? null
            );

            Cache::forget('inventory_valuation_summary');

            $msg = "Returned {$validated['qty']} units of [{$product->name}] to {$supplier->name} for vendor credit.";

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['success' => true, 'message' => $msg]);
            }

            return redirect()->route('inventory.returns')->with('success', $msg);
        } catch (InvalidArgumentException $e) {
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
            }
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    /**
     * Display comprehensive Stock History (Audit Trail).
     */
    public function history(Request $request): View
    {
        $query = StockTransaction::with(['product.category', 'user', 'supplier']);

        if ($request->filled('type') && $request->input('type') !== 'all') {
            $query->where('type', $request->input('type'));
        }

        if ($request->filled('product_id')) {
            $query->where('product_id', $request->input('product_id'));
        }

        if ($request->filled('start_date')) {
            $query->whereDate('date', '>=', $request->input('start_date'));
        }

        if ($request->filled('end_date')) {
            $query->whereDate('date', '<=', $request->input('end_date'));
        }

        if ($request->filled('search')) {
            $search = trim($request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('reference_number', 'LIKE', "%{$search}%")
                  ->orWhere('notes', 'LIKE', "%{$search}%")
                  ->orWhereHas('product', function ($pq) use ($search) {
                      $pq->where('name', 'LIKE', "%{$search}%")->orWhere('barcode', 'LIKE', "%{$search}%");
                  });
            });
        }

        $transactions = $query->latest('date')->latest('id')->paginate(20)->withQueryString();

        return view('inventory.history', [
            'transactions' => $transactions,
            'products' => Product::orderBy('name')->get(),
            'types' => [
                StockTransaction::TYPE_STOCK_IN => 'Stock In',
                StockTransaction::TYPE_STOCK_OUT => 'Stock Out',
                StockTransaction::TYPE_SALE => 'Sale Deduction',
                StockTransaction::TYPE_RETURN => 'Customer Return',
                StockTransaction::TYPE_TRANSFER => 'Transfer',
                StockTransaction::TYPE_DAMAGED => 'Damaged Write-off',
                StockTransaction::TYPE_ADJUSTMENT => 'Manual Adjustment',
            ],
            'filters' => $request->only(['type', 'product_id', 'start_date', 'end_date', 'search']),
        ]);
    }

    /**
     * Configurable low-stock alert threshold update per product.
     */
    public function updateThreshold(Request $request, Product $product): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'low_stock_threshold' => ['required', 'integer', 'min:1'],
        ]);

        $product->update(['low_stock_threshold' => $validated['low_stock_threshold']]);

        $msg = "Low-stock alert threshold for [{$product->name}] set to {$product->low_stock_threshold} units.";

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json(['success' => true, 'message' => $msg]);
        }

        return redirect()->back()->with('success', $msg);
    }
}
