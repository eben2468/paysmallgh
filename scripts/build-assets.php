<?php
declare(strict_types=1);

/**
 * Minify the stylesheet and script: public/assets/css/app.css -> app.min.css,
 * public/assets/js/app.js -> app.min.js. asset() serves the .min file
 * automatically while it's newer than the source, so editing the source and
 * forgetting to rebuild never ships stale styles — it just ships unminified.
 *
 *   php scripts/build-assets.php
 *
 * Deliberately conservative (no renaming, no reordering): it removes comments
 * and whitespace only, so it can't change how anything behaves.
 */

define('BASE_PATH', dirname(__DIR__));

function minify_css(string $css): string
{
    // Keep strings intact while we squeeze everything around them.
    $strings = [];
    $css = preg_replace_callback('/"(?:[^"\\\\]|\\\\.)*"|\'(?:[^\'\\\\]|\\\\.)*\'/s', static function (array $m) use (&$strings): string {
        $strings[] = $m[0];
        return "\x00" . (count($strings) - 1) . "\x00";
    }, $css) ?? $css;

    $css = preg_replace('#/\*.*?\*/#s', '', $css) ?? $css;    // comments
    $css = preg_replace('/\s+/', ' ', $css) ?? $css;           // runs of whitespace
    $css = preg_replace('/\s*([{};,>])\s*/', '$1', $css) ?? $css; // around punctuation
    $css = preg_replace('/:\s+/', ':', $css) ?? $css;          // "color: red" -> "color:red"
    $css = str_replace(';}', '}', $css);
    $css = trim($css);

    return preg_replace_callback('/\x00(\d+)\x00/', static fn (array $m): string => $strings[(int) $m[1]], $css) ?? $css;
}

/**
 * JS: drop block comments and whole-line // comments, indentation and blank
 * lines. Line breaks stay, so automatic semicolon insertion is unaffected,
 * and code on a line is never touched (no risk to strings, regexes or URLs).
 */
function minify_js(string $js): string
{
    $out = [];
    $inBlock = false;
    foreach (preg_split('/\R/', $js) ?: [] as $line) {
        $t = trim($line);
        if ($inBlock) {
            if (str_contains($t, '*/')) {
                $inBlock = false;
                $t = trim(substr($t, strpos($t, '*/') + 2));
            } else {
                continue;
            }
        }
        if (str_starts_with($t, '/*')) {
            if (!str_contains($t, '*/')) {
                $inBlock = true;
                continue;
            }
            $t = trim(substr($t, strpos($t, '*/') + 2));
        }
        if ($t === '' || str_starts_with($t, '//')) {
            continue;
        }
        $out[] = $t;
    }
    return implode("\n", $out) . "\n";
}

$jobs = [
    'assets/css/app.css' => ['assets/css/app.min.css', 'minify_css'],
    'assets/js/app.js' => ['assets/js/app.min.js', 'minify_js'],
];
foreach ($jobs as $src => [$dest, $fn]) {
    $in = (string) file_get_contents(BASE_PATH . '/public/' . $src);
    $min = $fn($in);
    file_put_contents(BASE_PATH . '/public/' . $dest, $min);
    printf("%-22s %7s -> %7s bytes (%d%% smaller; gzip %s)\n", $src, number_format(strlen($in)), number_format(strlen($min)),
        (int) round(100 - strlen($min) * 100 / max(1, strlen($in))), number_format(strlen(gzencode($min, 9))));
}
