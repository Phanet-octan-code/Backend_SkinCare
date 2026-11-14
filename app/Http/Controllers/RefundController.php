<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Services\RefundService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class RefundController extends Controller
{
    public function __construct(
        protected RefundService $refundService
    ) {}

    public function store(Request $request, Sale $sale): RedirectResponse
    {
        $validated = $request->validate([
            'refund_amount' => 'required|numeric|min:0.01',
            'refund_reason' => 'required|string|max:500',
            'restock_inventory' => 'nullable|boolean',
        ]);

        $restock = (bool) ($request->input('restock_inventory', true));

        try {
            $this->refundService->processRefund(
                sale: $sale,
                refundAmount: (float) $validated['refund_amount'],
                reason: $validated['refund_reason'],
                restockInventory: $restock,
                actor: auth()->user()
            );

            return back()->with('success', "Refund of \${$validated['refund_amount']} processed successfully.");
        } catch (\Throwable $e) {
            return back()->with('error', 'Refund failed: ' . $e->getMessage());
        }
    }
}

