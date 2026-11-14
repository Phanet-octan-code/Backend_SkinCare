<?php

namespace App\Http\Controllers;

use App\Models\Bundle;
use App\Models\Category;
use App\Models\Product;
use App\Services\BundleService;
use App\Services\ImageUploadService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BundleController extends Controller
{
    public function __construct(
        protected BundleService $bundleService,
        protected ImageUploadService $imageUploadService
    ) {}

    public function index(Request $request): View
    {
        $query = Bundle::with(['items.product'])->latest();

        if ($request->filled('type') && $request->type !== 'all') {
            $query->where('type', $request->type);
        }

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $s = trim($request->search);
            $query->where('name', 'LIKE', "%{$s}%");
        }

        $bundles = $query->paginate(15)->withQueryString();

        $counts = [
            'all' => Bundle::count(),
            'bundle' => Bundle::where('type', 'bundle')->count(),
            'gift_set' => Bundle::where('type', 'gift_set')->count(),
            'active' => Bundle::where('status', 'active')->count(),
        ];

        return view('bundles.index', compact('bundles', 'counts'));
    }

    public function create(): View
    {
        $products = Product::where('status', 'active')->orderBy('name')->get();
        return view('bundles.create', compact('products'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:bundle,gift_set',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'original_price' => 'nullable|numeric|min:0',
            'packaging_details' => 'nullable|string',
            'status' => 'required|in:active,inactive',
            'image' => 'nullable|image|max:2048',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.is_free_gift' => 'nullable|boolean',
        ]);

        if ($request->hasFile('image')) {
            $validated['image'] = $this->imageUploadService->upload($request->file('image'), 'bundles');
        }

        $bundle = $this->bundleService->saveBundle($validated);

        return redirect()->route('bundles.index')
            ->with('success', "Bundle '{$bundle->name}' created successfully.");
    }

    public function edit(Bundle $bundle): View
    {
        $bundle->load('items.product');
        $products = Product::where('status', 'active')->orderBy('name')->get();
        return view('bundles.edit', compact('bundle', 'products'));
    }

    public function update(Request $request, Bundle $bundle): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:bundle,gift_set',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'original_price' => 'nullable|numeric|min:0',
            'packaging_details' => 'nullable|string',
            'status' => 'required|in:active,inactive',
            'image' => 'nullable|image|max:2048',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.is_free_gift' => 'nullable|boolean',
        ]);

        if ($request->hasFile('image')) {
            $validated['image'] = $this->imageUploadService->upload($request->file('image'), 'bundles');
        }

        $this->bundleService->saveBundle($validated, $bundle);

        return redirect()->route('bundles.index')
            ->with('success', "Bundle '{$bundle->name}' updated successfully.");
    }

    public function destroy(Bundle $bundle): RedirectResponse
    {
        $bundle->delete();
        return redirect()->route('bundles.index')
            ->with('success', "Bundle '{$bundle->name}' archived successfully.");
    }
}

