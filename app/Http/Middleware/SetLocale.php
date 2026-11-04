<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Handle an incoming request and set application locale from session, cookie, or header.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $allowedLocales = ['en', 'km', 'zh'];

        // 1. Session first
        $locale = session('locale');

        // 2. Cookie fallback
        if (!$locale || !in_array($locale, $allowedLocales, true)) {
            $locale = $request->cookie('app_locale');
        }

        // 3. Header fallback (e.g. for API or AJAX)
        if (!$locale || !in_array($locale, $allowedLocales, true)) {
            $headerLocale = strtolower(substr($request->header('X-Locale', $request->header('Accept-Language', '')), 0, 2));
            if (in_array($headerLocale, $allowedLocales, true)) {
                $locale = $headerLocale;
            }
        }

        // 4. Default to app.locale or 'en'
        if (!$locale || !in_array($locale, $allowedLocales, true)) {
            $locale = config('app.locale', 'en');
        }

        App::setLocale($locale);

        return $next($request);
    }
}
