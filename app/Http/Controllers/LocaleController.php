<?php

namespace App\Http\Controllers;

use App\Http\Middleware\SetLocale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LocaleController extends Controller
{
    /**
     * Switches the UI language and returns the user to the page they were on,
     * so the switcher can sit in the header of any screen without needing to
     * know where it was clicked from.
     */
    public function switch(Request $request, string $locale): RedirectResponse
    {
        if (!array_key_exists($locale, SetLocale::SUPPORTED)) {
            return back();
        }

        $request->session()->put(SetLocale::SESSION_KEY, $locale);

        return back();
    }
}
