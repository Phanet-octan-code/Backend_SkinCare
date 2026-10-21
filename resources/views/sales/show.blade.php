@extends('layouts.app')

@section('title', 'Invoice #' . $sale->invoice_number)

@section('content')
<x-page-header 
    title="Invoice #{{ $sale->invoice_number }}" 
    description="Transaction audit details, customer loyalty tier, and itemized breakdown."
    :breadcrumbs="['Sales History' => route('sales.index'), 'Invoice #' . $sale->invoice_number => '']">
    <x-slot:actions>
        <a href="{{ route('pos.receipt', $sale->id) }}" target="_blank" class="btn btn-sm btn-skincare-primary">
            <i class="bi bi-printer me-1"></i> Print Receipt
        </a>
        <a href="{{ route('sales.index') }}" class="btn btn-sm btn-skincare-outline">
            &larr; Back to Sales History
        </a>
    </x-slot:actions>
</x-page-header>

<div class="row g-4">
    <div class="col-12 col-lg-8">
        <x-card title="Purchased Items" badge="{{ $sale->details->count() }} items">
            <div class="table-responsive">
                <table class="table table-skincare mb-0">
                    <thead>
                        <tr>
                            <th class="ps-3">Skincare Formula</th>
                            <th>Category</th>
                            <th class="text-center">Qty</th>
                            <th>Unit Price</th>
                            <th>Discount</th>
                            <th class="pe-3 text-end">Line Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($sale->details as $d)
                        <tr>
                            <td class="ps-3">
                                <div class="fw-bold text-dark">{{ $d->product->name ?? 'Custom Product' }}</div>
                                <div class="text-muted font-monospace small" style="font-size: 0.72rem;">{{ $d->product->barcode ?? 'N/A' }}</div>
                            </td>
                            <td>
                                <span class="badge bg-light text-muted border" style="font-size: 0.72rem;">{{ $d->product->category->name ?? 'Skincare' }}</span>
                            </td>
                            <td class="text-center fw-bold font-monospace">{{ $d->qty }}</td>
                            <td class="font-monospace">${{ number_format($d->price, 2) }}</td>
                            <td class="font-monospace text-danger">{{ $d->discount > 0 ? '-$' . number_format($d->discount, 2) : '—' }}</td>
                            <td class="pe-3 text-end fw-bold font-monospace">${{ number_format($d->subtotal, 2) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-card>
    </div>

    <div class="col-12 col-lg-4">
        <x-card title="Transaction Summary">
            <div class="d-flex justify-content-between mb-2">
                <span class="text-muted small">Date & Time:</span>
                <span class="small fw-semibold">{{ $sale->created_at->format('M j, Y h:i A') }}</span>
            </div>
            <div class="d-flex justify-content-between mb-2">
                <span class="text-muted small">Cashier:</span>
                <span class="small fw-semibold">{{ $sale->user->name ?? 'System' }}</span>
            </div>
            <div class="d-flex justify-content-between mb-2">
                <span class="text-muted small">Customer:</span>
                <span class="small fw-semibold">
                    {{ $sale->customer ? $sale->customer->name : 'Walk-in Guest' }}
                    @if($sale->customer)
                        <span class="badge bg-secondary badge-role-pill ms-1" style="font-size: 0.62rem;">{{ $sale->customer->membership_level }}</span>
                    @endif
                </span>
            </div>
            <div class="d-flex justify-content-between mb-2">
                <span class="text-muted small">Payment Method:</span>
                <span class="badge bg-light text-dark border text-uppercase" style="font-size: 0.72rem;">{{ $sale->payment_type }}</span>
            </div>
            <div class="d-flex justify-content-between mb-2">
                <span class="text-muted small">Status:</span>
                <x-status-badge :status="$sale->status" />
            </div>

            <div class="border-top pt-3 mt-3">
                <div class="d-flex justify-content-between small text-muted mb-1">
                    <span>Subtotal:</span>
                    <span class="font-monospace">${{ number_format($sale->subtotal, 2) }}</span>
                </div>
                @if($sale->discount > 0)
                <div class="d-flex justify-content-between small text-danger mb-1">
                    <span>Discount:</span>
                    <span class="font-monospace">-${{ number_format($sale->discount, 2) }}</span>
                </div>
                @endif
                <div class="d-flex justify-content-between small text-muted mb-2">
                    <span>Tax (5%):</span>
                    <span class="font-monospace">${{ number_format($sale->tax, 2) }}</span>
                </div>
                <div class="d-flex justify-content-between fw-bold text-dark fs-5 pt-2 border-top">
                    <span>Final Total:</span>
                    <span class="font-monospace">${{ number_format($sale->final_total, 2) }}</span>
                </div>
            </div>
        </x-card>
    </div>
</div>
@endsection
