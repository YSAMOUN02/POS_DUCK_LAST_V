<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applies the language the user picked (stored in the session by
 * LocaleController) to every request. Anything not in SUPPORTED falls back to
 * the app default, so a stale or hand-edited session value can't break
 * rendering.
 */
class SetLocale
{
    /** Locale code => native label shown in the switcher. */
    public const SUPPORTED = [
        'en' => 'English',
        'km' => 'ខ្មែរ',
    ];

    public const SESSION_KEY = 'app_locale';

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->session()->get(self::SESSION_KEY);

        if (is_string($locale) && array_key_exists($locale, self::SUPPORTED)) {
            app()->setLocale($locale);
        }

        return $next($request);
    }
}
