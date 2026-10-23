<?php

/*
 * Router for `php artisan serve`.
 *
 * Laravel uses this file instead of its stock router whenever it exists
 * (Illuminate\Foundation\Console\ServeCommand::serverFile).
 *
 * Why it exists: the POS runs under `php artisan serve` on :8080. PHP's built-in
 * web server ignores public/.htaccess and public/web.config, and the stock router
 * hands it every static file — which it sends with a Content-Type and nothing
 * else: no Cache-Control, no Last-Modified, no ETag. With no freshness and nothing
 * to revalidate against, every product image, font and script was downloaded
 * again on every page load.
 *
 * Static assets are served here with caching headers instead:
 *   - URL carries ?v=…  (App\Services\Asset, ProductImages thumbnails)
 *       -> cached for a year as immutable. A changed file is a changed URL,
 *          so this can never serve a stale copy.
 *   - URL has no version
 *       -> no-cache + ETag/Last-Modified. The browser checks each time, but an
 *          unchanged file costs a tiny 304, not a download — and an unversioned
 *          script cannot go stale after a deploy.
 *
 * Everything else behaves exactly like Laravel's stock router.
 */

if (! function_exists('pos_static_mime')) {
    /** MIME type for a cacheable static asset, or null to leave it alone. */
    function pos_static_mime(string $file, string $uri): ?string
    {
        static $types = [
            'css'   => 'text/css; charset=UTF-8',
            'js'    => 'application/javascript; charset=UTF-8',
            'mjs'   => 'application/javascript; charset=UTF-8',
            'png'   => 'image/png',
            'jpg'   => 'image/jpeg',
            'jpeg'  => 'image/jpeg',
            'gif'   => 'image/gif',
            'webp'  => 'image/webp',
            'avif'  => 'image/avif',
            'svg'   => 'image/svg+xml',
            'ico'   => 'image/x-icon',
            'woff2' => 'font/woff2',
            'woff'  => 'font/woff',
            'ttf'   => 'font/ttf',
            'otf'   => 'font/otf',
            'eot'   => 'application/vnd.ms-fontobject',
        ];

        // A regex, not pathinfo(): pathinfo() is locale-aware and can mangle the
        // Khmer file names used for product pictures.
        if (preg_match('/\.([A-Za-z0-9]{1,5})$/', $file, $m)) {
            return $types[strtolower($m[1])] ?? null;
        }

        // Legacy product pictures were saved with no extension at all. Only
        // inside the product image folder, and only if the content really is an
        // image, is the type taken from the file itself.
        if (str_starts_with($uri, '/assets/startic_img/')) {
            $info = @getimagesize($file);

            return $info['mime'] ?? null;
        }

        return null;
    }
}

$publicPath = getcwd();

$uri = urldecode(
    parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? ''
);

if ($uri !== '/' && file_exists($publicPath.$uri)) {
    $file = realpath($publicPath.$uri);
    $root = realpath($publicPath);

    $inside = $file !== false && $root !== false
        && is_file($file)
        && str_starts_with($file, $root.DIRECTORY_SEPARATOR);

    $mime = $inside ? pos_static_mime($file, $uri) : null;

    if ($mime === null) {
        return false; // the built-in server serves it, exactly as before
    }

    $mtime = filemtime($file);
    $size = filesize($file);
    $etag = '"'.dechex($mtime).'-'.dechex($size).'"';

    header('Content-Type: '.$mime);
    header('Cache-Control: '.(isset($_GET['v'])
        ? 'public, max-age=31536000, immutable'
        : 'public, no-cache'));
    header('Last-Modified: '.gmdate('D, d M Y H:i:s', $mtime).' GMT');
    header('ETag: '.$etag);

    $ifNoneMatch = $_SERVER['HTTP_IF_NONE_MATCH'] ?? null;
    $ifModifiedSince = $_SERVER['HTTP_IF_MODIFIED_SINCE'] ?? null;

    // ETag wins when both are sent (RFC 9110 §13.2.2).
    $notModified = $ifNoneMatch !== null
        ? in_array($etag, array_map('trim', explode(',', $ifNoneMatch)), true)
        : ($ifModifiedSince !== null && strtotime($ifModifiedSince) >= $mtime);

    if ($notModified) {
        http_response_code(304);

        return true;
    }

    header('Content-Length: '.$size);

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'HEAD') {
        readfile($file);
    }

    return true;
}

// ---- unchanged from Laravel's stock router --------------------------------
$formattedDateTime = date('D M j H:i:s Y');

$requestMethod = $_SERVER['REQUEST_METHOD'];
$remoteAddress = $_SERVER['REMOTE_ADDR'].':'.$_SERVER['REMOTE_PORT'];

file_put_contents('php://stdout', "[$formattedDateTime] $remoteAddress [$requestMethod] URI: $uri\n");

require_once $publicPath.'/index.php';
