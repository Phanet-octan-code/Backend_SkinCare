<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public static function clearCategoryCaches(): void
    {
        Cache::forget('categories_counts');
        Cache::forget('active_categories_list');
        Cache::forget('pos_catalog_products');
        Cache::forget('pos_terminal_catalog_bundle');
    }

    /**
     * Display categories listing with search and status filtering.
     * Authorized for: Admin, Manager.
     */
    public function index(Request $request): View
    {
        $query = Category::withCount('products');

        if ($request->filled('search')) {
            $search = trim($request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
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

        $categories = $query->paginate(12)->withQueryString();

        $counts = Cache::remember('categories_counts', 60, function () {
            $row = Category::selectRaw("
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

        return view('categories.index', [
            'categories' => $categories,
            'totalCategories' => $counts['total'],
            'activeCategories' => $counts['active'],
            'inactiveCategories' => $counts['inactive'],
        ]);
    }

    /**
     * Store a newly created category.
     */
    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:categories,name'],
            'description' => ['nullable', 'string', 'max:500'],
            'status' => ['required', 'in:active,inactive'],
        ], [
            'name.unique' => 'A skincare category with this name already exists.',
        ]);

        $category = Category::create($validated);
        self::clearCategoryCaches();

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => "Category '{$category->name}' created successfully.",
                'category' => $category,
            ]);
        }

        return redirect()->route('categories.index')
            ->with('success', "Category '{$category->name}' created successfully.");
    }

    /**
     * Update an existing category.
     */
    public function update(Request $request, Category $category): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:categories,name,' . $category->id],
            'description' => ['nullable', 'string', 'max:500'],
            'status' => ['required', 'in:active,inactive'],
        ], [
            'name.unique' => 'A skincare category with this name already exists.',
        ]);

        $category->update($validated);
        self::clearCategoryCaches();

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => "Category '{$category->name}' updated successfully.",
                'category' => $category,
            ]);
        }

        return redirect()->route('categories.index')
            ->with('success', "Category '{$category->name}' updated successfully.");
    }

    /**
     * Toggle active/inactive status.
     */
    public function toggleStatus(Category $category): RedirectResponse|JsonResponse
    {
        $newStatus = $category->status === 'active' ? 'inactive' : 'active';
        $category->update(['status' => $newStatus]);
        self::clearCategoryCaches();

        if (request()->wantsJson() || request()->ajax()) {
            return response()->json([
                'success' => true,
                'message' => "Category '{$category->name}' status changed to " . ucfirst($newStatus) . '.',
                'status' => $newStatus,
            ]);
        }

        return redirect()->route('categories.index')
            ->with('success', "Category '{$category->name}' status changed to " . ucfirst($newStatus) . '.');
    }

    /**
     * Safely delete category, preventing deletion if products are associated.
     */
    public function destroy(Category $category): RedirectResponse|JsonResponse
    {
        $productCount = $category->products()->withTrashed()->count();

        if ($productCount > 0) {
            $msg = "Cannot delete category '{$category->name}' because {$productCount} product(s) are assigned to it. Please reassign the products or deactivate the category instead.";

            if (request()->wantsJson() || request()->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => $msg,
                ], 422);
            }

            return redirect()->route('categories.index')
                ->with('error', $msg);
        }

        $categoryName = $category->name;
        $category->delete();
        self::clearCategoryCaches();

        if (request()->wantsJson() || request()->ajax()) {
            return response()->json([
                'success' => true,
                'message' => "Category '{$categoryName}' deleted successfully.",
            ]);
        }

        return redirect()->route('categories.index')
            ->with('success', "Category '{$categoryName}' deleted successfully.");
    }
}
