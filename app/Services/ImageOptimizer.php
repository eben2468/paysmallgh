<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Makes uploaded photos small enough for phones on mobile data:
 *  - shrinks anything wider/taller than MAX_EDGE px;
 *  - re-saves JPEGs at a sensible quality (only kept if actually smaller);
 *  - writes .webp and .avif copies next to the original, which picture()
 *    offers to browsers that understand them.
 *
 * Needs PHP's GD extension (AVIF needs PHP 8.1+ with libavif). Without GD it
 * quietly does nothing and the original file is served as before.
 */
final class ImageOptimizer
{
    public const MAX_EDGE = 1600;
    private const JPEG_QUALITY = 82;
    private const WEBP_QUALITY = 80;
    private const AVIF_QUALITY = 55;

    public static function available(): bool
    {
        return extension_loaded('gd') && function_exists('imagecreatefromstring');
    }

    /**
     * Optimise a file under /public by its public path ("uploads/x.jpg").
     * Returns a short report of what was written, for the CLI script.
     * @return list<string>
     */
    public static function optimize(string $publicPath, bool $force = false): array
    {
        if (!self::available()) {
            return [];
        }
        $file = BASE_PATH . '/public/' . ltrim($publicPath, '/');
        if (!is_file($file) || !preg_match('#^(.+)\.(jpe?g|png|webp)$#i', $file, $m)) {
            return [];
        }
        $base = $m[1];
        $ext = strtolower($m[2]);

        $info = @getimagesize($file);
        if (!$info || $info[0] * $info[1] > 40_000_000) {
            return [];
        }
        $img = @imagecreatefromstring((string) file_get_contents($file));
        if ($img === false) {
            return [];
        }
        $done = [];

        // 1. Shrink oversized photos, and re-save the original in place.
        $w = imagesx($img);
        $h = imagesy($img);
        if (max($w, $h) > self::MAX_EDGE) {
            $scale = self::MAX_EDGE / max($w, $h);
            $resized = imagescale($img, max(1, (int) round($w * $scale)), max(1, (int) round($h * $scale)), IMG_BICUBIC);
            if ($resized !== false) {
                imagedestroy($img);
                $img = $resized;
            }
        }
        if ($ext === 'png') {
            imagealphablending($img, false);
            imagesavealpha($img, true);
        }
        if ($ext === 'jpg' || $ext === 'jpeg') {
            imageinterlace($img, true); // progressive: a blurry preview shows while it loads
        }
        $tmp = $file . '.tmp';
        $saved = match ($ext) {
            'jpg', 'jpeg' => imagejpeg($img, $tmp, self::JPEG_QUALITY),
            'png' => imagepng($img, $tmp, 9),
            'webp' => function_exists('imagewebp') && imagewebp($img, $tmp, self::WEBP_QUALITY),
            default => false,
        };
        if ($saved && is_file($tmp) && filesize($tmp) > 0 && filesize($tmp) < filesize($file)) {
            rename($tmp, $file);
            $done[] = 'compressed';
        } elseif (is_file($tmp)) {
            @unlink($tmp);
        }

        // 2. Modern formats alongside (originals stay for older browsers).
        if ($ext !== 'webp') {
            if (function_exists('imagewebp') && ($force || !is_file($base . '.webp'))) {
                if (@imagewebp($img, $base . '.webp', self::WEBP_QUALITY)) {
                    $done[] = 'webp';
                }
            }
            if (function_exists('imageavif') && ($force || !is_file($base . '.avif'))) {
                if (@imageavif($img, $base . '.avif', self::AVIF_QUALITY, 6)) {
                    $done[] = 'avif';
                }
            }
            // A "modern" copy that came out bigger than the original is no help.
            foreach (['webp', 'avif'] as $alt) {
                $f = $base . '.' . $alt;
                if (is_file($f) && filesize($f) >= filesize($file)) {
                    @unlink($f);
                    $done = array_values(array_diff($done, [$alt]));
                }
            }
        }

        imagedestroy($img);
        return $done;
    }

    /** Delete the .webp/.avif copies of a public image path. */
    public static function deleteVariants(string $publicPath): void
    {
        if (!preg_match('#^(uploads/[a-z0-9_/-]+)\.(jpe?g|png)$#i', ltrim($publicPath, '/'), $m) || str_contains($publicPath, '..')) {
            return;
        }
        foreach (['webp', 'avif'] as $alt) {
            $f = BASE_PATH . '/public/' . $m[1] . '.' . $alt;
            if (is_file($f)) {
                @unlink($f);
            }
        }
    }
}
