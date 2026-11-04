<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Sale;
use App\Models\User;
use App\Services\CheckoutService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use InvalidArgumentException;

class SaleController extends Controller
{
    public function __construct(
        protected CheckoutService $checkoutService
    ) {}

    /**
     * Display sales history ledger with extensive filtering.
     * Cashiers see their own sales; Admins & Managers see store-wide transactions.
     */
    public function index(Request $request): View
    {
        $user = Auth::user();
        $isCashier = $user->role === 'cashier';

        $query = Sale::with(['user', 'customer', 'details.product'])->latest();

        // Scope to cashier if role is strictly cashier
        if ($isCashier) {
            $query->where('user_id', $user->id);
        }

        // 1. Invoice & Customer text search
        if ($request->filled('search')) {
            $search = trim($request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'LIKE', "%{$search}%")
                  ->orWhereHas('customer', function ($cq) use ($search) {
                      $cq->where('name', 'LIKE', "%{$search}%")
                         ->orWhere('phone', 'LIKE', "%{$search}%")
                         ->orWhere('membership_number', 'LIKE', "%{$search}%");
                  });
            });
        }

        // 2. Date Filtering (Presets or Custom Start/End)
        if ($request->filled('date_preset')) {
            $preset = $request->input('date_preset');
            if ($preset === 'today') {
                $query->whereDate('created_at', Carbon::today());
            } elseif ($preset === 'yesterday') {
                $query->whereDate('created_at', Carbon::yesterday());
            } elseif ($preset === '7days') {
                $query->whereBetween('created_at', [Carbon::now()->subDays(6)->startOfDay(), Carbon::now()->endOfDay()]);
            } elseif ($preset === 'this_month') {
                $query->whereBetween('created_at', [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()]);
            }
        } elseif ($request->filled('start_date') || $request->filled('end_date')) {
            if ($request->filled('start_date')) {
                $query->whereDate('created_at', '>=', Carbon::parse($request->input('start_date')));
            }
            if ($request->filled('end_date')) {
                $query->whereDate('created_at', '<=', Carbon::parse($request->input('end_date')));
            }
        }

        // 3. Cashier Filter (Managers/Admins only)
        if (!$isCashier && $request->filled('cashier_id')) {
            $query->where('user_id', $request->input('cashier_id'));
        }

        // 4. Customer Filter
        if ($request->filled('customer_id')) {
            if ($request->input('customer_id') === 'guest') {
                $query->whereNull('customer_id');
            } else {
                $query->where('customer_id', $request->input('customer_id'));
            }
        }

        // 5. Payment Method Filter
        if ($request->filled('payment_type')) {
            $type = $request->input('payment_type');
            if ($type === 'qr') {
                $query->whereIn('payment_type', ['qr', 'qris', 'transfer']);
            } else {
                $query->where('payment_type', $type);
            }
        }

        // 6. Status Filter
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $sales = $query->paginate(15)->withQueryString();

        // Filter metadata
        $cashiers = (!$isCashier) 
            ? \Illuminate\Support\Facades\Cache::remember('active_cashiers_list', 120, fn() => User::whereIn('role', ['admin', 'manager', 'cashier'])->orderBy('name')->get())
            : collect([]);
        $customers = \Illuminate\Support\Facades\Cache::remember('active_customers_dropdown', 120, fn() => Customer::where('status', 'active')->orderBy('name')->get());

        return view('sales.index', [
            'sales' => $sales,
            'cashiers' => $cashiers,
            'customers' => $customers,
            'isCashier' => $isCashier,
            'filters' => $request->only([
                'search', 
                'date_preset', 
                'start_date', 
                'end_date', 
                'cashier_id', 
                'customer_id', 
                'payment_type', 
                'status'
            ]),
        ]);
    }

    /**
     * View full sale details (JSON for modal preview or direct view).
     */
    public function show(Sale $sale): JsonResponse|View
    {
        $user = Auth::user();
        if ($user->role === 'cashier' && $sale->user_id !== $user->id) {
            abort(403, 'Unauthorized access to other cashiers\' transactions.');
        }

        $sale->load(['user', 'customer', 'details.product.category']);

        if (request()->wantsJson()) {
            return response()->json([
                'success' => true,
                'sale' => [
                    'id' => $sale->id,
                    'invoice_number' => $sale->invoice_number,
                    'created_at' => $sale->created_at->format('M j, Y h:i A'),
                    'cashier_name' => $sale->user ? $sale->user->name : 'System',
                    'customer_name' => $sale->customer ? $sale->customer->name : 'Walk-in Guest',
                    'customer_tier' => $sale->customer ? $sale->customer->membership_level : 'Standard',
                    'subtotal' => (float) $sale->subtotal,
                    'discount' => (float) $sale->discount,
                    'tax' => (float) $sale->tax,
                    'final_total' => (float) $sale->final_total,
                    'payment_type' => $sale->payment_type,
                    'payment_status' => $sale->payment_status,
                    'status' => $sale->status,
                    'details' => $sale->details->map(fn($d) => [
                        'id' => $d->id,
                        'name' => $d->product ? $d->product->name : 'Formula',
                        'barcode' => $d->product ? $d->product->barcode : '',
                        'category' => $d->product && $d->product->category ? $d->product->category->name : '',
                        'qty' => $d->qty,
                        'price' => (float) $d->price,
                        'discount' => (float) $d->discount,
                        'subtotal' => (float) $d->subtotal,
                    ]),
                    'receipt_url' => route('pos.receipt', $sale->id),
                ],
            ]);
        }

        return view('sales.show', ['sale' => $sale]);
    }

    /**
     * Cancel an existing sale, restock formulas, and void payment.
     * Authorized for: Admin, Manager, or Cashier for their own transaction.
     */
    public function cancel(Request $request, Sale $sale): RedirectResponse|JsonResponse
    {
        $user = Auth::user();
        if ($user->role === 'cashier' && $sale->user_id !== $user->id) {
            abort(403, 'Cashiers may only request void on their own active shift sales.');
        }

        $validated = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        try {
            $this->checkoutService->cancelSale(
                sale: $sale,
                authorizer: $user,
                reason: $validated['reason']
            );

            $msg = "Sale #{$sale->invoice_number} has been cancelled and items were successfully restocked into inventory.";

            if ($request->wantsJson()) {
                return response()->json(['success' => true, 'message' => $msg]);
            }

            return redirect()->back()->with('status', $msg);
        } catch (InvalidArgumentException $e) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
            }
            return redirect()->back()->withErrors(['cancel' => $e->getMessage()]);
        }
    }

    /**
     * Process return/refund and restock items into inventory.
     */
    public function processReturn(Request $request, Sale $sale): RedirectResponse|JsonResponse
    {
        $user = Auth::user();
        // Returns require Manager or Admin authorization
        if (!$user->isManager()) {
            abort(403, 'Return and refund authorizations require Store Manager or Administrator credentials.');
        }

        $validated = $request->validate([
            'reason' => 'required|string|max:500',
            'items' => 'nullable|array',
            'items.*.detail_id' => 'required_with:items|integer',
            'items.*.qty' => 'required_with:items|integer|min:1',
        ]);

        try {
            // If no specific item array passed, default to all items in the sale
            $itemsToReturn = $validated['items'] ?? $sale->details->map(fn($d) => [
                'detail_id' => $d->id,
                'qty' => $d->qty,
            ])->toArray();

            $this->checkoutService->processReturn(
                sale: $sale,
                returnedItems: $itemsToReturn,
                authorizer: $user,
                reason: $validated['reason']
            );

            $msg = "Refund & return processed for Invoice #{$sale->invoice_number}. Restocked items successfully.";

            if ($request->wantsJson()) {
                return response()->json(['success' => true, 'message' => $msg]);
            }

            return redirect()->back()->with('status', $msg);
        } catch (InvalidArgumentException $e) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
            }
            return redirect()->back()->withErrors(['return' => $e->getMessage()]);
        }
    }
}
