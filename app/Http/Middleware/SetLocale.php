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

        // 1. Explicit query parameter (?lang=km or ?locale=km)
        $queryLocale = strtolower((string) ($request->query('lang') ?? $request->query('locale') ?? ''));
        if (in_array($queryLocale, $allowedLocales, true)) {
            $locale = $queryLocale;
            if ($request->hasSession()) {
                session(['locale' => $locale]);
            }
        } else {
            // 2. Session
            $locale = $request->hasSession() ? session('locale') : null;

            // 3. Cookie fallback
            if (!$locale || !in_array($locale, $allowedLocales, true)) {
                $locale = $request->cookie('app_locale');
            }

            // 4. Header fallback (e.g. X-Locale or Accept-Language)
            if (!$locale || !in_array($locale, $allowedLocales, true)) {
                $rawHeader = $request->header('X-Locale', $request->header('Accept-Language', ''));
                $headerLocale = strtolower(substr($rawHeader, 0, 2));
                if (in_array($headerLocale, $allowedLocales, true)) {
                    $locale = $headerLocale;
                }
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
