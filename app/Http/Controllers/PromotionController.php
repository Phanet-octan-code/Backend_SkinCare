<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Promotion;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PromotionController extends Controller
{
    /**
     * Display promotions & discounts listing with date status tabs and search.
     * Authorized for: Admin, Manager.
     */
    public function index(Request $request): View
    {
        $today = Carbon::today()->toDateString();
        $query = Promotion::with(['products']);

        if ($request->filled('search')) {
            $search = trim($request->input('search'));
            $query->where('name', 'like', "%{$search}%");
        }

        $tab = $request->input('tab', 'all');
        match ($tab) {
            'active' => $query->where('status', 'active')
                ->whereDate('start_date', '<=', $today)
                ->whereDate('end_date', '>=', $today),
            'scheduled' => $query->whereDate('start_date', '>', $today),
            'expired' => $query->whereDate('end_date', '<', $today),
            'inactive' => $query->where('status', 'inactive'),
            default => null, // 'all'
        };

        $promotions = $query->latest()->paginate(12)->withQueryString();

        // Calculate count metrics for tab badges
        $counts = [
            'all' => Promotion::count(),
            'active' => Promotion::where('status', 'active')
                ->whereDate('start_date', '<=', $today)
                ->whereDate('end_date', '>=', $today)
                ->count(),
            'scheduled' => Promotion::whereDate('start_date', '>', $today)->count(),
            'expired' => Promotion::whereDate('end_date', '<', $today)->count(),
            'inactive' => Promotion::where('status', 'inactive')->count(),
        ];

        return view('promotions.index', [
            'promotions' => $promotions,
            'counts' => $counts,
            'activeTab' => $tab,
            'products' => Product::where('status', 'active')->orderBy('name')->get(),
        ]);
    }

    /**
     * Store a newly created promotion campaign.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validatePromotion($request);

        $productIds = $request->input('product_ids', []);
        $scopeType = $request->input('scope_type', 'general');

        $promotion = Promotion::create([
            'name' => $validated['name'],
            'discount_type' => $validated['discount_type'],
            'discount_value' => $validated['discount_value'],
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'status' => $validated['status'],
        ]);

        if ($scopeType === 'specific' && !empty($productIds)) {
            $promotion->products()->sync($productIds);
        }

        return redirect()->route('promotions.index')
            ->with('success', "Promotion '{$promotion->name}' created successfully.");
    }

    /**
     * Update an existing promotion campaign.
     */
    public function update(Request $request, Promotion $promotion): RedirectResponse
    {
        $validated = $this->validatePromotion($request, $promotion->id);

        $productIds = $request->input('product_ids', []);
        $scopeType = $request->input('scope_type', 'general');

        $promotion->update([
            'name' => $validated['name'],
            'discount_type' => $validated['discount_type'],
            'discount_value' => $validated['discount_value'],
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'status' => $validated['status'],
        ]);

        if ($scopeType === 'specific' && !empty($productIds)) {
            $promotion->products()->sync($productIds);
        } else {
            $promotion->products()->detach();
        }

        return redirect()->route('promotions.index')
            ->with('success', "Promotion '{$promotion->name}' updated successfully.");
    }

    /**
     * Toggle active/inactive status.
     */
    public function toggleStatus(Promotion $promotion): RedirectResponse
    {
        $newStatus = $promotion->status === 'active' ? 'inactive' : 'active';
        $promotion->update(['status' => $newStatus]);

        return redirect()->route('promotions.index')
            ->with('success', "Promotion '{$promotion->name}' status changed to " . ucfirst($newStatus) . '.');
    }

    /**
     * Delete a promotion and detach product associations.
     */
    public function destroy(Promotion $promotion): RedirectResponse
    {
        $promoName = $promotion->name;
        $promotion->products()->detach();
        $promotion->delete();

        return redirect()->route('promotions.index')
            ->with('success', "Promotion '{$promoName}' deleted successfully.");
    }

    /**
     * Common validation logic for store and update.
     *
     * @throws ValidationException
     */
    protected function validatePromotion(Request $request, ?int $id = null): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'discount_type' => ['required', 'in:percentage,fixed'],
            'discount_value' => ['required', 'numeric', 'min:0.01'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'status' => ['required', 'in:active,inactive'],
            'scope_type' => ['nullable', 'in:general,specific'],
            'product_ids' => ['nullable', 'array'],
            'product_ids.*' => ['exists:products,id'],
        ], [
            'end_date.after_or_equal' => 'Promotion end date must be on or after the start date.',
            'discount_value.min' => 'Discount value must be greater than zero.',
        ]);

        if ($validated['discount_type'] === 'percentage' && $validated['discount_value'] > 100) {
            throw ValidationException::withMessages([
                'discount_value' => 'Percentage discount cannot exceed 100%.',
            ]);
        }

        return $validated;
    }
}
