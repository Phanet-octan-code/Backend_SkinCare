@extends('layouts.app')

@section('title', __('messages.settings'))

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none text-muted">{{ __('messages.dashboard') }}</a></li>
    <li class="breadcrumb-item active text-dark" aria-current="page">{{ __('messages.settings') }}</li>
@endsection

@section('content')
<form id="settingsForm" method="POST" action="{{ route('settings.update') }}" enctype="multipart/form-data">
    @csrf

    <x-page-header 
        :title="__('messages.settings_title')" 
        :description="__('messages.settings_desc')"
        :breadcrumbs="['Administration' => null, __('messages.settings') => null]">
        <x-slot:actions>
            <button type="submit" class="btn btn-sm btn-skincare-primary px-3 shadow-sm">
                <i class="bi bi-floppy me-1"></i> {{ __('messages.save_changes') }}
            </button>
        </x-slot:actions>
    </x-page-header>

    <!-- Settings Navigation Tabs -->
    <ul class="nav nav-pills mb-4 gap-2 border-bottom pb-3" id="settingsTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active px-3 py-2 small fw-semibold d-flex align-items-center gap-2" id="business-tab" data-bs-toggle="pill" data-bs-target="#tab-business" type="button" role="tab">
                <i class="bi bi-shop"></i> {{ __('messages.tab_business') }}
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link px-3 py-2 small fw-semibold d-flex align-items-center gap-2" id="currency-tab" data-bs-toggle="pill" data-bs-target="#tab-currency" type="button" role="tab">
                <i class="bi bi-currency-exchange"></i> {{ __('messages.tab_currency') }}
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link px-3 py-2 small fw-semibold d-flex align-items-center gap-2" id="invoicing-tab" data-bs-toggle="pill" data-bs-target="#tab-invoicing" type="button" role="tab">
                <i class="bi bi-receipt"></i> {{ __('messages.tab_invoicing') }}
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link px-3 py-2 small fw-semibold d-flex align-items-center gap-2" id="inventory-tab" data-bs-toggle="pill" data-bs-target="#tab-inventory" type="button" role="tab">
                <i class="bi bi-boxes"></i> {{ __('messages.tab_inventory') }}
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link px-3 py-2 small fw-semibold d-flex align-items-center gap-2" id="attendance-tab" data-bs-toggle="pill" data-bs-target="#tab-attendance" type="button" role="tab">
                <i class="bi bi-clock-history"></i> {{ __('messages.tab_attendance') }}
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link px-3 py-2 small fw-semibold d-flex align-items-center gap-2" id="roles-tab" data-bs-toggle="pill" data-bs-target="#tab-roles" type="button" role="tab">
                <i class="bi bi-shield-check"></i> {{ __('messages.tab_roles') }}
            </button>
        </li>
    </ul>

    <div class="tab-content" id="settingsTabContent">
        <!-- 1. Store Identity & Profile -->
        <div class="tab-pane fade show active" id="tab-business" role="tabpanel">
            <div class="row g-4">
                <div class="col-12 col-lg-8">
                    <x-card title="Boutique Information & Branding">
                        <div class="mb-3">
                            <label class="form-label small fw-semibold" for="business_name">{{ __('messages.business_name') }} <span class="text-danger">*</span></label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-light border-end-0"><i class="bi bi-shop text-muted"></i></span>
                                <input type="text" id="business_name" name="business_name" class="form-control ps-2" value="{{ old('business_name', $settings['business_name']) }}" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-semibold" for="business_address">{{ __('messages.business_address') }} <span class="text-danger">*</span></label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-light border-end-0"><i class="bi bi-geo-alt text-muted"></i></span>
                                <input type="text" id="business_address" name="business_address" class="form-control ps-2" value="{{ old('business_address', $settings['business_address']) }}" required>
                            </div>
                        </div>

                        <div class="row g-3">
                            <div class="col-12 col-md-6">
                                <label class="form-label small fw-semibold" for="business_phone">{{ __('messages.business_phone') }} <span class="text-danger">*</span></label>
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text bg-light border-end-0"><i class="bi bi-telephone text-muted"></i></span>
                                    <input type="text" id="business_phone" name="business_phone" class="form-control ps-2" value="{{ old('business_phone', $settings['business_phone']) }}" required>
                                </div>
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label small fw-semibold" for="business_email">{{ __('messages.business_email') }} <span class="text-danger">*</span></label>
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text bg-light border-end-0"><i class="bi bi-envelope text-muted"></i></span>
                                    <input type="email" id="business_email" name="business_email" class="form-control ps-2" value="{{ old('business_email', $settings['business_email']) }}" required>
                                </div>
                            </div>
                        </div>
                    </x-card>
                </div>

                <div class="col-12 col-lg-4">
                    <x-card title="Boutique Logo & Emblems">
                        <div class="text-center p-3 border rounded-3 bg-light mb-3">
                            @if(!empty($settings['business_logo']) && file_exists(public_path($settings['business_logo'])))
                                <img src="{{ asset($settings['business_logo']) }}" alt="Store Logo" class="img-fluid rounded mb-2 shadow-sm" style="max-height: 90px; object-fit: contain;">
                                <div class="small text-muted font-monospace" style="font-size: 0.72rem;">{{ $settings['business_logo'] }}</div>
                            @else
                                <div class="brand-icon-box mx-auto mb-2" style="width: 64px; height: 64px; font-size: 1.8rem;">
                                    <i class="bi bi-flower1"></i>
                                </div>
                                <div class="fw-semibold text-dark small">Default SkinCare Boutique Icon</div>
                                <div class="text-muted" style="font-size: 0.72rem;">No custom image uploaded yet</div>
                            @endif
                        </div>

                        <div>
                            <label class="form-label small fw-semibold" for="business_logo">Upload Brand Logo</label>
                            <input type="file" id="business_logo" name="business_logo" class="form-control form-control-sm" accept="image/png,image/jpeg,image/webp,image/svg+xml">
                            <div class="form-text" style="font-size: 0.72rem;">Recommended: PNG/WEBP with transparent background. Max 2MB.</div>
                        </div>
                    </x-card>
                </div>
            </div>
        </div>

        <!-- 2. Currency & Financials -->
        <div class="tab-pane fade" id="tab-currency" role="tabpanel">
            <div class="row g-4">
                <div class="col-12 col-lg-7">
                    <x-card title="Currency Configuration & Rounding">
                        <div class="mb-4">
                            <label class="form-label small fw-semibold">{{ __('messages.currency_selection') }} <span class="text-danger">*</span></label>
                            <div class="row g-3">
                                <div class="col-6">
                                    <div class="p-3 border rounded-3 {{ $settings['currency'] === 'USD' ? 'border-primary bg-light' : '' }}">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="currency" id="curr_usd" value="USD" {{ $settings['currency'] === 'USD' ? 'checked' : '' }}>
                                            <label class="form-check-label fw-bold text-dark small" for="curr_usd">
                                                USD — US Dollar ($)
                                            </label>
                                        </div>
                                        <div class="text-muted ps-4 pt-1" style="font-size: 0.73rem;">
                                            Standard 2 decimal places ($12.50). International pricing standard.
                                        </div>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="p-3 border rounded-3 {{ $settings['currency'] === 'KHR' ? 'border-primary bg-light' : '' }}">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="currency" id="curr_khr" value="KHR" {{ $settings['currency'] === 'KHR' ? 'checked' : '' }}>
                                            <label class="form-check-label fw-bold text-dark small" for="curr_khr">
                                                KHR — Khmer Riel (៛)
                                            </label>
                                        </div>
                                        <div class="text-muted ps-4 pt-1" style="font-size: 0.73rem;">
                                            Rounded to whole numbers (51,250 ៛). Local Cambodian standard.
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row g-3">
                            <div class="col-12 col-md-6">
                                <label class="form-label small fw-semibold" for="exchange_rate_khr">{{ __('messages.exchange_rate') }} <span class="text-danger">*</span></label>
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text bg-light border-end-0">1 USD =</span>
                                    <input type="number" step="1" min="1" id="exchange_rate_khr" name="exchange_rate_khr" class="form-control text-end font-monospace" value="{{ old('exchange_rate_khr', $settings['exchange_rate_khr']) }}" required>
                                    <span class="input-group-text bg-light border-start-0">៛</span>
                                </div>
                                <div class="form-text" style="font-size: 0.72rem;">Applied explicitly to new transactions and dual-currency receipts.</div>
                            </div>

                            <div class="col-12 col-md-6">
                                <label class="form-label small fw-semibold" for="tax_rate">{{ __('messages.tax_rate') }} <span class="text-danger">*</span></label>
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text bg-light border-end-0"><i class="bi bi-percent text-muted"></i></span>
                                    <input type="number" step="0.01" min="0" max="100" id="tax_rate" name="tax_rate" class="form-control text-end font-monospace" value="{{ old('tax_rate', $settings['tax_rate']) }}" required>
                                    <span class="input-group-text bg-light border-start-0">%</span>
                                </div>
                                <div class="form-text" style="font-size: 0.72rem;">Applied to POS checkout net taxable subtotal.</div>
                            </div>
                        </div>
                    </x-card>
                </div>

                <div class="col-12 col-lg-5">
                    <x-card title="Auditing & Mathematical Integrity">
                        <div class="p-3 border rounded-3 bg-light mb-3">
                            <div class="d-flex align-items-center gap-2 mb-2 text-dark fw-semibold small">
                                <i class="bi bi-shield-lock-fill text-success"></i> Historical Data Protection
                            </div>
                            <p class="text-muted small mb-0" style="font-size: 0.78rem;">
                                When switching currency or exchange rates, the system stores transaction currency explicitly. Historical invoices will <strong>never</strong> be silently reinterpreted, preserving accounting compliance.
                            </p>
                        </div>

                        <div class="p-3 border rounded-3">
                            <div class="fw-semibold text-dark small mb-2">Live Format Simulation</div>
                            <table class="table table-sm table-borderless mb-0" style="font-size: 0.8rem;">
                                <tr>
                                    <td class="text-muted py-1">Active Currency:</td>
                                    <td class="fw-bold py-1">{{ $settings['currency'] }} ({{ \App\Models\Setting::currencySymbol($settings['currency']) }})</td>
                                </tr>
                                <tr>
                                    <td class="text-muted py-1">Sample Price ($25.00):</td>
                                    <td class="fw-bold font-monospace py-1">{{ \App\Models\Setting::formatMoney(25.00, $settings['currency']) }}</td>
                                </tr>
                                <tr>
                                    <td class="text-muted py-1">KHR Dual Equivalent:</td>
                                    <td class="fw-bold font-monospace py-1">{{ number_format(25.00 * (float) $settings['exchange_rate_khr'], 0) }} ៛</td>
                                </tr>
                            </table>
                        </div>
                    </x-card>
                </div>
            </div>
        </div>

        <!-- 3. Invoicing & Hardware -->
        <div class="tab-pane fade" id="tab-invoicing" role="tabpanel">
            <div class="row g-4">
                <div class="col-12 col-lg-6">
                    <x-card title="Invoice Numbering & Standards">
                        <div class="mb-3">
                            <label class="form-label small fw-semibold" for="invoice_prefix">{{ __('messages.invoice_prefix') }} <span class="text-danger">*</span></label>
                            <input type="text" id="invoice_prefix" name="invoice_prefix" class="form-control form-control-sm font-monospace" value="{{ old('invoice_prefix', $settings['invoice_prefix']) }}" required>
                            <div class="form-text" style="font-size: 0.72rem;">Prefix identifier prepended to every generated invoice.</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-semibold">{{ __('messages.invoice_format') }} <span class="text-danger">*</span></label>
                            <div class="form-check p-2 border rounded-3 mb-2">
                                <input class="form-check-input ms-1" type="radio" name="invoice_number_format" id="fmt_date_seq" value="prefix_date_seq" {{ $settings['invoice_number_format'] === 'prefix_date_seq' ? 'checked' : '' }}>
                                <label class="form-check-label ps-2 small text-dark" for="fmt_date_seq">
                                    <strong>Prefix + Date + Sequence</strong>
                                    <span class="d-block text-muted font-monospace" style="font-size: 0.72rem;">Example: INV-{{ date('Ymd') }}-0001 (Recommended for daily reconciliation)</span>
                                </label>
                            </div>
                            <div class="form-check p-2 border rounded-3">
                                <input class="form-check-input ms-1" type="radio" name="invoice_number_format" id="fmt_seq" value="prefix_seq" {{ $settings['invoice_number_format'] === 'prefix_seq' ? 'checked' : '' }}>
                                <label class="form-check-label ps-2 small text-dark" for="fmt_seq">
                                    <strong>Prefix + Continuous Sequence</strong>
                                    <span class="d-block text-muted font-monospace" style="font-size: 0.72rem;">Example: INV-0001 (Sequential global numbering)</span>
                                </label>
                            </div>
                        </div>

                        <div>
                            <label class="form-label small fw-semibold" for="printer_type">Thermal Printer Standard</label>
                            <input type="text" id="printer_type" name="printer_type" class="form-control form-control-sm" value="{{ old('printer_type', $settings['printer_type']) }}">
                            <div class="form-text" style="font-size: 0.72rem;">ESC/POS 80mm High-Speed Thermal, 58mm Receipt, or Standard Laser A4.</div>
                        </div>
                    </x-card>
                </div>

                <div class="col-12 col-lg-6">
                    <x-card title="Receipt Customization & Peripherals">
                        <div class="mb-3">
                            <label class="form-label small fw-semibold" for="receipt_footer">{{ __('messages.receipt_footer') }}</label>
                            <textarea id="receipt_footer" name="receipt_footer" class="form-control form-control-sm" rows="4">{{ old('receipt_footer', $settings['receipt_footer']) }}</textarea>
                            <div class="form-text" style="font-size: 0.72rem;">Printed at the bottom of customer receipts, return policies, social media.</div>
                        </div>

                        <div class="d-flex align-items-center justify-content-between p-3 border rounded-3 bg-light">
                            <div>
                                <div class="fw-semibold text-dark small">{{ __('messages.cash_drawer_kick') }}</div>
                                <div class="text-muted" style="font-size: 0.72rem;">Sends 24V RJ-11 signal to open cash drawer on cash checkout completion.</div>
                            </div>
                            <div class="form-check form-switch mb-0">
                                <input class="form-check-input" type="checkbox" id="cash_drawer_kick" name="cash_drawer_kick" value="1" {{ ($settings['cash_drawer_kick'] ?? '1') == '1' ? 'checked' : '' }}>
                            </div>
                        </div>
                    </x-card>
                </div>
            </div>
        </div>

        <!-- 4. Inventory Rules -->
        <div class="tab-pane fade" id="tab-inventory" role="tabpanel">
            <div class="row g-4">
                <div class="col-12 col-lg-6">
                    <x-card title="Stock Thresholds & Restock Triggers">
                        <div class="mb-3">
                            <label class="form-label small fw-semibold" for="default_low_stock_threshold">{{ __('messages.default_low_stock') }} <span class="text-danger">*</span></label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-light border-end-0"><i class="bi bi-bell text-warning"></i></span>
                                <input type="number" min="1" max="10000" id="default_low_stock_threshold" name="default_low_stock_threshold" class="form-control ps-2 font-monospace" value="{{ old('default_low_stock_threshold', $settings['default_low_stock_threshold']) }}" required>
                                <span class="input-group-text bg-light border-start-0">units</span>
                            </div>
                            <div class="form-text" style="font-size: 0.72rem;">Default minimum balance assigned when creating new skincare SKUs.</div>
                        </div>

                        <div class="p-3 border rounded-3 bg-light">
                            <div class="fw-semibold text-dark small mb-1"><i class="bi bi-info-circle text-primary me-1"></i> Automatic Notifications</div>
                            <p class="text-muted small mb-0" style="font-size: 0.78rem;">
                                Products reaching or falling below their threshold will automatically trigger a red counter badge in the top navigation bell, appear in Low-Stock reports, and display warning badges in POS catalog searches.
                            </p>
                        </div>
                    </x-card>
                </div>

                <div class="col-12 col-lg-6">
                    <x-card title="Configured Branches & Locations">
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered align-middle mb-0" style="font-size: 0.8rem;">
                                <thead class="table-light">
                                    <tr>
                                        <th>Location Identifier</th>
                                        <th>Branch Description</th>
                                        <th class="text-center">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach(config('inventory.branches', []) as $code => $label)
                                    <tr>
                                        <td class="font-monospace fw-semibold">{{ $code }}</td>
                                        <td>{{ $label }}</td>
                                        <td class="text-center"><span class="badge bg-success-subtle text-success">Active</span></td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </x-card>
                </div>
            </div>
        </div>

        <!-- 5. Shift & Attendance Policies -->
        <div class="tab-pane fade" id="tab-attendance" role="tabpanel">
            <div class="row g-4">
                <div class="col-12 col-lg-6">
                    <x-card title="Boutique Working Hours & Punctuality">
                        <div class="row g-3 mb-3">
                            <div class="col-6">
                                <label class="form-label small fw-semibold" for="work_start_time">{{ __('messages.work_start_time') }} <span class="text-danger">*</span></label>
                                <input type="time" id="work_start_time" name="work_start_time" class="form-control form-control-sm font-monospace" value="{{ old('work_start_time', $settings['work_start_time']) }}" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label small fw-semibold" for="work_end_time">{{ __('messages.work_end_time') }} <span class="text-danger">*</span></label>
                                <input type="time" id="work_end_time" name="work_end_time" class="form-control form-control-sm font-monospace" value="{{ old('work_end_time', $settings['work_end_time']) }}" required>
                            </div>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-6">
                                <label class="form-label small fw-semibold" for="late_threshold_time">{{ __('messages.late_threshold_time') }} <span class="text-danger">*</span></label>
                                <input type="time" id="late_threshold_time" name="late_threshold_time" class="form-control form-control-sm font-monospace" value="{{ old('late_threshold_time', $settings['late_threshold_time']) }}" required>
                                <div class="form-text" style="font-size: 0.72rem;">Check-ins after this time are automatically classified as <strong>Late</strong>.</div>
                            </div>
                            <div class="col-6">
                                <label class="form-label small fw-semibold" for="standard_daily_hours">{{ __('messages.standard_daily_hours') }} <span class="text-danger">*</span></label>
                                <div class="input-group input-group-sm">
                                    <input type="number" step="0.5" min="1" max="24" id="standard_daily_hours" name="standard_daily_hours" class="form-control font-monospace" value="{{ old('standard_daily_hours', $settings['standard_daily_hours']) }}" required>
                                    <span class="input-group-text bg-light">hours</span>
                                </div>
                                <div class="form-text" style="font-size: 0.72rem;">Baseline duty hours for full shift attendance calculations.</div>
                            </div>
                        </div>
                    </x-card>
                </div>

                <div class="col-12 col-lg-6">
                    <x-card title="HR & Attendance Rules">
                        <div class="p-3 border rounded-3 bg-light mb-3">
                            <div class="fw-semibold text-dark small mb-1"><i class="bi bi-person-check-fill text-success me-1"></i> Attendance Enforcement</div>
                            <p class="text-muted small mb-0" style="font-size: 0.78rem;">
                                The attendance module enforces unique daily check-in validation per staff member. Check-out timestamps are strictly validated to occur after clock-in times.
                            </p>
                        </div>

                        <div class="p-3 border rounded-3">
                            <div class="fw-semibold text-dark small mb-2">Shift Policy Summary</div>
                            <ul class="list-unstyled mb-0 text-muted small" style="font-size: 0.78rem; line-height: 1.6;">
                                <li><i class="bi bi-check2 text-success me-1"></i> Standard Shift: {{ $settings['work_start_time'] }} &ndash; {{ $settings['work_end_time'] }}</li>
                                <li><i class="bi bi-clock-history text-warning me-1"></i> Grace Period Cutoff: {{ $settings['late_threshold_time'] }}</li>
                                <li><i class="bi bi-hourglass-split text-primary me-1"></i> Standard Daily Shift: {{ $settings['standard_daily_hours'] }} duty hours</li>
                            </ul>
                        </div>
                    </x-card>
                </div>
            </div>
        </div>

        <!-- 6. Roles & User Security -->
        <div class="tab-pane fade" id="tab-roles" role="tabpanel">
            <div class="row g-4">
                <div class="col-12 col-lg-8">
                    <x-card title="Role Matrix & Module Permissions">
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered align-middle mb-0" style="font-size: 0.8rem;">
                                <thead class="table-light">
                                    <tr>
                                        <th>Role</th>
                                        <th>Scope & Permissions</th>
                                        <th class="text-center">Staff Count</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td><span class="badge-role-pill badge-admin">Administrator</span></td>
                                        <td>Full, unrestricted access across all modules, user administration, financial analytics, and system settings.</td>
                                        <td class="text-center fw-bold">{{ $userRoleCounts['admin'] ?? 0 }}</td>
                                    </tr>
                                    <tr>
                                        <td><span class="badge-role-pill badge-manager">Store Manager</span></td>
                                        <td>Operational authority: Catalog, promotions, inventory logistics, sales returns, and business reports.</td>
                                        <td class="text-center fw-bold">{{ $userRoleCounts['manager'] ?? 0 }}</td>
                                    </tr>
                                    <tr>
                                        <td><span class="badge-role-pill badge-cashier">Cashier</span></td>
                                        <td>Frontend retail: POS terminal, barcode scanning, customer checkout, receipt reprinting, and CRM lookup.</td>
                                        <td class="text-center fw-bold">{{ $userRoleCounts['cashier'] ?? 0 }}</td>
                                    </tr>
                                    <tr>
                                        <td><span class="badge-role-pill badge-inventory">Inventory Officer</span></td>
                                        <td>Warehouse and stock room: Receiving (Stock In), stock-outs, inter-branch transfers, damaged write-offs.</td>
                                        <td class="text-center fw-bold">{{ $userRoleCounts['inventory'] ?? 0 }}</td>
                                    </tr>
                                    <tr>
                                        <td><span class="badge-role-pill badge-hr">HR Specialist</span></td>
                                        <td>Staff management: Employees directory, positions and salary records, daily and monthly attendance audits.</td>
                                        <td class="text-center fw-bold">{{ $userRoleCounts['hr'] ?? 0 }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </x-card>
                </div>

                <div class="col-12 col-lg-4">
                    <x-card title="User Administration">
                        <div class="p-3 border rounded-3 bg-light text-center mb-3">
                            <div class="display-6 fw-bold text-dark">{{ $userRoleCounts['total'] ?? 0 }}</div>
                            <div class="text-muted small">Total User Accounts</div>
                            <div class="badge bg-success-subtle text-success mt-2">{{ $userRoleCounts['active'] ?? 0 }} Active Accounts</div>
                        </div>

                        <p class="text-muted small" style="font-size: 0.78rem;">
                            User accounts, passwords, role changes, and account activation statuses are securely managed in the User Management console.
                        </p>

                        <a href="{{ route('users.index') }}" class="btn btn-sm btn-skincare-outline w-100 d-flex align-items-center justify-content-center gap-2">
                            <i class="bi bi-shield-lock"></i>
                            <span>Open User Management</span>
                        </a>
                    </x-card>
                </div>
            </div>
        </div>
    </div>
</form>
@endsection
