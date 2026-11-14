@extends('layouts.app')

@section('title', 'Invoice #' . $sale->invoice_number)

@section('content')
<x-page-header 
    title="Invoice #{{ $sale->invoice_number }}" 
    description="Transaction audit details, customer loyalty tier, and itemized breakdown."
    :breadcrumbs="['Sales History' => route('sales.index'), 'Invoice #' . $sale->invoice_number => '']">
    <x-slot:actions>
        @if($sale->final_total > $sale->refunded_amount && !in_array($sale->status, ['cancelled', 'returned']))
            <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#refundModal">
                <i class="bi bi-arrow-counterclockwise me-1"></i> Issue Refund
            </button>
        @endif
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
                                @if($d->bundle)
                                    <div class="fw-bold text-dark"><i class="bi bi-gift text-primary me-1"></i> {{ $d->bundle->name }}</div>
                                    <div class="text-muted small">Promotional Bundle / Gift Set</div>
                                @else
                                    <div class="fw-bold text-dark">{{ $d->product->name ?? 'Custom Product' }}</div>
                                    <div class="text-muted font-monospace small" style="font-size: 0.72rem;">{{ $d->product->barcode ?? 'N/A' }}</div>
                                @endif
                            </td>
                            <td>
                                @if($d->bundle)
                                    <span class="badge bg-info-subtle text-info border" style="font-size: 0.72rem;">Curated Bundle</span>
                                @else
                                    <span class="badge bg-light text-muted border" style="font-size: 0.72rem;">{{ $d->product->category->name ?? 'Skincare' }}</span>
                                @endif
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
                @if($sale->refunded_amount > 0)
                <div class="d-flex justify-content-between small text-danger fw-semibold pt-1">
                    <span>Total Refunded:</span>
                    <span class="font-monospace">-${{ number_format($sale->refunded_amount, 2) }}</span>
                </div>
                @endif
            </div>

            @if($sale->applied_referral_code || $sale->loyalty_points_redeemed > 0 || $sale->loyalty_points_earned > 0)
            <div class="border-top pt-3 mt-3 small text-muted">
                <div class="fw-bold text-dark mb-1">Promotions & Loyalty Ledger</div>
                @if($sale->applied_referral_code)
                    <div>Referral Code: <span class="badge bg-light text-dark border">{{ $sale->applied_referral_code }}</span></div>
                @endif
                @if($sale->loyalty_points_redeemed > 0)
                    <div>Points Redeemed: <span class="text-danger fw-semibold">-{{ $sale->loyalty_points_redeemed }} pts (-${{ number_format($sale->loyalty_points_discount, 2) }})</span></div>
                @endif
                @if($sale->loyalty_points_earned > 0)
                    <div>Points Earned: <span class="text-success fw-semibold">+{{ $sale->loyalty_points_earned }} pts</span></div>
                @endif
            </div>
            @endif
        </x-card>
    </div>
</div>

<!-- Refund Modal -->
<div class="modal fade" id="refundModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form action="{{ route('sales.refund', $sale) }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title fs-6 fw-bold text-dark">Process Refund for #{{ $sale->invoice_number }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    @php
                        $remainingRefundable = round((float) $sale->final_total - (float) $sale->refunded_amount, 2);
                    @endphp
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Refund Amount ($) <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" name="refund_amount" class="form-control" max="{{ $remainingRefundable }}" value="{{ $remainingRefundable }}" required>
                        <div class="form-text small">Maximum refundable balance: ${{ number_format($remainingRefundable, 2) }}</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Reason for Refund <span class="text-danger">*</span></label>
                        <textarea name="refund_reason" class="form-control" rows="2" placeholder="e.g. Customer return of unopened product / skincare allergy" required></textarea>
                    </div>
                    <div class="form-check mb-2">
                        <input type="checkbox" name="restock_inventory" value="1" class="form-check-input" id="restockCheck" checked>
                        <label class="form-check-label small" for="restockCheck">
                            Restock inventory automatically (including constituent products for bundles)
                        </label>
                    </div>
                    <div class="alert alert-light border small text-muted mb-0">
                        <i class="bi bi-info-circle text-primary me-1"></i>
                        Proportional loyalty points earned on this sale will be safely reversed.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-danger px-3">Execute Refund</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
