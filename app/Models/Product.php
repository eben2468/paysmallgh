<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database as DB;

final class Product
{
    /** Product categories merchants pick from (slug => label). Slugs are stored. */
    public const CATEGORIES = [
        'phones' => 'Phones & tablets',
        'electronics' => 'Electronics',
        'appliances' => 'Home appliances',
        'furniture' => 'Furniture',
        'fashion' => 'Fashion & clothing',
        'accessories' => 'Accessories',
        'beauty' => 'Beauty & personal care',
        'school' => 'School items',
        'kitchen' => 'Kitchen & household',
        'building' => 'Building & tools',
        'general' => 'Other',
    ];

    /**
     * Weekly-budget bands for browsing (slug => [min, max] weekly pesewas, label).
     * "Weekly" is the price spread over 12 weeks — the same figure product cards show.
     */
    public const BUDGETS = [
        'up-to-25' => ['min' => 0, 'max' => 2500, 'label' => 'GHS 25 or less a week'],
        'up-to-50' => ['min' => 0, 'max' => 5000, 'label' => 'GHS 50 or less a week'],
        'up-to-100' => ['min' => 0, 'max' => 10000, 'label' => 'GHS 100 or less a week'],
        'over-100' => ['min' => 10001, 'max' => null, 'label' => 'Over GHS 100 a week'],
    ];

    /** Shop sort orders (slug => label). 'relevance' only applies to a search. */
    public const SORTS = [
        'relevance' => 'Best match',
        'newest' => 'Newest first',
        'popular' => 'Most people paying',
        'price-asc' => 'Price: low to high',
        'price-desc' => 'Price: high to low',
        'rating' => 'Top rated',
        'discount' => 'Biggest discount',
    ];

    /** Weeks used for the "/wk" figure on cards and in budget bands. */
    public const CARD_WEEKS = 12;

    /** Most of one item a customer can put on a single plan. */
    public const MAX_QTY = 10;

    /** Installment schedules a merchant can allow (paying in full is always allowed). */
    public const FREQUENCIES = ['daily' => 'Daily', 'weekly' => 'Weekly', 'monthly' => 'Monthly'];

    /**
     * Plan lengths offered per schedule, and the smallest installment allowed.
     * Shared by the product page, the cart and the browser-side picker.
     */
    public const PLAN_DEFS = [
        'daily'   => ['unit' => 'day',   'noun' => 'days',   'counts' => [7, 14, 21, 30, 45, 60, 90]],
        'weekly'  => ['unit' => 'week',  'noun' => 'weeks',  'counts' => [4, 6, 8, 12, 16, 24, 36]],
        'monthly' => ['unit' => 'month', 'noun' => 'months', 'counts' => [2, 3, 4, 6, 9, 12]],
    ];
    public const MIN_INSTALLMENT = 100; // GHS 1.00

    /** The "/wk" figure shown on product cards. */
    public static function cardWeekly(int $pricePesewas): int
    {
        return (int) ceil($pricePesewas / self::CARD_WEEKS);
    }

    /** True if a price's card weekly figure falls inside a budget band. */
    public static function inBudget(int $pricePesewas, string $budget): bool
    {
        $b = self::BUDGETS[$budget] ?? null;
        if ($b === null) {
            return false;
        }
        $wk = self::cardWeekly($pricePesewas);
        return $wk >= $b['min'] && ($b['max'] === null || $wk <= $b['max']);
    }

    /** Human label for a stored category slug. */
    public static function categoryLabel(string $slug): string
    {
        return self::CATEGORIES[$slug] ?? ucfirst($slug);
    }

    /**
     * Installment schedules the merchant allows for this product, in a fixed
     * order. Older rows without the column allow all three.
     * @return list<string>
     */
    public static function allowedFrequencies(array $product): array
    {
        $raw = (string) ($product['plan_frequencies'] ?? 'daily,weekly,monthly');
        $set = array_intersect(array_keys(self::FREQUENCIES), array_map('trim', explode(',', $raw)));
        return $set ? array_values($set) : array_keys(self::FREQUENCIES);
    }

    /**
     * Plan-picker options for a total price: per allowed schedule, each length
     * whose installment stays above the floor (always at least one), plus
     * 'once' (pay in full). Mirrored in app.js — keep the two in step.
     *
     * @return array<string, array{unit:string, noun:string, full?:bool, options: list<array{count:int, per:int, perLabel:string}>}>
     */
    public static function planOptions(int $price, array $allowed): array
    {
        $plans = [];
        foreach (self::PLAN_DEFS as $freq => $def) {
            if (!in_array($freq, $allowed, true)) {
                continue; // the merchant doesn't offer this schedule
            }
            $options = [];
            foreach ($def['counts'] as $count) {
                $per = (int) ceil($price / $count);
                if ($per >= self::MIN_INSTALLMENT) {
                    $options[] = ['count' => $count, 'per' => $per, 'perLabel' => ghs($per)];
                }
            }
            if (!$options) { // price too small for any listed count — offer the fewest installments
                $count = $def['counts'][0];
                $per = (int) ceil($price / $count);
                $options[] = ['count' => $count, 'per' => $per, 'perLabel' => ghs($per)];
            }
            $plans[$freq] = ['unit' => $def['unit'], 'noun' => $def['noun'], 'options' => $options];
        }

        // Always available: pay the whole price now in one payment.
        $plans['once'] = ['unit' => '', 'noun' => '', 'full' => true, 'options' => [
            ['count' => 1, 'per' => $price, 'perLabel' => ghs($price)],
        ]];
        return $plans;
    }

    /** Percent off an old price (0 when there's no real discount). */
    public static function discountPct(?int $compareAt, int $price): int
    {
        if ($compareAt === null || $compareAt <= $price || $compareAt <= 0) {
            return 0;
        }
        return (int) round(($compareAt - $price) * 100 / $compareAt);
    }

    public static function find(int $id): ?array
    {
        return DB::run(
            'SELECT p.*, m.shop_name, m.location AS merchant_location, m.status AS merchant_status,
                    m.verified AS merchant_verified, m.owner_name AS merchant_owner
             FROM products p JOIN merchants m ON m.id = p.merchant_id
             WHERE p.id = ?',
            [$id]
        )->fetch() ?: null;
    }

    /* ---------- Listings (shop, home, search) ---------- */

    /**
     * Every product listing row: the product, its shop, live plan count,
     * rating, price range across variants (price_from/price_to), stock state
     * and discount. Wrapped as a derived table so filters and sorts can use
     * the computed columns.
     */
    private const LISTING_INNER = "SELECT p.*, m.shop_name, m.location AS merchant_location, m.verified AS merchant_verified,
                (SELECT COUNT(*) FROM plans pl WHERE pl.product_id = p.id AND pl.status IN ('active','completed')) AS plan_count,
                (SELECT COUNT(*) FROM reviews r WHERE r.product_id = p.id) AS review_count,
                (SELECT ROUND(AVG(r.rating), 1) FROM reviews r WHERE r.product_id = p.id) AS avg_rating,
                (SELECT COUNT(*) FROM product_variants v WHERE v.product_id = p.id) AS variant_count,
                COALESCE((SELECT MIN(COALESCE(v.price_pesewas, p.cash_price_pesewas)) FROM product_variants v WHERE v.product_id = p.id), p.cash_price_pesewas) AS price_from,
                COALESCE((SELECT MAX(COALESCE(v.price_pesewas, p.cash_price_pesewas)) FROM product_variants v WHERE v.product_id = p.id), p.cash_price_pesewas) AS price_to,
                CASE WHEN EXISTS (SELECT 1 FROM product_variants v WHERE v.product_id = p.id)
                     THEN EXISTS (SELECT 1 FROM product_variants v WHERE v.product_id = p.id AND (v.stock IS NULL OR v.stock > 0))
                     ELSE (p.stock IS NULL OR p.stock > 0) END AS in_stock
            FROM products p JOIN merchants m ON m.id = p.merchant_id
            WHERE p.active = 1 AND m.status = 'approved'";

    private const LISTING = "SELECT x.*,
                CASE WHEN x.compare_at_pesewas > x.price_from
                     THEN ROUND((x.compare_at_pesewas - x.price_from) * 100 / x.compare_at_pesewas) ELSE 0 END AS discount_pct
            FROM (" . self::LISTING_INNER . ") x";

    /**
     * Active products from approved shops. Filters (all optional):
     *   q         words to find in name, SKU (product or variant), description, shop or category
     *   category  category slug
     *   budget    a BUDGETS slug (weekly price over 12 weeks)
     *   min, max  price range in pesewas (on the lowest variant price)
     *   verified  only verified shops
     *   in_stock  hide sold-out items
     *   freq      only items the shop allows on this schedule (daily/weekly/monthly)
     *   sort      a SORTS slug (default: relevance when searching, else newest)
     *   limit     max rows
     */
    public static function browse(array $f = []): array
    {
        $where = [];
        $params = [];
        $q = trim((string) ($f['q'] ?? ''));

        if ($q !== '') {
            // Every word has to match somewhere, so "samsung 128" narrows down.
            foreach (array_slice(preg_split('/\s+/', $q) ?: [], 0, 6) as $word) {
                $like = '%' . self::escapeLike($word) . '%';
                $where[] = '(x.name LIKE ? OR x.description LIKE ? OR x.shop_name LIKE ? OR x.category LIKE ?
                             OR x.sku LIKE ? OR EXISTS (SELECT 1 FROM product_variants v WHERE v.product_id = x.id AND v.sku LIKE ?))';
                array_push($params, $like, $like, $like, $like, $like, $like);
            }
        }
        if (!empty($f['category'])) {
            $where[] = 'x.category = ?';
            $params[] = (string) $f['category'];
        }
        if (!empty($f['budget']) && isset(self::BUDGETS[$f['budget']])) {
            $b = self::BUDGETS[$f['budget']];
            $where[] = 'CEIL(x.price_from / ' . self::CARD_WEEKS . ') >= ?';
            $params[] = $b['min'];
            if ($b['max'] !== null) {
                $where[] = 'CEIL(x.price_from / ' . self::CARD_WEEKS . ') <= ?';
                $params[] = $b['max'];
            }
        }
        if (isset($f['min']) && (int) $f['min'] > 0) {
            $where[] = 'x.price_from >= ?';
            $params[] = (int) $f['min'];
        }
        if (isset($f['max']) && (int) $f['max'] > 0) {
            $where[] = 'x.price_from <= ?';
            $params[] = (int) $f['max'];
        }
        if (!empty($f['verified'])) {
            $where[] = 'x.merchant_verified = 1';
        }
        if (!empty($f['in_stock'])) {
            $where[] = 'x.in_stock = 1';
        }
        if (!empty($f['freq']) && isset(self::FREQUENCIES[$f['freq']])) {
            $where[] = 'FIND_IN_SET(?, x.plan_frequencies) > 0';
            $params[] = (string) $f['freq'];
        }

        $sort = (string) ($f['sort'] ?? '');
        if (!isset(self::SORTS[$sort]) || ($sort === 'relevance' && $q === '')) {
            $sort = $q !== '' ? 'relevance' : 'newest';
        }
        $order = match ($sort) {
            'popular' => 'x.plan_count DESC, x.created_at DESC',
            'price-asc' => 'x.price_from ASC, x.id ASC',
            'price-desc' => 'x.price_from DESC, x.id DESC',
            'rating' => 'COALESCE(x.avg_rating, 0) DESC, x.review_count DESC, x.created_at DESC',
            'discount' => 'discount_pct DESC, x.created_at DESC',
            'newest' => 'x.created_at DESC, x.id DESC',
            default => null, // relevance, built below
        };
        if ($order === null) {
            // Exact SKU first, then names starting with the search, names containing
            // it, then category matches — shop-name/description matches come last.
            $order = '(x.sku = ? OR EXISTS (SELECT 1 FROM product_variants v WHERE v.product_id = x.id AND v.sku = ?)) DESC,
                      (x.name LIKE ?) DESC, (x.name LIKE ?) DESC, (x.category LIKE ?) DESC, x.plan_count DESC, x.created_at DESC';
            $esc = self::escapeLike($q);
            array_push($params, $q, $q, $esc . '%', '%' . $esc . '%', '%' . $esc . '%');
        }

        $sql = self::LISTING . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY ' . $order;
        if (!empty($f['limit'])) {
            $sql .= ' LIMIT ' . max(1, (int) $f['limit']);
        }
        return DB::run($sql, $params)->fetchAll();
    }

    /** Escape % and _ so a search for "50%" means the characters, not a wildcard. */
    private static function escapeLike(string $s): string
    {
        return addcslashes($s, '%_\\');
    }

    /** Products people are actually paying for (active or finished plans), most plans first. */
    public static function popular(int $limit = 10): array
    {
        return DB::run(
            self::LISTING . ' WHERE x.plan_count > 0 ORDER BY x.plan_count DESC, x.created_at DESC LIMIT ' . max(1, $limit)
        )->fetchAll();
    }

    /** Listing rows for these ids, in the order given (missing/hidden ones skipped). */
    public static function byIds(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $i): bool => $i > 0)));
        if (!$ids) {
            return [];
        }
        $rows = DB::run(
            self::LISTING . ' WHERE x.id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')',
            $ids
        )->fetchAll();
        $byId = [];
        foreach ($rows as $r) {
            $byId[(int) $r['id']] = $r;
        }
        $out = [];
        foreach ($ids as $id) {
            if (isset($byId[$id])) {
                $out[] = $byId[$id];
            }
        }
        return $out;
    }

    /** Same-category items first, topped up from the same shop. */
    public static function related(array $product, int $limit = 8): array
    {
        $id = (int) $product['id'];
        $rows = DB::run(
            self::LISTING . ' WHERE x.category = ? AND x.id <> ? ORDER BY x.plan_count DESC, x.created_at DESC LIMIT ' . $limit,
            [(string) $product['category'], $id]
        )->fetchAll();
        if (count($rows) < $limit) {
            $seen = array_merge([$id], array_map(static fn (array $r): int => (int) $r['id'], $rows));
            $more = DB::run(
                self::LISTING . ' WHERE x.merchant_id = ? AND x.id NOT IN (' . implode(',', array_fill(0, count($seen), '?')) . ')
                 ORDER BY x.plan_count DESC, x.created_at DESC LIMIT ' . ($limit - count($rows)),
                array_merge([(int) $product['merchant_id']], $seen)
            )->fetchAll();
            $rows = array_merge($rows, $more);
        }
        return $rows;
    }

    /** Category => product count, for nav and tiles. */
    public static function categoryCounts(): array
    {
        return DB::run(
            "SELECT p.category, COUNT(*) AS n FROM products p
             JOIN merchants m ON m.id = p.merchant_id
             WHERE p.active = 1 AND m.status = 'approved'
             GROUP BY p.category ORDER BY n DESC, p.category"
        )->fetchAll(\PDO::FETCH_KEY_PAIR);
    }

    public static function categories(): array
    {
        return DB::run(
            "SELECT DISTINCT p.category FROM products p
             JOIN merchants m ON m.id = p.merchant_id
             WHERE p.active = 1 AND m.status = 'approved' ORDER BY p.category"
        )->fetchAll(\PDO::FETCH_COLUMN);
    }

    public static function forMerchant(int $merchantId): array
    {
        return DB::run(
            'SELECT p.*,
                    (SELECT COUNT(*) FROM product_variants v WHERE v.product_id = p.id) AS variant_count,
                    (SELECT SUM(v.stock) FROM product_variants v WHERE v.product_id = p.id) AS variant_stock,
                    (SELECT COUNT(*) FROM product_variants v WHERE v.product_id = p.id AND v.stock IS NULL) AS variant_untracked
             FROM products p WHERE p.merchant_id = ? ORDER BY p.created_at DESC',
            [$merchantId]
        )->fetchAll();
    }

    /** Columns a merchant edits on the product form (besides photos and variants). */
    private const EDITABLE = [
        'name', 'sku', 'description', 'specs', 'delivery_info', 'return_policy',
        'cash_price_pesewas', 'compare_at_pesewas', 'stock', 'category', 'plan_frequencies',
        'option1_name', 'option2_name', 'option3_name', 'active',
    ];

    public static function create(array $d): int
    {
        $cols = array_merge(['merchant_id', 'photo'], array_values(array_filter(self::EDITABLE, static fn (string $c): bool => array_key_exists($c, $d))));
        $vals = array_map(static fn (string $c) => $d[$c] ?? ($c === 'photo' ? '' : null), $cols);
        if (!array_key_exists('plan_frequencies', $d)) {
            $cols[] = 'plan_frequencies';
            $vals[] = 'daily,weekly,monthly';
        }
        DB::run(
            'INSERT INTO products (' . implode(', ', $cols) . ') VALUES (' . implode(', ', array_fill(0, count($cols), '?')) . ')',
            $vals
        );
        return DB::lastId();
    }

    /** Update everything except the cover photo (managed via the images below). */
    public static function updateDetails(int $id, array $d): void
    {
        $cols = array_values(array_filter(self::EDITABLE, static fn (string $c): bool => array_key_exists($c, $d)));
        if (!$cols) {
            return;
        }
        $vals = array_map(static fn (string $c) => $d[$c], $cols);
        $vals[] = $id;
        DB::run('UPDATE products SET ' . implode(', ', array_map(static fn (string $c): string => "{$c} = ?", $cols)) . ' WHERE id = ?', $vals);
    }

    public static function toggle(int $id, int $merchantId): void
    {
        DB::run('UPDATE products SET active = 1 - active WHERE id = ? AND merchant_id = ?', [$id, $merchantId]);
    }

    /** True if any layaway plan references this product (so it can't be deleted). */
    public static function hasPlans(int $id): bool
    {
        return (bool) DB::run('SELECT 1 FROM plans WHERE product_id = ? LIMIT 1', [$id])->fetchColumn();
    }

    /** Delete a product (image and variant rows cascade; caller unlinks the files). */
    public static function delete(int $id, int $merchantId): void
    {
        DB::run('DELETE FROM products WHERE id = ? AND merchant_id = ?', [$id, $merchantId]);
    }

    /* ---------- Specifications ---------- */

    /**
     * The merchant's "Key: Value" lines as rows. A line without a colon becomes
     * a full-width row (key '').
     * @return list<array{0:string,1:string}>
     */
    public static function specRows(array $product): array
    {
        $rows = [];
        foreach (preg_split('/\R/', (string) ($product['specs'] ?? '')) ?: [] as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $pos = strpos($line, ':');
            $rows[] = $pos === false
                ? ['', $line]
                : [trim(substr($line, 0, $pos)), trim(substr($line, $pos + 1))];
        }
        return $rows;
    }

    /* ---------- Variants (size / colour / storage …) ---------- */

    /** Option names in use, keyed 1..3 (e.g. [1 => 'Colour', 2 => 'Storage']). */
    public static function optionNames(array $product): array
    {
        $out = [];
        for ($i = 1; $i <= 3; $i++) {
            $n = trim((string) ($product["option{$i}_name"] ?? ''));
            if ($n !== '') {
                $out[$i] = $n;
            }
        }
        return $out;
    }

    public static function variants(int $productId): array
    {
        return DB::run('SELECT * FROM product_variants WHERE product_id = ? ORDER BY sort_order, id', [$productId])->fetchAll();
    }

    public static function findVariant(int $productId, int $variantId): ?array
    {
        return DB::run('SELECT * FROM product_variants WHERE id = ? AND product_id = ?', [$variantId, $productId])->fetch() ?: null;
    }

    /** The variant whose option values match exactly (used when no variant id was posted). */
    public static function matchVariant(array $product, array $values): ?array
    {
        foreach (self::variants((int) $product['id']) as $v) {
            $ok = true;
            foreach (self::optionNames($product) as $i => $_) {
                if (trim((string) ($values[$i] ?? '')) !== (string) $v["option{$i}"]) {
                    $ok = false;
                    break;
                }
            }
            if ($ok) {
                return $v;
            }
        }
        return null;
    }

    /** "Black / 128GB" — what the customer picked, kept on the plan. */
    public static function variantLabel(array $product, ?array $variant): string
    {
        if ($variant === null) {
            return '';
        }
        $parts = [];
        foreach (self::optionNames($product) as $i => $_) {
            if ((string) $variant["option{$i}"] !== '') {
                $parts[] = (string) $variant["option{$i}"];
            }
        }
        return implode(' / ', $parts);
    }

    /** Price of one item: the variant's own price if it has one, else the product's. */
    public static function unitPrice(array $product, ?array $variant): int
    {
        if ($variant !== null && $variant['price_pesewas'] !== null) {
            return (int) $variant['price_pesewas'];
        }
        return (int) $product['cash_price_pesewas'];
    }

    /** Units on hand, or null when the shop doesn't track stock for it. */
    public static function stockFor(array $product, ?array $variant): ?int
    {
        $raw = $variant !== null ? $variant['stock'] : $product['stock'];
        return $raw === null ? null : (int) $raw;
    }

    /**
     * Replace a product's variants with the posted rows, keeping the ids of
     * rows that still exist (carts and plans may point at them).
     * Each row: id?, option1..3, sku, price_pesewas (?int), stock (?int).
     */
    public static function saveVariants(int $productId, array $rows): void
    {
        $existing = [];
        foreach (self::variants($productId) as $v) {
            $existing[(int) $v['id']] = true;
        }
        $keep = [];
        foreach (array_values($rows) as $sort => $r) {
            $vals = [$r['option1'], $r['option2'], $r['option3'], $r['sku'], $r['price_pesewas'], $r['stock'], $sort];
            $id = (int) ($r['id'] ?? 0);
            if ($id > 0 && isset($existing[$id])) {
                DB::run(
                    'UPDATE product_variants SET option1 = ?, option2 = ?, option3 = ?, sku = ?, price_pesewas = ?, stock = ?, sort_order = ?
                     WHERE id = ? AND product_id = ?',
                    array_merge($vals, [$id, $productId])
                );
                $keep[$id] = true;
            } else {
                DB::run(
                    'INSERT INTO product_variants (option1, option2, option3, sku, price_pesewas, stock, sort_order, product_id)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                    array_merge($vals, [$productId])
                );
            }
        }
        foreach (array_keys($existing) as $id) {
            if (!isset($keep[$id])) {
                DB::run('DELETE FROM product_variants WHERE id = ? AND product_id = ?', [$id, $productId]);
            }
        }
    }

    /**
     * Take $qty off the shelf (never below zero). No-op when stock isn't
     * tracked or the variant has since been deleted.
     */
    public static function takeStock(int $productId, ?int $variantId, int $qty): void
    {
        if ($variantId !== null) {
            DB::run('UPDATE product_variants SET stock = IF(stock > ?, stock - ?, 0) WHERE id = ? AND product_id = ? AND stock IS NOT NULL',
                [$qty, $qty, $variantId, $productId]);
            return;
        }
        DB::run('UPDATE products SET stock = IF(stock > ?, stock - ?, 0) WHERE id = ? AND stock IS NOT NULL', [$qty, $qty, $productId]);
    }

    /** Put $qty back on the shelf (a cancelled plan). */
    public static function returnStock(int $productId, ?int $variantId, int $qty): void
    {
        if ($variantId !== null) {
            DB::run('UPDATE product_variants SET stock = stock + ? WHERE id = ? AND product_id = ? AND stock IS NOT NULL',
                [$qty, $variantId, $productId]);
            return;
        }
        DB::run('UPDATE products SET stock = stock + ? WHERE id = ? AND stock IS NOT NULL', [$qty, $productId]);
    }

    /* ---------- Product images (gallery) ---------- */

    /** All images for a product, cover first. Falls back to products.photo for older single-photo rows. */
    public static function images(int $productId): array
    {
        $rows = DB::run(
            'SELECT id, path, sort_order FROM product_images WHERE product_id = ? ORDER BY sort_order, id',
            [$productId]
        )->fetchAll();
        if ($rows) {
            return $rows;
        }
        // Legacy fallback: a single-photo product with no product_images rows.
        $p = DB::run('SELECT photo FROM products WHERE id = ?', [$productId])->fetch();
        if ($p && $p['photo'] !== '') {
            return [['id' => 0, 'path' => $p['photo'], 'sort_order' => 0]];
        }
        return [];
    }

    public static function addImage(int $productId, string $path, int $sort = 0): int
    {
        DB::run('INSERT INTO product_images (product_id, path, sort_order) VALUES (?, ?, ?)', [$productId, $path, $sort]);
        return DB::lastId();
    }

    /** Delete one image (scoped to its product) and return its stored path so the file can be removed. */
    public static function deleteImage(int $imageId, int $productId): ?string
    {
        $row = DB::run('SELECT path FROM product_images WHERE id = ? AND product_id = ?', [$imageId, $productId])->fetch();
        if (!$row) {
            return null;
        }
        DB::run('DELETE FROM product_images WHERE id = ? AND product_id = ?', [$imageId, $productId]);
        return $row['path'];
    }

    /** Highest sort_order in use (or -1 when there are none). */
    public static function maxImageSort(int $productId): int
    {
        $r = DB::run('SELECT COALESCE(MAX(sort_order), -1) AS m FROM product_images WHERE product_id = ?', [$productId])->fetch();
        return (int) $r['m'];
    }

    /** Sync products.photo to the first gallery image (or '' when there are none). */
    public static function refreshCover(int $productId): void
    {
        $r = DB::run(
            'SELECT path FROM product_images WHERE product_id = ? ORDER BY sort_order, id LIMIT 1',
            [$productId]
        )->fetch();
        DB::run('UPDATE products SET photo = ? WHERE id = ?', [$r['path'] ?? '', $productId]);
    }
}
