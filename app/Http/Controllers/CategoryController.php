<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CategoryController extends Controller
{
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

        return view('categories.index', [
            'categories' => $categories,
            'totalCategories' => Category::count(),
            'activeCategories' => Category::where('status', 'active')->count(),
            'inactiveCategories' => Category::where('status', 'inactive')->count(),
        ]);
    }

    /**
     * Store a newly created category.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:categories,name'],
            'description' => ['nullable', 'string', 'max:500'],
            'status' => ['required', 'in:active,inactive'],
        ], [
            'name.unique' => 'A skincare category with this name already exists.',
        ]);

        $category = Category::create($validated);

        return redirect()->route('categories.index')
            ->with('success', "Category '{$category->name}' created successfully.");
    }

    /**
     * Update an existing category.
     */
    public function update(Request $request, Category $category): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:categories,name,' . $category->id],
            'description' => ['nullable', 'string', 'max:500'],
            'status' => ['required', 'in:active,inactive'],
        ], [
            'name.unique' => 'A skincare category with this name already exists.',
        ]);

        $category->update($validated);

        return redirect()->route('categories.index')
            ->with('success', "Category '{$category->name}' updated successfully.");
    }

    /**
     * Toggle active/inactive status.
     */
    public function toggleStatus(Category $category): RedirectResponse
    {
        $newStatus = $category->status === 'active' ? 'inactive' : 'active';
        $category->update(['status' => $newStatus]);

        return redirect()->route('categories.index')
            ->with('success', "Category '{$category->name}' status changed to " . ucfirst($newStatus) . '.');
    }

    /**
     * Safely delete category, preventing deletion if products are associated.
     */
    public function destroy(Category $category): RedirectResponse
    {
        $productCount = $category->products()->count();

        if ($productCount > 0) {
            return redirect()->route('categories.index')
                ->with('error', "Cannot delete category '{$category->name}' because {$productCount} product(s) are assigned to it. Please reassign the products or deactivate the category instead.");
        }

        $categoryName = $category->name;
        $category->delete();

        return redirect()->route('categories.index')
            ->with('success', "Category '{$categoryName}' deleted successfully.");
    }
}
