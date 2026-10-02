<?php
use App\Models\Product;

$f = $filters ?? ['q' => (string) ($q ?? ''), 'category' => (string) ($current ?? ''), 'budget' => (string) ($budget ?? ''),
    'min' => 0, 'max' => 0, 'verified' => false, 'in_stock' => false, 'freq' => '', 'sort' => ''];

/** Current filters as query params (only the ones that are set), in GHS for prices. */
$params = array_filter([
    'q' => $f['q'],
    'category' => $f['category'],
    'budget' => $f['budget'],
    'min' => $f['min'] > 0 ? rtrim(rtrim(number_format($f['min'] / 100, 2, '.', ''), '0'), '.') : '',
    'max' => $f['max'] > 0 ? rtrim(rtrim(number_format($f['max'] / 100, 2, '.', ''), '0'), '.') : '',
    'verified' => $f['verified'] ? '1' : '',
    'in_stock' => $f['in_stock'] ? '1' : '',
    'freq' => $f['freq'],
    'sort' => $f['sort'],
], static fn ($v) => $v !== '' && $v !== null);

/** /shop link keeping the current filters, with some overridden (null drops one). */
$shopUrl = static function (array $override) use ($params): string {
    $p = array_filter(array_merge($params, $override), static fn ($v) => $v !== null && $v !== '');
    return url('/shop' . ($p ? '?' . http_build_query($p) : ''));
};

$sort = $f['sort'] !== '' ? $f['sort'] : ($f['q'] !== '' ? 'relevance' : 'newest');
$activeCount = count(array_intersect_key($params, array_flip(['category', 'budget', 'min', 'max', 'verified', 'in_stock', 'freq'])));

// Chips for each active filter, each with its own "remove" link.
$chips = [];
if ($f['category'] !== '') { $chips[] = [Product::categoryLabel($f['category']), ['category' => null]]; }
if ($f['budget'] !== '') { $chips[] = [Product::BUDGETS[$f['budget']]['label'], ['budget' => null]]; }
if (isset($params['min'])) { $chips[] = ['From GHS ' . $params['min'], ['min' => null]]; }
if (isset($params['max'])) { $chips[] = ['Up to GHS ' . $params['max'], ['max' => null]]; }
if ($f['verified']) { $chips[] = ['Verified shops', ['verified' => null]]; }
if ($f['in_stock']) { $chips[] = ['In stock', ['in_stock' => null]]; }
if ($f['freq'] !== '') { $chips[] = ['Pay ' . strtolower(Product::FREQUENCIES[$f['freq']]), ['freq' => null]]; }
?>
<div class="wrap browse-layout">
  <!-- Filters: a sidebar on desktop, a fold-out panel on phones -->
  <details class="browse-aside" data-filters open>
    <summary class="filters-toggle">
      <?= micon('tune', ['size' => 20]) ?> Filters<?= $activeCount > 0 ? ' <span class="count-pill">' . $activeCount . '</span>' : '' ?>
      <?= micon('expand_more', ['class' => 'info-chev']) ?>
    </summary>

    <div class="filters-body">
      <div class="filter-group">
        <h3>Categories</h3>
        <div class="filter-list">
          <a class="filter-item <?= $f['category'] === '' ? 'active' : '' ?>" href="<?= $shopUrl(['category' => null]) ?>">
            <?= micon('grid_view', ['size' => 18]) ?> All products
          </a>
          <?php foreach (($categories ?? []) as $cat): ?>
            <a class="filter-item <?= $f['category'] === $cat ? 'active' : '' ?>" href="<?= $shopUrl(['category' => $cat]) ?>">
              <?= micon(product_micon($cat), ['size' => 18]) ?> <?= e(Product::categoryLabel($cat)) ?>
            </a>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="filter-divider"></div>

      <form class="filter-form" method="get" action="<?= url('/shop') ?>">
        <?php foreach (['q', 'category', 'budget', 'sort'] as $keep): ?>
          <?php if (isset($params[$keep])): ?><input type="hidden" name="<?= $keep ?>" value="<?= e((string) $params[$keep]) ?>"><?php endif; ?>
        <?php endforeach; ?>

        <div class="filter-group">
          <h3>Price (GHS)</h3>
          <div class="price-range">
            <label class="sr-only" for="f-min">Lowest price</label>
            <input id="f-min" name="min" type="number" min="0" step="1" inputmode="numeric" placeholder="Min" value="<?= e((string) ($params['min'] ?? '')) ?>">
            <span aria-hidden="true">–</span>
            <label class="sr-only" for="f-max">Highest price</label>
            <input id="f-max" name="max" type="number" min="0" step="1" inputmode="numeric" placeholder="Max" value="<?= e((string) ($params['max'] ?? '')) ?>">
          </div>
        </div>

        <div class="filter-group">
          <h3>Shop &amp; stock</h3>
          <label class="check-line"><input type="checkbox" name="verified" value="1" <?= $f['verified'] ? 'checked' : '' ?>> Verified shops only</label>
          <label class="check-line"><input type="checkbox" name="in_stock" value="1" <?= $f['in_stock'] ? 'checked' : '' ?>> Hide sold-out items</label>
        </div>

        <div class="filter-group">
          <h3>I want to pay</h3>
          <div class="seg">
            <?php foreach (['' => 'Any way'] + Product::FREQUENCIES as $slug => $label): ?>
              <label class="seg-item"><input type="radio" name="freq" value="<?= e($slug) ?>" <?= $f['freq'] === $slug ? 'checked' : '' ?>><span><?= e($label) ?></span></label>
            <?php endforeach; ?>
          </div>
        </div>

        <button class="btn btn-primary btn-block" type="submit">Show results</button>
      </form>

      <div class="filter-divider"></div>
      <div class="filter-group">
        <h3>Weekly budget</h3>
        <div class="filter-list">
          <a class="filter-item <?= $f['budget'] === '' ? 'active' : '' ?>" href="<?= $shopUrl(['budget' => null]) ?>">
            <?= micon('payments', ['size' => 18]) ?> Any budget
          </a>
          <?php foreach (Product::BUDGETS as $slug => $b): ?>
            <a class="filter-item <?= $f['budget'] === $slug ? 'active' : '' ?>" href="<?= $shopUrl(['budget' => $slug]) ?>">
              <?= micon('savings', ['size' => 18]) ?> <?= e($b['label']) ?>
            </a>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="filter-divider hide-mobile"></div>
      <div class="filter-group hide-mobile">
        <h3>Why PaySmallSmall</h3>
        <div class="info-card" style="padding:1rem">
          <div class="info-ic"><?= micon('shield', ['size' => 20, 'fill' => true]) ?></div>
          <div>
            <h4 style="font-size:.92rem">Escrow protected</h4>
            <p style="font-size:.82rem">Your money is held safely. The shop is only paid once your plan finishes.</p>
          </div>
        </div>
      </div>
    </div>
  </details>

  <!-- Product grid -->
  <div class="browse-main">
    <form class="shop-search" action="<?= url('/shop') ?>" method="get" role="search" data-suggest-form>
      <?= micon('search', ['class' => 'search-ic']) ?>
      <label class="sr-only" for="shop-q">Search products</label>
      <input id="shop-q" type="search" name="q" value="<?= e($f['q']) ?>" placeholder="Search by name, shop or SKU…" autocomplete="off" data-suggest>
      <?php if ($f['category'] !== ''): ?><input type="hidden" name="category" value="<?= e($f['category']) ?>"><?php endif; ?>
      <button class="btn btn-primary btn-sm" type="submit">Search</button>
    </form>

    <div class="browse-head">
      <div>
        <h1><?= $f['q'] !== '' ? 'Results for "' . e($f['q']) . '"' : ($f['category'] !== '' ? e(Product::categoryLabel($f['category'])) : 'Explore plans') ?></h1>
        <p><?= $f['budget'] !== '' ? e(Product::BUDGETS[$f['budget']]['label']) . ' (price spread over ' . Product::CARD_WEEKS . ' weeks). ' : '' ?>Every price shows two ways: cash, and small small.</p>
      </div>
      <form class="sort-form" method="get" action="<?= url('/shop') ?>">
        <?php foreach ($params as $k => $v): ?>
          <?php if ($k !== 'sort'): ?><input type="hidden" name="<?= e($k) ?>" value="<?= e((string) $v) ?>"><?php endif; ?>
        <?php endforeach; ?>
        <label for="sort" class="shop-count"><b><?= count($products) ?></b> item<?= count($products) === 1 ? '' : 's' ?> · Sort</label>
        <select id="sort" name="sort" data-autosubmit>
          <?php foreach (Product::SORTS as $slug => $label): ?>
            <?php if ($slug === 'relevance' && $f['q'] === '') { continue; } ?>
            <option value="<?= e($slug) ?>" <?= $sort === $slug ? 'selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
        <noscript><button class="btn btn-sm btn-ghost" type="submit">Sort</button></noscript>
      </form>
    </div>

    <?php if ($chips): ?>
      <div class="active-filters">
        <?php foreach ($chips as [$label, $drop]): ?>
          <a class="chip chip-x" href="<?= $shopUrl($drop) ?>" aria-label="Remove filter: <?= e($label) ?>"><?= e($label) ?> <?= micon('close', ['size' => 15]) ?></a>
        <?php endforeach; ?>
        <a class="clear-all" href="<?= url('/shop' . ($f['q'] !== '' ? '?q=' . urlencode($f['q']) : '')) ?>">Clear all</a>
      </div>
    <?php endif; ?>

    <?php if (empty($products)): ?>
      <div class="empty-card">
        <?php if ($f['q'] !== ''): ?>
          <h2>Nothing matched "<?= e($f['q']) ?>"</h2>
          <p>Try a shorter word — "phone", "bed", "kaba" — or check the spelling of an item code.</p>
          <a class="btn btn-primary" href="<?= url('/shop') ?>">See all products</a>
        <?php elseif ($activeCount > 0): ?>
          <h2>Nothing fits all those filters</h2>
          <p>Loosen one or two — a wider price range usually does it.</p>
          <a class="btn btn-primary" href="<?= url('/shop') ?>">Clear filters</a>
        <?php else: ?>
          <h2>Nothing here yet</h2>
          <p>Check back soon — shops are adding products every week.</p>
        <?php endif; ?>
      </div>
    <?php else: ?>
      <div class="product-grid">
        <?php foreach ($products as $p): ?>
          <?= (new App\Core\View())->partial('partials/product-card', ['p' => $p]) ?>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>
