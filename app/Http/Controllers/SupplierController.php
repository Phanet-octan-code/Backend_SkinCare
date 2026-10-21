<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SupplierController extends Controller
{
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

        return view('suppliers.index', [
            'suppliers' => $suppliers,
            'totalSuppliers' => Supplier::count(),
            'activeSuppliers' => Supplier::where('status', 'active')->count(),
            'inactiveSuppliers' => Supplier::where('status', 'inactive')->count(),
        ]);
    }

    /**
     * Store a newly registered supplier.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:100'],
            'address' => ['nullable', 'string', 'max:255'],
            'status' => ['required', 'in:active,inactive'],
        ]);

        $supplier = Supplier::create($validated);

        return redirect()->route('suppliers.index')
            ->with('success', "Supplier '{$supplier->name}' registered successfully.");
    }

    /**
     * Update an existing supplier.
     */
    public function update(Request $request, Supplier $supplier): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:100'],
            'address' => ['nullable', 'string', 'max:255'],
            'status' => ['required', 'in:active,inactive'],
        ]);

        $supplier->update($validated);

        return redirect()->route('suppliers.index')
            ->with('success', "Supplier '{$supplier->name}' updated successfully.");
    }

    /**
     * Toggle active/inactive status.
     */
    public function toggleStatus(Supplier $supplier): RedirectResponse
    {
        $newStatus = $supplier->status === 'active' ? 'inactive' : 'active';
        $supplier->update(['status' => $newStatus]);

        return redirect()->route('suppliers.index')
            ->with('success', "Supplier '{$supplier->name}' status changed to " . ucfirst($newStatus) . '.');
    }

    /**
     * Safely delete supplier, preserving foreign keys and historical transaction references.
     */
    public function destroy(Supplier $supplier): RedirectResponse
    {
        $productCount = $supplier->products()->count();
        $txnCount = $supplier->stockTransactions()->count();

        if ($productCount > 0 || $txnCount > 0) {
            $reason = [];
            if ($productCount > 0) {
                $reason[] = "{$productCount} catalog product(s)";
            }
            if ($txnCount > 0) {
                $reason[] = "{$txnCount} historical stock-in transaction(s)";
            }

            return redirect()->route('suppliers.index')
                ->with('error', "Cannot delete supplier '{$supplier->name}' because it is linked to " . implode(' and ', $reason) . '. Please deactivate this supplier instead to protect audit history.');
        }

        $supplierName = $supplier->name;
        $supplier->delete();

        return redirect()->route('suppliers.index')
            ->with('success', "Supplier '{$supplierName}' deleted successfully.");
    }
}
