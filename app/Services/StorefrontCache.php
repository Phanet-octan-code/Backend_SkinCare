<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class StorefrontCache
{
    public const DEFAULT_TTL = 300; // 5 minutes

    /**
     * Get the current global cache version for storefront catalog.
     */
    public static function version(): int
    {
        return (int) Cache::get('storefront_catalog_version', 1);
    }

    /**
     * Increment the catalog version to instantly invalidate all cached product queries.
     */
    public static function bust(): void
    {
        try {
            Cache::increment('storefront_catalog_version');
        } catch (\Throwable $e) {
            Cache::put('storefront_catalog_version', time(), 86400 * 30);
        }

        Cache::forget('storefront_categories');
        Cache::forget('storefront_brands');
        Cache::forget('storefront_promotions');
        Cache::forget('storefront_featured');
        Cache::forget('storefront_stats');
        Cache::forget('storefront_api_settings');
    }

    /**
     * Generate versioned cache key.
     */
    public static function key(string $prefix, array $params = []): string
    {
        $v = self::version();
        if (empty($params)) {
            return "{$prefix}_v{$v}";
        }
        // Normalize params for deterministic hashing
        ksort($params);
        $hash = md5(json_encode($params));
        return "{$prefix}_v{$v}_{$hash}";
    }
}

