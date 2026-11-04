<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CustomerController extends Controller
{
    public static function clearCustomerCaches(): void
    {
        \Illuminate\Support\Facades\Cache::forget('pos_terminal_catalog_bundle');
        \Illuminate\Support\Facades\Cache::forget('active_customers_list');
    }

    /**
     * Display skincare customers & VIP loyalty members with CRM filters and search.
     */
    public function index(Request $request): View
    {
        $query = Customer::withCount('sales')
            ->withSum(['sales' => fn ($q) => $q->where('payment_status', 'paid')], 'final_total');

        if ($request->filled('search')) {
            $search = trim($request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('phone', 'LIKE', "%{$search}%")
                  ->orWhere('membership_number', 'LIKE', "%{$search}%")
                  ->orWhere('email', 'LIKE', "%{$search}%");
            });
        }

        if ($request->filled('tier')) {
            $query->where('membership_level', $request->input('tier'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        // Sorting
        $allowedSorts = ['name', 'sales_count', 'sales_sum_final_total', 'created_at'];
        $sortBy = in_array($request->input('sort_by'), $allowedSorts, true) ? $request->input('sort_by') : 'created_at';
        $sortDir = strtolower($request->input('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';

        $query->orderBy($sortBy, $sortDir);

        $customers = $query->paginate(15)->withQueryString();

        return view('customers.index', [
            'customers' => $customers,
            'filters' => $request->only(['search', 'tier', 'status', 'sort_by', 'sort_dir']),
        ]);
    }

    /**
     * Store a newly created skincare customer / loyalty VIP.
     */
    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255|unique:customers,email',
            'address' => 'nullable|string|max:500',
            'membership_number' => 'nullable|string|max:50|unique:customers,membership_number',
            'membership_level' => 'required|in:Bronze,Silver,Gold,Platinum',
            'status' => 'nullable|in:active,inactive',
        ], [
            'email.unique' => 'A customer with this email is already registered.',
            'membership_number.unique' => 'This membership number is already assigned.',
        ]);

        // Generate unique membership number if not provided
        $tierCode = match ($validated['membership_level']) {
            'Platinum' => 'PLT',
            'Gold' => 'GLD',
            'Silver' => 'SLV',
            default => 'BRZ',
        };

        $membershipNumber = !empty($validated['membership_number'])
            ? trim($validated['membership_number'])
            : 'MEM-' . $tierCode . '-' . strtoupper(substr(uniqid(), -4));

        $customer = Customer::create([
            'name' => $validated['name'],
            'phone' => $validated['phone'] ?? null,
            'email' => $validated['email'] ?? null,
            'address' => $validated['address'] ?? null,
            'membership_number' => $membershipNumber,
            'membership_level' => $validated['membership_level'],
            'status' => $validated['status'] ?? 'active',
        ]);

        self::clearCustomerCaches();

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Customer registered successfully.',
                'customer' => [
                    'id' => $customer->id,
                    'name' => $customer->name,
                    'phone' => $customer->phone,
                    'membership_level' => $customer->membership_level,
                    'membership_number' => $customer->membership_number,
                    'discount_rate' => $customer->membership_discount_percentage,
                ],
            ]);
        }

        return redirect()->route('customers.index')->with('status', "Client [{$customer->name}] enrolled successfully.");
    }

    /**
     * Display detailed customer profile and complete sales purchase history.
     */
    public function show(Customer $customer, Request $request): View
    {
        $salesQuery = $customer->sales()->with(['user', 'details.product'])->latest();

        if ($request->filled('payment_status')) {
            $salesQuery->where('payment_status', $request->input('payment_status'));
        }

        if ($request->filled('payment_type')) {
            $salesQuery->where('payment_type', $request->input('payment_type'));
        }

        $sales = $salesQuery->paginate(10)->withQueryString();

        // Calculate analytics
        $totalSales = $customer->sales()->count();
        $completedSales = $customer->sales()->where('payment_status', 'paid')->count();
        $totalSpent = (float) $customer->sales()->where('payment_status', 'paid')->sum('final_total');
        $averageOrder = $completedSales > 0 ? round($totalSpent / $completedSales, 2) : 0.0;
        $latestSale = $customer->sales()->latest()->first();

        return view('customers.show', [
            'customer' => $customer,
            'sales' => $sales,
            'stats' => [
                'total_orders' => $totalSales,
                'completed_orders' => $completedSales,
                'total_spent' => $totalSpent,
                'average_order' => $averageOrder,
                'latest_order_date' => $latestSale ? $latestSale->created_at->format('M j, Y') : 'Never',
            ],
            'filters' => $request->only(['payment_status', 'payment_type']),
        ]);
    }

    /**
     * Update customer profile and membership details.
     */
    public function update(Request $request, Customer $customer): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:50',
            'email' => [
                'nullable',
                'email',
                'max:255',
                Rule::unique('customers', 'email')->ignore($customer->id),
            ],
            'address' => 'nullable|string|max:500',
            'membership_number' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('customers', 'membership_number')->ignore($customer->id),
            ],
            'membership_level' => 'required|in:Bronze,Silver,Gold,Platinum',
            'status' => 'required|in:active,inactive',
        ], [
            'email.unique' => 'A customer with this email is already registered.',
            'membership_number.unique' => 'This membership number is already assigned.',
        ]);

        $customer->update($validated);
        self::clearCustomerCaches();

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => "Customer [{$customer->name}] profile updated successfully.",
                'customer' => $customer,
            ]);
        }

        return redirect()->back()
            ->with('status', "Customer [{$customer->name}] profile updated successfully.");
    }

    /**
     * Toggle customer active/inactive status.
     */
    public function toggleStatus(Customer $customer): RedirectResponse|JsonResponse
    {
        $newStatus = $customer->status === 'active' ? 'inactive' : 'active';
        $customer->update(['status' => $newStatus]);
        self::clearCustomerCaches();

        if (request()->wantsJson() || request()->ajax()) {
            return response()->json([
                'success' => true,
                'message' => "Customer [{$customer->name}] status changed to " . ucfirst($newStatus) . '.',
                'status' => $newStatus,
            ]);
        }

        return redirect()->back()
            ->with('status', "Customer [{$customer->name}] status changed to " . ucfirst($newStatus) . '.');
    }

    /**
     * Remove customer or deactivate if historical sales exist.
     */
    public function destroy(Customer $customer): RedirectResponse|JsonResponse
    {
        $hasSales = $customer->sales()->exists();

        if ($hasSales) {
            $customer->update(['status' => 'inactive']);
            self::clearCustomerCaches();

            $msg = "Customer [{$customer->name}] has historical sales transactions. The client profile was deactivated instead of deleted to protect sales history.";

            if (request()->wantsJson() || request()->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => $msg,
                    'deactivated' => true,
                ]);
            }

            return redirect()->route('customers.index')
                ->with('status', $msg);
        }

        $name = $customer->name;
        $customer->delete();
        self::clearCustomerCaches();

        if (request()->wantsJson() || request()->ajax()) {
            return response()->json([
                'success' => true,
                'message' => "Customer [{$name}] deleted successfully.",
            ]);
        }

        return redirect()->route('customers.index')
            ->with('status', "Customer [{$name}] deleted successfully.");
    }
}
