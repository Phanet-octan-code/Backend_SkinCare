@extends('layouts.app')

@section('title', 'POS Terminal')

@push('styles')
<style id="pos-terminal-styles">
    /* POS Terminal Workspace Layout */
    .pos-container {
        display: flex;
        flex-direction: column;
        height: calc(100vh - var(--topbar-height) - 48px);
        min-height: 680px;
    }
    @media (max-width: 991.98px) {
        .pos-container {
            height: auto;
            min-height: auto;
        }
    }
    .pos-catalog-panel {
        display: flex;
        flex-direction: column;
        height: 100%;
        min-height: 0;
        overflow: hidden;
    }
    .pos-product-scroll {
        overflow-y: auto;
        padding-right: 4px;
        flex: 1;
        min-height: 0;
    }
    .pos-product-scroll::-webkit-scrollbar {
        width: 5px;
    }
    .pos-product-scroll::-webkit-scrollbar-thumb {
        background-color: #CBD3C8;
        border-radius: 4px;
    }
    .pos-cart-panel {
        background: #FFFFFF;
        border: 1px solid var(--border-color);
        border-radius: var(--radius-md);
        display: flex;
        flex-direction: column;
        height: 100%;
        min-height: 0;
        box-shadow: 0 4px 16px rgba(37, 43, 37, 0.04);
    }
    .pos-cart-scroll {
        flex: 1;
        min-height: 0;
        overflow-y: auto;
        padding: 12px 16px;
    }
    .pos-cart-scroll::-webkit-scrollbar {
        width: 5px;
    }
    .pos-cart-scroll::-webkit-scrollbar-thumb {
        background-color: #CBD3C8;
        border-radius: 4px;
    }
    .pos-product-card {
        background: #FFFFFF;
        border: 1px solid var(--border-color);
        border-radius: var(--radius-md);
        padding: 12px;
        cursor: pointer;
        transition: transform 0.15s cubic-bezier(0.4, 0, 0.2, 1), box-shadow 0.15s ease, border-color 0.15s ease;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        height: 100%;
        position: relative;
        user-select: none;
    }
    .pos-product-card:hover:not(.out-of-stock) {
        border-color: var(--primary);
        box-shadow: 0 6px 18px rgba(113, 133, 107, 0.18);
        transform: translateY(-2px);
    }
    .pos-product-card:active:not(.out-of-stock) {
        transform: scale(0.98);
    }
    .pos-product-card.out-of-stock {
        opacity: 0.55;
        cursor: not-allowed;
        background-color: #FAFAFA;
        filter: grayscale(0.2);
    }
    .pos-thumb-box {
        width: 100%;
        height: 110px;
        border-radius: 8px;
        overflow: hidden;
        background-color: #F8F9F7;
        border: 1px solid #ECEEEA;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 10px;
        position: relative;
    }
    .pos-thumb-img {
        width: 100%;
        height: 100%;
        object-fit: contain;
        padding: 6px;
        transition: transform 0.25s ease;
    }
    .pos-product-card:hover:not(.out-of-stock) .pos-thumb-img {
        transform: scale(1.08);
    }
    .pos-card-add-overlay {
        position: absolute;
        bottom: 6px;
        right: 6px;
        opacity: 0;
        transform: scale(0.85);
        transition: all 0.18s ease;
        z-index: 2;
    }
    .pos-product-card:hover:not(.out-of-stock) .pos-card-add-overlay {
        opacity: 1;
        transform: scale(1);
    }
    .pos-cat-pill {
        font-size: 0.76rem;
        padding: 6px 14px;
        border-radius: 20px;
        font-weight: 600;
        cursor: pointer;
        border: 1px solid var(--border-color);
        background-color: #FFFFFF;
        color: var(--text-muted);
        white-space: nowrap;
        transition: all 0.15s ease;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    .pos-cat-pill:hover {
        border-color: var(--primary);
        color: var(--primary);
        background-color: var(--primary-subtle);
    }
    .pos-cat-pill.active {
        background-color: var(--primary) !important;
        border-color: var(--primary) !important;
        color: #FFFFFF !important;
        box-shadow: 0 2px 8px rgba(113, 133, 107, 0.32);
    }
    .pos-cat-nav-btn {
        background-color: #FFFFFF;
        color: var(--text-dark);
        border: 1px solid var(--border-color);
        transition: all 0.2s ease;
        cursor: pointer;
    }
    .pos-cat-nav-btn:hover {
        background-color: var(--primary);
        color: #FFFFFF;
        border-color: var(--primary);
    }
    .cart-item-row {
        background: #FAFCFA;
        border: 1px solid var(--border-color);
        border-radius: 8px;
        padding: 10px 12px;
        margin-bottom: 8px;
        transition: border-color 0.12s ease, background 0.12s ease;
    }
    .cart-item-row:hover {
        border-color: #CBD3C8;
        background: #FFFFFF;
    }
    .qty-btn {
        width: 26px;
        height: 26px;
        border-radius: 6px;
        border: 1px solid var(--border-color);
        background: #FFFFFF;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 0.8rem;
        cursor: pointer;
        transition: all 0.12s ease;
    }
    .qty-btn:hover {
        background: var(--primary-subtle);
        border-color: var(--primary);
        color: var(--primary-hover);
    }
    .pay-btn-tile {
        border: 2px solid var(--border-color);
        border-radius: 10px;
        padding: 12px 8px;
        text-align: center;
        cursor: pointer;
        background: #FFFFFF;
        transition: all 0.15s ease;
    }
    .pay-btn-tile:hover {
        border-color: #CBD3C8;
        background: #FAFCFA;
    }
    .pay-btn-tile.active {
        border-color: var(--primary);
        background-color: var(--primary-subtle);
        color: var(--primary-hover);
    }
    .quick-cash-chip {
        font-size: 0.8rem;
        padding: 6px 12px;
        border-radius: 6px;
        border: 1px solid var(--border-color);
        background: #FFFFFF;
        cursor: pointer;
        font-weight: 600;
        transition: all 0.12s ease;
    }
    .quick-cash-chip:hover {
        background: #F0F3ED;
        border-color: var(--primary);
        color: var(--primary-hover);
    }
    .pos-cat-pill-scroll {
        scrollbar-width: none;
        -webkit-overflow-scrolling: touch;
    }
    .pos-cat-pill-scroll::-webkit-scrollbar {
        display: none;
    }
    @media (max-width: 575.98px) {
        .pos-product-card {
            padding: 8px;
        }
        .pos-thumb-box {
            height: 85px;
            margin-bottom: 6px;
        }
    }
</style>
@endpush

@section('content')
<!-- Top POS Station Header -->
<div class="d-flex flex-column flex-md-row align-items-start align-items-md-center justify-content-between gap-2 mb-3">
    <div class="d-flex align-items-center gap-3">
        <h4 class="fw-bold mb-0 text-dark" style="letter-spacing: -0.4px;">
            <i class="bi bi-cart3 text-primary me-2"></i>{{ __('messages.pos_terminal') }}
        </h4>
        <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1 small d-inline-flex align-items-center gap-1">
            <span class="spinner-grow spinner-grow-sm text-success" style="width: 7px; height: 7px;" role="status"></span>
            <span>{{ __('messages.pos_register_ready') }}</span>
        </span>
    </div>

    <div class="d-flex align-items-center gap-2">
        <span class="text-muted small">
            {{ __('messages.cashier') }}: <strong>{{ auth()->user()->name }}</strong>
        </span>
        <a href="{{ route('sales.index') }}" class="btn btn-sm btn-skincare-outline py-1 px-2" style="font-size: 0.8rem;">
            <i class="bi bi-receipt me-1"></i> {{ __('messages.sales_ledger') }}
        </a>
        <a href="{{ route('dashboard') }}" class="btn btn-sm btn-skincare-outline py-1 px-2" style="font-size: 0.8rem;">
            <i class="bi bi-speedometer2 me-1"></i> {{ __('messages.dashboard') }}
        </a>
    </div>
</div>

<div class="pos-container">
    <div class="row g-3 h-100">
        <!-- ── LEFT PANEL: Catalog, Search, Barcode Scanner, & Category Tabs (col-xl-8) ── -->
        <div class="col-12 col-lg-7 col-xl-8 h-100">
            <div class="pos-catalog-panel">
                <!-- Barcode & Search Controls -->
                <div class="card-skincare p-3 mb-2 flex-shrink-0">
                    <div class="row g-2">
                        <div class="col-12 col-md-8">
                            <div class="input-group">
                                <span class="input-group-text bg-white border-end-0 text-muted">
                                    <i class="bi bi-upc-scan fs-6 text-primary"></i>
                                </span>
                                <input type="text" 
                                       id="barcodeScannerInput" 
                                       class="form-control border-start-0" 
                                       placeholder="{{ __('messages.scan_barcode_placeholder') }}" 
                                       autocomplete="off" 
                                       autofocus>
                                <button type="button" class="btn btn-skincare-outline" id="clearSearchBtn" title="Clear search">
                                    <i class="bi bi-x-lg"></i>
                                </button>
                            </div>
                        </div>
                        <div class="col-12 col-md-4 text-end">
                            <span class="badge bg-light text-muted border py-2 px-3 w-100 text-truncate" id="productCountBadge" style="font-size: 0.78rem;">
                                {{ __('messages.showing_formulas', ['count' => $products->count()]) }}
                            </span>
                        </div>
                    </div>

                    <!-- Category Pills Filter with Forward/Back Scroll Controls -->
                    <div class="d-flex align-items-center gap-1 pt-2 mt-2 border-top position-relative">
                        <button type="button" class="btn btn-sm btn-light border rounded-circle pos-cat-nav-btn d-flex align-items-center justify-content-center p-0 flex-shrink-0" id="posCatScrollLeft" style="width: 28px; height: 28px;" title="Scroll Categories Backward">
                            <i class="bi bi-chevron-left" style="font-size: 0.72rem;"></i>
                        </button>
                        <div class="d-flex align-items-center gap-1 overflow-x-auto pos-cat-pill-scroll flex-grow-1" id="posCatPillContainer" style="scrollbar-width: none; -ms-overflow-style: none;">
                            <button type="button" class="pos-cat-pill active" data-category="all">{{ __('messages.all_items') }}</button>
                            @foreach($categories as $cat)
                            <button type="button" class="pos-cat-pill d-inline-flex align-items-center gap-1" data-category="{{ $cat->id }}">
                                @if($cat->image)
                                    <img src="{{ asset($cat->image) }}" alt="{{ $cat->name }}" class="rounded-circle" style="width: 16px; height: 16px; object-fit: cover;" onerror="this.remove()">
                                @endif
                                <span>{{ $cat->localized_name }}</span>
                            </button>
                            @endforeach
                        </div>
                        <button type="button" class="btn btn-sm btn-light border rounded-circle pos-cat-nav-btn d-flex align-items-center justify-content-center p-0 flex-shrink-0" id="posCatScrollRight" style="width: 28px; height: 28px;" title="Scroll Categories Forward">
                            <i class="bi bi-chevron-right" style="font-size: 0.72rem;"></i>
                        </button>
                    </div>
                </div>

                <!-- Product Catalog Grid Scroll Area -->
                <div class="pos-product-scroll">
                    <div class="row g-2" id="posProductGrid">
                        @forelse($catalogPayload as $p)
                        @php
                            $isOutOfStock = $p['qty'] <= 0;
                            $stockBadgeClass = $isOutOfStock 
                                ? 'bg-danger-subtle text-danger border border-danger-subtle' 
                                : ($p['is_low_stock'] ? 'bg-warning-subtle text-warning-emphasis border border-warning-subtle' : 'bg-success-subtle text-success border border-success-subtle');
                            $stockText = $isOutOfStock ? __('messages.out_of_stock') : $p['qty'] . ' ' . __('messages.units');
                            $imgRaw = $p['image'] ?? null;
                            if ($imgRaw) {
                                $imgUrl = (str_starts_with($imgRaw, 'http://') || str_starts_with($imgRaw, 'https://')) ? $imgRaw : asset($imgRaw);
                            } else {
                                $imgUrl = asset('images/products/skincare_bottle.jpg');
                            }
                        @endphp
                        <div class="col-6 col-sm-4 col-md-4 col-xl-3 pos-grid-card-wrapper" data-category="{{ $p['category_id'] }}" data-product-id="{{ $p['id'] }}">
                            <div class="pos-product-card {{ $isOutOfStock ? 'out-of-stock' : '' }}" 
                                 onclick="window.posAddToCart ? window.posAddToCart({{ $p['id'] }}) : null"
                                 title="{{ $isOutOfStock ? __('messages.out_of_stock') : __('messages.add_to_ticket') . ' ' . $p['name'] }}"
                                 role="button"
                                 tabindex="0">
                                @if(!empty($p['promo_text']))
                                <span class="badge bg-danger position-absolute top-0 end-0 m-2 shadow-sm" style="font-size: 0.65rem; z-index: 2;">{{ $p['promo_text'] }}</span>
                                @endif
                                <div>
                                    <div class="pos-thumb-box position-relative">
                                        <img src="{{ $imgUrl }}" 
                                             alt="{{ $p['name'] }}" 
                                             class="pos-thumb-img" 
                                             loading="lazy"
                                             onerror="this.onerror=null;this.src='/images/products/skincare_bottle.jpg';">
                                        @if(!$isOutOfStock)
                                        <div class="pos-card-add-overlay">
                                            <span class="btn btn-sm btn-light rounded-circle shadow-sm p-1 d-inline-flex align-items-center justify-content-center" style="width: 28px; height: 28px;">
                                                <i class="bi bi-plus text-primary fw-bold fs-6"></i>
                                            </span>
                                        </div>
                                        @endif
                                    </div>
                                    <span class="badge bg-light text-muted border mb-1" style="font-size: 0.65rem;">{{ $p['category_name'] ?? __('messages.general') }}</span>
                                    <div class="fw-bold text-dark text-truncate" style="font-size: 0.83rem;" title="{{ $p['name'] }}">{{ $p['name'] }}</div>
                                    <div class="text-muted font-monospace" style="font-size: 0.7rem;">
                                        <i class="bi bi-upc me-1 text-muted" style="font-size: 0.65rem;"></i>{{ $p['barcode'] ?? 'N/A' }}
                                    </div>
                                </div>

                                <div class="d-flex align-items-center justify-content-between mt-2 pt-2 border-top">
                                    <span class="fw-bold text-dark fs-6 font-monospace">${{ number_format($p['price'], 2) }}</span>
                                    <span class="badge {{ $stockBadgeClass }}" style="font-size: 0.68rem;">{{ $stockText }}</span>
                                </div>
                            </div>
                        </div>
                        @empty
                        <div class="col-12 p-5 text-center text-muted">
                            <i class="bi bi-box-seam fs-1 mb-2 d-block"></i>
                            <h6 class="fw-bold text-dark">{{ __('messages.no_formulas_available') }}</h6>
                            <p class="text-muted small">{{ __('messages.verify_catalog_inventory') }}</p>
                        </div>
                        @endforelse
                    </div>
                    <div id="noProductsFound" class="d-none p-5 text-center">
                        <div class="mb-3">
                            <i class="bi bi-search text-muted fs-1 d-inline-block p-3 rounded-circle" style="background-color: var(--primary-subtle); color: var(--primary) !important;"></i>
                        </div>
                        <h6 class="fw-bold text-dark">{{ __('messages.no_matching_formulas') }}</h6>
                        <p class="text-muted small mb-3">{{ __('messages.try_adjusting_search') }}</p>
                        <button type="button" class="btn btn-sm btn-skincare-primary px-3" onclick="window.posResetFilters ? window.posResetFilters() : null">
                            <i class="bi bi-arrow-clockwise me-1"></i> {{ __('messages.reset_filters') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── RIGHT PANEL: Checkout Cart Register Ticket (col-xl-4) ── -->
        <div class="col-12 col-lg-5 col-xl-4 h-100">
            <div class="pos-cart-panel">
                <!-- Cart Header & Customer Selection -->
                <div class="p-3 border-bottom flex-shrink-0">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <div class="d-flex align-items-center gap-2">
                            <h6 class="fw-bold text-dark mb-0 fs-6">{{ __('messages.current_ticket') }}</h6>
                            <span class="badge bg-primary text-white rounded-pill px-2" id="ticketCountBadge">0 {{ __('messages.selected') }}</span>
                        </div>
                        <button type="button" class="btn btn-xs btn-outline-danger py-0 px-2" id="clearCartBtn" style="font-size: 0.74rem;">
                            <i class="bi bi-trash3 me-1"></i> {{ __('messages.clear_cart') }}
                        </button>
                    </div>

                    <!-- Customer Lookup / Guest Selector -->
                    <div class="p-2 border rounded-3 bg-light">
                        <div class="d-flex align-items-center justify-content-between mb-1">
                            <span class="text-muted small fw-semibold" style="font-size: 0.72rem;">{{ __('messages.customer_loyalty') }}</span>
                            <button type="button" class="btn btn-link p-0 text-decoration-none small text-primary fw-semibold" data-bs-toggle="modal" data-bs-target="#newCustomerModal" style="font-size: 0.72rem;">
                                {{ __('messages.new_client') }}
                            </button>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <select id="customerSelect" class="form-select form-select-sm">
                                <option value="" data-tier="Guest" data-discount="0">{{ __('messages.walk_in_guest') }}</option>
                                @foreach($customers as $c)
                                <option value="{{ $c->id }}" data-tier="{{ $c->membership_level ?? 'Standard' }}" data-discount="{{ $c->membership_discount_percentage }}">
                                    {{ $c->name }} ({{ $c->membership_level }} — {{ (int) $c->membership_discount_percentage }}% off)
                                </option>
                                @endforeach
                            </select>
                        </div>
                        <div id="customerTierBadgeContainer" class="d-flex align-items-center justify-content-between mt-1 pt-1 border-top" style="display: none !important;">
                            <span class="text-muted" style="font-size: 0.72rem;">{{ __('messages.vip_benefit') }}:</span>
                            <span class="badge bg-success-subtle text-success border border-success-subtle" id="customerBenefitBadge" style="font-size: 0.68rem;">15% Off Total</span>
                        </div>
                    </div>
                </div>

                <!-- Cart Line Items Scroll Area -->
                <div class="pos-cart-scroll" id="cartItemsContainer">
                    <!-- Populated dynamically via JS -->
                    <div class="text-center py-5 text-muted" id="emptyCartPlaceholder">
                        <i class="bi bi-cart-x fs-1 d-block mb-2 text-muted" style="opacity: 0.5;"></i>
                        <div class="fw-semibold text-dark small">{{ __('messages.ticket_empty') }}</div>
                        <small class="text-muted">{{ __('messages.ticket_empty_hint') }}</small>
                    </div>
                </div>

                <!-- Cart Financial Summary & Checkout Trigger -->
                <div class="p-3 border-top bg-light flex-shrink-0" style="border-radius: 0 0 var(--radius-md) var(--radius-md);">
                    <div class="d-flex justify-content-between small text-muted mb-1">
                        <span>{{ __('messages.subtotal') }}:</span>
                        <span class="font-monospace fw-semibold text-dark" id="summarySubtotal">$0.00</span>
                    </div>
                    <div class="d-flex justify-content-between small text-danger mb-1" id="discountRow" style="display: none !important;">
                        <span>{{ __('messages.discounts') }}:</span>
                        <span class="font-monospace fw-semibold" id="summaryDiscount">-$0.00</span>
                    </div>
                    <div class="d-flex justify-content-between small text-muted mb-2">
                        <span>{{ __('messages.tax') }}:</span>
                        <span class="font-monospace fw-semibold text-dark" id="summaryTax">$0.00</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center pt-2 border-top mb-3">
                        <span class="fw-bold text-dark fs-6">{{ __('messages.final_total') }}:</span>
                        <span class="fw-bold text-dark fs-4 font-monospace" id="summaryFinalTotal">$0.00</span>
                    </div>

                    <!-- Checkout Trigger Button -->
                    <button type="button" 
                            id="proceedCheckoutBtn" 
                            class="btn btn-skincare-primary w-100 py-2 fs-6 fw-bold d-flex align-items-center justify-content-center gap-2"
                            disabled>
                        <i class="bi bi-credit-card-2-front"></i>
                        <span>{{ __('messages.checkout') }} (<span id="btnPayAmount">$0.00</span>)</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Mobile Sticky Ticket & Checkout Bar (Visible only on < 992px) -->
<div id="posMobileTicketBar" class="d-lg-none position-fixed bottom-0 start-0 end-0 p-2 bg-white border-top shadow-lg" style="z-index: 1025; display: none;">
    <div class="d-flex align-items-center justify-content-between gap-2 px-2">
        <div class="d-flex align-items-center gap-2">
            <span class="avatar-circle" style="width: 36px; height: 36px; background-color: var(--primary); color: #fff;">
                <i class="bi bi-cart3 fs-6"></i>
            </span>
            <div>
                <div class="fw-bold text-dark font-monospace" id="mobileTicketTotal" style="font-size: 1rem;">$0.00</div>
                <div class="text-muted" style="font-size: 0.72rem;" id="mobileTicketItems">0 {{ __('messages.selected') }}</div>
            </div>
        </div>
        <button type="button" class="btn btn-sm btn-skincare-primary px-3 py-2 fw-semibold d-flex align-items-center gap-1 shadow-sm" onclick="document.querySelector('.pos-cart-panel').scrollIntoView({behavior: 'smooth'})">
            <span>{{ __('messages.current_ticket') }} & {{ __('messages.checkout') }}</span>
            <i class="bi bi-arrow-down-short fs-5"></i>
        </button>
    </div>
</div>

<!-- ── CHECKOUT MODAL: Payment Method & Change Calculation ── -->
<div class="modal fade" id="checkoutModal" tabindex="-1" aria-labelledby="checkoutModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-md">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-bottom py-3">
                <div class="d-flex align-items-center gap-2">
                    <span class="avatar-circle" style="width: 32px; height: 32px; font-size: 0.9rem;">
                        <i class="bi bi-receipt"></i>
                    </span>
                    <div>
                        <h6 class="modal-title fw-bold text-dark mb-0" id="checkoutModalLabel">{{ __('messages.complete_checkout') }}</h6>
                        <small class="text-muted" style="font-size: 0.72rem;">{{ __('messages.select_payment_method') }}</small>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body p-4">
                <!-- Total Display Banner -->
                <div class="p-3 bg-light border rounded-3 text-center mb-3">
                    <small class="text-muted d-block text-uppercase fw-semibold" style="font-size: 0.72rem; letter-spacing: 0.5px;">{{ __('messages.amount_to_pay') }}</small>
                    <div class="fs-2 fw-bold text-dark font-monospace" id="modalFinalTotal">$0.00</div>
                    <small class="text-muted" id="modalClientSummary">{{ __('messages.client_label') }}: {{ __('messages.walk_in_guest') }}</small>
                </div>

                <!-- Payment Method Selector Tiles -->
                <label class="form-label small fw-semibold text-dark mb-2">{{ __('messages.select_payment_method') }}</label>
                <div class="row g-2 mb-3">
                    <div class="col-4">
                        <div class="pay-btn-tile active" data-method="cash" id="payMethodCash">
                            <i class="bi bi-cash-stack fs-4 d-block mb-1"></i>
                            <span class="fw-bold" style="font-size: 0.8rem;">{{ __('messages.cash') }}</span>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="pay-btn-tile" data-method="card" id="payMethodCard">
                            <i class="bi bi-credit-card fs-4 d-block mb-1"></i>
                            <span class="fw-bold" style="font-size: 0.8rem;">{{ __('messages.card') }}</span>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="pay-btn-tile" data-method="qr" id="payMethodQr">
                            <i class="bi bi-qr-code fs-4 d-block mb-1"></i>
                            <span class="fw-bold" style="font-size: 0.8rem;">{{ __('messages.qr') }}</span>
                        </div>
                    </div>
                </div>

                <!-- Payment Method Specific Panes -->
                <!-- 1. Cash Pane: Amount Received & Change Due -->
                <div id="cashPaymentPane">
                    <div class="mb-3">
                        <label for="cashReceivedInput" class="form-label small fw-semibold text-dark">{{ __('messages.cash_tendered') }}</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white">$</span>
                            <input type="number" 
                                   id="cashReceivedInput" 
                                   class="form-control form-control-lg font-monospace fw-bold" 
                                   step="0.01" 
                                   min="0" 
                                   placeholder="0.00">
                        </div>
                    </div>

                    <!-- Quick Cash Tender Chips -->
                    <div class="d-flex flex-wrap gap-1 mb-3" id="quickCashButtons">
                        <!-- Populated dynamically via JS -->
                    </div>

                    <!-- Change Due Display -->
                    <div class="p-3 border rounded-3 bg-white d-flex align-items-center justify-content-between mb-3">
                        <span class="fw-semibold text-dark">{{ __('messages.change_due') }}:</span>
                        <span class="fs-4 fw-bold font-monospace text-success" id="cashChangeDue">$0.00</span>
                    </div>
                </div>

                <!-- 2. Card Pane -->
                <div id="cardPaymentPane" class="d-none">
                    <div class="p-3 border rounded-3 bg-light text-center mb-3">
                        <i class="bi bi-credit-card-2-front text-primary fs-1 d-block mb-2"></i>
                        <div class="fw-bold text-dark">{{ __('messages.card_terminal_ready') }}</div>
                        <small class="text-muted">{{ __('messages.card_terminal_desc') }}</small>
                    </div>
                </div>

                <!-- 3. QR / QRIS Pane -->
                <div id="qrPaymentPane" class="d-none text-center">
                    <div class="p-3 border rounded-3 bg-white d-inline-block shadow-sm mb-2">
                        <!-- Dynamic SVG QR Code Representation -->
                        <div class="p-2 border rounded" style="background-color: #FFFFFF;">
                            <i class="bi bi-qr-code text-dark" style="font-size: 7rem; line-height: 1;"></i>
                        </div>
                        <div class="fw-bold text-dark font-monospace mt-2" id="qrDisplayTotal">$0.00</div>
                        <small class="text-muted" style="font-size: 0.7rem;">{{ __('messages.qr_payment_prompt') }}</small>
                    </div>
                </div>

                <!-- Optional Manager Override / Manual Discount Rate -->
                <div class="accordion accordion-flush border-top pt-2" id="checkoutOptionsAccordion">
                    <div class="accordion-item bg-transparent">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed py-2 px-0 bg-transparent small text-muted" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOptions">
                                <i class="bi bi-sliders me-1"></i> {{ __('messages.special_discount_notes') }}
                            </button>
                        </h2>
                        <div id="collapseOptions" class="accordion-collapse collapse" data-bs-parent="#checkoutOptionsAccordion">
                            <div class="accordion-body px-0 pt-2 pb-0">
                                <div class="row g-2">
                                    <div class="col-6">
                                        <label for="manualDiscountInput" class="form-label small text-muted">{{ __('messages.special_promo_percent') }}</label>
                                        <input type="number" id="manualDiscountInput" class="form-control form-control-sm" min="0" max="100" step="1" placeholder="0%">
                                    </div>
                                    <div class="col-6">
                                        <label for="checkoutNotesInput" class="form-label small text-muted">{{ __('messages.notes') }}</label>
                                        <input type="text" id="checkoutNotesInput" class="form-control form-control-sm" placeholder="{{ __('messages.optional_notes') }}">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal-footer border-top py-2 bg-light">
                <button type="button" class="btn btn-sm btn-skincare-outline" data-bs-dismiss="modal">{{ __('messages.cancel') }}</button>
                <button type="button" class="btn btn-skincare-primary px-4 fw-bold" id="confirmPaymentBtn">
                    <span id="confirmBtnSpinner" class="spinner-border spinner-border-sm me-1 d-none" role="status"></span>
                    <i class="bi bi-check2-circle me-1" id="confirmBtnIcon"></i>
                    <span id="confirmBtnText">{{ __('messages.complete_sale') }}</span>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ── CHECKOUT SUCCESS & RECEIPT MODAL ── -->
<div class="modal fade" id="receiptModal" tabindex="-1" aria-labelledby="receiptModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-bottom py-3 bg-light">
                <h6 class="modal-title fw-bold text-dark mb-0 d-flex align-items-center gap-2" id="receiptModalLabel">
                    <i class="bi bi-check-circle-fill text-success"></i>
                    <span>{{ __('messages.checkout_completed') }}</span>
                </h6>
            </div>
            <div class="modal-body p-4 text-center">
                <div class="brand-icon-box mx-auto mb-2" style="width: 48px; height: 48px; font-size: 1.5rem; background-color: var(--primary-subtle); color: var(--primary);">
                    <i class="bi bi-receipt"></i>
                </div>
                <h5 class="fw-bold text-dark mb-1" id="receiptInvoiceNumber">INV-00000000-0000</h5>
                <p class="text-muted small mb-3" id="receiptMetaTime">Oct 22, 2026 &bull; Main Boutique</p>

                <div class="p-3 bg-light border rounded-3 text-start mb-3 small">
                    <div class="d-flex justify-content-between mb-1">
                        <span class="text-muted">{{ __('messages.total_paid') }}:</span>
                        <span class="fw-bold text-dark font-monospace" id="receiptTotal">$0.00</span>
                    </div>
                    <div class="d-flex justify-content-between mb-1">
                        <span class="text-muted">{{ __('messages.payment_type') }}:</span>
                        <span class="fw-bold text-dark text-uppercase" id="receiptPaymentType">CASH</span>
                    </div>
                    <div class="d-flex justify-content-between text-success fw-bold" id="receiptChangeContainer">
                        <span>{{ __('messages.change_returned') }}:</span>
                        <span class="font-monospace" id="receiptChange">$0.00</span>
                    </div>
                </div>

                <div class="d-flex flex-column gap-2">
                    <a href="#" target="_blank" id="printReceiptBtn" class="btn btn-skincare-primary w-100 py-2 fw-semibold">
                        <i class="bi bi-printer me-1"></i> {{ __('messages.print_thermal_receipt') }}
                    </a>
                    <button type="button" class="btn btn-skincare-outline w-100 py-2 fw-semibold" id="startNewSaleBtn">
                        <i class="bi bi-plus-circle me-1"></i> {{ __('messages.start_new_sale') }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ── QUICK REGISTER NEW CUSTOMER MODAL ── -->
<div class="modal fade" id="newCustomerModal" tabindex="-1" aria-labelledby="newCustomerModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-bottom py-3">
                <h6 class="modal-title fw-bold text-dark mb-0">
                    <i class="bi bi-person-plus text-primary me-1"></i>{{ __('messages.enroll_new_client') }}
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="quickCustomerForm">
                <div class="modal-body py-3">
                    <div class="mb-2">
                        <label class="form-label small fw-semibold text-dark mb-1">{{ __('messages.full_name') }} *</label>
                        <input type="text" id="newCustName" class="form-control form-control-sm" required placeholder="e.g. Elena Rostova">
                    </div>
                    <div class="mb-2">
                        <label class="form-label small fw-semibold text-dark mb-1">{{ __('messages.phone_number') }}</label>
                        <input type="text" id="newCustPhone" class="form-control form-control-sm" placeholder="e.g. +855 12 345 678">
                    </div>
                    <div class="mb-2">
                        <label class="form-label small fw-semibold text-dark mb-1">{{ __('messages.email') }}</label>
                        <input type="email" id="newCustEmail" class="form-control form-control-sm" placeholder="e.g. client@skincare.com">
                    </div>
                    <div class="mb-2">
                        <label class="form-label small fw-semibold text-dark mb-1">{{ __('messages.loyalty_tier') }} *</label>
                        <select id="newCustTier" class="form-select form-select-sm" required>
                            <option value="Standard">Standard (0% discount)</option>
                            <option value="Silver">Silver VIP (5% discount)</option>
                            <option value="Gold">Gold VIP (10% discount)</option>
                            <option value="Platinum">Platinum VIP (15% discount)</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer border-top py-2 bg-light">
                    <button type="button" class="btn btn-sm btn-skincare-outline" data-bs-dismiss="modal">{{ __('messages.cancel') }}</button>
                    <button type="submit" class="btn btn-sm btn-skincare-primary" id="saveQuickCustBtn">{{ __('messages.enroll_and_attach') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    // ── DATA SOURCES LOADED FROM SERVER ──
    const productCatalog = @json($catalogPayload);
    const taxRatePercent = {{ $taxRate }};
    const checkoutRoute = "{{ route('pos.checkout') }}";
    const customerStoreRoute = "{{ route('customers.store') }}";
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}';

    // Multilingual Localization Strings Dictionary
    const posI18n = {
        showingFormulas: function (count) {
            return "{{ __('messages.showing_formulas', ['count' => ':COUNT:']) }}".replace(':COUNT:', count);
        },
        itemsCount: function (count) {
            return "{{ __('messages.items_selected', ['count' => ':COUNT:']) }}".replace(':COUNT:', count);
        },
        outOfStock: "{{ __('messages.out_of_stock') }}",
        inStock: "{{ __('messages.units_in_stock') }}",
        ticketEmpty: "{{ __('messages.ticket_empty') }}",
        ticketEmptyHint: "{{ __('messages.ticket_empty_hint') }}",
        walkInGuest: "{{ __('messages.walk_in_guest') }}",
        clientPrefix: "{{ __('messages.client_label') }}: ",
        addToTicket: "{{ __('messages.add_to_ticket') }}",
        units: "{{ __('messages.units') }}",
        unitPrice: "{{ __('messages.unit_price') }}",
        exact: "{{ __('messages.exact') }}",
        outOfStockMsg: function (name) {
            return `[${name}] {{ __('messages.out_of_stock') }}`;
        },
        limitExceededMsg: function (qty, name) {
            return "{{ __('messages.stock_limit_reached', ['qty' => ':QTY:', 'name' => ':NAME:']) }}"
                .replace(':QTY:', qty)
                .replace(':NAME:', name);
        }
    };

    // ── POS ACTIVE REGISTER STATE ──
    let cart = []; // Array of: { product: Object, qty: int }
    let selectedCustomerId = null;
    let selectedCustomerDiscount = 0.0;
    let activeCategoryFilter = 'all';
    let searchQuery = '';
    let activePaymentMethod = 'cash';

    // Modals instances
    let checkoutModalInstance = null;
    let receiptModalInstance = null;
    let newCustomerModalInstance = null;

    function initPosTerminal() {
        const checkoutModalEl = document.getElementById('checkoutModal');
        const receiptModalEl = document.getElementById('receiptModal');
        const newCustomerModalEl = document.getElementById('newCustomerModal');

        if (checkoutModalEl && window.bootstrap) {
            checkoutModalInstance = bootstrap.Modal.getInstance(checkoutModalEl) || new bootstrap.Modal(checkoutModalEl);
        }
        if (receiptModalEl && window.bootstrap) {
            receiptModalInstance = bootstrap.Modal.getInstance(receiptModalEl) || new bootstrap.Modal(receiptModalEl);
        }
        if (newCustomerModalEl && window.bootstrap) {
            newCustomerModalInstance = bootstrap.Modal.getInstance(newCustomerModalEl) || new bootstrap.Modal(newCustomerModalEl);
        }

        renderProductGrid();
        setupEventListeners();
    }

    // ── EVENT LISTENERS & SCANNER SETUP ──
    function setupEventListeners() {
        const scannerInput = document.getElementById('barcodeScannerInput');
        if (!scannerInput || scannerInput.dataset.posEventsBound) return;
        scannerInput.dataset.posEventsBound = 'true';

        // Barcode Scanner & Search Handler
        scannerInput.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                const code = this.value.trim();
                if (!code) return;

                // 1. Try exact barcode match (scanner hardware behavior)
                const exactMatch = productCatalog.find(p => p.barcode && p.barcode.toLowerCase() === code.toLowerCase());
                if (exactMatch) {
                    addToCart(exactMatch.id);
                    this.value = '';
                    searchQuery = '';
                    renderProductGrid();
                    if (window.SkinCareToast) {
                        window.SkinCareToast.success(`Scanned: ${exactMatch.name}`);
                    }
                    return;
                }

                // 2. Otherwise update search filter
                searchQuery = code.toLowerCase();
                renderProductGrid();
            }
        });

        // Real-time typing search filter
        scannerInput.addEventListener('input', function () {
            searchQuery = this.value.trim().toLowerCase();
            renderProductGrid();
        });

        const clearBtn = document.getElementById('clearSearchBtn');
        if (clearBtn) {
            clearBtn.addEventListener('click', function () {
                scannerInput.value = '';
                searchQuery = '';
                renderProductGrid();
                scannerInput.focus();
            });
        }

        // Global hotkey: F2 or '/' auto-focuses scanner input
        document.addEventListener('keydown', function (e) {
            if (e.key === 'F2' || (e.key === '/' && document.activeElement.tagName !== 'INPUT')) {
                e.preventDefault();
                scannerInput.focus();
            }
        });

        // Category Filter Buttons with Live API Access
        document.querySelectorAll('.pos-cat-pill').forEach(btn => {
            btn.addEventListener('click', function () {
                document.querySelectorAll('.pos-cat-pill').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                activeCategoryFilter = this.getAttribute('data-category');
                renderProductGrid();

                // Live API access by category at POS
                const apiUrl = `{{ route('pos.products.api') }}?category_id=${encodeURIComponent(activeCategoryFilter)}${searchQuery ? '&search=' + encodeURIComponent(searchQuery) : ''}`;
                fetch(apiUrl, {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success && Array.isArray(data.products)) {
                        data.products.forEach(newP => {
                            const idx = productCatalog.findIndex(p => p.id === newP.id);
                            if (idx !== -1) {
                                productCatalog[idx] = newP;
                            } else {
                                productCatalog.push(newP);
                            }
                        });
                        renderProductGrid();
                    }
                })
                .catch(err => console.debug('POS category API access:', err));
            });
        });

        // Category Horizontal Scroll Navigation (Forward & Backward)
        const catContainer = document.getElementById('posCatPillContainer');
        const posCatLeftBtn = document.getElementById('posCatScrollLeft');
        const posCatRightBtn = document.getElementById('posCatScrollRight');

        function updatePosCatArrows() {
            if (!catContainer) return;
            const { scrollLeft, scrollWidth, clientWidth } = catContainer;
            if (posCatLeftBtn) posCatLeftBtn.style.opacity = scrollLeft > 8 ? '1' : '0.4';
            if (posCatRightBtn) posCatRightBtn.style.opacity = scrollLeft < scrollWidth - clientWidth - 8 ? '1' : '0.4';
        }

        posCatLeftBtn?.addEventListener('click', () => {
            catContainer?.scrollBy({ left: -200, behavior: 'smooth' });
            setTimeout(updatePosCatArrows, 260);
        });
        posCatRightBtn?.addEventListener('click', () => {
            catContainer?.scrollBy({ left: 200, behavior: 'smooth' });
            setTimeout(updatePosCatArrows, 260);
        });
        catContainer?.addEventListener('scroll', updatePosCatArrows);
        setTimeout(updatePosCatArrows, 200);

        // Customer Selection
        const custSelect = document.getElementById('customerSelect');
        if (custSelect) {
            custSelect.addEventListener('change', function () {
                const selectedOpt = this.options[this.selectedIndex];
                selectedCustomerId = this.value ? parseInt(this.value) : null;
                selectedCustomerDiscount = parseFloat(selectedOpt ? selectedOpt.getAttribute('data-discount') : 0) || 0;

                const tierBadgeContainer = document.getElementById('customerTierBadgeContainer');
                const benefitBadge = document.getElementById('customerBenefitBadge');

                if (selectedCustomerId && selectedCustomerDiscount > 0) {
                    if (tierBadgeContainer) tierBadgeContainer.style.setProperty('display', 'flex', 'important');
                    if (benefitBadge) benefitBadge.innerText = `${selectedOpt.getAttribute('data-tier')} VIP (${selectedCustomerDiscount}% Off)`;
                } else {
                    if (tierBadgeContainer) tierBadgeContainer.style.setProperty('display', 'none', 'important');
                }

                updateCartTotals();
            });
        }

        // Clear Cart
        const clearCartBtn = document.getElementById('clearCartBtn');
        if (clearCartBtn) {
            clearCartBtn.addEventListener('click', function () {
                if (cart.length === 0) return;
                if (confirm('Clear all items from this register ticket?')) {
                    cart = [];
                    renderCart();
                }
            });
        }

        // Proceed to Checkout Button
        const checkoutBtn = document.getElementById('proceedCheckoutBtn');
        if (checkoutBtn) {
            checkoutBtn.addEventListener('click', function () {
                if (cart.length === 0) return;
                openCheckoutModal();
            });
        }

        // Payment Method Selector
        document.querySelectorAll('.pay-btn-tile').forEach(tile => {
            tile.addEventListener('click', function () {
                document.querySelectorAll('.pay-btn-tile').forEach(t => t.classList.remove('active'));
                this.classList.add('active');
                activePaymentMethod = this.getAttribute('data-method');
                updatePaymentPanes();
            });
        });

        // Cash Received Input
        const cashInput = document.getElementById('cashReceivedInput');
        if (cashInput) cashInput.addEventListener('input', calculateCashChange);

        // Confirm Payment Submission
        const confirmBtn = document.getElementById('confirmPaymentBtn');
        if (confirmBtn) confirmBtn.addEventListener('click', submitCheckout);

        // New Sale Reset Button from Receipt Modal
        const newSaleBtn = document.getElementById('startNewSaleBtn');
        if (newSaleBtn) {
            newSaleBtn.addEventListener('click', function () {
                if (receiptModalInstance) receiptModalInstance.hide();
                cart = [];
                selectedCustomerId = null;
                const cSelect = document.getElementById('customerSelect');
                if (cSelect) cSelect.value = '';
                const tBadge = document.getElementById('customerTierBadgeContainer');
                if (tBadge) tBadge.style.setProperty('display', 'none', 'important');
                renderCart();
                scannerInput.focus();
            });
        }

        // Quick Customer Enrollment Form
        const quickCustForm = document.getElementById('quickCustomerForm');
        if (quickCustForm) quickCustForm.addEventListener('submit', handleQuickCustomerEnrollment);
    }

    function resetFilters() {
        const scannerInput = document.getElementById('barcodeScannerInput');
        if (scannerInput) scannerInput.value = '';
        searchQuery = '';
        activeCategoryFilter = 'all';
        document.querySelectorAll('.pos-cat-pill').forEach(b => {
            b.classList.toggle('active', b.getAttribute('data-category') === 'all');
        });
        renderProductGrid();
        if (scannerInput) scannerInput.focus();
    }

    // ── CATALOG RENDERING & FILTERING ──
    function renderProductGrid() {
        const grid = document.getElementById('posProductGrid');
        const emptyState = document.getElementById('noProductsFound');
        const countBadge = document.getElementById('productCountBadge');
        if (!grid) return;

        const filtered = productCatalog.filter(p => {
            const matchesCategory = (activeCategoryFilter === 'all') || (String(p.category_id) === String(activeCategoryFilter));
            const matchesSearch = !searchQuery || 
                p.name.toLowerCase().includes(searchQuery) || 
                (p.barcode && p.barcode.toLowerCase().includes(searchQuery));
            return matchesCategory && matchesSearch;
        });

        if (countBadge) {
            countBadge.innerText = `Showing ${filtered.length} formula${filtered.length === 1 ? '' : 's'}`;
        }

        if (filtered.length === 0) {
            grid.innerHTML = '';
            if (emptyState) emptyState.classList.remove('d-none');
            return;
        }

        if (emptyState) emptyState.classList.add('d-none');

        grid.innerHTML = filtered.map(p => {
            const isOutOfStock = Number(p.qty) <= 0;
            const stockBadgeClass = isOutOfStock 
                ? 'bg-danger-subtle text-danger border border-danger-subtle' 
                : (p.is_low_stock ? 'bg-warning-subtle text-warning-emphasis border border-warning-subtle' : 'bg-success-subtle text-success border border-success-subtle');
            const stockText = isOutOfStock ? 'Out of Stock' : `${p.qty} in stock`;

            let imageUrl = p.image || '';
            if (imageUrl) {
                imageUrl = (imageUrl.startsWith('http://') || imageUrl.startsWith('https://')) ? imageUrl : (imageUrl.startsWith('/') ? imageUrl : '/' + imageUrl);
            } else {
                imageUrl = '/images/products/skincare_bottle.jpg';
            }

            const promoBadgeHtml = p.promo_text 
                ? `<span class="badge bg-danger position-absolute top-0 end-0 m-2 shadow-sm" style="font-size: 0.65rem; z-index: 2;">${p.promo_text}</span>` 
                : '';

            const priceFormatted = (Number(p.price) || 0).toFixed(2);

            return `
                <div class="col-6 col-sm-4 col-md-4 col-xl-3">
                    <div class="pos-product-card ${isOutOfStock ? 'out-of-stock' : ''}" 
                         onclick="window.posAddToCart ? window.posAddToCart(${p.id}) : null"
                         title="${isOutOfStock ? 'Out of Stock' : 'Add ' + p.name + ' to ticket'}"
                         role="button"
                         tabindex="0">
                        ${promoBadgeHtml}
                        <div>
                            <div class="pos-thumb-box position-relative">
                                <img src="${imageUrl}" 
                                     alt="${p.name}" 
                                     class="pos-thumb-img" 
                                     loading="lazy"
                                     onerror="this.onerror=null;this.src='/images/products/skincare_bottle.jpg';">
                                ${!isOutOfStock ? `
                                <div class="pos-card-add-overlay">
                                    <span class="btn btn-sm btn-light rounded-circle shadow-sm p-1 d-inline-flex align-items-center justify-content-center" style="width: 28px; height: 28px;">
                                        <i class="bi bi-plus text-primary fw-bold fs-6"></i>
                                    </span>
                                </div>` : ''}
                            </div>
                            <span class="badge bg-light text-muted border mb-1" style="font-size: 0.65rem;">${p.category_name || 'Skincare'}</span>
                            <div class="fw-bold text-dark text-truncate" style="font-size: 0.83rem;" title="${p.name}">${p.name}</div>
                            <div class="text-muted font-monospace" style="font-size: 0.7rem;">
                                <i class="bi bi-upc me-1 text-muted" style="font-size: 0.65rem;"></i>${p.barcode || 'N/A'}
                            </div>
                        </div>

                        <div class="d-flex align-items-center justify-content-between mt-2 pt-2 border-top">
                            <span class="fw-bold text-dark fs-6 font-monospace">${priceFormatted}</span>
                            <span class="badge ${stockBadgeClass}" style="font-size: 0.68rem;">${stockText}</span>
                        </div>
                    </div>
                </div>
            `;
        }).join('');
    }

    // ── CART MANAGEMENT ──
    function addToCart(productId) {
        const product = productCatalog.find(p => p.id === productId);
        if (!product) return;

        if (product.qty <= 0) {
            if (window.SkinCareToast) {
                window.SkinCareToast.warning(`[${product.name}] is currently out of stock.`);
            }
            return;
        }

        const existing = cart.find(item => item.product.id === productId);
        if (existing) {
            if (existing.qty + 1 > product.qty) {
                if (window.SkinCareToast) {
                    window.SkinCareToast.warning(`Cannot exceed available stock of ${product.qty} units for [${product.name}].`);
                }
                return;
            }
            existing.qty += 1;
        } else {
            cart.push({ product: product, qty: 1 });
        }

        renderCart();
    }

    function updateCartQty(productId, delta) {
        const item = cart.find(i => i.product.id === productId);
        if (!item) return;

        const newQty = item.qty + delta;
        if (newQty <= 0) {
            removeCartItem(productId);
            return;
        }

        if (newQty > item.product.qty) {
            if (window.SkinCareToast) {
                window.SkinCareToast.warning(`Only ${item.product.qty} units available in inventory.`);
            }
            return;
        }

        item.qty = newQty;
        renderCart();
    }

    function removeCartItem(productId) {
        cart = cart.filter(i => i.product.id !== productId);
        renderCart();
    }

    function renderCart() {
        const container = document.getElementById('cartItemsContainer');
        const empty = document.getElementById('emptyCartPlaceholder');
        const countBadge = document.getElementById('ticketCountBadge');
        const proceedBtn = document.getElementById('proceedCheckoutBtn');

        const totalItemsCount = cart.reduce((sum, i) => sum + i.qty, 0);
        countBadge.innerText = `${totalItemsCount} item${totalItemsCount === 1 ? '' : 's'}`;

        if (cart.length === 0) {
            container.innerHTML = `
                <div class="text-center py-5 text-muted" id="emptyCartPlaceholder">
                    <i class="bi bi-cart-x fs-1 d-block mb-2 text-muted" style="opacity: 0.5;"></i>
                    <div class="fw-semibold text-dark small">Ticket is empty</div>
                    <small class="text-muted">Scan a barcode or click catalog formulas to add them.</small>
                </div>
            `;
            proceedBtn.disabled = true;
            updateCartTotals();
            return;
        }

        proceedBtn.disabled = false;

        container.innerHTML = cart.map(item => {
            const lineSubtotal = (item.product.price * item.qty).toFixed(2);
            return `
                <div class="cart-item-row">
                    <div class="d-flex justify-content-between align-items-start mb-1">
                        <div class="pe-2">
                            <div class="fw-bold text-dark text-truncate" style="font-size: 0.84rem; max-width: 170px;" title="${item.product.name}">
                                ${item.product.name}
                            </div>
                            <div class="text-muted font-monospace" style="font-size: 0.68rem;">
                                ${item.product.barcode} &bull; $${item.product.price.toFixed(2)} ea
                            </div>
                        </div>
                        <button type="button" class="btn btn-link p-0 text-danger" onclick="removeCartItem(${item.product.id})" title="Remove item">
                            <i class="bi bi-x-circle fs-6"></i>
                        </button>
                    </div>

                    <div class="d-flex align-items-center justify-content-between pt-1">
                        <div class="d-flex align-items-center gap-1">
                            <button type="button" class="qty-btn" onclick="updateCartQty(${item.product.id}, -1)">
                                <i class="bi bi-dash"></i>
                            </button>
                            <span class="fw-bold font-monospace px-2" style="font-size: 0.85rem; min-width: 24px; text-align: center;">
                                ${item.qty}
                            </span>
                            <button type="button" class="qty-btn" onclick="updateCartQty(${item.product.id}, 1)">
                                <i class="bi bi-plus"></i>
                            </button>
                        </div>
                        <span class="fw-bold text-dark font-monospace" style="font-size: 0.9rem;">
                            $${lineSubtotal}
                        </span>
                    </div>
                </div>
            `;
        }).join('');

        updateCartTotals();
    }

    // ── TOTALS CALCULATION ──
    function calculateTotals() {
        let subtotal = 0.00;
        let discount = 0.00;

        cart.forEach(item => {
            const rawLine = item.product.price * item.qty;
            subtotal += rawLine;

            // 1. Promotional discount
            let promoDisc = 0.00;
            if (item.product.promo) {
                if (item.product.promo.discount_type === 'percentage') {
                    promoDisc = rawLine * (item.product.promo.discount_value / 100);
                } else {
                    promoDisc = Math.min(rawLine, item.product.promo.discount_value * item.qty);
                }
            }

            // 2. VIP Loyalty member discount
            let memberDisc = 0.00;
            if (selectedCustomerDiscount > 0) {
                memberDisc = (rawLine - promoDisc) * (selectedCustomerDiscount / 100);
            }

            discount += (promoDisc + memberDisc);
        });

        // Manual discount override from modal if active
        const manualDiscountInput = document.getElementById('manualDiscountInput');
        const manualRate = manualDiscountInput ? parseFloat(manualDiscountInput.value || 0) : 0;
        if (manualRate > 0) {
            const remaining = Math.max(0, subtotal - discount);
            discount += remaining * (Math.min(100, manualRate) / 100);
        }

        discount = Math.min(subtotal, discount);
        const netTaxable = Math.max(0, subtotal - discount);
        const tax = netTaxable * (taxRatePercent / 100);
        const finalTotal = netTaxable + tax;

        return {
            subtotal: subtotal,
            discount: discount,
            tax: tax,
            finalTotal: finalTotal
        };
    }

    function updateCartTotals() {
        const totals = calculateTotals();

        document.getElementById('summarySubtotal').innerText = `$${totals.subtotal.toFixed(2)}`;

        const discountRow = document.getElementById('discountRow');
        if (totals.discount > 0) {
            discountRow.style.setProperty('display', 'flex', 'important');
            document.getElementById('summaryDiscount').innerText = `-$${totals.discount.toFixed(2)}`;
        } else {
            discountRow.style.setProperty('display', 'none', 'important');
        }

        document.getElementById('summaryTax').innerText = `$${totals.tax.toFixed(2)}`;
        document.getElementById('summaryFinalTotal').innerText = `$${totals.finalTotal.toFixed(2)}`;
        document.getElementById('btnPayAmount').innerText = `$${totals.finalTotal.toFixed(2)}`;

        const mobileTicketBar = document.getElementById('posMobileTicketBar');
        if (mobileTicketBar) {
            if (cart.length > 0) {
                mobileTicketBar.style.display = 'block';
                const mobileTotalEl = document.getElementById('mobileTicketTotal');
                const mobileItemsEl = document.getElementById('mobileTicketItems');
                if (mobileTotalEl) mobileTotalEl.innerText = `$${totals.finalTotal.toFixed(2)}`;
                if (mobileItemsEl) mobileItemsEl.innerText = posI18n.itemsCount(cart.reduce((sum, i) => sum + i.qty, 0));
            } else {
                mobileTicketBar.style.display = 'none';
            }
        }
    }

    // ── CHECKOUT MODAL LOGIC ──
    function openCheckoutModal() {
        const totals = calculateTotals();
        document.getElementById('modalFinalTotal').innerText = `$${totals.finalTotal.toFixed(2)}`;

        const customerSelect = document.getElementById('customerSelect');
        const clientName = customerSelect.value ? customerSelect.options[customerSelect.selectedIndex].text : posI18n.walkInGuest;
        document.getElementById('modalClientSummary').innerText = `${posI18n.clientPrefix}${clientName}`;

        // Setup quick cash buttons
        const quickCashContainer = document.getElementById('quickCashButtons');
        const exact = totals.finalTotal;
        const next10 = Math.ceil(exact / 10) * 10;
        const next50 = Math.ceil(exact / 50) * 50;
        const next100 = Math.ceil(exact / 100) * 100;

        const suggestions = Array.from(new Set([exact, next10, next50, next100])).sort((a,b) => a - b);

        quickCashContainer.innerHTML = suggestions.map(val => {
            const label = (val === exact) ? `${posI18n.exact} (${val.toFixed(2)})` : `${val.toFixed(2)}`;
            return `<button type="button" class="quick-cash-chip" onclick="setCashTender(${val})">${label}</button>`;
        }).join('');

        // Reset cash input to exact by default
        document.getElementById('cashReceivedInput').value = exact.toFixed(2);
        calculateCashChange();

        // Reset display
        document.getElementById('qrDisplayTotal').innerText = `$${totals.finalTotal.toFixed(2)}`;
        updatePaymentPanes();

        checkoutModalInstance.show();
    }

    function updatePaymentPanes() {
        const cashPane = document.getElementById('cashPaymentPane');
        const cardPane = document.getElementById('cardPaymentPane');
        const qrPane = document.getElementById('qrPaymentPane');

        cashPane.classList.toggle('d-none', activePaymentMethod !== 'cash');
        cardPane.classList.toggle('d-none', activePaymentMethod !== 'card');
        qrPane.classList.toggle('d-none', activePaymentMethod !== 'qr');
    }

    function setCashTender(amount) {
        document.getElementById('cashReceivedInput').value = amount.toFixed(2);
        calculateCashChange();
    }

    function calculateCashChange() {
        const totals = calculateTotals();
        const cashTendered = parseFloat(document.getElementById('cashReceivedInput').value || 0);
        const change = Math.max(0, cashTendered - totals.finalTotal);
        const changeDisplay = document.getElementById('cashChangeDue');

        changeDisplay.innerText = `$${change.toFixed(2)}`;

        if (cashTendered < totals.finalTotal && activePaymentMethod === 'cash') {
            changeDisplay.classList.remove('text-success');
            changeDisplay.classList.add('text-danger');
            changeDisplay.innerText = `-$${(totals.finalTotal - cashTendered).toFixed(2)} (Short)`;
        } else {
            changeDisplay.classList.remove('text-danger');
            changeDisplay.classList.add('text-success');
        }
    }

    // ── SUBMIT CHECKOUT TO SERVER ──
    async function submitCheckout() {
        const totals = calculateTotals();
        const confirmBtn = document.getElementById('confirmPaymentBtn');
        const spinner = document.getElementById('confirmBtnSpinner');
        const btnIcon = document.getElementById('confirmBtnIcon');
        const btnText = document.getElementById('confirmBtnText');

        let amountReceived = totals.finalTotal;
        if (activePaymentMethod === 'cash') {
            amountReceived = parseFloat(document.getElementById('cashReceivedInput').value || 0);
            if (amountReceived < totals.finalTotal) {
                alert(`Cash tendered ($${amountReceived.toFixed(2)}) is less than total ($${totals.finalTotal.toFixed(2)}).`);
                return;
            }
        }

        const payload = {
            items: cart.map(i => ({ product_id: i.product.id, qty: i.qty })),
            customer_id: selectedCustomerId,
            payment_type: activePaymentMethod,
            amount_received: amountReceived,
            manual_discount: parseFloat(document.getElementById('manualDiscountInput').value || 0),
            notes: document.getElementById('checkoutNotesInput').value.trim()
        };

        // Loading state
        confirmBtn.disabled = true;
        spinner.classList.remove('d-none');
        btnIcon.classList.add('d-none');
        btnText.innerText = 'Processing Sale...';

        try {
            const response = await fetch(checkoutRoute, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify(payload)
            });

            const result = await response.json();

            if (!response.ok || !result.success) {
                throw new Error(result.message || 'Checkout failed. Please verify items and inventory.');
            }

            // Deduct local catalog stock to match server
            cart.forEach(cartItem => {
                const prod = productCatalog.find(p => p.id === cartItem.product.id);
                if (prod) {
                    prod.qty = Math.max(0, prod.qty - cartItem.qty);
                    prod.is_low_stock = prod.qty <= prod.low_stock_threshold;
                }
            });

            // Close checkout modal & show receipt modal
            checkoutModalInstance.hide();

            document.getElementById('receiptInvoiceNumber').innerText = result.sale.invoice_number;
            document.getElementById('receiptMetaTime').innerText = `${result.sale.created_at} &bull; Cashier: ${result.sale.cashier_name}`;
            document.getElementById('receiptTotal').innerText = `$${result.sale.final_total.toFixed(2)}`;
            document.getElementById('receiptPaymentType').innerText = result.sale.payment_type;
            document.getElementById('receiptChange').innerText = `$${result.sale.change_due.toFixed(2)}`;
            document.getElementById('printReceiptBtn').setAttribute('href', result.receipt_url);

            const changeContainer = document.getElementById('receiptChangeContainer');
            changeContainer.style.display = (result.sale.payment_type.toLowerCase() === 'cash') ? 'flex' : 'none';

            receiptModalInstance.show();

            if (window.SkinCareToast) {
                window.SkinCareToast.success(`Sale #${result.sale.invoice_number} successfully completed!`);
            }

            renderProductGrid();
        } catch (error) {
            alert('Checkout Error: ' + error.message);
        } finally {
            confirmBtn.disabled = false;
            spinner.classList.add('d-none');
            btnIcon.classList.remove('d-none');
            btnText.innerText = 'Complete Sale';
        }
    }

    // ── QUICK CUSTOMER ENROLLMENT ──
    async function handleQuickCustomerEnrollment(e) {
        e.preventDefault();
        const submitBtn = document.getElementById('saveQuickCustBtn');
        submitBtn.disabled = true;

        const payload = {
            name: document.getElementById('newCustName').value.trim(),
            phone: document.getElementById('newCustPhone').value.trim(),
            email: document.getElementById('newCustEmail').value.trim(),
            membership_level: document.getElementById('newCustTier').value,
        };

        try {
            const response = await fetch(customerStoreRoute, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify(payload)
            });

            const result = await response.json();
            if (!response.ok || !result.success) {
                throw new Error(result.message || 'Failed to register client.');
            }

            // Append to dropdown and select
            const select = document.getElementById('customerSelect');
            const newOption = document.createElement('option');
            newOption.value = result.customer.id;
            newOption.setAttribute('data-tier', result.customer.membership_level);
            newOption.setAttribute('data-discount', result.customer.discount_rate);
            newOption.text = `${result.customer.name} (${result.customer.membership_level} — ${result.customer.discount_rate}% off)`;
            select.appendChild(newOption);
            select.value = result.customer.id;

            // Trigger change event to apply loyalty discount immediately
            select.dispatchEvent(new Event('change'));

            newCustomerModalInstance.hide();
            document.getElementById('quickCustomerForm').reset();

            if (window.SkinCareToast) {
                window.SkinCareToast.success(`Enrolled client [${result.customer.name}] into ${result.customer.membership_level} tier.`);
            }
        } catch (err) {
            alert('Error: ' + err.message);
        } finally {
            submitBtn.disabled = false;
        }
    }

    // Expose helpers globally so inline handlers and window scope always work
    window.posAddToCart = addToCart;
    window.posUpdateCartQty = updateCartQty;
    window.posRemoveCartItem = removeCartItem;
    window.posSetCashTender = setCashTender;
    window.posResetFilters = resetFilters;
    window.addToCart = addToCart;
    window.updateCartQty = updateCartQty;
    window.removeCartItem = removeCartItem;
    window.setCashTender = setCashTender;

    // Run initialization whether DOM is loading, already ready, or swapped via AJAX
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initPosTerminal);
    } else {
        initPosTerminal();
    }
    document.addEventListener('app:page-loaded', initPosTerminal);
})();
</script>
@endpush
