<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\Sale;
use App\Models\Setting;
use App\Services\CheckoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use InvalidArgumentException;

class PosController extends Controller
{
    public function __construct(
        protected CheckoutService $checkoutService
    ) {}

    /**
     * Display the full-featured, responsive POS Terminal interface.
     * Authorized for: Admin, Manager, Cashier.
     */
    public function index(): View
    {
        $categories = Category::where('status', 'active')->orderBy('name')->get();

        $products = Product::with([
                'category',
                'promotions' => fn($q) => $q->active(),
            ])
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        $customers = Customer::where('status', 'active')
            ->orderBy('name')
            ->get();

        $promotions = Promotion::active()
            ->with('products')
            ->get();

        // Prepare client-side payload for instant barcode scanning and search
        $catalogPayload = $products->map(function ($p) {
            $bestPromo = null;
            $bestDiscountText = null;

            if ($p->promotions->isNotEmpty()) {
                $firstPromo = $p->promotions->first();
                $bestPromo = [
                    'id' => $firstPromo->id,
                    'name' => $firstPromo->name,
                    'discount_type' => $firstPromo->discount_type,
                    'discount_value' => (float) $firstPromo->discount_value,
                ];
                $bestDiscountText = $firstPromo->discount_type === 'percentage'
                    ? (int) $firstPromo->discount_value . '% OFF'
                    : '$' . number_format($firstPromo->discount_value, 2) . ' OFF';
            }

            return [
                'id' => $p->id,
                'name' => $p->name,
                'barcode' => $p->barcode,
                'price' => (float) $p->sale_price,
                'cost_price' => (float) $p->cost_price,
                'qty' => (int) $p->qty,
                'category_id' => $p->category_id,
                'category_name' => $p->category ? $p->category->name : 'Unassigned',
                'image' => $p->image,
                'low_stock_threshold' => (int) $p->low_stock_threshold,
                'is_low_stock' => $p->isLowStock(),
                'promo' => $bestPromo,
                'promo_text' => $bestDiscountText,
            ];
        });

        $customersPayload = $customers->map(function ($c) {
            return [
                'id' => $c->id,
                'name' => $c->name,
                'phone' => $c->phone,
                'membership_level' => $c->membership_level ?? 'Standard',
                'membership_number' => $c->membership_number,
                'discount_percentage' => (float) $c->membership_discount_percentage,
            ];
        });

        return view('pos.index', [
            'categories' => $categories,
            'products' => $products,
            'catalogPayload' => $catalogPayload,
            'customers' => $customers,
            'customersPayload' => $customersPayload,
            'promotions' => $promotions,
            'taxRate' => (float) Setting::get('tax_rate', 5.0),
            'currency' => Setting::get('currency', 'USD'),
            'currencySymbol' => Setting::currencySymbol(),
            'exchangeRateKhr' => (float) Setting::get('exchange_rate_khr', 4100.0),
        ]);
    }

    /**
     * Authoritative server-side checkout submission.
     */
    public function checkout(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.qty' => 'required|integer|min:1',
            'customer_id' => 'nullable|integer|exists:customers,id',
            'payment_type' => 'required|string|in:cash,card,qr,transfer,qris',
            'amount_received' => 'nullable|numeric|min:0',
            'manual_discount' => 'nullable|numeric|min:0|max:100',
            'notes' => 'nullable|string|max:500',
        ]);

        try {
            $result = $this->checkoutService->processCheckout(
                cashier: Auth::user(),
                items: $validated['items'],
                customerId: $validated['customer_id'] ?? null,
                paymentType: $validated['payment_type'],
                amountReceived: (float) ($validated['amount_received'] ?? 0.00),
                manualDiscountRate: (float) ($validated['manual_discount'] ?? 0.00),
                notes: $validated['notes'] ?? null
            );

            $sale = $result['sale'];

            return response()->json([
                'success' => true,
                'message' => "Invoice #{$sale->invoice_number} checkout completed successfully.",
                'sale' => [
                    'id' => $sale->id,
                    'invoice_number' => $sale->invoice_number,
                    'subtotal' => (float) $sale->subtotal,
                    'discount' => (float) $sale->discount,
                    'tax' => (float) $sale->tax,
                    'final_total' => (float) $sale->final_total,
                    'payment_type' => $sale->payment_type,
                    'amount_received' => (float) $result['amount_received'],
                    'change_due' => (float) $result['change_due'],
                    'customer_name' => $result['customer'] ? $result['customer']->name : 'Walk-in Guest',
                    'customer_tier' => $result['customer'] ? $result['customer']->membership_level : 'Standard',
                    'cashier_name' => $result['cashier']->name,
                    'created_at' => $sale->created_at->format('M j, Y h:i A'),
                    'details' => collect($result['details'])->map(function ($d) {
                        return [
                            'name' => $d->product ? $d->product->name : 'Formula',
                            'barcode' => $d->product ? $d->product->barcode : '',
                            'qty' => $d->qty,
                            'price' => (float) $d->price,
                            'discount' => (float) $d->discount,
                            'subtotal' => (float) $d->subtotal,
                        ];
                    }),
                ],
                'receipt_url' => route('pos.receipt', $sale->id),
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'An unexpected transaction error occurred: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display printable, professional thermal receipt view for a sale.
     */
    public function receipt(Sale $sale): View
    {
        $sale->load(['user', 'customer', 'details.product.category']);

        return view('pos.receipt', [
            'sale' => $sale,
        ]);
    }
}
