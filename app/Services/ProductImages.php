<?php

namespace App\Services;

/**
 * Small WebP thumbnails for product pictures.
 *
 * The product pickers drew every card from the ORIGINAL upload — camera photos
 * up to 2400px and 1.4 MB — to fill a ~150px tile, and redrew the whole grid on
 * every stock refresh. A 360px WebP is a few dozen KB, so a tab of products is a
 * fraction of the download, and the browser no longer has to shrink a
 * multi-megapixel image every time the grid repaints.
 *
 * It also fixes caching for the legacy uploads saved with NO file extension
 * (the old rand(1,100) . '-' . $name naming). Apache chooses its Expires rule
 * by MIME type, which it takes from the extension, so those files were served
 * with no cache header at all. Every thumbnail ends in .webp.
 *
 * Thumbnails are a cache: if one cannot be made, callers get null and fall back
 * to the original image. Nothing here is allowed to break a page.
 */
class ProductImages
{
    public const SOURCE_DIR = 'assets/startic_img';
    public const THUMB_DIR  = 'assets/startic_img/thumbs';

    /** Longest edge. Covers a ~150px tile at 2x pixel density, with headroom. */
    public const MAX_EDGE = 360;

    public const QUALITY = 78;

    /**
     * Missing thumbnails made per request. Production images were uploaded on
     * the server and have no thumbnails yet; this lets them fill in over the
     * first few page loads without any one request stalling on a full backfill.
     * `php artisan images:thumbnails` does the whole set in one go instead.
     */
    private const LAZY_BUDGET = 6;

    /** Refuse anything larger — decoding it would need hundreds of MB. */
    private const MAX_SOURCE_PIXELS = 40_000_000;

    private static int $budget = self::LAZY_BUDGET;

    /**
     * Root-relative thumbnail URL with a ?v=<mtime> stamp, or null to mean
     * "use the original".
     *
     * The stamp matters because images are cached for 30 days: a regenerated
     * thumbnail must be a NEW url, or browsers keep showing the old picture.
     */
    public static function url(?string $image, bool $lazy = true): ?string
    {
        if (! self::isSafeName($image)) {
            return null;
        }

        $thumb = self::thumbPath($image);

        if (! is_file($thumb) || self::isStale($image)) {
            // Source checked BEFORE spending budget: a product whose picture is
            // missing from disk would otherwise burn a slot on every request,
            // never succeed, and starve the products that could be done.
            if (! $lazy || self::$budget <= 0 || ! is_file(self::sourcePath($image))) {
                return null;
            }
            self::$budget--;

            if (! self::make($image)) {
                return null;
            }
        }

        $stamp = @filemtime($thumb);

        return '/' . self::THUMB_DIR . '/' . rawurlencode(self::thumbName($image))
            . ($stamp ? '?v=' . $stamp : '');
    }

    /** Build (or rebuild) one thumbnail. True when a usable file exists after. */
    public static function make(string $image): bool
    {
        if (! self::isSafeName($image) || ! function_exists('imagewebp')) {
            return false;
        }

        $source = self::sourcePath($image);
        if (! is_file($source)) {
            return false;
        }

        // Type from the CONTENT, never the name: 28 legacy files have no
        // extension to go on.
        $info = @getimagesize($source);
        if (! $info || $info[0] < 1 || $info[1] < 1) {
            return false;
        }

        [$w, $h, $type] = $info;
        if ($w * $h > self::MAX_SOURCE_PIXELS) {
            return false;
        }

        $img = match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($source),
            IMAGETYPE_PNG  => @imagecreatefrompng($source),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($source) : false,
            IMAGETYPE_GIF  => @imagecreatefromgif($source),
            default        => false,
        };
        if (! $img) {
            return false;
        }

        // Phone photos are often stored sideways with an EXIF flag saying how to
        // turn them. Browsers honour that flag on the original; GD ignores it,
        // so without this the thumbnail would come out rotated.
        if ($type === IMAGETYPE_JPEG) {
            $img = self::applyExifOrientation($img, $source);
            $w = imagesx($img);
            $h = imagesy($img);
        }

        $scale = min(1, self::MAX_EDGE / max($w, $h));   // shrink only, never enlarge
        $tw = max(1, (int) round($w * $scale));
        $th = max(1, (int) round($h * $scale));

        $thumb = imagecreatetruecolor($tw, $th);

        // Keep transparency for PNG / WebP / GIF cut-outs.
        imagealphablending($thumb, false);
        imagesavealpha($thumb, true);
        imagefill($thumb, 0, 0, imagecolorallocatealpha($thumb, 0, 0, 0, 127));

        imagecopyresampled($thumb, $img, 0, 0, 0, 0, $tw, $th, $w, $h);
        // No imagedestroy(): GD images are objects since PHP 8.0 and are freed
        // when they go out of scope; the call is a deprecated no-op.

        $dir = public_path(self::THUMB_DIR);
        if (! is_dir($dir) && ! @mkdir($dir, 0755, true) && ! is_dir($dir)) {
            return false;
        }

        // Write beside the target, then rename: two terminals loading the same
        // grid at once must never see a half-written file.
        $target = self::thumbPath($image);
        $tmp = $target . '.' . bin2hex(random_bytes(4)) . '.tmp';

        $ok = @imagewebp($thumb, $tmp, self::QUALITY);

        if (! $ok || ! @rename($tmp, $target)) {
            @unlink($tmp);
            return false;
        }

        return true;
    }

    /** Remove a thumbnail, e.g. when its product's image is replaced. */
    public static function forget(?string $image): void
    {
        if (self::isSafeName($image)) {
            @unlink(self::thumbPath($image));
        }
    }

    public static function thumbName(string $image): string
    {
        // The full original name plus .webp, not a swapped extension:
        // "FG-1.jpg" and "FG-1.png" would otherwise collide on one thumbnail.
        // Plain concatenation also avoids pathinfo(), which is locale-aware
        // and can mangle the Khmer file names this shop uses.
        return $image . '.webp';
    }

    private static function sourcePath(string $image): string
    {
        return public_path(self::SOURCE_DIR . '/' . $image);
    }

    private static function thumbPath(string $image): string
    {
        return public_path(self::THUMB_DIR . '/' . self::thumbName($image));
    }

    /** The original was replaced under the same name since the thumb was made. */
    private static function isStale(string $image): bool
    {
        $src = @filemtime(self::sourcePath($image));
        $tmb = @filemtime(self::thumbPath($image));

        return $src && $tmb && $src > $tmb;
    }

    /** A bare file name from our own upload code — never a path. */
    private static function isSafeName(?string $image): bool
    {
        return is_string($image)
            && $image !== ''
            && ! str_contains($image, '/')
            && ! str_contains($image, '\\')
            && ! str_contains($image, '..');
    }

    private static function applyExifOrientation(\GdImage $img, string $source): \GdImage
    {
        if (! function_exists('exif_read_data')) {
            return $img;
        }

        $exif = @exif_read_data($source);
        $orientation = (int) ($exif['Orientation'] ?? 1);

        $rotated = match ($orientation) {
            3       => imagerotate($img, 180, 0),
            6       => imagerotate($img, -90, 0),
            8       => imagerotate($img, 90, 0),
            default => null,
        };

        if ($rotated) {
            return $rotated;
        }

        return $img;
    }
}
