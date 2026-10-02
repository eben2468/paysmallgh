<?php
declare(strict_types=1);

/**
 * Compress every product/review upload and bundled photo, and write WebP/AVIF
 * copies next to them (served automatically through picture()). New uploads
 * are optimised as they arrive; run this once after deploying, or after
 * copying photos onto the server by hand.
 *
 *   php scripts/optimize-images.php          # skip files that already have a .webp
 *   php scripts/optimize-images.php --force  # redo everything
 *
 * Needs PHP's GD extension (AVIF: PHP 8.1+ with libavif).
 */

define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/app/Services/ImageOptimizer.php';

use App\Services\ImageOptimizer;

if (!ImageOptimizer::available()) {
    fwrite(STDERR, "PHP's GD extension isn't loaded — install/enable php-gd and run again.\n");
    exit(1);
}
$force = in_array('--force', $argv, true);
printf("WebP: %s, AVIF: %s\n", function_exists('imagewebp') ? 'yes' : 'no', function_exists('imageavif') ? 'yes' : 'no');

// Icons and logos are already tiny or need exact pixels — leave them alone.
$skip = '#/(favicon[^/]*|apple-touch-icon[^/]*)$#i';
$roots = ['uploads', 'assets/img'];
$before = $after = 0;
$count = 0;

foreach ($roots as $root) {
    $dir = BASE_PATH . '/public/' . $root;
    if (!is_dir($dir)) {
        continue;
    }
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $file) {
        $path = str_replace('\\', '/', $file->getPathname());
        if (!preg_match('#\.(jpe?g|png)$#i', $path) || preg_match($skip, $path)) {
            continue;
        }
        $base = (string) preg_replace('#\.[a-z]+$#i', '', $path);
        if (!$force && is_file($base . '.webp')) {
            continue;
        }
        $public = substr($path, strlen(str_replace('\\', '/', BASE_PATH) . '/public/'));
        $size = (int) filesize($path);
        $done = ImageOptimizer::optimize($public, $force);
        clearstatcache();
        $smallest = min(array_filter([
            (int) filesize($path),
            is_file($base . '.webp') ? (int) filesize($base . '.webp') : 0,
            is_file($base . '.avif') ? (int) filesize($base . '.avif') : 0,
        ]));
        $before += $size;
        $after += $smallest;
        $count++;
        printf("%-55s %8s -> %8s  %s\n", $public, number_format($size), number_format($smallest), implode(', ', $done) ?: '(no change)');
    }
}
printf("\n%d images. Smallest version served: %s -> %s bytes.\n", $count, number_format($before), number_format($after));
