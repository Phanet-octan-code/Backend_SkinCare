<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'value',
        'group',
    ];

    /**
     * Default application settings fallback matrix.
     */
    public static function defaults(): array
    {
        return [
            // Business profile
            'business_name' => 'SkinCare Boutique — Luxury Spa & POS',
            'business_logo' => null,
            'business_address' => '128 Rue Pasteur, Suite 04, Phnom Penh',
            'business_phone' => '+855 23 888 999',
            'business_email' => 'contact@skincare-boutique.com',
            
            // Financial & Currency
            'currency' => 'USD',
            'exchange_rate_khr' => '4100.00',
            'tax_rate' => '5.00',
            
            // Invoicing & Receipts
            'invoice_prefix' => 'INV-',
            'invoice_number_format' => 'prefix_date_seq', // prefix_date_seq or prefix_seq
            'receipt_footer' => "Thank you for shopping at SkinCare POS Luxury Boutique!\nPlease retain receipt for exchange within 14 days.\nVisit us online: www.skincare-boutique.com",
            'printer_type' => '80mm Thermal Receipt (ESC/POS)',
            'cash_drawer_kick' => '1',
            
            // Inventory
            'default_low_stock_threshold' => '10',
            
            // Attendance & Shift Rules
            'work_start_time' => '09:00',
            'work_end_time' => '18:00',
            'late_threshold_time' => '09:15',
            'standard_daily_hours' => '8.0',
        ];
    }

    /**
     * Retrieve a setting value by key with cache and default fallback.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $settings = Cache::remember('app_settings_assoc', 86400, function () {
            return static::pluck('value', 'key')->toArray();
        });

        if (array_key_exists($key, $settings) && $settings[$key] !== null) {
            return $settings[$key];
        }

        $defaults = static::defaults();
        if (array_key_exists($key, $defaults)) {
            return $default !== null ? $default : $defaults[$key];
        }

        return $default;
    }

    /**
     * Set a setting value and invalidate the settings cache.
     */
    public static function set(string $key, mixed $value, string $group = 'general'): self
    {
        $setting = static::updateOrCreate(
            ['key' => $key],
            ['value' => is_array($value) ? json_encode($value) : (string) $value, 'group' => $group]
        );

        Cache::forget('app_settings_assoc');
        Cache::forget('storefront_api_settings');

        return $setting;
    }

    /**
     * Retrieve all settings with defaults merged.
     */
    public static function getAll(): array
    {
        $dbSettings = Cache::remember('app_settings_assoc', 86400, function () {
            return static::pluck('value', 'key')->toArray();
        });

        return array_merge(static::defaults(), $dbSettings);
    }

    /**
     * Currency Symbol Resolver.
     */
    public static function currencySymbol(?string $currency = null): string
    {
        $curr = strtoupper($currency ?: static::get('currency', 'USD'));
        return match ($curr) {
            'KHR' => '៛',
            'EUR' => '€',
            'GBP' => '£',
            default => '$',
        };
    }

    /**
     * Format a monetary amount consistently with USD and KHR rules.
     *
     * USD: 2 decimal places, e.g. "$12.50"
     * KHR: 0 decimal places rounded to whole riel, e.g. "51,250 ៛"
     */
    public static function formatMoney(float|int|string|null $amount, ?string $currency = null, bool $withSymbol = true): string
    {
        $amount = (float) ($amount ?? 0);
        $curr = strtoupper($currency ?: static::get('currency', 'USD'));

        if ($curr === 'KHR') {
            $rounded = round($amount);
            $formatted = number_format($rounded, 0);
            return $withSymbol ? $formatted . ' ៛' : $formatted;
        }

        $formatted = number_format($amount, 2);
        return $withSymbol ? '$' . $formatted : $formatted;
    }

    /**
     * Currency conversion utility between USD and KHR.
     */
    public static function convert(float $amount, string $from, string $to): float
    {
        $from = strtoupper($from);
        $to = strtoupper($to);
        $rate = (float) static::get('exchange_rate_khr', 4100.0);

        if ($from === $to) {
            return $amount;
        }

        if ($from === 'USD' && $to === 'KHR') {
            return round($amount * $rate);
        }

        if ($from === 'KHR' && $to === 'USD') {
            return round($amount / $rate, 2);
        }

        return $amount;
    }
}
