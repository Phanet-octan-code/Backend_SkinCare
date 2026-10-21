@extends('layouts.app')

@section('title', 'Sales History & Invoices')

@section('content')
<x-page-header 
    title="Sales History & Invoices" 
    description="Browse completed and pending sales transactions, customer receipts, and payment breakdowns."
    :breadcrumbs="['Sales History' => '']">
    <x-slot:actions>
        @if(auth()->user()->isCashier())
        <a href="{{ route('pos.index') }}" class="btn btn-skincare-primary d-inline-flex align-items-center gap-2">
            <i class="bi bi-cart3"></i>
            <span>New Sale (POS)</span>
        </a>
        @endif
    </x-slot:actions>
</x-page-header>

<!-- Filter Panel -->
<div class="card-skincare p-3 mb-4">
    <form action="{{ route('sales.index') }}" method="GET">
        <div class="row g-2 align-items-center">
            <!-- Text Search -->
            <div class="col-12 col-md-4">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-white border-end-0 text-muted">
                        <i class="bi bi-search"></i>
                    </span>
                    <input type="text" 
                           name="search" 
                           class="form-control border-start-0" 
                           placeholder="Search invoice # or customer..." 
                           value="{{ request('search') }}">
                </div>
            </div>

            <!-- Date Preset -->
            <div class="col-6 col-md-2">
                <select name="date_preset" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">All Time</option>
                    <option value="today" {{ request('date_preset') === 'today' ? 'selected' : '' }}>Today</option>
                    <option value="yesterday" {{ request('date_preset') === 'yesterday' ? 'selected' : '' }}>Yesterday</option>
                    <option value="7days" {{ request('date_preset') === '7days' ? 'selected' : '' }}>Last 7 Days</option>
                    <option value="this_month" {{ request('date_preset') === 'this_month' ? 'selected' : '' }}>This Month</option>
                </select>
            </div>

            <!-- Payment Method -->
            <div class="col-6 col-md-2">
                <select name="payment_type" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">All Payments</option>
                    <option value="cash" {{ request('payment_type') === 'cash' ? 'selected' : '' }}>Cash</option>
                    <option value="card" {{ request('payment_type') === 'card' ? 'selected' : '' }}>Card</option>
                    <option value="qr" {{ request('payment_type') === 'qr' ? 'selected' : '' }}>QR / QRIS</option>
                </select>
            </div>

            <!-- Status -->
            <div class="col-6 col-md-2">
                <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">All Statuses</option>
                    <option value="completed" {{ request('status') === 'completed' ? 'selected' : '' }}>Completed</option>
                    <option value="cancelled" {{ request('status') === 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                    <option value="returned" {{ request('status') === 'returned' ? 'selected' : '' }}>Returned</option>
                </select>
            </div>

            <!-- Cashier Filter (Manager & Admin only) -->
            @if(!$isCashier && $cashiers->isNotEmpty())
            <div class="col-6 col-md-2">
                <select name="cashier_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">All Cashiers</option>
                    @foreach($cashiers as $c)
                    <option value="{{ $c->id }}" {{ request('cashier_id') == $c->id ? 'selected' : '' }}>{{ $c->name }}</option>
                    @endforeach
                </select>
            </div>
            @endif

            <!-- Submit & Reset Buttons -->
            <div class="col-12 col-md-auto ms-auto d-flex gap-1">
                <button type="submit" class="btn btn-sm btn-skincare-primary">
                    <i class="bi bi-funnel"></i> Filter
                </button>
                @if(request()->hasAny(['search', 'date_preset', 'start_date', 'end_date', 'cashier_id', 'customer_id', 'payment_type', 'status']))
                <a href="{{ route('sales.index') }}" class="btn btn-sm btn-skincare-outline" title="Reset all filters">
                    <i class="bi bi-arrow-counterclockwise"></i> Reset
                </a>
                @endif
            </div>
        </div>
    </form>
</div>

<!-- Transaction Ledger Card -->
<x-card :noPadding="true" title="Transaction Ledger" badge="{{ $sales->total() }} sales recorded">
    <div class="table-responsive">
        <table class="table table-skincare mb-0">
            <thead>
                <tr>
                    <th class="ps-4">Invoice #</th>
                    <th>Date & Time</th>
                    <th>Customer</th>
                    <th>Cashier</th>
                    <th>Payment</th>
                    <th>Subtotal</th>
                    <th>Discount</th>
                    <th>Final Total</th>
                    <th>Status</th>
                    <th class="pe-4 text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($sales as $sale)
                <tr>
                    <td class="ps-4">
                        <span class="fw-bold font-monospace text-dark" style="font-size: 0.84rem;">{{ $sale->invoice_number }}</span>
                    </td>
                    <td class="text-muted small">
                        {{ $sale->created_at->format('M j, Y h:i A') }}
                    </td>
                    <td>
                        @if($sale->customer)
                            <div class="fw-semibold text-dark" style="font-size: 0.84rem;">{{ $sale->customer->name }}</div>
                            <span class="badge bg-secondary badge-role-pill" style="font-size: 0.62rem;">{{ $sale->customer->membership_level ?? 'Standard' }}</span>
                        @else
                            <span class="text-muted fst-italic small">Walk-in Guest</span>
                        @endif
                    </td>
                    <td class="small text-muted">
                        {{ $sale->user->name ?? 'System' }}
                    </td>
                    <td>
                        @php
                            $pt = strtolower($sale->payment_type);
                            $icon = match($pt) {
                                'cash' => 'bi-cash',
                                'card', 'credit_card', 'debit_card' => 'bi-credit-card',
                                'qr', 'qris', 'transfer' => 'bi-qr-code',
                                default => 'bi-wallet2',
                            };
                        @endphp
                        <span class="badge bg-light text-dark border d-inline-flex align-items-center gap-1" style="font-size: 0.72rem;">
                            <i class="bi {{ $icon }}"></i>
                            <span class="text-uppercase">{{ $sale->payment_type }}</span>
                        </span>
                    </td>
                    <td class="text-muted small">${{ number_format($sale->subtotal, 2) }}</td>
                    <td class="small {{ $sale->discount > 0 ? 'text-danger' : 'text-muted' }}">
                        {{ $sale->discount > 0 ? '-$' . number_format($sale->discount, 2) : '—' }}
                    </td>
                    <td>
                        <span class="fw-bold text-dark font-monospace">${{ number_format($sale->final_total, 2) }}</span>
                    </td>
                    <td>
                        <x-status-badge :status="$sale->status" />
                    </td>
                    <td class="pe-4 text-end">
                        <div class="dropdown">
                            <button class="btn btn-sm btn-skincare-outline py-1 px-2" type="button" data-bs-toggle="dropdown" aria-expanded="false" style="font-size: 0.78rem;">
                                Options <i class="bi bi-chevron-down ms-1" style="font-size: 0.65rem;"></i>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end shadow-sm" style="font-size: 0.82rem;">
                                <li>
                                    <button class="dropdown-item d-flex align-items-center gap-2" onclick="viewSaleDetails({{ $sale->id }})">
                                        <i class="bi bi-eye text-primary"></i>
                                        <span>View Invoice Items</span>
                                    </button>
                                </li>
                                <li>
                                    <a class="dropdown-item d-flex align-items-center gap-2" href="{{ route('pos.receipt', $sale->id) }}" target="_blank">
                                        <i class="bi bi-printer text-muted"></i>
                                        <span>Reprint Receipt</span>
                                    </a>
                                </li>

                                @if($sale->status === 'completed')
                                    <li><hr class="dropdown-divider"></li>
                                    <li>
                                        <button class="dropdown-item text-danger d-flex align-items-center gap-2" onclick="openCancelModal({{ $sale->id }}, '{{ $sale->invoice_number }}')">
                                            <i class="bi bi-x-circle"></i>
                                            <span>Void / Cancel Sale</span>
                                        </button>
                                    </li>
                                    @if(auth()->user()->isManager())
                                    <li>
                                        <button class="dropdown-item text-warning-emphasis d-flex align-items-center gap-2" onclick="openReturnModal({{ $sale->id }}, '{{ $sale->invoice_number }}')">
                                            <i class="bi bi-arrow-return-left"></i>
                                            <span>Refund / Return</span>
                                        </button>
                                    </li>
                                    @endif
                                @endif
                            </ul>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="10" class="p-0">
                        <x-empty-state 
                            icon="bi-receipt" 
                            title="No transaction records found" 
                            description="Invoices matching your search and filter criteria will appear here." />
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($sales->hasPages())
    <x-slot:footer>
        {{ $sales->links() }}
    </x-slot:footer>
    @endif
</x-card>

<!-- ── SALE DETAILS MODAL ── -->
<div class="modal fade" id="saleDetailsModal" tabindex="-1" aria-labelledby="saleDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-bottom py-3">
                <div class="d-flex align-items-center gap-2">
                    <span class="avatar-circle" style="width: 32px; height: 32px; font-size: 0.9rem;">
                        <i class="bi bi-receipt"></i>
                    </span>
                    <div>
                        <h6 class="modal-title fw-bold text-dark mb-0" id="detailInvoiceNumber">Invoice #</h6>
                        <small class="text-muted" id="detailMetaHeader">Transaction Audit</small>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body p-4">
                <div class="row g-3 mb-3">
                    <div class="col-6 col-md-3">
                        <small class="text-muted d-block" style="font-size: 0.72rem;">Customer</small>
                        <span class="fw-semibold text-dark" id="detailCustomerName">Guest</span>
                        <span class="badge bg-secondary badge-role-pill ms-1" id="detailCustomerTier">Standard</span>
                    </div>
                    <div class="col-6 col-md-3">
                        <small class="text-muted d-block" style="font-size: 0.72rem;">Cashier</small>
                        <span class="fw-semibold text-dark" id="detailCashierName">Staff</span>
                    </div>
                    <div class="col-6 col-md-3">
                        <small class="text-muted d-block" style="font-size: 0.72rem;">Payment Method</small>
                        <span class="badge bg-light text-dark border text-uppercase" id="detailPaymentType">CASH</span>
                    </div>
                    <div class="col-6 col-md-3">
                        <small class="text-muted d-block" style="font-size: 0.72rem;">Transaction Status</small>
                        <span id="detailStatusBadge"></span>
                    </div>
                </div>

                <!-- Line Items Table -->
                <div class="table-responsive border rounded mb-3">
                    <table class="table table-sm table-hover mb-0" style="font-size: 0.84rem;">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">Skincare Formula</th>
                                <th>Category</th>
                                <th class="text-center">Qty</th>
                                <th>Unit Price</th>
                                <th>Discount</th>
                                <th class="pe-3 text-end">Line Total</th>
                            </tr>
                        </thead>
                        <tbody id="detailItemsTbody">
                            <!-- Populated dynamically via JS -->
                        </tbody>
                    </table>
                </div>

                <!-- Financial Breakdown -->
                <div class="row justify-content-end">
                    <div class="col-12 col-md-5">
                        <div class="d-flex justify-content-between small text-muted mb-1">
                            <span>Subtotal:</span>
                            <span class="font-monospace fw-semibold text-dark" id="detailSubtotal">$0.00</span>
                        </div>
                        <div class="d-flex justify-content-between small text-danger mb-1" id="detailDiscountRow">
                            <span>Discount:</span>
                            <span class="font-monospace fw-semibold" id="detailDiscount">-$0.00</span>
                        </div>
                        <div class="d-flex justify-content-between small text-muted mb-1">
                            <span>Tax (5%):</span>
                            <span class="font-monospace fw-semibold text-dark" id="detailTax">$0.00</span>
                        </div>
                        <div class="d-flex justify-content-between fw-bold text-dark fs-6 pt-2 border-top">
                            <span>Final Total:</span>
                            <span class="font-monospace fs-5 text-dark" id="detailFinalTotal">$0.00</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal-footer border-top py-2 bg-light">
                <button type="button" class="btn btn-sm btn-skincare-outline" data-bs-dismiss="modal">Close</button>
                <a href="#" target="_blank" id="detailPrintBtn" class="btn btn-sm btn-skincare-primary">
                    <i class="bi bi-printer me-1"></i> Print Thermal Receipt
                </a>
            </div>
        </div>
    </div>
</div>

<!-- ── CANCEL SALE MODAL ── -->
<div class="modal fade" id="cancelSaleModal" tabindex="-1" aria-labelledby="cancelSaleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-bottom py-3">
                <h6 class="modal-title fw-bold text-danger mb-0">
                    <i class="bi bi-exclamation-triangle me-1"></i>Void Sale Transaction
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="cancelSaleForm" method="POST">
                @csrf
                <div class="modal-body py-3">
                    <p class="small text-muted mb-2">
                        Are you sure you want to void <strong id="cancelModalInvoice">INV</strong>?
                        All purchased items will be <strong>restocked into inventory</strong> automatically.
                    </p>
                    <div class="mb-2">
                        <label class="form-label small fw-semibold text-dark mb-1">Cancellation Reason *</label>
                        <textarea name="reason" class="form-control form-control-sm" rows="3" required placeholder="e.g. Customer changed mind, accidental duplicate entry..."></textarea>
                    </div>
                </div>
                <div class="modal-footer border-top py-2 bg-light">
                    <button type="button" class="btn btn-sm btn-skincare-outline" data-bs-dismiss="modal">Keep Sale</button>
                    <button type="submit" class="btn btn-sm btn-outline-danger">Confirm Void</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ── PROCESS RETURN MODAL ── -->
<div class="modal fade" id="returnSaleModal" tabindex="-1" aria-labelledby="returnSaleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-bottom py-3">
                <h6 class="modal-title fw-bold text-warning-emphasis mb-0">
                    <i class="bi bi-arrow-return-left me-1"></i>Refund & Restock
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="returnSaleForm" method="POST">
                @csrf
                <div class="modal-body py-3">
                    <p class="small text-muted mb-2">
                        Process return for <strong id="returnModalInvoice">INV</strong>. Units will be restocked to inventory.
                    </p>
                    <div class="mb-2">
                        <label class="form-label small fw-semibold text-dark mb-1">Return Reason *</label>
                        <textarea name="reason" class="form-control form-control-sm" rows="3" required placeholder="e.g. Customer returned unopened formula within 14 days..."></textarea>
                    </div>
                </div>
                <div class="modal-footer border-top py-2 bg-light">
                    <button type="button" class="btn btn-sm btn-skincare-outline" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-outline-warning">Process Return</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    let saleDetailsModalInstance = null;
    let cancelSaleModalInstance = null;
    let returnSaleModalInstance = null;

    document.addEventListener('DOMContentLoaded', function () {
        saleDetailsModalInstance = new bootstrap.Modal(document.getElementById('saleDetailsModal'));
        cancelSaleModalInstance = new bootstrap.Modal(document.getElementById('cancelSaleModal'));
        returnSaleModalInstance = new bootstrap.Modal(document.getElementById('returnSaleModal'));
    });

    async function viewSaleDetails(saleId) {
        try {
            const response = await fetch(`/sales/${saleId}`, {
                headers: { 'Accept': 'application/json' }
            });
            const result = await response.json();
            if (!result.success) throw new Error('Failed to load transaction data');

            const sale = result.sale;

            document.getElementById('detailInvoiceNumber').innerText = sale.invoice_number;
            document.getElementById('detailMetaHeader').innerText = `${sale.created_at} &bull; Main Counter`;
            document.getElementById('detailCustomerName').innerText = sale.customer_name;
            document.getElementById('detailCustomerTier').innerText = sale.customer_tier;
            document.getElementById('detailCashierName').innerText = sale.cashier_name;
            document.getElementById('detailPaymentType').innerText = sale.payment_type;

            const statusBadge = document.getElementById('detailStatusBadge');
            const statusClass = (sale.status === 'completed') ? 'bg-success-subtle text-success border border-success-subtle' :
                               (sale.status === 'cancelled' ? 'bg-danger-subtle text-danger border border-danger-subtle' : 'bg-warning-subtle text-warning-emphasis border border-warning-subtle');
            statusBadge.className = `badge ${statusClass} text-uppercase`;
            statusBadge.innerText = sale.status;

            // Render line items
            const tbody = document.getElementById('detailItemsTbody');
            tbody.innerHTML = sale.details.map(d => `
                <tr>
                    <td class="ps-3">
                        <div class="fw-semibold text-dark">${d.name}</div>
                        <div class="text-muted font-monospace" style="font-size: 0.72rem;">${d.barcode || 'N/A'}</div>
                    </td>
                    <td><span class="badge bg-light text-muted border" style="font-size: 0.68rem;">${d.category || 'Skincare'}</span></td>
                    <td class="text-center font-monospace fw-bold">${d.qty}</td>
                    <td class="font-monospace">$${d.price.toFixed(2)}</td>
                    <td class="font-monospace text-danger">${d.discount > 0 ? '-$' + d.discount.toFixed(2) : '—'}</td>
                    <td class="pe-3 text-end font-monospace fw-bold">$${d.subtotal.toFixed(2)}</td>
                </tr>
            `).join('');

            document.getElementById('detailSubtotal').innerText = `$${sale.subtotal.toFixed(2)}`;
            document.getElementById('detailDiscount').innerText = `-$${sale.discount.toFixed(2)}`;
            document.getElementById('detailDiscountRow').style.display = sale.discount > 0 ? 'flex' : 'none';
            document.getElementById('detailTax').innerText = `$${sale.tax.toFixed(2)}`;
            document.getElementById('detailFinalTotal').innerText = `$${sale.final_total.toFixed(2)}`;

            document.getElementById('detailPrintBtn').setAttribute('href', sale.receipt_url);

            saleDetailsModalInstance.show();
        } catch (err) {
            alert('Error loading sale details: ' + err.message);
        }
    }

    function openCancelModal(saleId, invoiceNumber) {
        document.getElementById('cancelModalInvoice').innerText = invoiceNumber;
        document.getElementById('cancelSaleForm').setAttribute('action', `/sales/${saleId}/cancel`);
        cancelSaleModalInstance.show();
    }

    function openReturnModal(saleId, invoiceNumber) {
        document.getElementById('returnModalInvoice').innerText = invoiceNumber;
        document.getElementById('returnSaleForm').setAttribute('action', `/sales/${saleId}/return`);
        returnSaleModalInstance.show();
    }
</script>
@endpush
