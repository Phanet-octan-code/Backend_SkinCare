@php
    $bizName = \App\Models\Setting::get('business_name', 'ÉLIXIR BOTANIQUE');
    $bizAddress = \App\Models\Setting::get('business_address', '128 Rue Pasteur, Suite 04');
    $bizPhone = \App\Models\Setting::get('business_phone', '+855 23 888 999');
    $bizLogo = \App\Models\Setting::get('business_logo');
    $receiptFooter = \App\Models\Setting::get('receipt_footer', "Thank you for shopping at Élixir Botanique Dispensary!\nPlease retain receipt for exchange within 14 days.\nVisit us online: www.elixirbotanique.com");
    $saleCurrency = $sale->currency ?? 'USD';
    $rateKhr = (float) ($sale->exchange_rate > 0 ? $sale->exchange_rate : \App\Models\Setting::get('exchange_rate_khr', 4100.0));
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Receipt #{{ $sale->invoice_number }} — {{ $bizName }}</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: #F7F8F5;
            color: #252B25;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 24px 16px;
            min-height: 100vh;
        }

        .receipt-container {
            width: 100%;
            max-width: 380px;
            background: #FFFFFF;
            border: 1px solid #E6E9E2;
            border-radius: 12px;
            padding: 24px 20px;
            box-shadow: 0 4px 14px rgba(37, 43, 37, 0.05);
            position: relative;
        }

        .receipt-brand-icon {
            width: 44px;
            height: 44px;
            border-radius: 10px;
            background-color: #EBF0E9;
            color: #71856B;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
            margin-bottom: 8px;
        }

        .text-center { text-align: center; }
        .text-end { text-align: right; }
        .text-muted { color: #777F76; }
        .fw-bold { font-weight: 700; }
        .font-mono { font-family: 'Space Mono', monospace; }

        .divider {
            border-top: 1px dashed #D6DBD2;
            margin: 12px 0;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            font-size: 0.8rem;
            margin-bottom: 4px;
        }

        .receipt-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.82rem;
            margin: 8px 0;
        }

        .receipt-table th {
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #777F76;
            padding: 6px 0;
            border-bottom: 1px solid #E6E9E2;
        }

        .receipt-table td {
            padding: 8px 0;
            border-bottom: 1px solid #F0F3ED;
            vertical-align: top;
        }

        .totals-section {
            font-size: 0.82rem;
            margin-top: 8px;
        }

        .total-highlight {
            font-size: 1.05rem;
            font-weight: 700;
            color: #252B25;
            padding-top: 6px;
            border-top: 1px solid #252B25;
            margin-top: 6px;
        }

        .btn-action-group {
            display: flex;
            gap: 12px;
            width: 100%;
            max-width: 380px;
            margin-top: 18px;
        }

        .btn-print {
            flex: 1;
            padding: 10px;
            background-color: #71856B;
            color: #FFFFFF;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.9rem;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            text-decoration: none;
            transition: background 0.15s ease;
        }

        .btn-print:hover {
            background-color: #596D54;
        }

        .btn-back {
            flex: 1;
            padding: 10px;
            background-color: #FFFFFF;
            color: #252B25;
            border: 1px solid #D6DBD2;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.9rem;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            text-decoration: none;
            transition: all 0.15s ease;
        }

        .btn-back:hover {
            background-color: #F7F8F5;
        }

        /* 80mm Thermal Printer Media Query */
        @media print {
            body {
                background: #FFFFFF;
                padding: 0;
                margin: 0;
            }
            .receipt-container {
                border: none;
                box-shadow: none;
                max-width: 100%;
                width: 78mm;
                padding: 4mm 2mm;
            }
            .btn-action-group {
                display: none !important;
            }
        }
    </style>
</head>
<body>

<div class="receipt-container">
    <!-- Header / Boutique Identity -->
    <div class="text-center">
        @if(!empty($bizLogo) && file_exists(public_path($bizLogo)))
            <img src="{{ asset($bizLogo) }}" alt="Logo" class="rounded mb-2" style="max-height: 44px; object-fit: contain;">
        @else
            <div class="receipt-brand-icon">
                <i class="bi bi-flower1"></i>
            </div>
        @endif
        <h2 style="font-size: 1.15rem; font-weight: 700; color: #252B25; letter-spacing: -0.3px;">{{ strtoupper($bizName) }}</h2>
        <p class="text-muted" style="font-size: 0.74rem;">Luxury Retail & Dermal Care</p>
        <p class="text-muted" style="font-size: 0.7rem; margin-top: 2px;">{{ $bizAddress }} &bull; Tel: {{ $bizPhone }}</p>
    </div>

    <div class="divider"></div>

    <!-- Transaction Audit Meta -->
    <div class="info-row">
        <span class="text-muted">Invoice #:</span>
        <span class="fw-bold font-mono">{{ $sale->invoice_number }}</span>
    </div>
    <div class="info-row">
        <span class="text-muted">Date & Time:</span>
        <span>{{ $sale->created_at->format('d M Y, h:i A') }}</span>
    </div>
    <div class="info-row">
        <span class="text-muted">Cashier:</span>
        <span class="fw-bold">{{ $sale->user->name ?? 'POS Register' }}</span>
    </div>
    <div class="info-row">
        <span class="text-muted">Customer:</span>
        <span>
            @if($sale->customer)
                <span class="fw-bold">{{ $sale->customer->name }}</span>
                <span style="font-size: 0.68rem; color: #71856B;">({{ $sale->customer->membership_level }})</span>
            @else
                <span class="text-muted">Walk-in Guest</span>
            @endif
        </span>
    </div>

    <div class="divider"></div>

    <!-- Itemized Formulas -->
    <table class="receipt-table">
        <thead>
            <tr>
                <th style="width: 55%;">Item</th>
                <th style="width: 15%; text-align: center;">Qty</th>
                <th style="width: 30%;" class="text-end">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($sale->details as $item)
            <tr>
                <td>
                    <div class="fw-bold" style="color: #252B25;">{{ $item->product->name ?? 'Custom Product' }}</div>
                    <div class="text-muted font-mono" style="font-size: 0.68rem;">{{ $item->product->barcode ?? '' }}</div>
                    @if($item->discount > 0)
                        <div style="font-size: 0.68rem; color: #D9534F;">Disc: -{{ $sale->formatAmount($item->discount) }}</div>
                    @endif
                </td>
                <td style="text-align: center;">{{ $item->qty }}</td>
                <td class="text-end font-mono">
                    {{ $sale->formatAmount($item->subtotal) }}
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <!-- Totals Summary -->
    <div class="totals-section">
        <div class="info-row">
            <span class="text-muted">Subtotal:</span>
            <span class="font-mono">{{ $sale->formatAmount($sale->subtotal) }}</span>
        </div>
        @if($sale->discount > 0)
        <div class="info-row" style="color: #D9534F;">
            <span>Total Discounts:</span>
            <span class="font-mono">-{{ $sale->formatAmount($sale->discount) }}</span>
        </div>
        @endif
        <div class="info-row">
            <span class="text-muted">VAT / Tax:</span>
            <span class="font-mono">{{ $sale->formatAmount($sale->tax) }}</span>
        </div>
        <div class="info-row total-highlight">
            <span>FINAL TOTAL:</span>
            <span class="font-mono">{{ $sale->formatAmount($sale->final_total) }}</span>
        </div>
        @if($saleCurrency === 'USD')
        <div class="info-row text-muted" style="font-size: 0.74rem; margin-top: 4px;">
            <span>KHR Dual Total (Rate: {{ number_format($rateKhr, 0) }}):</span>
            <span class="font-mono fw-bold">{{ number_format(round($sale->final_total * $rateKhr), 0) }} ៛</span>
        </div>
        @elseif($saleCurrency === 'KHR')
        <div class="info-row text-muted" style="font-size: 0.74rem; margin-top: 4px;">
            <span>USD Equivalent (Rate: {{ number_format($rateKhr, 0) }}):</span>
            <span class="font-mono fw-bold">${{ number_format($sale->final_total / $rateKhr, 2) }}</span>
        </div>
        @endif
    </div>

    <div class="divider"></div>

    <!-- Payment Breakdown -->
    <div class="totals-section">
        <div class="info-row">
            <span class="text-muted">Payment Method:</span>
            <span class="fw-bold" style="text-transform: uppercase;">{{ $sale->payment_type }}</span>
        </div>
        @if(strtolower($sale->payment_type) === 'cash')
        <div class="info-row">
            <span class="text-muted">Cash Tendered:</span>
            <span class="font-mono">{{ $sale->formatAmount($sale->final_total) }}</span>
        </div>
        <div class="info-row">
            <span class="text-muted">Change:</span>
            <span class="font-mono">{{ $sale->formatAmount(0.00) }}</span>
        </div>
        @endif
        <div class="info-row">
            <span class="text-muted">Payment Status:</span>
            <span style="font-weight: 700; color: #2E7D32; text-transform: uppercase;">{{ $sale->payment_status }}</span>
        </div>
    </div>

    <div class="divider"></div>

    <!-- Barcode & Footer Note -->
    <div class="text-center" style="margin-top: 10px;">
        <div class="font-mono" style="font-size: 0.72rem; letter-spacing: 2px; color: #777F76; margin-bottom: 6px;">
            *{{ $sale->invoice_number }}*
        </div>
        <p style="font-size: 0.72rem; color: #777F76; line-height: 1.4; white-space: pre-line;">
            {{ $receiptFooter }}
        </p>
    </div>
</div>

<!-- On-Screen Control Buttons (Hidden when printing) -->
<div class="btn-action-group">
    <button type="button" class="btn-print" onclick="window.print()">
        <i class="bi bi-printer"></i> Print Receipt
    </button>
    <a href="{{ route('pos.index') }}" class="btn-back">
        <i class="bi bi-cart3"></i> Back to POS
    </a>
</div>

</body>
</html>
