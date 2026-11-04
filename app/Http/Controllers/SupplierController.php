<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class SupplierController extends Controller
{
    public static function clearSupplierCaches(): void
    {
        Cache::forget('suppliers_counts');
        Cache::forget('active_suppliers_list');
    }

    /**
     * Display skincare suppliers directory with search and status filtering.
     * Authorized for: Admin, Manager, Inventory.
     */
    public function index(Request $request): View
    {
        $query = Supplier::withCount(['products', 'stockTransactions']);

        if ($request->filled('search')) {
            $search = trim($request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('address', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status') && in_array($request->input('status'), ['active', 'inactive'], true)) {
            $query->where('status', $request->input('status'));
        }

        $sort = $request->input('sort', 'latest');
        match ($sort) {
            'name_asc' => $query->orderBy('name', 'asc'),
            'name_desc' => $query->orderBy('name', 'desc'),
            'products_count' => $query->orderBy('products_count', 'desc'),
            default => $query->latest(),
        };

        $suppliers = $query->paginate(12)->withQueryString();

        $counts = Cache::remember('suppliers_counts', 60, function () {
            $row = Supplier::selectRaw("
                COUNT(*) as total,
                COUNT(CASE WHEN status = 'active' THEN 1 END) as active,
                COUNT(CASE WHEN status = 'inactive' THEN 1 END) as inactive
            ")->first();

            return [
                'total' => (int) ($row->total ?? 0),
                'active' => (int) ($row->active ?? 0),
                'inactive' => (int) ($row->inactive ?? 0),
            ];
        });

        return view('suppliers.index', [
            'suppliers' => $suppliers,
            'totalSuppliers' => $counts['total'],
            'activeSuppliers' => $counts['active'],
            'inactiveSuppliers' => $counts['inactive'],
        ]);
    }

    /**
     * Store a newly registered supplier.
     */
    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:100'],
            'address' => ['nullable', 'string', 'max:255'],
            'status' => ['required', 'in:active,inactive'],
        ]);

        $supplier = Supplier::create($validated);
        self::clearSupplierCaches();

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => "Supplier '{$supplier->name}' registered successfully.",
                'supplier' => $supplier,
            ]);
        }

        return redirect()->route('suppliers.index')
            ->with('success', "Supplier '{$supplier->name}' registered successfully.");
    }

    /**
     * Update an existing supplier.
     */
    public function update(Request $request, Supplier $supplier): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:100'],
            'address' => ['nullable', 'string', 'max:255'],
            'status' => ['required', 'in:active,inactive'],
        ]);

        $supplier->update($validated);
        self::clearSupplierCaches();

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => "Supplier '{$supplier->name}' updated successfully.",
                'supplier' => $supplier,
            ]);
        }

        return redirect()->route('suppliers.index')
            ->with('success', "Supplier '{$supplier->name}' updated successfully.");
    }

    /**
     * Toggle active/inactive status.
     */
    public function toggleStatus(Supplier $supplier): RedirectResponse|JsonResponse
    {
        $newStatus = $supplier->status === 'active' ? 'inactive' : 'active';
        $supplier->update(['status' => $newStatus]);
        self::clearSupplierCaches();

        if (request()->wantsJson() || request()->ajax()) {
            return response()->json([
                'success' => true,
                'message' => "Supplier '{$supplier->name}' status changed to " . ucfirst($newStatus) . '.',
                'status' => $newStatus,
            ]);
        }

        return redirect()->route('suppliers.index')
            ->with('success', "Supplier '{$supplier->name}' status changed to " . ucfirst($newStatus) . '.');
    }

    /**
     * Safely delete supplier, preserving foreign keys and historical transaction references.
     */
    public function destroy(Supplier $supplier): RedirectResponse|JsonResponse
    {
        $productCount = $supplier->products()->withTrashed()->count();
        $txnCount = $supplier->stockTransactions()->count();

        if ($productCount > 0 || $txnCount > 0) {
            $reason = [];
            if ($productCount > 0) {
                $reason[] = "{$productCount} catalog product(s)";
            }
            if ($txnCount > 0) {
                $reason[] = "{$txnCount} historical stock-in transaction(s)";
            }

            $msg = "Cannot delete supplier '{$supplier->name}' because it is linked to " . implode(' and ', $reason) . '. Please deactivate this supplier instead to protect audit history.';

            if (request()->wantsJson() || request()->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => $msg,
                ], 422);
            }

            return redirect()->route('suppliers.index')
                ->with('error', $msg);
        }

        $supplierName = $supplier->name;
        $supplier->delete();
        self::clearSupplierCaches();

        if (request()->wantsJson() || request()->ajax()) {
            return response()->json([
                'success' => true,
                'message' => "Supplier '{$supplierName}' deleted successfully.",
            ]);
        }

        return redirect()->route('suppliers.index')
            ->with('success', "Supplier '{$supplierName}' deleted successfully.");
    }
}
