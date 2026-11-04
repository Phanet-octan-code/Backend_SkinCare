<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LanguageController extends Controller
{
    /**
     * Switch application language between English ('en'), Khmer ('km'), and Chinese ('zh').
     */
    public function switch(string $locale): RedirectResponse
    {
        if (in_array($locale, ['en', 'km', 'zh'], true)) {
            session(['locale' => $locale]);
            cookie()->queue(cookie('app_locale', $locale, 60 * 24 * 365, null, null, false, false));
        }

        return redirect()->back();
    }
}
