<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LanguageController extends Controller
{
    /**
     * Switch application language between English ('en') and Khmer ('km').
     */
    public function switch(string $locale): RedirectResponse
    {
        if (in_array($locale, ['en', 'km'], true)) {
            session(['locale' => $locale]);
        }

        return redirect()->back();
    }
}
