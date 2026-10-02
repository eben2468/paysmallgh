<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Models\Product;
use App\Models\Review;

final class ShopController extends Controller
{
    /** How many recently viewed products we remember per visitor. */
    private const RECENT_MAX = 12;

    /** Products per page in the shop grid. */
    public const PER_PAGE = 24;

    public function index(): void
    {
        $f = self::filtersFromQuery($_GET);
        $q = $f['q'];
        $total = Product::browseCount($f);
        $pages = max(1, (int) ceil($total / self::PER_PAGE));
        $page = max(1, (int) ($_GET['page'] ?? 1));
        if ($page > $pages) {
            $page = $pages;
        }
        $products = Product::browse($f + ['limit' => self::PER_PAGE, 'offset' => ($page - 1) * self::PER_PAGE]);

        // Link to another page of these same results.
        $pageUrl = static function (int $n): string {
            $query = $_GET;
            unset($query['page'], $query['fragment']);
            if ($n > 1) {
                $query['page'] = $n;
            }
            return '/shop' . ($query ? '?' . http_build_query($query) : '');
        };
        $next = $page < $pages ? $pageUrl($page + 1) : null;

        // Infinite scroll asks for just the next batch of cards.
        if (!empty($_GET['fragment'])) {
            $html = '';
            foreach ($products as $p) {
                $html .= $this->view->partial('partials/product-card', ['p' => $p]);
            }
            header('Cache-Control: private, max-age=60');
            $this->json(['html' => $html, 'next' => $next !== null ? url($next) : null, 'page' => $page, 'pages' => $pages]);
        }

        // Search engines: index the shop and each category (and their pages);
        // searches and narrow filter/sort combos are followed but not indexed.
        $isFiltered = $q !== '' || $f['budget'] !== '' || $f['min'] > 0 || $f['max'] > 0
            || $f['verified'] || $f['in_stock'] || $f['freq'] !== '' || $f['sort'] !== '';
        $canonicalQuery = array_filter(['category' => $f['category'], 'page' => $page > 1 ? $page : null]);
        $label = $f['category'] !== '' ? Product::categoryLabel($f['category']) : 'All products';

        $this->render('shop/index', [
            'title' => ($q !== '' ? "\"{$q}\" — search" : $label . ' on weekly payments')
                . ($page > 1 ? ' — page ' . $page : '') . ' | PaySmallSmall',
            'metaDescription' => $f['category'] !== ''
                ? "Shop {$label} in Ghana and pay small small — daily, weekly or monthly MoMo payments. {$total} items, money held in escrow till you finish."
                : "Phones, furniture, clothes and more from Ghanaian shops. Pay small small by MoMo — {$total} items, every price shown cash and weekly.",
            'canonical' => canonical_url('/shop' . ($canonicalQuery ? '?' . http_build_query($canonicalQuery) : '')),
            'robots' => $isFiltered ? 'noindex, follow' : null,
            'prevUrl' => $page > 1 ? canonical_url($pageUrl($page - 1)) : null,
            'nextUrl' => $next !== null ? canonical_url($next) : null,
            'products' => $products,
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
            'pageUrl' => $pageUrl,
            'categories' => Product::categories(),
            'filters' => $f,
            'current' => $f['category'],
            'q' => $q,
            'budget' => $f['budget'],
        ]);
    }

    /**
     * Clean the shop's query string into browse() filters. Prices arrive in
     * GHS and are stored as pesewas; anything unknown is dropped.
     */
    public static function filtersFromQuery(array $in): array
    {
        $cedis = static function ($v): int {
            $n = (float) str_replace(',', '', trim((string) $v));
            return $n > 0 ? (int) round($n * 100) : 0;
        };
        $f = [
            'q' => mb_substr(trim((string) ($in['q'] ?? '')), 0, 80),
            'category' => (string) ($in['category'] ?? ''),
            'budget' => isset($in['budget']) && isset(Product::BUDGETS[(string) $in['budget']]) ? (string) $in['budget'] : '',
            'min' => $cedis($in['min'] ?? ''),
            'max' => $cedis($in['max'] ?? ''),
            'verified' => !empty($in['verified']),
            'in_stock' => !empty($in['in_stock']),
            'freq' => isset($in['freq']) && isset(Product::FREQUENCIES[(string) $in['freq']]) ? (string) $in['freq'] : '',
            'sort' => isset($in['sort']) && isset(Product::SORTS[(string) $in['sort']]) ? (string) $in['sort'] : '',
        ];
        if ($f['min'] > 0 && $f['max'] > 0 && $f['min'] > $f['max']) {
            [$f['min'], $f['max']] = [$f['max'], $f['min']]; // typed the wrong way round
        }
        return $f;
    }

    /** $id is "12-samsung-galaxy-a16" (or a bare "12" from older links). */
    public function show(string $id): void
    {
        $id = (string) (int) $id; // the leading number is the product id
        $product = Product::find((int) $id);
        if (!$product || !$product['active'] || $product['merchant_status'] !== 'approved') {
            http_response_code(404);
            $this->render('errors/404', ['title' => 'Product not found', 'robots' => 'noindex']);
            return;
        }

        // One address per product: old/renamed/bare-id links move permanently
        // to the current slug, so search engines don't index duplicates.
        $canonicalPath = product_path($product);
        $requested = (string) parse_url(current_path(), PHP_URL_PATH);
        if (rawurldecode($requested) !== $canonicalPath && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
            $query = (string) ($_SERVER['QUERY_STRING'] ?? '');
            http_response_code(301);
            header('Location: ' . url($canonicalPath) . ($query !== '' ? '?' . $query : ''));
            exit;
        }

        $variants = Product::variants((int) $id);
        // Start on the first option that's actually available.
        $selected = null;
        foreach ($variants as $v) {
            if ($v['stock'] === null || (int) $v['stock'] > 0) {
                $selected = $v;
                break;
            }
        }
        $selected ??= $variants[0] ?? null;

        $recent = $this->rememberViewed((int) $id);
        $uid = Auth::userId();
        $images = Product::images((int) $id);
        $reviewSummary = Review::summary((int) $id);
        $reviews = Review::forProduct((int) $id, $uid);
        $price = Product::unitPrice($product, $selected);
        $weekly = Product::cardWeekly($price);
        $this->render('shop/show', [
            'title' => $product['name'] . ' — ' . ghs($weekly) . '/week | PaySmallSmall',
            'metaDescription' => meta_excerpt(
                $product['name'] . ' from ' . $product['shop_name'] . '. Cash price ' . ghs($price)
                . ', or pay small small — ' . ghs($weekly) . ' a week for ' . Product::CARD_WEEKS . ' weeks. '
                . (string) $product['description']
            ),
            'canonical' => canonical_url(product_path($product)),
            'ogType' => 'product',
            'ogImage' => $images ? $images[0]['path'] : null,
            'jsonLd' => $this->productSchema($product, $variants, $images, $reviewSummary, $reviews),
            'product' => $product,
            'variants' => $variants,
            'optionNames' => $variants ? Product::optionNames($product) : [],
            'selected' => $selected,
            'plans' => Product::planOptions($price, Product::allowedFrequencies($product)),
            'images' => $images,
            'specs' => Product::specRows($product),
            'reviews' => $reviews,
            'reviewSummary' => $reviewSummary,
            'ratingBars' => Review::distribution((int) $id),
            'myReview' => $uid ? Review::byUser((int) $id, $uid) : null,
            'reportedIds' => $uid ? Review::reportedBy($uid, (int) $id) : [],
            'isBuyer' => $uid ? Review::isVerifiedBuyer((int) $id, $uid) : false,
            'related' => Product::related($product, 8),
            'recent' => Product::byIds(array_slice($recent, 0, 8)),
        ]);
    }

    /**
     * schema.org Product data for Google's rich results: price (or price range
     * across options), stock, the shop as seller, rating and a few reviews.
     */
    private function productSchema(array $product, array $variants, array $images, array $summary, array $reviews): array
    {
        $prices = [];
        $inStock = false;
        foreach ($variants as $v) {
            $prices[] = Product::unitPrice($product, $v);
            $inStock = $inStock || $v['stock'] === null || (int) $v['stock'] > 0;
        }
        if (!$variants) {
            $prices[] = (int) $product['cash_price_pesewas'];
            $inStock = $product['stock'] === null || (int) $product['stock'] > 0;
        }
        $cedis = static fn (int $p): string => number_format($p / 100, 2, '.', '');
        $availability = 'https://schema.org/' . ($inStock ? 'InStock' : 'OutOfStock');
        $seller = ['@type' => 'Organization', 'name' => (string) $product['shop_name']];
        $url = canonical_url(product_path($product));

        if (count(array_unique($prices)) > 1) {
            $offers = [
                '@type' => 'AggregateOffer',
                'priceCurrency' => 'GHS',
                'lowPrice' => $cedis(min($prices)),
                'highPrice' => $cedis(max($prices)),
                'offerCount' => count($prices),
                'availability' => $availability,
                'seller' => $seller,
                'url' => $url,
            ];
        } else {
            $offers = [
                '@type' => 'Offer',
                'priceCurrency' => 'GHS',
                'price' => $cedis($prices[0]),
                'availability' => $availability,
                'itemCondition' => 'https://schema.org/NewCondition',
                'seller' => $seller,
                'url' => $url,
            ];
        }

        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => (string) $product['name'],
            'description' => meta_excerpt((string) $product['description'], 500),
            'url' => $url,
            'category' => Product::categoryLabel((string) $product['category']),
            'offers' => $offers,
        ];
        if ($images) {
            $data['image'] = array_map(static fn (array $i): string => absolute_media_url($i['path']), array_slice($images, 0, 5));
        }
        if (!empty($product['sku'])) {
            $data['sku'] = (string) $product['sku'];
        }
        if ($summary['count'] > 0) {
            $data['aggregateRating'] = [
                '@type' => 'AggregateRating',
                'ratingValue' => $summary['avg'],
                'reviewCount' => $summary['count'],
                'bestRating' => 5,
                'worstRating' => 1,
            ];
            $published = array_filter($reviews, static fn (array $r): bool => $r['status'] === 'approved');
            foreach (array_slice(array_values($published), 0, 5) as $r) {
                $data['review'][] = [
                    '@type' => 'Review',
                    'reviewRating' => ['@type' => 'Rating', 'ratingValue' => (int) $r['rating'], 'bestRating' => 5],
                    'author' => ['@type' => 'Person', 'name' => masked_name((string) $r['user_name'])],
                    'datePublished' => substr((string) $r['created_at'], 0, 10),
                    'reviewBody' => meta_excerpt((string) ($r['body'] ?? ''), 500),
                ];
            }
        }
        return [
            $data,
            [
                '@context' => 'https://schema.org',
                '@type' => 'BreadcrumbList',
                'itemListElement' => [
                    ['@type' => 'ListItem', 'position' => 1, 'name' => 'Shop', 'item' => canonical_url('/shop')],
                    ['@type' => 'ListItem', 'position' => 2, 'name' => Product::categoryLabel((string) $product['category']),
                        'item' => canonical_url('/shop?category=' . urlencode((string) $product['category']))],
                    ['@type' => 'ListItem', 'position' => 3, 'name' => (string) $product['name'], 'item' => $url],
                ],
            ],
        ];
    }

    /**
     * Search-as-you-type for the search boxes: a few matching products and
     * categories. JSON, read-only, safe for anyone to call.
     */
    public function suggest(): void
    {
        $q = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 80);
        if (mb_strlen($q) < 2) {
            $this->json(['q' => $q, 'products' => [], 'categories' => []]);
        }

        $products = [];
        foreach (Product::browse(['q' => $q, 'sort' => 'relevance', 'limit' => 6]) as $p) {
            $from = (int) $p['price_from'];
            $products[] = [
                'name' => $p['name'],
                'url' => product_url($p),
                'price' => ((int) $p['price_to'] > $from ? 'From ' : '') . ghs($from),
                'weekly' => ghs(Product::cardWeekly($from)) . '/wk',
                'shop' => $p['shop_name'],
                'sku' => (string) ($p['sku'] ?? ''),
                'photo' => $p['photo'] !== '' ? media_url($p['photo']) : null,
                'soldOut' => !(int) $p['in_stock'],
            ];
        }

        $categories = [];
        $needle = mb_strtolower($q);
        foreach (Product::categoryCounts() as $slug => $n) {
            $label = Product::categoryLabel((string) $slug);
            if (str_contains(mb_strtolower($label), $needle) || str_contains((string) $slug, $needle)) {
                $categories[] = ['label' => $label, 'count' => (int) $n, 'url' => url('/shop?category=' . urlencode((string) $slug))];
            }
        }

        header('Cache-Control: private, max-age=30');
        $this->json(['q' => $q, 'products' => $products, 'categories' => array_slice($categories, 0, 3)]);
    }

    /**
     * Push this product to the front of the visitor's recently-viewed list and
     * return the others (most recent first), not including this one.
     * @return list<int>
     */
    private function rememberViewed(int $productId): array
    {
        $list = array_values(array_filter(
            array_map('intval', (array) ($_SESSION['recently_viewed'] ?? [])),
            static fn (int $id): bool => $id > 0 && $id !== $productId
        ));
        $_SESSION['recently_viewed'] = array_slice(array_merge([$productId], $list), 0, self::RECENT_MAX);
        return $list;
    }
}
