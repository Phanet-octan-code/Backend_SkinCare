<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\View\View;

class SettingController extends Controller
{
    /**
     * Display the full System & Store Settings management dashboard.
     * Authorized for: Administrator only.
     */
    public function index(): View
    {
        $settings = Setting::getAll();

        // Calculate role breakdown for Role & Security overview
        $userRoleCounts = [
            'admin' => User::where('role', 'admin')->count(),
            'manager' => User::where('role', 'manager')->count(),
            'cashier' => User::where('role', 'cashier')->count(),
            'inventory' => User::where('role', 'inventory')->count(),
            'hr' => User::where('role', 'hr')->count(),
            'total' => User::count(),
            'active' => User::where('status', 'active')->count(),
        ];

        return view('settings.index', [
            'settings' => $settings,
            'userRoleCounts' => $userRoleCounts,
        ]);
    }

    /**
     * Store and persist updated system and store settings.
     * Authorized for: Administrator only.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            // Boutique Identity
            'business_name' => ['required', 'string', 'max:200'],
            'business_address' => ['required', 'string', 'max:500'],
            'business_phone' => ['required', 'string', 'max:50'],
            'business_email' => ['required', 'email', 'max:100'],
            'business_logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,svg', 'max:2048'],

            // Financial & Currency
            'currency' => ['required', 'string', 'in:USD,KHR'],
            'exchange_rate_khr' => ['required', 'numeric', 'min:1'],
            'tax_rate' => ['required', 'numeric', 'min:0', 'max:100'],

            // Invoicing & Hardware
            'invoice_prefix' => ['required', 'string', 'max:20'],
            'invoice_number_format' => ['required', 'string', 'in:prefix_date_seq,prefix_seq'],
            'receipt_footer' => ['nullable', 'string', 'max:1000'],
            'printer_type' => ['nullable', 'string', 'max:100'],
            'cash_drawer_kick' => ['nullable', 'boolean'],

            // Inventory
            'default_low_stock_threshold' => ['required', 'integer', 'min:1', 'max:10000'],

            // Shift & Attendance Policies
            'work_start_time' => ['required', 'string'],
            'work_end_time' => ['required', 'string'],
            'late_threshold_time' => ['required', 'string'],
            'standard_daily_hours' => ['required', 'numeric', 'min:1', 'max:24'],
        ]);

        // Process logo upload if submitted
        if ($request->hasFile('business_logo')) {
            $logoFile = $request->file('business_logo');
            $logoName = 'logo_' . time() . '_' . uniqid() . '.' . $logoFile->getClientOriginalExtension();
            $destinationDir = public_path('images/branding');

            if (!File::isDirectory($destinationDir)) {
                File::makeDirectory($destinationDir, 0755, true, true);
            }

            $logoFile->move($destinationDir, $logoName);
            Setting::set('business_logo', 'images/branding/' . $logoName, 'business');
        }

        // Persist Business Settings
        Setting::set('business_name', $validated['business_name'], 'business');
        Setting::set('business_address', $validated['business_address'], 'business');
        Setting::set('business_phone', $validated['business_phone'], 'business');
        Setting::set('business_email', $validated['business_email'], 'business');

        // Persist Currency & Finance
        Setting::set('currency', $validated['currency'], 'finance');
        Setting::set('exchange_rate_khr', number_format((float) $validated['exchange_rate_khr'], 2, '.', ''), 'finance');
        Setting::set('tax_rate', number_format((float) $validated['tax_rate'], 2, '.', ''), 'finance');

        // Persist Invoicing & Hardware
        Setting::set('invoice_prefix', $validated['invoice_prefix'], 'invoicing');
        Setting::set('invoice_number_format', $validated['invoice_number_format'], 'invoicing');
        Setting::set('receipt_footer', $validated['receipt_footer'] ?? '', 'invoicing');
        Setting::set('printer_type', $validated['printer_type'] ?? '80mm Thermal Receipt (ESC/POS)', 'hardware');
        Setting::set('cash_drawer_kick', $request->has('cash_drawer_kick') ? '1' : '0', 'hardware');

        // Persist Inventory Rules
        Setting::set('default_low_stock_threshold', (string) $validated['default_low_stock_threshold'], 'inventory');

        // Persist Shift & Attendance
        Setting::set('work_start_time', $validated['work_start_time'], 'attendance');
        Setting::set('work_end_time', $validated['work_end_time'], 'attendance');
        Setting::set('late_threshold_time', $validated['late_threshold_time'], 'attendance');
        Setting::set('standard_daily_hours', (string) $validated['standard_daily_hours'], 'attendance');

        return redirect()->route('settings.index')
            ->with('success', __('messages.settings_updated_success'));
    }
}
