<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Services\ImageUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
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
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
        ], [
            'name.unique' => 'A skincare category with this name already exists.',
            'image.image' => 'The category image must be a valid image file.',
            'image.mimes' => 'The category image must be a file of type: jpg, jpeg, png, webp.',
            'image.max' => 'The category image must not exceed 10MB in size.',
        ]);

        $imagePath = null;
        try {
            if ($request->hasFile('image')) {
                $imagePath = ImageUploadService::storeImage($request->file('image'), 'categories', 'cat');
                $validated['image'] = $imagePath;
            }

            DB::beginTransaction();

            $category = Category::create($validated);

            DB::commit();

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

        } catch (\Illuminate\Validation\ValidationException $e) {
            if ($imagePath) {
                ImageUploadService::deleteImage($imagePath, 'cat_');
            }
            throw $e;
        } catch (\Throwable $e) {
            DB::rollBack();
            if ($imagePath) {
                ImageUploadService::deleteImage($imagePath, 'cat_');
            }
            Log::error('Category creation failed: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'input' => $request->except(['image']),
            ]);

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unable to save category: ' . $e->getMessage(),
                ], 422);
            }

            return redirect()->back()
                ->withInput()
                ->with('error', 'Unable to save category: ' . $e->getMessage());
        }
    }

    /**
     * Update an existing category.
     */
    public function update(Request $request, ?Category $category = null): RedirectResponse|JsonResponse
    {
        if (!$category || !$category->exists) {
            $id = $request->input('id') ?? $request->input('category_id');
            if ($id) {
                $category = Category::findOrFail((int) $id);
            } else {
                return redirect()->route('categories.index')
                    ->with('error', 'Category ID is required for updating.');
            }
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:categories,name,' . $category->id],
            'description' => ['nullable', 'string', 'max:500'],
            'status' => ['required', 'in:active,inactive'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
        ], [
            'name.unique' => 'A skincare category with this name already exists.',
            'image.image' => 'The category image must be a valid image file.',
            'image.mimes' => 'The category image must be a file of type: jpg, jpeg, png, webp.',
            'image.max' => 'The category image must not exceed 10MB in size.',
        ]);

        $newUploadedFile = null;
        try {
            DB::beginTransaction();

            if ($request->hasFile('image')) {
                $newUploadedFile = ImageUploadService::storeImage($request->file('image'), 'categories', 'cat');
                ImageUploadService::deleteImage($category->image, 'cat_');
                $validated['image'] = $newUploadedFile;
            } elseif ($request->boolean('remove_image')) {
                ImageUploadService::deleteImage($category->image, 'cat_');
                $validated['image'] = null;
            }

            $category->update($validated);

            DB::commit();

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

        } catch (\Illuminate\Validation\ValidationException $e) {
            if ($newUploadedFile) {
                ImageUploadService::deleteImage($newUploadedFile, 'cat_');
            }
            throw $e;
        } catch (\Throwable $e) {
            DB::rollBack();
            if ($newUploadedFile) {
                ImageUploadService::deleteImage($newUploadedFile, 'cat_');
            }
            Log::error('Category update failed: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'category_id' => $category->id,
            ]);

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unable to update category: ' . $e->getMessage(),
                ], 422);
            }

            return redirect()->back()
                ->withInput()
                ->with('error', 'Unable to update category: ' . $e->getMessage());
        }
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
        ImageUploadService::deleteImage($category->image, 'cat_');
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
