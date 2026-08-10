<?php

namespace App\Services;

/**
 * Cache-busted URL for a file in public/.
 *
 * Static assets are cached for 30 days (public/web.config and public/.htaccess),
 * so every asset URL carries ?v=<filemtime> — a changed file is a changed URL,
 * and the browser refetches it without anyone clearing anything.
 *
 * The layouts used to inline `filemtime(public_path(...))`. filemtime() raises a
 * warning and returns false when the file is missing, and Laravel turns warnings
 * into ErrorException, so ONE asset absent from a deployment took every page down
 * with a 500 — which is what happened to assets/icon/download.jpg, present here
 * but never committed. A stamp is an optimisation; it must never be able to break
 * a page, so a missing file degrades to the plain URL.
 */
class Asset
{
    public static function v(string $path): string
    {
        $path = ltrim($path, '/');
        $full = public_path($path);
        $url  = asset($path);

        // is_file() first: it answers false for a missing path without raising
        // the warning that filemtime() would.
        if (! is_file($full)) {
            return $url;
        }

        $stamp = @filemtime($full);

        return $stamp ? $url . '?v=' . $stamp : $url;
    }
}
