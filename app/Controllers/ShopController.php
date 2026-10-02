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

    public function index(): void
    {
        $f = self::filtersFromQuery($_GET);
        $q = $f['q'];
        $this->render('shop/index', [
            'title' => ($q !== '' ? "\"{$q}\" — search" : 'Browse products') . ' — PaySmallSmall',
            'products' => Product::browse($f),
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

    public function show(string $id): void
    {
        $product = Product::find((int) $id);
        if (!$product || !$product['active'] || $product['merchant_status'] !== 'approved') {
            http_response_code(404);
            $this->render('errors/404', ['title' => 'Product not found']);
            return;
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
        $this->render('shop/show', [
            'title' => $product['name'] . ' — PaySmallSmall',
            'product' => $product,
            'variants' => $variants,
            'optionNames' => $variants ? Product::optionNames($product) : [],
            'selected' => $selected,
            'plans' => Product::planOptions(Product::unitPrice($product, $selected), Product::allowedFrequencies($product)),
            'images' => Product::images((int) $id),
            'specs' => Product::specRows($product),
            'reviews' => Review::forProduct((int) $id),
            'reviewSummary' => Review::summary((int) $id),
            'ratingBars' => Review::distribution((int) $id),
            'myReview' => $uid ? Review::byUser((int) $id, $uid) : null,
            'related' => Product::related($product, 8),
            'recent' => Product::byIds(array_slice($recent, 0, 8)),
        ]);
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
                'url' => url('/product/' . (int) $p['id']),
                'price' => ((int) $p['price_to'] > $from ? 'From ' : '') . ghs($from),
                'weekly' => ghs(Product::cardWeekly($from)) . '/wk',
                'shop' => $p['shop_name'],
                'sku' => (string) ($p['sku'] ?? ''),
                'photo' => $p['photo'] !== '' ? url('/' . $p['photo']) : null,
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
