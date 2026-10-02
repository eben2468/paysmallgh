<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database as DB;
use App\Models\Product;

/**
 * robots.txt and sitemap.xml, generated so they always name the live domain
 * (APP_URL) and list every product that's actually for sale.
 * Submit https://your-domain/sitemap.xml in Google Search Console.
 */
final class SeoController extends Controller
{
    public function robots(): void
    {
        header('Content-Type: text/plain; charset=UTF-8');
        header('Cache-Control: public, max-age=86400');
        // Private pages also send noindex; this keeps crawlers from wasting
        // time on them (and on endless filter/sort combinations) at all.
        $disallow = [
            '/account', '/plans', '/plan/', '/cart', '/wishlist', '/checkout', '/login', '/logout',
            '/register', '/verify-phone', '/forgot-pin', '/reset-pin', '/merchant/', '/admin',
            '/webhook/', '/search/suggest', '/*?*sort=', '/*?*min=', '/*?*max=',
        ];
        $base = rtrim(parse_url(url('/'), PHP_URL_PATH) ?: '', '/');
        $out = "User-agent: *\n";
        foreach ($disallow as $path) {
            $out .= 'Disallow: ' . $base . $path . "\n";
        }
        $out .= "Allow: /\n\nSitemap: " . canonical_url('/sitemap.xml') . "\n";
        echo $out;
    }

    public function sitemap(): void
    {
        $urls = [
            ['loc' => canonical_url('/'), 'changefreq' => 'daily', 'priority' => '1.0'],
            ['loc' => canonical_url('/shop'), 'changefreq' => 'daily', 'priority' => '0.9'],
            ['loc' => canonical_url('/how-it-works'), 'changefreq' => 'monthly', 'priority' => '0.6'],
            ['loc' => canonical_url('/merchant'), 'changefreq' => 'monthly', 'priority' => '0.6'],
        ];
        foreach (array_keys(Product::categoryCounts()) as $slug) {
            $urls[] = ['loc' => canonical_url('/shop?category=' . urlencode((string) $slug)), 'changefreq' => 'daily', 'priority' => '0.7'];
        }

        $products = DB::run(
            "SELECT p.id, p.name, p.photo, p.created_at,
                    GREATEST(p.created_at, COALESCE((SELECT MAX(r.created_at) FROM reviews r WHERE r.product_id = p.id AND r.status = 'approved'), p.created_at)) AS lastmod
             FROM products p JOIN merchants m ON m.id = p.merchant_id
             WHERE p.active = 1 AND m.status = 'approved'
             ORDER BY p.id"
        )->fetchAll();
        foreach ($products as $p) {
            $u = [
                'loc' => canonical_url(product_path($p)),
                'lastmod' => date('Y-m-d', (int) strtotime((string) $p['lastmod'])),
                'changefreq' => 'weekly',
                'priority' => '0.8',
            ];
            if ($p['photo'] !== '') {
                $u['image'] = absolute_media_url((string) $p['photo']);
            }
            $urls[] = $u;
        }

        header('Content-Type: application/xml; charset=UTF-8');
        header('Cache-Control: public, max-age=3600');
        $x = static fn (string $s): string => htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">' . "\n";
        foreach ($urls as $u) {
            echo '  <url><loc>' . $x($u['loc']) . '</loc>';
            if (isset($u['lastmod'])) {
                echo '<lastmod>' . $u['lastmod'] . '</lastmod>';
            }
            echo '<changefreq>' . $u['changefreq'] . '</changefreq><priority>' . $u['priority'] . '</priority>';
            if (isset($u['image'])) {
                echo '<image:image><image:loc>' . $x($u['image']) . '</image:loc></image:image>';
            }
            echo "</url>\n";
        }
        echo "</urlset>\n";
    }
}
